/**
 * Unified Payment Handler
 * Handles M-Pesa, PayPal, and Cash on Delivery payments
 */

async function handleUnifiedPayment(event) {
    if (event) event.preventDefault();
    
    console.log('💳 Unified payment handler called');
    
    // Get selected payment method
    const selectedMethod = document.querySelector('input[name="paymentMethod"]:checked');
    if (!selectedMethod) {
        alert('Please select a payment method');
        return;
    }
    
    const paymentMethod = selectedMethod.value;
    console.log('💰 Selected payment method:', paymentMethod);
    
    // Validate cart
    if (!cart || cart.items.length === 0) {
        alert('Your cart is empty');
        return;
    }
    
    // Get form data
    const formData = new FormData(event.target);
    const customerData = {
        name: formData.get('name'),
        email: formData.get('email') || '',
        phone: formData.get('phone'),
        address: formData.get('address'),
        city: formData.get('city') || '',
        zip: formData.get('zip') || ''
    };
    
    // Validate required fields
    if (!customerData.name || !customerData.phone || !customerData.address) {
        alert('Please fill in all required fields');
        return;
    }
    
    // Disable submit button with specific loading text
    const submitBtn = document.getElementById('submitPaymentBtn');
    if (submitBtn) {
        submitBtn.disabled = true;
        
        // Set loading text based on payment method
        let loadingText = 'Processing...';
        switch (paymentMethod) {
            case 'mpesa':
                loadingText = 'Initiating M-Pesa Payment...';
                break;
            case 'paypal':
                loadingText = 'Initiating PayPal Payment...';
                break;
            case 'cash':
                loadingText = 'Placing Your Order...';
                break;
        }
        
        submitBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${loadingText}`;
    }
    
    try {
        let result;
        
        switch (paymentMethod) {
            case 'mpesa':
                result = await processMpesaPayment(customerData);
                break;
            case 'paypal':
                result = await processPayPalPayment(customerData);
                break;
            case 'cash':
                result = await processCashOnDelivery(customerData);
                break;
            default:
                throw new Error('Invalid payment method');
        }
        
        if (result.success) {
            handlePaymentSuccess(result, paymentMethod);
        } else {
            throw new Error(result.message || 'Payment failed');
        }
        
    } catch (error) {
        console.error('Payment error:', error);
        alert('Payment failed: ' + error.message);
        
        // Re-enable submit button
        if (submitBtn) {
            submitBtn.disabled = false;
            updateSubmitButtonText(paymentMethod);
        }
    }
}

async function processMpesaPayment(customerData) {
    console.log('📱 Processing M-Pesa payment...');
    
    const orderData = {
        customer: customerData,
        items: cart.items,
        total: cart.getTotal(),
        payment_method: 'mpesa'
    };
    
    const response = await fetch('api/initiate-payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(orderData)
    });
    
    if (!response.ok) {
        const errorText = await response.text();
        throw new Error(`HTTP ${response.status}: ${errorText}`);
    }
    
    return await response.json();
}

async function processPayPalPayment(customerData) {
    console.log('💙 Processing PayPal payment...');
    
    const orderData = {
        customer: customerData,
        items: cart.items,
        total: cart.getTotal(),
        payment_method: 'paypal'
    };
    
    const response = await fetch('api/initiate-paypal-payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(orderData)
    });
    
    if (!response.ok) {
        const errorText = await response.text();
        throw new Error(`HTTP ${response.status}: ${errorText}`);
    }
    
    const result = await response.json();
    
    // If PayPal returns approval URL, redirect user
    if (result.approval_url && result.approval_url !== '#') {
        window.location.href = result.approval_url;
        return { success: false, message: 'Redirecting to PayPal...' };
    }
    
    return result;
}

async function processCashOnDelivery(customerData) {
    console.log('💵 Processing Cash on Delivery...');
    
    const orderData = {
        customer: customerData,
        items: cart.items,
        total: cart.getTotal(),
        payment_method: 'cash'
    };
    
    const response = await fetch('api/create-order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(orderData)
    });
    
    if (!response.ok) {
        const errorText = await response.text();
        throw new Error(`HTTP ${response.status}: ${errorText}`);
    }
    
    return await response.json();
}

function handlePaymentSuccess(result, paymentMethod) {
    console.log('✅ Payment successful:', result);
    
    // Close checkout modal
    closeCheckout();
    
    // Clear cart
    cart.clearCart();
    cart.updateCartUI();  // Fixed: use cart.updateCartUI() instead of updateCartDisplay()
    
    // Show success message
    let message = '';
    
    switch (paymentMethod) {
        case 'mpesa':
            message = `✅ M-Pesa Payment Initiated!\n\n` +
                     `Order Reference: ${result.order_reference}\n` +
                     `Amount: KES ${result.amount}\n` +
                     `Phone: ${result.phone}\n\n`;
            if (result.test_mode) {
                message += '🧪 TEST MODE: No real payment charged\n\n';
            }
            message += 'Please complete the payment on your phone.';
            break;
            
        case 'paypal':
            message = `✅ PayPal Payment Initiated!\n\n` +
                     `Order Reference: ${result.order_reference || 'N/A'}\n` +
                     `Amount: $${result.amount_usd} USD (KES ${result.amount_kes})\n\n`;
            if (result.test_mode) {
                message += '🧪 TEST MODE: No real payment charged\n\n';
            }
            message += 'Your order has been placed!';
            break;
            
        case 'cash':
            message = `✅ Order Placed Successfully!\n\n` +
                     `Order Reference: ${result.order_reference}\n` +
                     `Amount: KES ${result.total}\n` +
                     `Payment Method: Cash on Delivery\n\n` +
                     `Please have the exact amount ready when your order arrives.`;
            break;
    }
    
    alert(message);
    
    // Optionally show success modal
    showSuccessModal(result, paymentMethod);
}

function showSuccessModal(result, paymentMethod) {
    const modal = document.getElementById('successModal');
    const detailsDiv = document.getElementById('successOrderDetails');
    
    if (modal && detailsDiv) {
        let paymentIcon = '';
        let paymentLabel = '';
        
        switch (paymentMethod) {
            case 'mpesa':
                paymentIcon = '<i class="fas fa-mobile-alt"></i>';
                paymentLabel = 'M-Pesa';
                break;
            case 'paypal':
                paymentIcon = '<i class="fab fa-paypal"></i>';
                paymentLabel = 'PayPal';
                break;
            case 'cash':
                paymentIcon = '<i class="fas fa-money-bill-wave"></i>';
                paymentLabel = 'Cash on Delivery';
                break;
        }
        
        detailsDiv.innerHTML = `
            <div class="order-detail-item">
                <strong>Payment Method:</strong>
                <span>${paymentIcon} ${paymentLabel}</span>
            </div>
            <div class="order-detail-item">
                <strong>Order Reference:</strong>
                <span>${result.order_reference || 'N/A'}</span>
            </div>
            <div class="order-detail-item">
                <strong>Total Amount:</strong>
                <span>KES ${result.amount || result.total || cart.getTotal()}</span>
            </div>
            ${result.test_mode ? '<p class="test-mode-badge">🧪 Test Mode - No real payment</p>' : ''}
        `;
        
        modal.style.display = 'flex';
        document.getElementById('overlay').style.display = 'block';
    }
}

function updateSubmitButtonText(paymentMethod) {
    const submitBtn = document.getElementById('submitPaymentBtn');
    const btnText = document.getElementById('paymentBtnText');
    const btnIcon = submitBtn.querySelector('i');
    
    if (!submitBtn || !btnText || !btnIcon) return;
    
    switch (paymentMethod) {
        case 'mpesa':
            btnText.textContent = 'Pay with M-Pesa';
            btnIcon.className = 'fas fa-mobile-alt';
            submitBtn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
            break;
        case 'paypal':
            btnText.textContent = 'Pay with PayPal';
            btnIcon.className = 'fab fa-paypal';
            submitBtn.style.background = 'linear-gradient(135deg, #0070ba 0%, #003087 100%)';
            break;
        case 'cash':
            btnText.textContent = 'Place Order (Cash on Delivery)';
            btnIcon.className = 'fas fa-money-bill-wave';
            submitBtn.style.background = 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)';
            break;
    }
}

// Export for use in main.js
if (typeof window !== 'undefined') {
    window.handleUnifiedPayment = handleUnifiedPayment;
    window.updateSubmitButtonText = updateSubmitButtonText;
}
