<?php
// M-Pesa Integration Functions

class MPesaPaymentHandler {
    private $consumer_key;
    private $consumer_secret;
    private $passkey;
    private $shortcode;
    private $callback_url;
    private $base_url;
    
    public function __construct() {
        // Load M-Pesa configuration (ensure we actually get the array)
        $config = include __DIR__ . '/mpesa-config.php';
        if (!is_array($config)) {
            throw new Exception('Invalid M-Pesa configuration. Ensure mpesa-config.php returns an array.');
        }
        
        $this->consumer_key = $config['consumer_key'];
        $this->consumer_secret = $config['consumer_secret'];
        $this->passkey = $config['passkey'];
        $this->shortcode = $config['shortcode'];
        $this->callback_url = $config['callback_url'];
        $this->base_url = $config['base_url'];
    }
    
    private function generateAccessToken() {
        $url = $this->base_url . '/oauth/v1/generate?grant_type=client_credentials';
        $credentials = base64_encode($this->consumer_key . ':' . $this->consumer_secret);
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/json'
        ));
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        curl_close($curl);
        
        // Better error handling
        if ($curl_error) {
            throw new Exception("Network error generating access token: $curl_error");
        }
        
        if ($http_code !== 200) {
            throw new Exception("M-Pesa API error (HTTP $http_code): $response");
        }
        
        $result = json_decode($response, true);
        
        if (!$result) {
            throw new Exception("Invalid JSON response from M-Pesa: $response");
        }
        
        if (isset($result['access_token'])) {
            return $result['access_token'];
        }
        
        $error_msg = $result['error_description'] ?? $result['error'] ?? 'Unknown error';
        throw new Exception("M-Pesa access token failed: $error_msg");
    }
    
    public function initiatePayment($phone, $amount, $order_reference, $description = null) {
        try {
            $access_token = $this->generateAccessToken();
            
            $timestamp = date('YmdHis');
            $password = base64_encode($this->shortcode . $this->passkey . $timestamp);
            
            $phone = $this->formatPhoneNumber($phone);
            $amount = round($amount);
            
            if ($amount < 1) {
                throw new Exception('Amount must be at least 1 KES');
            }
            
            $curl_post_data = array(
                'BusinessShortCode' => $this->shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => $amount,
                'PartyA' => $phone,
                'PartyB' => $this->shortcode,
                'PhoneNumber' => $phone,
                'CallBackURL' => $this->callback_url,
                'AccountReference' => $order_reference,
                'TransactionDesc' => $description ?: 'Medicine Order Payment'
            );
            
            $data_string = json_encode($curl_post_data);
            
            $url = $this->base_url . '/mpesa/stkpush/v1/processrequest';
            
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $access_token
            ));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($curl);
            curl_close($curl);
              // Enhanced error handling with comprehensive details
            if ($curl_error) {
                throw new Exception("CURL error in STK Push: $curl_error");
            }

            if ($http_code !== 200) {
                // Capture comprehensive error information
                $error_info = [
                    'http_code' => $http_code,
                    'response_body' => $response,
                    'request_data' => $curl_post_data,
                    'request_url' => $url,
                    'curl_info' => curl_getinfo($curl)
                ];
                
                // Try to parse error response
                $error_json = json_decode($response, true);
                $error_message = "M-Pesa API error (HTTP $http_code)";
                
                if ($error_json) {
                    $specific_error = $error_json['errorMessage'] ?? 
                                    $error_json['ResponseDescription'] ?? 
                                    $error_json['error_description'] ?? 
                                    $error_json['message'] ?? 
                                    'Unknown error';
                    $error_message .= ": $specific_error";
                    
                    if (isset($error_json['errorCode'])) {
                        $error_message .= " (Code: {$error_json['errorCode']})";
                    }
                } else {
                    $error_message .= ": $response";
                }
                
                // Add detailed debugging info
                $error_message .= "\n\nDEBUG INFO:\n";
                $error_message .= "Request URL: $url\n";
                $error_message .= "Request Data: " . json_encode($curl_post_data, JSON_PRETTY_PRINT) . "\n";
                $error_message .= "Response Headers: " . (curl_getinfo($curl, CURLINFO_HEADER_OUT) ?? 'N/A') . "\n";
                $error_message .= "Full Response: $response\n";
                
                throw new Exception($error_message);
            }
            
            $result = json_decode($response, true);
            
            if (!$result) {
                throw new Exception("Invalid JSON response from M-Pesa: $response");
            }
            
            if (isset($result['ResponseCode']) && $result['ResponseCode'] == '0') {
                return [
                    'success' => true,
                    'checkout_request_id' => $result['CheckoutRequestID'],
                    'merchant_request_id' => $result['MerchantRequestID'],
                    'message' => 'Payment request sent to phone',
                    'raw_response' => $result
                ];
            } else {
                // Enhanced error message with more details
                $error_msg = $result['errorMessage'] ?? 
                           $result['ResponseDescription'] ?? 
                           $result['CustomerMessage'] ?? 
                           'Payment request failed';
                
                $error_code = $result['ResponseCode'] ?? 
                            $result['errorCode'] ?? 
                            'unknown';
                
                throw new Exception("STK Push failed (Code: $error_code): $error_msg. Full response: " . json_encode($result));
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    private function formatPhoneNumber($phone) {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Handle different phone number formats
        if (substr($phone, 0, 1) == '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (substr($phone, 0, 1) == '+') {
            $phone = substr($phone, 1);
        } elseif (substr($phone, 0, 3) != '254') {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }
    
    public function querySTKPushStatus($checkout_request_id) {
        try {
            $access_token = $this->generateAccessToken();
            
            $timestamp = date('YmdHis');
            $password = base64_encode($this->shortcode . $this->passkey . $timestamp);
            
            $curl_post_data = array(
                'BusinessShortCode' => $this->shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $checkout_request_id
            );
            
            $data_string = json_encode($curl_post_data);
            
            $url = $this->base_url . '/mpesa/stkpushquery/v1/query';
            
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $access_token
            ));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($curl);
            curl_close($curl);
            
            $result = json_decode($response, true);
            
            if (isset($result['ResponseCode'])) {
                return [
                    'success' => true,
                    'result_code' => $result['ResultCode'] ?? '',
                    'result_desc' => $result['ResultDesc'] ?? '',
                    'checkout_request_id' => $result['CheckoutRequestID'] ?? '',
                    'message' => $result['ResultDesc'] ?? 'Status checked',
                    'raw_response' => $result
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid response from M-Pesa API'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

// Global function for easy access (guarded against redeclaration)
if (!function_exists('initiate_mpesa_payment')) {
    function initiate_mpesa_payment($data) {
        $mpesa = new MPesaPaymentHandler();
        return $mpesa->initiatePayment(
            $data['phone'],
            $data['amount'],
            $data['order_reference'],
            $data['description'] ?? null
        );
    }
}
?>
