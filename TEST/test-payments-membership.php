<?php
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/app/includes/bootstrap.php';
    if ((getenv('APP_ENV') ?: 'production') === 'production'
        || !filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN)) {
        http_response_code(404);
        exit('Not found');
    }
}
require dirname(__DIR__) . '/app/includes/bootstrap.php';
use Matrimony\Database\Connection;
use Matrimony\Services\RazorpayClient;
use Matrimony\Services\MembershipService;
use Matrimony\Services\ContactRevealService;
use Matrimony\Services\InterestService;
use Matrimony\Services\PaymentService;
$pdo = Connection::pdo();
$ok = function($n,$c){ echo ($c?"PASS":"FAIL")." $n\n"; if(!$c) $GLOBALS['fail']=1; };

// 1. signature vectors (official algorithm)
$secret='test_secret';
$order='order_ABC123'; $pay='pay_XYZ';
$sig=hash_hmac('sha256', $order.'|'.$pay, $secret);
$ok('checkout sig valid', RazorpayClient::verifyPaymentSignature($order,$pay,$sig,$secret));
$ok('checkout sig tamper rejected', !RazorpayClient::verifyPaymentSignature($order,$pay,'bad',$secret));
$raw='{"event":"payment.captured"}';
$wsig=hash_hmac('sha256',$raw,$secret);
$ok('webhook sig valid', RazorpayClient::verifyWebhookSignature($raw,$wsig,$secret));
$ok('webhook sig tamper rejected', !RazorpayClient::verifyWebhookSignature($raw,'bad',$secret));

// 2. test users
$pdo->exec("DELETE FROM contact_reveals WHERE viewer_user_id IN (SELECT id FROM users WHERE email IN ('qa_viewer@t.local','qa_target@t.local'))");
$pdo->exec("DELETE FROM memberships WHERE user_id IN (SELECT id FROM users WHERE email IN ('qa_viewer@t.local','qa_target@t.local'))");
$pdo->exec("DELETE FROM activation_requests WHERE user_id IN (SELECT id FROM users WHERE email IN ('qa_viewer@t.local','qa_target@t.local'))");
$pdo->exec("DELETE FROM purchases WHERE user_id IN (SELECT id FROM users WHERE email IN ('qa_viewer@t.local','qa_target@t.local'))");
$pdo->exec("DELETE FROM profiles WHERE user_id IN (SELECT id FROM users WHERE email IN ('qa_viewer@t.local','qa_target@t.local'))");
$pdo->exec("DELETE FROM users WHERE email IN ('qa_viewer@t.local','qa_target@t.local')");
foreach ([['qa_viewer@t.local','male'],['qa_target@t.local','female']] as [$e,$g]) {
  $pdo->prepare("INSERT INTO users (email,email_normalized,password_hash,role,account_status,is_active,is_verified) VALUES (:e,:en,:h,'user','active',1,1)")->execute([':e'=>$e,':en'=>$e,':h'=>password_hash('x',PASSWORD_DEFAULT)]);
  $id=(int)$pdo->lastInsertId();
  $pdo->prepare("INSERT INTO profiles (user_id,first_name,last_name,gender,date_of_birth,moderation_status) VALUES (:u,'QA','User',:g,'1995-01-01','approved')")->execute([':u'=>$id,':g'=>$g]);
}
$viewer=(int)$pdo->query("SELECT id FROM users WHERE email='qa_viewer@t.local'")->fetchColumn();
$target=(int)$pdo->query("SELECT id FROM users WHERE email='qa_target@t.local'")->fetchColumn();

// 3. interest blocked without membership
$r=InterestService::canSend($viewer,$target);
$ok('interest denied without plan', !$r['ok'] && ($r['code']??'')==='no_active_plan');

// 4. contact denied without membership
$r=ContactRevealService::reveal($viewer,$target);
$ok('reveal denied without plan', !$r['success']);

