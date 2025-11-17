<?php
/**
 * PayPal Configuration
 * 
 * FOR TESTING: Use PayPal Sandbox credentials
 * FOR PRODUCTION: Use Live PayPal credentials
 * 
 * Get credentials from: https://developer.paypal.com/dashboard/
 */

return [
    // Environment: 'sandbox' for testing, 'live' for production
    'environment' => 'sandbox',
    
    // PayPal Sandbox Credentials (for testing)
    // Get from: https://developer.paypal.com/dashboard/applications/sandbox
    // INSTRUCTIONS: Replace with your actual credentials from PayPal Developer Dashboard
    'sandbox' => [
        'client_id' => 'ASDeDNN1IsQfwvLIbLlanI6h1MWBRBxcu7w-EFKWaYMqSvD1ePZKVTBXvLWsqQgUC0FzjsBtqwhtLmqV',
        'client_secret' => 'EO-BCW5jS8azm7V_0OolAYeltl9Yndb40vZ_QDXh8fQM3e89hOxcXl--yku-_fly1184sgYjuXr5YIb6',
        'base_url' => 'https://sandbox.paypal.com'
    ],
    
    // PayPal Live Credentials (for production)
    // Get from: https://developer.paypal.com/dashboard/applications/live
    'live' => [
        'client_id' => 'YOUR_LIVE_CLIENT_ID',
        'client_secret' => 'YOUR_LIVE_CLIENT_SECRET',
        'base_url' => 'https://api-m.paypal.com'
    ],
    
    // Currency Settings
    'currency' => 'USD',  // PayPal supports multiple currencies
    
    // Return URLs
    'return_url' => 'http://localhost/Phamarcy/customer-portal/payment-success.html',
    'cancel_url' => 'http://localhost/Phamarcy/customer-portal/payment-cancelled.html',
    
    // Test Mode Settings
    // Set to false when using real PayPal Sandbox for testing actual transactions
    // Set to true to simulate PayPal without actual API calls
    'test_mode_enabled' => true,  // Changed back to true - using mock PayPal until credentials are valid
    'auto_approve_test_payments' => true,
    
    // Exchange Rate (KES to USD) - Update regularly for accurate conversions
    'kes_to_usd_rate' => 0.0077,  // 1 KES = ~0.0077 USD (approximate)
    
    /*
     * HOW TO GET PAYPAL SANDBOX CREDENTIALS:
     * 
     * 1. Go to https://developer.paypal.com
     * 2. Login or Create a Developer Account
     * 3. Go to "Dashboard" → "My Apps & Credentials"
     * 4. Under "Sandbox", click "Create App"
     * 5. Give your app a name and click "Create App"
     * 6. Copy your "Client ID" and "Secret"
     * 7. Update the sandbox credentials above
     * 8. For testing, use sandbox test accounts from:
     *    https://developer.paypal.com/dashboard/accounts
     * 
     * FOR LIVE PAYMENTS:
     * - Complete PayPal business account verification
     * - Get Live credentials from the "Live" section
     * - Change 'environment' to 'live'
     * - Update return URLs to your production domain
     */
];
?>
