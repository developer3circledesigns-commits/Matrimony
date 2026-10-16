<?php
/**
 * Payment gateway configuration. Secrets come ONLY from environment.
 * Never commit real keys. Test keys until go-live checks pass.
 */
return [
    'gateway' => getenv('PAYMENT_GATEWAY') ?: 'razorpay',
    'currency' => getenv('PAYMENT_CURRENCY') ?: 'INR',
    'razorpay' => [
        'key_id' => getenv('RAZORPAY_KEY_ID') ?: '',
        'key_secret' => getenv('RAZORPAY_KEY_SECRET') ?: '',
        'webhook_secret' => getenv('RAZORPAY_WEBHOOK_SECRET') ?: '',
        // 'test' | 'live' — informational; keys determine mode
        'mode' => getenv('RAZORPAY_MODE') ?: 'test',
    ],
    'admin_email' => getenv('ADMIN_EMAIL') ?: (getenv('MAIL_FROM_ADDRESS') ?: ''),
];
