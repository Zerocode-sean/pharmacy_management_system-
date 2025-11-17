// Enhanced checkout form initialization - place this right after the checkout form in HTML
console.log('🔧 Enhanced checkout initialization starting...');

// Function to safely attach checkout event listener
function initializeCheckoutForm() {
    console.log('🔍 Looking for checkout form...');
    const checkoutForm = document.getElementById('checkoutForm');
    
    if (checkoutForm) {
        console.log('✅ Checkout form found!', checkoutForm);
        
        // Remove any existing listeners (to prevent duplicates)
        const newForm = checkoutForm.cloneNode(true);
        checkoutForm.parentNode.replaceChild(newForm, checkoutForm);
        
        // Add the event listener
        newForm.addEventListener('submit', function(event) {
            console.log('🚀 CHECKOUT FORM SUBMITTED!');
            console.log('Event:', event);
            
            // Call the main handleCheckout function
            if (typeof handleCheckout === 'function') {
                handleCheckout(event);
            } else {
                console.error('❌ handleCheckout function not found!');
                event.preventDefault();
                alert('Payment system not ready. Please refresh the page.');
            }
        });
        
        console.log('✅ Checkout form event listener attached successfully');
        
        // Test the button click
        const submitBtn = newForm.querySelector('button[type="submit"]');
        if (submitBtn) {
            console.log('✅ Submit button found:', submitBtn.textContent);
            
            submitBtn.addEventListener('click', function(e) {
                console.log('🖱️ Submit button clicked!');
            });
        }
        
        return true;
    } else {
        console.log('❌ Checkout form not found');
        return false;
    }
}

// Try to initialize immediately
if (document.readyState === 'loading') {
    console.log('⏳ Document still loading, waiting for DOMContentLoaded...');
    document.addEventListener('DOMContentLoaded', initializeCheckoutForm);
} else {
    console.log('✅ Document already loaded, initializing immediately...');
    initializeCheckoutForm();
}

// Also try with a slight delay
setTimeout(() => {
    console.log('🔄 Delayed initialization attempt...');
    if (!initializeCheckoutForm()) {
        console.log('⚠️ Checkout form still not found after delay');
    }
}, 1000);

console.log('🔧 Enhanced checkout initialization complete');
