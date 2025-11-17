<?php
/**
 * PayPal Payment Handler
 * Handles PayPal payment processing with test mode fallback
 */

class PayPalPaymentHandler {
    private $config;
    private $client_id;
    private $client_secret;
    private $base_url;
    private $test_mode;
    
    public function __construct() {
        $this->config = require __DIR__ . '/paypal-config.php';
        $this->test_mode = $this->config['test_mode_enabled'] ?? true;
        
        $environment = $this->config['environment'] ?? 'sandbox';
        $env_config = $this->config[$environment] ?? $this->config['sandbox'];
        
        $this->client_id = $env_config['client_id'];
        $this->client_secret = $env_config['client_secret'];
        $this->base_url = $env_config['base_url'];
    }
    
    /**
     * Generate PayPal access token
     */
    private function getAccessToken() {
        $url = $this->base_url . '/v1/oauth2/token';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_USERPWD, $this->client_id . ':' . $this->client_secret);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: en_US'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception("Failed to get PayPal access token (HTTP $http_code)");
        }
        
        $result = json_decode($response, true);
        
        if (!isset($result['access_token'])) {
            throw new Exception("Invalid PayPal access token response");
        }
        
        return $result['access_token'];
    }
    
    /**
     * Convert KES to USD
     */
    private function convertKEStoUSD($kes_amount) {
        $rate = $this->config['kes_to_usd_rate'] ?? 0.0077;
        return round($kes_amount * $rate, 2);
    }
    
    /**
     * Create PayPal order
     */
    public function createOrder($amount_kes, $order_reference, $description, $items = []) {
        // Check if test mode
        if ($this->test_mode || $this->isCredentialsInvalid()) {
            return $this->createTestModeOrder($amount_kes, $order_reference, $description);
        }
        
        try {
            $access_token = $this->getAccessToken();
            $amount_usd = $this->convertKEStoUSD($amount_kes);
            
            // Prepare order data
            $order_data = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => $order_reference,
                        'description' => $description,
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => number_format($amount_usd, 2, '.', '')
                        ]
                    ]
                ],
                'application_context' => [
                    'return_url' => $this->config['return_url'],
                    'cancel_url' => $this->config['cancel_url'],
                    'brand_name' => 'PharmaCare',
                    'user_action' => 'PAY_NOW'
                ]
            ];
            
            // Create order
            $url = $this->base_url . '/v2/checkout/orders';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $access_token
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code !== 201) {
                throw new Exception("PayPal order creation failed (HTTP $http_code): $response");
            }
            
            $result = json_decode($response, true);
            
            if (!isset($result['id'])) {
                throw new Exception("Invalid PayPal order response");
            }
            
            // Get approval URL
            $approval_url = '';
            foreach ($result['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    $approval_url = $link['href'];
                    break;
                }
            }
            
            return [
                'success' => true,
                'order_id' => $result['id'],
                'approval_url' => $approval_url,
                'amount_usd' => $amount_usd,
                'amount_kes' => $amount_kes,
                'message' => 'PayPal order created successfully',
                'test_mode' => false
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create test mode order (no real PayPal connection)
     */
    private function createTestModeOrder($amount_kes, $order_reference, $description) {
        $amount_usd = $this->convertKEStoUSD($amount_kes);
        $order_id = 'TEST_PP_' . time() . rand(1000, 9999);
        
        // Simulate processing delay
        usleep(500000); // 0.5 second
        
        return [
            'success' => true,
            'order_id' => $order_id,
            'approval_url' => '#',  // No redirect needed in test mode
            'amount_usd' => $amount_usd,
            'amount_kes' => $amount_kes,
            'message' => '🧪 TEST MODE: PayPal payment simulated successfully',
            'test_mode' => true,
            'test_info' => [
                'message' => 'PayPal is in TEST MODE. No real payment will be processed.',
                'order_reference' => $order_reference,
                'description' => $description,
                'auto_approve' => true
            ]
        ];
    }
    
    /**
     * Check if credentials are invalid
     */
    private function isCredentialsInvalid() {
        if ($this->client_id === 'YOUR_SANDBOX_CLIENT_ID' || 
            $this->client_id === 'YOUR_LIVE_CLIENT_ID' ||
            empty($this->client_id)) {
            return true;
        }
        return false;
    }
    
    /**
     * Capture payment after user approval
     */
    public function captureOrder($order_id) {
        if ($this->test_mode || strpos($order_id, 'TEST_PP_') === 0) {
            return [
                'success' => true,
                'order_id' => $order_id,
                'message' => 'Test mode: Payment captured successfully',
                'test_mode' => true
            ];
        }
        
        try {
            $access_token = $this->getAccessToken();
            $url = $this->base_url . '/v2/checkout/orders/' . $order_id . '/capture';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $access_token
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code !== 201) {
                throw new Exception("PayPal capture failed (HTTP $http_code): $response");
            }
            
            $result = json_decode($response, true);
            
            return [
                'success' => true,
                'order_id' => $order_id,
                'capture_id' => $result['purchase_units'][0]['payments']['captures'][0]['id'] ?? null,
                'message' => 'Payment captured successfully',
                'test_mode' => false
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

/**
 * Helper function to initiate PayPal payment
 */
function initiate_paypal_payment($data) {
    $handler = new PayPalPaymentHandler();
    
    return $handler->createOrder(
        $data['amount'],
        $data['order_reference'],
        $data['description'],
        $data['items'] ?? []
    );
}
?>