// 5. create fake verified purchase + activate (bypass gateway, direct paid_verified like webhook would)
$plan=$pdo->query("SELECT * FROM membership_plans WHERE slug='silver' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$pdo->prepare("INSERT INTO purchases (public_reference,user_id,plan_id,plan_name_snapshot,amount_paise,currency,duration_days_snapshot,contact_limit_snapshot,is_unlimited_snapshot,gateway,gateway_order_id,gateway_payment_id,payment_status,signature_verified_at,paid_at) VALUES ('QATEST1',:u,:p,:nm,:amt,'INR',:dur,:lim,0,'razorpay','order_QA1','pay_QA1','paid_verified',UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([':u'=>$viewer,':p'=>$plan['id'],':nm'=>$plan['name'],':amt'=>$plan['price_paise'],':dur'=>$plan['duration_days'],':lim'=>$plan['contact_limit']]);
$pid=(int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO activation_requests (purchase_id,user_id,status) VALUES (:p,:u,'awaiting_admin')")->execute([':p'=>$pid,':u'=>$viewer]);
$ar=(int)$pdo->lastInsertId();
// non-admin activation path requires verified payment — simulate admin id
$pdo->prepare("INSERT INTO users (email,email_normalized,password_hash,role,account_status,is_active,is_verified) VALUES ('qa_admin@t.local','qa_admin@t.local',:h,'super_admin','active',1,1) ON DUPLICATE KEY UPDATE email=email")->execute([':h'=>password_hash('x',PASSWORD_DEFAULT)]);
$admin=(int)$pdo->query("SELECT id FROM users WHERE email='qa_admin@t.local'")->fetchColumn();
$res=MembershipService::activate($admin,$ar,'replace','QA test');
$ok('admin activation works', !empty($res['success']));
// duplicate activation idempotent
$res2=MembershipService::activate($admin,$ar,'replace','QA dup');
$ok('duplicate activation safe', !empty($res2['success']));

// 6. interest allowed now
$r=InterestService::canSend($viewer,$target);
$ok('interest allowed with plan', !empty($r['ok']));

// 7. reveal consumes once, repeat free
$r1=ContactRevealService::reveal($viewer,$target);
$ok('first reveal ok', !empty($r1['success']) && empty($r1['repeat']));
$c1=(int)$pdo->query("SELECT COUNT(*) FROM contact_reveals WHERE viewer_user_id=$viewer AND target_user_id=$target")->fetchColumn();
$r2=ContactRevealService::reveal($viewer,$target);
$c2=(int)$pdo->query("SELECT COUNT(*) FROM contact_reveals WHERE viewer_user_id=$viewer AND target_user_id=$target")->fetchColumn();
$ok('repeat reveal deduped', !empty($r2['success']) && $c1===1 && $c2===1);

// 8. activation of unverified purchase must fail
$pdo->prepare("INSERT INTO purchases (public_reference,user_id,plan_id,plan_name_snapshot,amount_paise,currency,duration_days_snapshot,contact_limit_snapshot,is_unlimited_snapshot,gateway,payment_status) VALUES ('QATEST2',:u,:p,:nm,:amt,'INR',:dur,:lim,0,'razorpay','pending')")->execute([':u'=>$viewer,':p'=>$plan['id'],':nm'=>$plan['name'],':amt'=>$plan['price_paise'],':dur'=>$plan['duration_days'],':lim'=>$plan['contact_limit']]);
$pid2=(int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO activation_requests (purchase_id,user_id,status) VALUES (:p,:u,'awaiting_admin')")->execute([':p'=>$pid2,':u'=>$viewer]);
$ar2=(int)$pdo->lastInsertId();
$res3=MembershipService::activate($admin,$ar2,'replace','QA bad');
$ok('unverified activation blocked', empty($res3['success']));

// 9. webhook idempotency + unknown order + amount mismatch
putenv('RAZORPAY_WEBHOOK_SECRET=qa_wh_secret'); $_ENV['RAZORPAY_WEBHOOK_SECRET']='qa_wh_secret';
$pdo->exec("DELETE FROM gateway_webhook_events WHERE provider_event_key LIKE 'qa-%'");
$make=function($body) { return \Matrimony\Services\WebhookService::handle($body, ['x-razorpay-signature'=>hash_hmac('sha256',$body,'qa_wh_secret')]); };
// unknown order
$body=json_encode(['id'=>'qa-1','event'=>'payment.captured','payload'=>['payment'=>['entity'=>['id'=>'pay_unknown','order_id'=>'order_unknown','amount'=>100000,'currency'=>'INR']]]]);
$rr=$make($body); $ok('webhook unknown order acked', !empty($rr['ok']));
$rr2=$make($body); $ok('webhook duplicate suppressed', !empty($rr2['duplicate']));
// amount mismatch on real order
$body2=json_encode(['id'=>'qa-2','event'=>'payment.captured','payload'=>['payment'=>['entity'=>['id'=>'pay_QA1','order_id'=>'order_QA1','amount'=>1,'currency'=>'INR']]]]);
$rr3=$make($body2); $ok('webhook amount mismatch -> review', !empty($rr3['needs_review']));
$st=$pdo->query("SELECT payment_status FROM purchases WHERE id=$pid")->fetchColumn();
$ok('mismatch does not downgrade verified payment', $st==='paid_verified');

// cleanup
$pdo->exec("DELETE FROM gateway_webhook_events WHERE provider_event_key LIKE 'qa-%'");
$pdo->exec("DELETE FROM contact_reveals WHERE viewer_user_id=$viewer");
$pdo->exec("DELETE FROM activation_requests WHERE id IN ($ar,$ar2)");
$pdo->exec("DELETE FROM purchases WHERE id IN ($pid,$pid2)");
$pdo->exec("DELETE FROM memberships WHERE user_id=$viewer");
$pdo->exec("DELETE FROM profiles WHERE user_id IN ($viewer,$target,$admin)");
$pdo->exec("DELETE FROM users WHERE id IN ($viewer,$target,$admin)");
echo isset($GLOBALS['fail']) ? "QA FAILURES\n" : "ALL QA PASS\n";
