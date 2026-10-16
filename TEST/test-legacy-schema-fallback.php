<?php
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/app/includes/bootstrap.php';
    if ((getenv('APP_ENV') ?: 'production') === 'production'
        || !filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN)) {
        http_response_code(404);
        exit('Not found');
    }
}
// Simulates production WITHOUT migration 008: legacy tables only.
putenv('DB_DATABASE=matrimony_legacy_sim');
$_ENV['DB_DATABASE'] = 'matrimony_legacy_sim';
// setup with root (app user cannot CREATE DATABASE)
$root = new PDO('mysql:host=localhost;port=3306;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$root->exec('CREATE DATABASE IF NOT EXISTS matrimony_legacy_sim CHARACTER SET utf8mb4');
$root->exec("GRANT ALL PRIVILEGES ON matrimony_legacy_sim.* TO 'u814177917_matrimony'@'localhost'");
require dirname(__DIR__) . '/app/includes/bootstrap.php';
use Matrimony\Database\Connection;
$pdo = Connection::pdo();
$pdo->exec('USE matrimony_legacy_sim');
$pdo->exec('DROP TABLE IF EXISTS memberships');
$pdo->exec('DROP TABLE IF EXISTS membership_plans');
$pdo->exec('DROP TABLE IF EXISTS profiles');
$pdo->exec('DROP TABLE IF EXISTS users');
$pdo->exec("CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL, password_hash VARCHAR(255) NOT NULL, is_active TINYINT(1) DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE membership_plans (id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(40) NOT NULL, name VARCHAR(80) NOT NULL, duration_days INT NOT NULL, price_cents INT NOT NULL, features TEXT NULL, is_active TINYINT(1) DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE memberships (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, plan_id INT NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, status ENUM('active','expired','cancelled') DEFAULT 'active') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE profiles (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, first_name VARCHAR(80), last_name VARCHAR(80), gender ENUM('male','female','other'), date_of_birth DATE, moderation_status ENUM('pending','approved') DEFAULT 'approved', is_hidden TINYINT(1) DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("INSERT INTO membership_plans (code,name,duration_days,price_cents,features,is_active) VALUES ('SILVER','Silver',180,299900,'legacy',1),('GOLD','Gold',365,799900,'legacy',1),('PLATINUM','Platinum',365,1499900,'legacy',1)");
$fail = 0;
$ok = function($n,$c) use (&$fail){ echo ($c?'PASS':'FAIL')." $n\n"; if(!$c) $fail=1; };
$ok('paymentsReady false pre-migration', !Matrimony\Database\Schema::paymentsReady());
$plans = Matrimony\Services\PlanService::activePlans();
$ok('activePlans no crash, 3 rows', count($plans) === 3);
$byCode = [];
foreach ($plans as $p) $byCode[$p['code']] = $p;
$ok('fallback silver poster values', $byCode['SILVER']['price_paise']==100000 && $byCode['SILVER']['contact_limit']==25 && $byCode['SILVER']['duration_days']==45);
$ok('fallback platinum unlimited', !empty($byCode['PLATINUM']['is_unlimited']));
$ok('findActive slug pre-migration', Matrimony\Services\PlanService::findActive('silver')['code']==='SILVER');
// membership read path on legacy schema
$pdo->exec("INSERT INTO users (email,password_hash) VALUES ('leg@t.local','x')");
$uid = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO profiles (user_id,first_name,last_name,gender,date_of_birth) VALUES ($uid,'A','B','male','1990-01-01')");
$pdo->exec("INSERT INTO memberships (user_id,plan_id,starts_at,ends_at,status) VALUES ($uid,1,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 DAY),'active')");
$m = Matrimony\Services\MembershipService::activeFor($uid);
$ok('activeFor no crash legacy', !empty($m) && $m['status']==='active');
echo $fail ? "LEGACY SIM FAILURES\n" : "LEGACY SIM ALL PASS\n";
// cleanup
$root->exec('DROP DATABASE matrimony_legacy_sim');
exit($fail ? 1 : 0);
