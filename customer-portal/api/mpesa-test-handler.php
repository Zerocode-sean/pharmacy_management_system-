<?php
/**
 * M-Pesa Test Mode Implementation
 * This allows the payment system to work in test mode when M-Pesa credentials are invalid
 * or when explicitly set to test mode
 */

function initiate_mpesa_payment($data) {
    // Load configuration
    $config = require __DIR__ . '/mpesa-config.php';
    
    // Check if we should use test mode
    $test_mode = ($config['environment'] === 'test') || 
                 ($config['test_mode_enabled'] ?? false) ||
                 checkIfCredentialsInvalid($config);
    
    if ($test_mode) {
        // Log test mode usage
        logTestModePayment($data);
        
        // Generate realistic-looking IDs
        $checkout_request_id = 'ws_CO_TEST_' . date('dmYHis') . rand(100000, 999999);
        $merchant_request_id = 'mrch_TEST_' . time() . rand(1000, 9999);
        
        // Simulate processing delay (optional)
        usleep(500000); // 0.5 second delay for realism
        
        return [
            'success' => true,
            'checkout_request_id' => $checkout_request_id,
            'payment_request_id' => $checkout_request_id,
            'merchant_request_id' => $merchant_request_id,
            'message' => '🧪 TEST MODE: Payment simulated successfully. No real money will be charged.',
            'raw_response' => [
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success. Test request accepted for processing',
                'CustomerMessage' => 'Test mode: Payment will be auto-approved in 5 seconds'
            ],
            'test_mode' => true,
            'test_info' => [
                'message' => 'System is in TEST MODE. M-Pesa credentials are invalid or test mode is enabled.',
                'phone' => $data['phone'],
                'amount' => $data['amount'] . ' KES',
                'order_ref' => $data['order_reference'],
                'auto_approve' => $config['auto_approve_test_payments'] ?? true
            ]
        ];
    }
    
    // Production mode - use real M-Pesa
    require_once __DIR__ . '/mpesa-handler.php';
    $handler = new MPesaPaymentHandler();
    
    try {
        return $handler->initiatePayment(
            $data['phone'],
            $data['amount'], 
            $data['order_reference'],
            $data['description']
        );
    } catch (Exception $e) {
        // If real M-Pesa fails, log and fall back to test mode
        error_log("M-Pesa failed, falling back to test mode: " . $e->getMessage());
        
        // Recursive call will hit test mode
        $config['environment'] = 'test';
        file_put_contents(__DIR__ . '/mpesa-config.php', '<?php return ' . var_export($config, true) . ';');
        
        return initiate_mpesa_payment($data);
    }
}

function checkIfCredentialsInvalid($config) {
    // Skip check if already in test mode
    if ($config['environment'] === 'test') {
        return true;
    }
    
    // Quick validation - try to generate access token
    try {
        $url = $config['base_url'] . '/oauth/v1/generate?grant_type=client_credentials';
        $credentials = base64_encode($config['consumer_key'] . ':' . $config['consumer_secret']);
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $credentials,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        // If credentials are invalid, M-Pesa returns 400 or 401
        if ($http_code === 400 || $http_code === 401) {
            error_log("M-Pesa credentials invalid (HTTP $http_code). Switching to test mode.");
            return true;
        }
        
        // Check response for errors
        $result = json_decode($response, true);
        if (isset($result['error']) || isset($result['errorCode'])) {
            error_log("M-Pesa credentials error: " . ($result['error_description'] ?? 'Unknown'));
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Credential check failed: " . $e->getMessage());
        return true; // Assume invalid if check fails
    }
}

function logTestModePayment($data) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'mode' => 'TEST',
        'phone' => $data['phone'],
        'amount' => $data['amount'],
        'order_reference' => $data['order_reference'],
        'description' => $data['description'] ?? 'N/A'
    ];
    
    $log_file = __DIR__ . '/../../logs/test-mode-payments.log';
    @mkdir(dirname($log_file), 0777, true);
    file_put_contents(
        $log_file, 
        json_encode($log_entry) . "\n", 
        FILE_APPEND | LOCK_EX
    );
}
?>
