<?php
/**
 * M-Pesa Core Functions
 * Production-ready M-Pesa integration functions
 * Based on working sample code and best practices
 */

/**
 * Generate M-Pesa Access Token
 */
function generateMpesaAccessToken($config) {
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
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADER => false
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);
    
    if ($curl_error) {
        throw new Exception("Network error: $curl_error");
    }
    
    if ($http_code !== 200) {
        throw new Exception("M-Pesa API error (HTTP $http_code): $response");
    }
    
    $result = json_decode($response, true);
    if (!$result || !isset($result['access_token'])) {
        $error = $result['error_description'] ?? 'Invalid response from M-Pesa';
        throw new Exception("Access token error: $error");
    }
    
    return $result['access_token'];
}

/**
 * Format phone number for M-Pesa
 */
function formatMpesaPhoneNumber($phone) {
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Convert to international format
    if (substr($phone, 0, 1) === '0') {
        $phone = '254' . substr($phone, 1);
    } elseif (substr($phone, 0, 3) !== '254') {
        $phone = '254' . $phone;
    }
    
    // Validate format
    if (strlen($phone) !== 12 || !preg_match('/^254[0-9]{9}$/', $phone)) {
        throw new Exception('Invalid phone number format. Use: 0712345678 or 254712345678');
    }
    
    return $phone;
}

/**
 * Validate M-Pesa amount
 */
function validateMpesaAmount($amount) {
    $amount = round(floatval($amount));
    
    if ($amount < 1) {
        throw new Exception('Amount must be at least KES 1');
    }
    
    if ($amount > 70000) {
        throw new Exception('Amount cannot exceed KES 70,000');
    }
    
    return $amount;
}

/**
 * Initiate STK Push
 */
function initiateStkPush($phone, $amount, $account_reference, $description = null) {
    $config = require_once __DIR__ . '/mpesa-config.php';
    
    try {
        // Format and validate inputs
        $phone = formatMpesaPhoneNumber($phone);
        $amount = validateMpesaAmount($amount);
        
        // Generate access token
        $access_token = generateMpesaAccessToken($config);
        
        // Generate password
        $timestamp = date('YmdHis');
        $password = base64_encode($config['shortcode'] . $config['passkey'] . $timestamp);
        
        // Prepare STK Push request
        $request_data = [
            'BusinessShortCode' => $config['shortcode'],
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $config['shortcode'],
            'PhoneNumber' => $phone,
            'CallBackURL' => $config['callback_url'],
            'AccountReference' => $account_reference,
            'TransactionDesc' => $description ?: 'Payment for Order ' . $account_reference
        ];
        
        // Make STK Push request
        $url = $config['base_url'] . '/mpesa/stkpush/v1/processrequest';
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $access_token,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($request_data),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        curl_close($curl);
        
        if ($curl_error) {
            throw new Exception("Network error: $curl_error");
        }
        
        $result = json_decode($response, true);
        
        if (!$result) {
            throw new Exception("Invalid response from M-Pesa API");
        }
        
        // Log the transaction
        logMpesaTransaction('STK_PUSH_REQUEST', $request_data, $result);
        
        // Check response
        if ($http_code === 200 && isset($result['ResponseCode']) && $result['ResponseCode'] === '0') {
            return [
                'success' => true,
                'checkout_request_id' => $result['CheckoutRequestID'],
                'merchant_request_id' => $result['MerchantRequestID'],
                'response_code' => $result['ResponseCode'],
                'response_description' => $result['ResponseDescription'],
                'customer_message' => $result['CustomerMessage']
            ];
        } else {
            $error_msg = $result['ResponseDescription'] ?? $result['errorMessage'] ?? 'Unknown error';
            throw new Exception("STK Push failed: $error_msg");
        }
        
    } catch (Exception $e) {
        // Log error
        logMpesaTransaction('STK_PUSH_ERROR', $request_data ?? [], ['error' => $e->getMessage()]);
        
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'details' => isset($result) ? $result : null
        ];
    }
}

/**
 * Query STK Push status
 */
function queryStkPushStatus($checkout_request_id) {
    $config = require_once __DIR__ . '/mpesa-config.php';
    
    try {
        $access_token = generateMpesaAccessToken($config);
        
        $timestamp = date('YmdHis');
        $password = base64_encode($config['shortcode'] . $config['passkey'] . $timestamp);
        
        $request_data = [
            'BusinessShortCode' => $config['shortcode'],
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkout_request_id
        ];
        
        $url = $config['base_url'] . '/mpesa/stkpushquery/v1/query';
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $access_token,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($request_data),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        $result = json_decode($response, true);
        
        if ($http_code === 200 && $result) {
            return [
                'success' => true,
                'result_code' => $result['ResultCode'] ?? null,
                'result_desc' => $result['ResultDesc'] ?? null,
                'response_code' => $result['ResponseCode'] ?? null,
                'response_description' => $result['ResponseDescription'] ?? null
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Query failed',
                'details' => $result
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Log M-Pesa transactions
 */
function logMpesaTransaction($type, $request, $response) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => $type,
        'request' => $request,
        'response' => $response
    ];
    
    $log_file = __DIR__ . '/../../logs/mpesa-transactions.log';
    @mkdir(dirname($log_file), 0777, true);
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Check if M-Pesa credentials are valid
 */
function validateMpesaCredentials($config) {
    // Check if credentials are placeholder values
    if ($config['consumer_key'] === 'your_consumer_key_here' || 
        $config['consumer_secret'] === 'your_consumer_secret_here') {
        return false;
    }
    
    // Try to generate access token
    try {
        $access_token = generateMpesaAccessToken($config);
        return !empty($access_token);
    } catch (Exception $e) {
        return false;
    }
}
?>
