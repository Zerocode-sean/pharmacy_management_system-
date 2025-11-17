<?php
// MPesa configuration example. Copy to mpesa-config.php and fill real values.
return [
    'consumer_key' => 'YOUR_SANDBOX_CONSUMER_KEY',
    'consumer_secret' => 'YOUR_SANDBOX_CONSUMER_SECRET',
    'shortcode' => '174379',
    'passkey' => 'YOUR_APP_SPECIFIC_SANDBOX_PASSKEY',
    'base_url' => 'https://sandbox.safaricom.co.ke',
    'callback_url' => 'https://your-domain.ngrok-free.app/Phamarcy/customer-portal/api/mpesa-callback.php',
    'environment' => 'sandbox'
];
?>

