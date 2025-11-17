// Main JavaScript for Customer Portal
let allMedicines = [];
let filteredMedicines = [];
let cart;

// ============================================
// UTILITY FUNCTIONS
// ============================================

/**
 * Escape HTML to prevent XSS attacks
 * @param {string} str - String to escape
 * @returns {string} - Escaped string
 */
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

/**
 * Format price with currency
 * @param {number} price - Price to format
 * @returns {string} - Formatted price
 */
function formatPrice(price) {
    if (price === null || price === undefined) return '0.00';
    return parseFloat(price).toFixed(2);
}

/**
 * Format date to readable format
 * @param {string} dateString - Date string to format
 * @returns {string} - Formatted date
 */
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

/**
 * Debounce function to limit function calls
 * @param {Function} func - Function to debounce
 * @param {number} wait - Wait time in milliseconds
 * @returns {Function} - Debounced function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// ============================================
// APPLICATION INITIALIZATION
// ============================================

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Customer Portal - DOMContentLoaded fired!');
    cart = new ShoppingCart();
    console.log('📦 Cart initialized:', cart);
    loadMedicines();
    setupEventListeners();
    checkCustomerAuth(); // Check customer authentication on load
    console.log('✅ Customer Portal initialization complete');
});

// Setup event listeners
function setupEventListeners() {
    console.log('🔧 Setting up event listeners...');
    
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(searchMedicines, 300));
        console.log('✅ Search input listener attached');
    } else {
        console.log('❌ Search input not found');
    }

    // Category filter
    const categoryFilter = document.getElementById('categoryFilter');
    if (categoryFilter) {
        categoryFilter.addEventListener('change', filterMedicines);
        console.log('✅ Category filter listener attached');
    } else {
        console.log('❌ Category filter not found');
    }

    // Payment method selection
    setupPaymentMethodListeners();

    // Checkout form - with retry mechanism
    setupCheckoutFormListener();
}

// Setup payment method listeners
function setupPaymentMethodListeners() {
    const paymentMethods = document.getElementsByName('paymentMethod');
    const paymentBtnText = document.getElementById('paymentBtnText');
    const submitBtn = document.getElementById('submitPaymentBtn');
    
    if (paymentMethods.length > 0 && paymentBtnText && submitBtn) {
        // Remove existing listeners first to prevent duplicates
        paymentMethods.forEach(radio => {
            // Clone and replace to remove old listeners
            const newRadio = radio.cloneNode(true);
            radio.parentNode.replaceChild(newRadio, radio);
        });
        
        // Get the updated radio buttons after cloning
        const updatedPaymentMethods = document.getElementsByName('paymentMethod');
        
        updatedPaymentMethods.forEach(radio => {
            radio.addEventListener('change', function() {
                const selectedMethod = this.value;
                const btnIcon = submitBtn.querySelector('i');
                
                console.log('💳 Payment method changed to:', selectedMethod);
                
                // Update button text and icon
                switch(selectedMethod) {
                    case 'mpesa':
                        paymentBtnText.textContent = 'Pay with M-Pesa';
                        btnIcon.className = 'fas fa-mobile-alt';
                        submitBtn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                        break;
                    case 'paypal':
                        paymentBtnText.textContent = 'Pay with PayPal';
                        btnIcon.className = 'fab fa-paypal';
                        submitBtn.style.background = 'linear-gradient(135deg, #0070ba 0%, #003087 100%)';
                        break;
                    case 'cash':
                        paymentBtnText.textContent = 'Place Order (Cash on Delivery)';
                        btnIcon.className = 'fas fa-money-bill-wave';
                        submitBtn.style.background = 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)';
                        break;
                }
            });
        });
        console.log('✅ Payment method listeners attached to', updatedPaymentMethods.length, 'radio buttons');
    } else {
        console.log('⚠️ Payment method elements not found yet');
    }
}

// Make function globally accessible
window.setupPaymentMethodListeners = setupPaymentMethodListeners;

// Setup checkout form listener with retry
function setupCheckoutFormListener() {
    const checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        // Remove any existing listeners first
        const newForm = checkoutForm.cloneNode(true);
        checkoutForm.parentNode.replaceChild(newForm, checkoutForm);
        
        // Add the submit listener
        newForm.addEventListener('submit', handleCheckout);
        console.log('✅ Checkout form listener attached to:', newForm);
        
        // Also add click listener to submit button for debugging
        const submitBtn = newForm.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.addEventListener('click', function(e) {
                console.log('🖱️ Pay button clicked! Form will submit...');
            });
        }
    } else {
        console.log('❌ Checkout form not found - retrying in 500ms...');
        setTimeout(setupCheckoutFormListener, 500);
    }
    
    console.log('🔧 Event listeners setup complete');
}

// Load medicines from the inventory API
async function loadMedicines() {
    try {
        showLoading(true);
        hideError();

        console.log('Loading medicines from API...');

        // Use the same medicines API that the pharmacist dashboard uses
        const response = await fetch('../src/backend/api/medicines_working.php?action=get', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        console.log('API Response status:', response.status, response.ok);

        if (!response.ok) {
            console.warn('API request failed, trying fallback...');
            loadFallbackMedicines();
            return;
        }

        // Get response text first to check if it's valid JSON
        const responseText = await response.text();
        console.log('Raw API Response:', responseText.substring(0, 200) + '...');

        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            console.error('Response text that failed to parse:', responseText.substring(0, 500));
            console.warn('Using fallback medicines due to JSON parse error');
            loadFallbackMedicines();
            return;
        }
        console.log('API Response data:', data);

        if (data.success && data.data) {
            console.log('Total medicines received:', data.data.length);
            
            // Normalize the data: convert 'category' to 'category_name' and 'manufacturer' to 'supplier_name'
            const normalizedData = data.data.map(medicine => ({
                ...medicine,
                category_name: medicine.category_name || medicine.category || 'General',
                supplier_name: medicine.supplier_name || medicine.manufacturer || 'Unknown Manufacturer'
            }));
            
            allMedicines = normalizedData.filter(medicine => {
                // Only show medicines that are in stock and not expired
                const isInStock = medicine.stock_quantity > 0;
                const isNotExpired = !medicine.expiry_date || new Date(medicine.expiry_date) > new Date();
                const isAvailable = isInStock && isNotExpired;
                
                console.log(`Medicine ${medicine.name}: stock=${medicine.stock_quantity}, expiry=${medicine.expiry_date}, available=${isAvailable}`);
                
                return isAvailable;
            });
            
            console.log('Available medicines count:', allMedicines.length);
            console.log('Filtered medicines:', allMedicines);
            
            filteredMedicines = [...allMedicines];
            displayMedicines(filteredMedicines);
            populateCategories();
            updateStats();
            
            // Show success message if medicines loaded
            if (allMedicines.length > 0) {
                console.log('✅ Successfully loaded medicines');
            } else {
                console.warn('⚠️ No available medicines found (all out of stock or expired)');
                showError('No medicines are currently available. All medicines are either out of stock or expired.');
            }
        } else {
            console.error('API returned unsuccessful response:', data);
            if (data.data && data.data.length === 0) {
                showError('No medicines found in the database. Please contact the pharmacy to add medicines.');
            } else {
                throw new Error(data.message || 'Failed to load medicines');
            }
        }
    } catch (error) {
        console.error('Error loading medicines:', error);
        console.warn('Using fallback medicines due to error');
        loadFallbackMedicines();
    } finally {
        showLoading(false);
    }
}

// Display medicines in the grid
function displayMedicines(medicines) {
    const grid = document.getElementById('medicinesGrid');
    const noResults = document.getElementById('noResults');
    
    if (!grid) return;

    if (medicines.length === 0) {
        grid.innerHTML = '';
        if (noResults) noResults.style.display = 'block';
        return;
    }

    if (noResults) noResults.style.display = 'none';

    grid.innerHTML = medicines.map(medicine => `
        <div class="medicine-card" data-id="${medicine.id}">
            <div class="medicine-info">
                <h4 class="medicine-name">${escapeHtml(medicine.name)}</h4>
                <p class="medicine-category">
                    <i class="fas fa-tag"></i>
                    ${escapeHtml(medicine.category_name || 'General')}
                </p>
                <p class="medicine-manufacturer">
                    <i class="fas fa-industry"></i>
                    ${escapeHtml(medicine.supplier_name || 'Unknown')}
                </p>
                <div class="medicine-details">
                    <div class="stock-info">
                        <i class="fas fa-boxes"></i>
                        <span class="stock-count">${medicine.stock_quantity} available</span>
                    </div>
                    ${medicine.expiry_date ? `
                        <div class="expiry-info">
                            <i class="fas fa-calendar"></i>
                            <span>Expires: ${formatDate(medicine.expiry_date)}</span>
                        </div>
                    ` : ''}
                </div>
            </div>
            <div class="medicine-actions">
                <div class="price">
                    <span class="currency">KES</span>
                    <span class="amount">${formatPrice(medicine.unit_price)}</span>
                </div>
                <button class="add-to-cart-btn" onclick="addToCart(${medicine.id})" 
                        ${medicine.stock_quantity <= 0 ? 'disabled' : ''}>
                    <i class="fas fa-cart-plus"></i>
                    ${medicine.stock_quantity <= 0 ? 'Out of Stock' : 'Add to Cart'}
                </button>
            </div>
        </div>
    `).join('');
}

// Populate category filter
function populateCategories() {
    const categoryFilter = document.getElementById('categoryFilter');
    if (!categoryFilter) return;

    const categories = [...new Set(allMedicines.map(m => m.category_name).filter(Boolean))];
    
    // Clear existing options except "All Categories"
    categoryFilter.innerHTML = '<option value="">All Categories</option>';
    
    categories.forEach(category => {
        const option = document.createElement('option');
        option.value = category;
        option.textContent = category;
        categoryFilter.appendChild(option);
    });
}

// Search medicines
function searchMedicines() {
    const searchTerm = document.getElementById('searchInput')?.value.toLowerCase().trim() || '';
    
    if (searchTerm === '') {
        filteredMedicines = [...allMedicines];
    } else {
        filteredMedicines = allMedicines.filter(medicine => 
            medicine.name.toLowerCase().includes(searchTerm) ||
            (medicine.category_name && medicine.category_name.toLowerCase().includes(searchTerm)) ||
            (medicine.supplier_name && medicine.supplier_name.toLowerCase().includes(searchTerm))
        );
    }
    
    applyFilters();
}

// Filter medicines by category
function filterMedicines() {
    applyFilters();
}

// Apply all filters
function applyFilters() {
    const categoryFilter = document.getElementById('categoryFilter')?.value || '';
    const priceFilter = document.getElementById('priceFilter')?.value || '';
    
    let filtered = [...filteredMedicines];
    
    // Apply category filter
    if (categoryFilter) {
        filtered = filtered.filter(medicine => medicine.category_name === categoryFilter);
    }
    
    // Apply price filter
    if (priceFilter) {
        const price = parseFloat(medicine.unit_price) || 0;
        switch(priceFilter) {
            case '0-10':
                filtered = filtered.filter(medicine => price <= 10);
                break;
            case '10-25':
                filtered = filtered.filter(medicine => price > 10 && price <= 25);
                break;
            case '25-50':
                filtered = filtered.filter(medicine => price > 25 && price <= 50);
                break;
            case '50+':
                filtered = filtered.filter(medicine => price > 50);
                break;
        }
    }
    
    displayMedicines(filtered);
}

// Clear all filters
function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('categoryFilter').value = '';
    if (document.getElementById('priceFilter')) {
        document.getElementById('priceFilter').value = '';
    }
    filteredMedicines = [...allMedicines];
    displayMedicines(filteredMedicines);
}

// Add medicine to cart
function addToCart(medicineId) {
    const medicine = allMedicines.find(m => m.id == medicineId);
    if (medicine && cart) {
        cart.addItem(medicine);
    }
}

// Proceed to checkout
function proceedToCheckout() {
    if (!cart || cart.items.length === 0) {
        alert('Your cart is empty');
        return;
    }

    // Close cart modal/sidebar
    toggleCart();
    
    // Show checkout modal
    showCheckout();
}

// Show checkout modal
function showCheckout() {
    const modal = document.getElementById('checkoutModal');
    const orderSummary = document.getElementById('orderSummary');
    const orderTotal = document.getElementById('orderTotal');
    
    if (modal) {
        // Populate order summary
        if (orderSummary && cart) {
            orderSummary.innerHTML = cart.items.map(item => `
                <div class="order-item">
                    <span class="item-name">${escapeHtml(item.name)}</span>
                    <span class="item-quantity">x${item.quantity}</span>
                    <span class="item-price">KES ${formatPrice(item.price * item.quantity)}</span>
                </div>
            `).join('');
        }
        
        if (orderTotal && cart) {
            orderTotal.textContent = `KES ${formatPrice(cart.getTotal())}`;
        }
        
        modal.style.display = 'flex';
        document.getElementById('overlay').style.display = 'block';
    }
}

// Handle checkout form submission - Payment First Flow
async function handleCheckout(event) {
    console.log('🚀 handleCheckout function called!');
    console.log('Event:', event);
    console.log('Event type:', typeof event);
    console.log('Event target:', event ? event.target : 'NO TARGET');
    
    // Add visual feedback immediately
    document.body.style.backgroundColor = '#fff3cd';
    setTimeout(() => { document.body.style.backgroundColor = ''; }, 1000);
    
    try {
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
            console.log('✅ preventDefault called successfully');
        } else {
            console.warn('⚠️ No preventDefault available');
        }
    } catch (preventError) {
        console.error('❌ preventDefault failed:', preventError);
    }
    
    console.log('🛒 Checking cart...');
    console.log('Cart exists:', !!cart);
    console.log('Cart items:', cart ? cart.items : 'NO CART');
    console.log('Cart length:', cart ? cart.items.length : 'NO CART');
    
    if (!cart || cart.items.length === 0) {
        console.error('❌ Cart validation failed - empty cart');
        alert('Your cart is empty');
        return;
    }
    
    console.log('✅ Cart validation passed');
    console.log('📋 Creating FormData from event.target:', event.target);

    let formData;
    let customerData;
    
    try {
        formData = new FormData(event.target);
        console.log('✅ FormData created successfully');
        
        customerData = {
            name: formData.get('name'),
            email: formData.get('email') || '',
            phone: formData.get('phone'),
            address: formData.get('address'),
            city: formData.get('city') || '',
            zip: formData.get('zip') || ''
        };
        
        console.log('📊 Customer data extracted:');
        console.log('  - Name:', customerData.name);
        console.log('  - Phone:', customerData.phone);
        console.log('  - Email:', customerData.email);
        console.log('  - Address:', customerData.address);
        
    } catch (formError) {
        console.error('❌ FormData creation failed:', formError);
        alert('Form data error: ' + formError.message);
        return;
    }

    // Validate required fields
    console.log('🔍 Validating required fields...');
    
    if (!customerData.name || !customerData.phone || !customerData.address) {
        console.error('❌ Validation failed:');
        console.error('  - Name:', customerData.name || 'MISSING');
        console.error('  - Phone:', customerData.phone || 'MISSING');
        console.error('  - Address:', customerData.address || 'MISSING');
        alert('Please fill in all required fields (Name, Phone, Address)');
        return;
    }
    
    console.log('✅ All required fields are present');

    const orderData = {
        customer: customerData,
        items: cart.items,
        total: cart.getTotal(),
        order_date: new Date().toISOString()
    };

    try {
        // Disable submit button to prevent double submission - multiple fallbacks
        let submitBtn = null;
        
        // Try multiple ways to find the button
        if (event.target) {
            submitBtn = event.target.querySelector('button[type="submit"]') || 
                       event.target.querySelector('button[type="button"]') ||
                       event.target.querySelector('button.btn-primary') ||
                       event.target._payButton; // Custom reference we set
        }
        
        // Fallback to document search
        if (!submitBtn) {
            submitBtn = document.querySelector('#checkoutForm button.btn-primary') ||
                       document.querySelector('#checkoutForm button[onclick*="handleCheckoutDirect"]');
        }
        
        console.log('🔍 Button search result:', submitBtn);
        console.log('Button type:', submitBtn ? submitBtn.type : 'N/A');
        console.log('Button class:', submitBtn ? submitBtn.className : 'N/A');
        
        if (submitBtn) {
            console.log('🔒 Disabling button and setting loading state...');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Initiating Payment...';
            console.log('✅ Button disabled and loading state set');
            
            // Visual feedback that something is happening
            submitBtn.style.backgroundColor = '#ffc107';
            submitBtn.style.borderColor = '#ffc107';
        } else {
            console.error('❌ Submit button not found for disabling!');
            console.error('Available buttons in form:', event.target ? event.target.querySelectorAll('button') : 'NO TARGET');
        }

        console.log('💾 Order data prepared:', orderData);
        console.log('📊 Order summary:');
        console.log('  - Customer:', orderData.customer.name);
        console.log('  - Phone:', orderData.customer.phone);
        console.log('  - Items:', orderData.items.length);
        console.log('  - Total: KES', orderData.total);

        console.log('🚀 Starting payment initiation process...');
        
        // Enhanced fetch with better error handling and debugging
        let response;
        try {
            // Build absolute URL to avoid path resolution issues
            const baseUrl = window.location.origin + window.location.pathname.replace('/index.html', '').replace(/\/$/, '');
            const apiUrl = baseUrl + '/api/initiate-payment.php';
            
            console.log('🔗 Base URL:', baseUrl);
            console.log('📡 API URL:', apiUrl);
            console.log('📍 Current location:', window.location.href);
            
            response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                },
                body: JSON.stringify(orderData)
            });

            console.log('✅ Fetch completed. Status:', response.status, 'OK:', response.ok);
            
        } catch (fetchError) {
            console.error('❌ Primary fetch failed:', fetchError);
            
            // Try alternative URL paths if first attempt fails
            const fallbackUrls = [
                './api/initiate-payment.php',
                'api/initiate-payment.php',
                window.location.origin + '/Phamarcy/customer-portal/api/initiate-payment.php'
            ];
            
            for (const fallbackUrl of fallbackUrls) {
                try {
                    console.log('🔄 Trying fallback:', fallbackUrl);
                    response = await fetch(fallbackUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(orderData)
                    });
                    
                    if (response.ok) {
                        console.log('✅ Fallback succeeded:', response.status);
                        break; // Exit the loop on success
                    }
                } catch (altError) {
                    console.error('❌ Fallback failed:', altError);
                    continue; // Try next URL
                }
            }
            
            // If all fallbacks failed
            if (!response || !response.ok) {
                throw new Error(`Network error: Unable to reach payment server at any URL. Original error: ${fetchError.message}`);
            }
        }

		// Check if response is ok
		if (!response.ok) {
			let errorBody = '';
			try {
				errorBody = await response.text();
			} catch (e) {
				// ignore
			}
			console.error('HTTP Error Response:', {
				status: response.status,
				statusText: response.statusText,
				url: response.url,
				headers: Object.fromEntries(response.headers.entries()),
				body: errorBody
			});
			throw new Error(`HTTP ${response.status}: ${response.statusText}${errorBody ? ` - ${errorBody}` : ''}`);
		}

        // Get response text first to check for JSON issues
        const responseText = await response.text();
        console.log('Raw response:', responseText);

        // Try to parse JSON
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (jsonError) {
            console.error('JSON parsing error:', jsonError);
            console.error('Response text:', responseText);
            throw new Error('Invalid response format. Please check server logs.');
        }

        console.log('Parsed result:', result);

        if (result.success) {
            // Close checkout modal
            closeCheckout();
            
            if (result.test_mode || !result.checkout_request_id) {
                // Test mode or mock payment - show success immediately
                alert(`✅ Payment Initiated Successfully!\n\nOrder Reference: ${result.order_reference}\nAmount: KES ${result.amount}\nPhone: ${result.phone}\n\n${result.test_mode ? 'TEST MODE: ' : ''}${result.message}`);
                
                // Clear cart to show order was placed
                cart.clearCart();
                cart.updateCartUI();  // Fixed: use cart.updateCartUI()
                
                // Show success message
                showPaymentSuccess(result);
            } else {
                // Real M-Pesa - show payment status modal
                showPaymentStatusModal(result);
                
                // Start monitoring payment status
                monitorPaymentStatus(result.checkout_request_id, result.order_reference);
            }
        } else {
            throw new Error(result.message || 'Failed to initiate payment');
        }
    } catch (error) {
        console.error('Error initiating payment:', error);
        alert(`Failed to initiate payment: ${error.message}`);
    } finally {
        // Re-enable submit button - multiple fallbacks
        let submitBtn = null;
        
        // Try multiple ways to find the button (same as above)
        if (event.target) {
            submitBtn = event.target.querySelector('button[type="submit"]') || 
                       event.target.querySelector('button[type="button"]') ||
                       event.target.querySelector('button.btn-primary') ||
                       event.target._payButton;
        }
        
        if (!submitBtn) {
            submitBtn = document.querySelector('#checkoutForm button.btn-primary') ||
                       document.querySelector('#checkoutForm button[onclick*="handleCheckoutDirect"]');
        }
        
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-credit-card"></i> Pay with M-Pesa';
            console.log('✅ Button re-enabled');
        } else {
            console.error('❌ Submit button not found for re-enabling!');
        }
    }
}

// Show payment modal
function showPaymentModal(orderId, amount, phoneNumber) {
    // Create payment modal if it doesn't exist
    if (!document.getElementById('paymentModal')) {
        createPaymentModal();
    }
    
    const modal = document.getElementById('paymentModal');
    const orderIdSpan = document.getElementById('paymentOrderId');
    const amountSpan = document.getElementById('paymentAmount');
    const phoneInput = document.getElementById('paymentPhone');
    
    if (orderIdSpan) orderIdSpan.textContent = orderId;
    if (amountSpan) amountSpan.textContent = `KES ${formatPrice(amount)}`;
    if (phoneInput) phoneInput.value = phoneNumber;
    
    modal.style.display = 'flex';
    document.getElementById('overlay').style.display = 'block';
}

// Create payment modal
function createPaymentModal() {
    const modalHTML = `
        <div class="modal" id="paymentModal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-credit-card"></i> Complete Payment</h3>
                    <button class="close-modal" onclick="closePayment()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="payment-info">
                    <div class="order-summary">
                        <h4>Order Summary</h4>
                        <p><strong>Order ID:</strong> #<span id="paymentOrderId"></span></p>
                        <p><strong>Total Amount:</strong> KES <span id="paymentAmount"></span></p>
                    </div>
                    
                    <div class="payment-method">
                        <h4><i class="fas fa-mobile-alt"></i> M-Pesa Payment</h4>
                        <p>Pay securely using M-Pesa Lipa Na M-Pesa</p>
                        
                        <div class="form-group">
                            <label for="paymentPhone">M-Pesa Phone Number</label>
                            <input type="tel" id="paymentPhone" placeholder="0712345678" required>
                            <small>Enter the phone number registered with M-Pesa</small>
                        </div>
                        
                        <div class="payment-instructions">
                            <ol>
                                <li>Enter your M-Pesa phone number above</li>
                                <li>Click "Pay with M-Pesa" button</li>
                                <li>Check your phone for the M-Pesa prompt</li>
                                <li>Enter your M-Pesa PIN to complete payment</li>
                            </ol>
                        </div>
                        
                        <div class="payment-status" id="paymentStatus" style="display: none;">
                            <div class="status-message"></div>
                            <div class="status-spinner" style="display: none;">
                                <i class="fas fa-spinner fa-spin"></i>
                                <span>Waiting for payment confirmation...</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closePayment()">Cancel Order</button>
                    <button type="button" class="btn btn-success" id="payWithMpesaBtn" onclick="initiatePayment()">
                        <i class="fas fa-mobile-alt"></i> Pay with M-Pesa
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// Initiate M-Pesa payment
async function initiatePayment() {
    const orderId = document.getElementById('paymentOrderId').textContent;
    const amount = document.getElementById('paymentAmount').textContent;
    const phone = document.getElementById('paymentPhone').value.trim();
    
    if (!phone) {
        alert('Please enter your M-Pesa phone number');
        return;
    }
    
    // Validate phone number format
    const phonePattern = /^(0|\+254|254)?[17]\d{8}$/;
    if (!phonePattern.test(phone)) {
        alert('Please enter a valid M-Pesa phone number (e.g., 0712345678)');
        return;
    }
    
    try {
        const payBtn = document.getElementById('payWithMpesaBtn');
        const statusDiv = document.getElementById('paymentStatus');
        const statusMessage = statusDiv.querySelector('.status-message');
        const statusSpinner = statusDiv.querySelector('.status-spinner');
        
        // Show loading state
        payBtn.disabled = true;
        payBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        const response = await fetch('api/mpesa-payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'initiate_payment',
                order_id: orderId,
                phone: phone,
                amount: parseFloat(amount)
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Show payment initiated status
            statusMessage.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-mobile-alt"></i>
                    ${result.customer_message || 'Payment request sent to your phone. Please check your M-Pesa and enter your PIN.'}
                </div>
            `;
            statusDiv.style.display = 'block';
            statusSpinner.style.display = 'block';
            
            // Hide pay button
            payBtn.style.display = 'none';
            
            // Start checking payment status
            checkPaymentStatus(result.payment_id, orderId);
            
        } else {
            throw new Error(result.message || 'Failed to initiate payment');
        }
        
    } catch (error) {
        console.error('Payment initiation error:', error);
        alert('Failed to initiate payment: ' + error.message);
        
        // Reset button
        const payBtn = document.getElementById('payWithMpesaBtn');
        payBtn.disabled = false;
        payBtn.innerHTML = '<i class="fas fa-mobile-alt"></i> Pay with M-Pesa';
    }
}

// Check payment status
async function checkPaymentStatus(paymentId, orderId, attempts = 0) {
    const maxAttempts = 60; // Check for 5 minutes (60 * 5 seconds)
    
    if (attempts >= maxAttempts) {
        showPaymentTimeout(orderId);
        return;
    }
    
    try {
        const response = await fetch('api/mpesa-payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'check_payment_status',
                payment_id: paymentId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            const status = result.status;
            
            if (status === 'completed') {
                showPaymentSuccess(orderId);
            } else if (status === 'failed') {
                showPaymentFailed(result.payment_details?.failure_reason || 'Payment was cancelled or failed');
            } else {
                // Still pending, check again in 5 seconds
                setTimeout(() => {
                    checkPaymentStatus(paymentId, orderId, attempts + 1);
                }, 5000);
            }
        } else {
            throw new Error(result.message);
        }
        
    } catch (error) {
        console.error('Payment status check error:', error);
        // Continue checking despite error
        setTimeout(() => {
            checkPaymentStatus(paymentId, orderId, attempts + 1);
        }, 5000);
    }
}

// Show payment status modal
function showPaymentStatusModal(paymentData) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal payment-status-modal">
            <div class="modal-header">
                <h3>Payment in Progress</h3>
                <button class="close-btn" onclick="closePaymentStatusModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="payment-status">
                    <div class="status-icon">
                        <i class="fas fa-mobile-alt fa-3x"></i>
                    </div>
                    <h4>Check Your Phone</h4>
                    <p>A payment request has been sent to <strong>${paymentData.phone}</strong></p>
                    <p class="amount">Amount: <strong>KES ${paymentData.amount}</strong></p>
                    <p>Order Reference: <strong>${paymentData.order_reference}</strong></p>
                    <div class="status-message" id="paymentStatusMessage">
                        <i class="fas fa-spinner fa-spin"></i>
                        Waiting for payment confirmation...
                    </div>
                    <div class="payment-instructions">
                        <h5>Instructions:</h5>
                        <ol>
                            <li>Check your phone for M-Pesa popup</li>
                            <li>Enter your M-Pesa PIN</li>
                            <li>Press OK to complete payment</li>
                        </ol>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="cancelPayment()">Cancel Order</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

// Close payment status modal
function closePaymentStatusModal() {
    const modal = document.querySelector('.payment-status-modal');
    if (modal) {
        modal.parentElement.remove();
    }
}

// Monitor payment status
function monitorPaymentStatus(checkoutRequestId, orderReference) {
    let attempts = 0;
    const maxAttempts = 60; // Monitor for 5 minutes (60 * 5 seconds)
    
    const checkStatus = async () => {
        attempts++;
        
        try {
            const response = await fetch('api/check-payment-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    checkout_request_id: checkoutRequestId,
                    order_reference: orderReference
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                if (result.status === 'completed') {
                    // Payment successful
                    showPaymentSuccess(result);
                    return;
                } else if (result.status === 'failed') {
                    // Payment failed
                    showPaymentFailure(result.message);
                    return;
                }
                // If pending, continue monitoring
            }
            
            // Continue monitoring if not completed and within attempts limit
            if (attempts < maxAttempts) {
                setTimeout(checkStatus, 5000); // Check every 5 seconds
                updatePaymentStatusMessage(`Checking payment status... (${attempts}/${maxAttempts})`);
            } else {
                // Timeout
                showPaymentTimeout();
            }
            
        } catch (error) {
            console.error('Error checking payment status:', error);
            if (attempts < maxAttempts) {
                setTimeout(checkStatus, 5000);
            } else {
                showPaymentTimeout();
            }
        }
    };
    
    // Start monitoring after 5 seconds
    setTimeout(checkStatus, 5000);
}

// Update payment status message
function updatePaymentStatusMessage(message) {
    const statusMessage = document.getElementById('paymentStatusMessage');
    if (statusMessage) {
        statusMessage.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${message}`;
    }
}

// Show payment success
function showPaymentSuccess(result) {
    const statusMessage = document.getElementById('paymentStatusMessage');
    if (statusMessage) {
        statusMessage.innerHTML = `
            <div class="success-message">
                <i class="fas fa-check-circle fa-2x" style="color: #28a745;"></i>
                <h4>Payment Successful!</h4>
                <p>Order #${result.order_id} has been confirmed</p>
                <p>Transaction ID: ${result.transaction_id}</p>
            </div>
        `;
    }
    
    // Clear cart and close modal after delay
    setTimeout(() => {
        cart.clearCart();
        cart.updateCartUI();  // Fixed: use cart.updateCartUI()
        closePaymentStatusModal();
        alert('Order placed successfully! You will receive a confirmation SMS shortly.');
    }, 3000);
}

// Show payment failure
function showPaymentFailure(message) {
    const statusMessage = document.getElementById('paymentStatusMessage');
    if (statusMessage) {
        statusMessage.innerHTML = `
            <div class="error-message">
                <i class="fas fa-times-circle fa-2x" style="color: #dc3545;"></i>
                <h4>Payment Failed</h4>
                <p>${message}</p>
                <button type="button" class="btn btn-primary" onclick="retryPayment()">Try Again</button>
            </div>
        `;
    }
}

// Show payment timeout
function showPaymentTimeout() {
    const statusMessage = document.getElementById('paymentStatusMessage');
    if (statusMessage) {
        statusMessage.innerHTML = `
            <div class="warning-message">
                <i class="fas fa-clock fa-2x" style="color: #ffc107;"></i>
                <h4>Payment Timeout</h4>
                <p>Payment verification timed out. If you completed the payment, your order will be processed automatically.</p>
                <p>Contact support if you need assistance.</p>
                <button type="button" class="btn btn-secondary" onclick="closePaymentStatusModal()">Close</button>
            </div>
        `;
    }
}

// Cancel payment
function cancelPayment() {
    if (confirm('Are you sure you want to cancel this order?')) {
        closePaymentStatusModal();
    }
}

// Retry payment (placeholder)
function retryPayment() {
    alert('Please try placing your order again.');
    closePaymentStatusModal();
}

// Close payment modal
function closePayment() {
    const modal = document.getElementById('paymentModal');
    if (modal) {
        modal.style.display = 'none';
        document.getElementById('overlay').style.display = 'none';
    }
}

// Show success modal
function showSuccess(orderId) {
    const modal = document.getElementById('successModal');
    const orderDetails = document.getElementById('successOrderDetails');
    
    if (modal) {
        if (orderDetails && orderId) {
            orderDetails.innerHTML = `
                <div class="order-id">
                    <strong>Order ID:</strong> #${orderId}
                </div>
                <p>We will contact you shortly to confirm your order and arrange delivery.</p>
            `;
        }
        
        modal.style.display = 'flex';
        document.getElementById('overlay').style.display = 'block';
    }
}

// Close checkout modal
function closeCheckout() {
    const modal = document.getElementById('checkoutModal');
    if (modal) {
        modal.style.display = 'none';
        document.getElementById('overlay').style.display = 'none';
    }
}

// Close success modal
function closeSuccess() {
    const modal = document.getElementById('successModal');
    if (modal) {
        modal.style.display = 'none';
        document.getElementById('overlay').style.display = 'none';
        
        // Reload the page to refresh inventory
        location.reload();
    }
}

// Close all modals
function closeAll() {
    closeCheckout();
    closeSuccess();
    toggleCart();
}

// Toggle cart sidebar
function toggleCart() {
    const sidebar = document.getElementById('cartSidebar');
    const overlay = document.getElementById('overlay');
    
    if (sidebar) {
        const isOpen = sidebar.classList.contains('open');
        
        if (isOpen) {
            sidebar.classList.remove('open');
            overlay.style.display = 'none';
        } else {
            sidebar.classList.add('open');
            overlay.style.display = 'block';
        }
    }
}

// Update statistics
function updateStats() {
    const totalElement = document.getElementById('totalMedicines');
    if (totalElement) {
        totalElement.textContent = allMedicines.length;
    }
}

// Show/hide loading spinner
function showLoading(show) {
    const loading = document.getElementById('loading');
    if (loading) {
        loading.style.display = show ? 'flex' : 'none';
    }
}

// Show/hide error message
function showError(message, type = 'error') {
    const errorDiv = document.getElementById('errorMessage');
    if (errorDiv) {
        let icon = 'fas fa-exclamation-triangle';
        let className = 'error-message';
        
        if (type === 'info') {
            icon = 'fas fa-info-circle';
            className = 'info-message';
        } else if (type === 'warning') {
            icon = 'fas fa-exclamation-triangle';
            className = 'warning-message';
        }
        
        const messageP = errorDiv.querySelector('p');
        if (messageP) {
            messageP.textContent = message;
            errorDiv.querySelector('i').className = icon;
        } else {
            errorDiv.innerHTML = `<i class="${icon}"></i><p>${message}</p>`;
        }
        
        errorDiv.className = className;
        errorDiv.style.display = 'flex';
    } else {
        // Fallback: show alert if error div not found
        console.error('Error message div not found, showing alert:', message);
        alert((type === 'info' ? 'Info: ' : 'Error: ') + message);
    }
}

function hideError() {
    const errorDiv = document.getElementById('errorMessage');
    if (errorDiv) {
        errorDiv.style.display = 'none';
    }
}

// Customer Authentication Functions
function checkCustomerAuth() {
    console.log('🔐 Checking customer authentication status...');
    
    const customerData = localStorage.getItem('customer');
    const customerToken = localStorage.getItem('customer_token');
    
    if (customerData && customerToken) {
        try {
            const customer = JSON.parse(customerData);
            showCustomerProfile(customer);
            console.log('✅ Customer authenticated:', customer.firstName);
            return true;
        } catch (error) {
            console.error('❌ Error parsing customer data:', error);
            clearCustomerAuth();
            return false;
        }
    } else {
        showAuthButtons();
        console.log('ℹ️ Customer not authenticated');
        return false;
    }
}

function showCustomerProfile(customer) {
    const authButtons = document.getElementById('authButtons');
    const customerProfile = document.getElementById('customerProfile');
    const customerName = document.getElementById('customerName');
    const customerEmail = document.getElementById('customerEmail');
    const customerAvatar = document.getElementById('customerAvatar');
    
    if (authButtons && customerProfile && customerName && customerEmail && customerAvatar) {
        // Hide auth buttons, show profile
        authButtons.style.display = 'none';
        customerProfile.style.display = 'flex';
        
        // Update profile information
        customerName.textContent = `${customer.firstName} ${customer.lastName}`;
        customerEmail.textContent = customer.email;
        
        // Set avatar initial
        const initial = customer.firstName.charAt(0).toUpperCase();
        customerAvatar.innerHTML = initial;
        
        console.log('✅ Customer profile displayed');
    } else {
        console.error('❌ Customer profile elements not found');
    }
}

function showAuthButtons() {
    const authButtons = document.getElementById('authButtons');
    const customerProfile = document.getElementById('customerProfile');
    
    if (authButtons && customerProfile) {
        // Show auth buttons, hide profile
        authButtons.style.display = 'flex';
        customerProfile.style.display = 'none';
        
        console.log('✅ Auth buttons displayed');
    }
}

function toggleProfileDropdown() {
    const dropdown = document.getElementById('profileDropdown');
    
    if (dropdown) {
        dropdown.classList.toggle('active');
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function closeDropdown(event) {
        if (!event.target.closest('.customer-profile')) {
            dropdown.classList.remove('active');
            document.removeEventListener('click', closeDropdown);
        }
    });
}

function customerLogout(event) {
    // Prevent default action if event exists
    if (event) {
        event.preventDefault();
    }
    
    console.log('🚪 Customer logout initiated');
    
    // Clear authentication data
    clearCustomerAuth();
    
    // Update UI
    showAuthButtons();
    
    // Close dropdown if open
    const dropdown = document.getElementById('profileDropdown');
    if (dropdown) {
        dropdown.classList.remove('active');
    }
    
    // Clear cart
    if (window.cart) {
        window.cart.clearCart();
    }
    
    // Show logout message
    showNotification('You have been logged out successfully. Come back soon!', 'success');
    
    console.log('✅ Customer logout complete');
    
    // Optional: Scroll to top for better UX
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function clearCustomerAuth() {
    localStorage.removeItem('customer');
    localStorage.removeItem('customer_token');
    console.log('🗑️ Customer authentication data cleared');
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// Update the main initialization function to include auth check

// Fallback medicines data when API fails
function loadFallbackMedicines() {
    console.log('🔄 Loading fallback medicines data...');
    
    // Demo medicines data
    allMedicines = [
        {
            id: 1,
            name: 'Paracetamol 500mg',
            category: 'Pain Relief',
            manufacturer: 'PharmaCorp',
            unit_price: 250.00,
            stock_quantity: 100,
            description: 'Effective pain and fever relief tablets. Safe for adults and children over 12 years.',
            dosage: '500mg',
            expiry_date: '2025-12-31',
            image_url: null
        },
        {
            id: 2,
            name: 'Amoxicillin 250mg',
            category: 'Antibiotics',
            manufacturer: 'MediPharm',
            unit_price: 450.00,
            stock_quantity: 75,
            description: 'Broad-spectrum antibiotic for bacterial infections. Prescription required.',
            dosage: '250mg',
            expiry_date: '2025-11-30',
            image_url: null
        },
        {
            id: 3,
            name: 'Vitamin C 1000mg',
            category: 'Vitamins',
            manufacturer: 'HealthPlus',
            unit_price: 180.00,
            stock_quantity: 150,
            description: 'Immune system support with high-potency Vitamin C tablets.',
            dosage: '1000mg',
            expiry_date: '2026-06-30',
            image_url: null
        },
        {
            id: 4,
            name: 'Ibuprofen 400mg',
            category: 'Pain Relief',
            manufacturer: 'PharmaCorp',
            unit_price: 320.00,
            stock_quantity: 80,
            description: 'Anti-inflammatory pain relief for headaches, muscle pain, and fever.',
            dosage: '400mg',
            expiry_date: '2025-10-15',
            image_url: null
        },
        {
            id: 5,
            name: 'Cough Syrup',
            category: 'Cold & Flu',
            manufacturer: 'CoughCare',
            unit_price: 380.00,
            stock_quantity: 45,
            description: 'Effective cough suppressant syrup for dry and productive coughs.',
            dosage: '120ml bottle',
            expiry_date: '2025-09-20',
            image_url: null
        },
        {
            id: 6,
            name: 'Multivitamin Complex',
            category: 'Vitamins',
            manufacturer: 'HealthPlus',
            unit_price: 520.00,
            stock_quantity: 60,
            description: 'Complete daily vitamin and mineral supplement for overall health.',
            dosage: 'Daily tablet',
            expiry_date: '2026-03-15',
            image_url: null
        },
        {
            id: 7,
            name: 'Antacid Tablets',
            category: 'Digestive Health',
            manufacturer: 'DigestWell',
            unit_price: 220.00,
            stock_quantity: 90,
            description: 'Fast relief from heartburn, indigestion, and stomach acid.',
            dosage: 'Chewable tablets',
            expiry_date: '2025-12-01',
            image_url: null
        },
        {
            id: 8,
            name: 'Antiseptic Cream',
            category: 'First Aid',
            manufacturer: 'WoundCare',
            unit_price: 280.00,
            stock_quantity: 35,
            description: 'Topical antiseptic cream for minor cuts, burns, and wounds.',
            dosage: '30g tube',
            expiry_date: '2025-08-30',
            image_url: null
        }
    ];
    
    console.log('✅ Fallback medicines loaded:', allMedicines.length);
    
    // Filter and display
    filteredMedicines = [...allMedicines];
    displayMedicines(filteredMedicines);
    populateCategories();
    updateStats();
    
    // Show info message about fallback mode
    showError('Demo mode: Using sample medicine data. Connect to database for live inventory.', 'info');
}