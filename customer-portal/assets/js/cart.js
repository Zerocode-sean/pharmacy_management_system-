// Shopping Cart Management
class ShoppingCart {
    constructor() {
        this.items = [];
        this.init();
    }

    init() {
        this.loadCartFromStorage();
        this.updateCartUI();
    }

    // Add item to cart
    addItem(medicine) {
        const existingItem = this.items.find(item => item.id === medicine.id);
        
        if (existingItem) {
            existingItem.quantity += 1;
        } else {
            this.items.push({
                id: medicine.id,
                name: medicine.name,
                price: parseFloat(medicine.unit_price || medicine.price || 0),
                quantity: 1,
                stock: medicine.stock_quantity || 0
            });
        }
        
        this.saveCartToStorage();
        this.updateCartUI();
        this.showAddedNotification(medicine.name);
    }

    // Remove item from cart
    removeItem(medicineId) {
        this.items = this.items.filter(item => item.id !== medicineId);
        this.saveCartToStorage();
        this.updateCartUI();
    }

    // Update item quantity
    updateQuantity(medicineId, newQuantity) {
        const item = this.items.find(item => item.id === medicineId);
        if (item) {
            if (newQuantity <= 0) {
                this.removeItem(medicineId);
            } else if (newQuantity <= item.stock) {
                item.quantity = newQuantity;
                this.saveCartToStorage();
                this.updateCartUI();
            } else {
                alert(`Sorry, only ${item.stock} units available in stock.`);
            }
        }
    }

    // Get total amount
    getTotal() {
        return this.items.reduce((total, item) => total + (item.price * item.quantity), 0);
    }

    // Get item count
    getItemCount() {
        return this.items.reduce((count, item) => count + item.quantity, 0);
    }

    // Clear cart
    clearCart() {
        this.items = [];
        this.saveCartToStorage();
        this.updateCartUI();
    }

    // Save cart to localStorage
    saveCartToStorage() {
        localStorage.setItem('pharmacyCart', JSON.stringify(this.items));
    }

    // Load cart from localStorage
    loadCartFromStorage() {
        const savedCart = localStorage.getItem('pharmacyCart');
        if (savedCart) {
            this.items = JSON.parse(savedCart);
        }
    }

    // Update cart UI
    updateCartUI() {
        this.updateCartCount();
        this.updateCartSidebar();
    }

    // Update cart count badge
    updateCartCount() {
        const cartCount = document.getElementById('cartCount');
        const count = this.getItemCount();
        cartCount.textContent = count;
        cartCount.style.display = count > 0 ? 'flex' : 'none';
    }

    // Update cart sidebar
    updateCartSidebar() {
        const cartItems = document.getElementById('cartItems');
        const cartEmpty = document.getElementById('cartEmpty');
        const cartFooter = document.getElementById('cartFooter');

        if (this.items.length === 0) {
            cartItems.style.display = 'none';
            cartEmpty.style.display = 'block';
            cartFooter.style.display = 'none';
        } else {
            cartItems.style.display = 'block';
            cartEmpty.style.display = 'none';
            cartFooter.style.display = 'block';

            // Render cart items
            cartItems.innerHTML = this.items.map(item => `
                <div class="cart-item">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-price">KES ${item.price.toFixed(2)} each</div>
                    </div>
                    <div class="quantity-controls">
                        <button class="quantity-btn" onclick="cart.updateQuantity(${item.id}, ${item.quantity - 1})">
                            <i class="fas fa-minus"></i>
                        </button>
                        <span>${item.quantity}</span>
                        <button class="quantity-btn" onclick="cart.updateQuantity(${item.id}, ${item.quantity + 1})">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <button class="remove-item" onclick="cart.removeItem(${item.id})" title="Remove item">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `).join('');

            // Update total
            document.getElementById('cartTotal').textContent = `KES ${this.getTotal().toFixed(2)}`;
        }
    }

    // Show notification when item added
    showAddedNotification(medicineName) {
        // Create a simple notification
        const notification = document.createElement('div');
        notification.className = 'add-notification';
        notification.innerHTML = `
            <i class="fas fa-check"></i>
            ${medicineName} added to cart
        `;
        
        // Add styles
        Object.assign(notification.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            background: '#27ae60',
            color: 'white',
            padding: '15px 20px',
            borderRadius: '8px',
            zIndex: '3000',
            display: 'flex',
            alignItems: 'center',
            gap: '10px',
            boxShadow: '0 4px 15px rgba(0,0,0,0.2)',
            transform: 'translateX(400px)',
            transition: 'transform 0.3s ease'
        });

        document.body.appendChild(notification);

        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);

        // Remove after 3 seconds
        setTimeout(() => {
            notification.style.transform = 'translateX(400px)';
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 3000);
    }
}

// Initialize cart (will be done in main.js)
// const cart = new ShoppingCart();

// Cart UI functions
function toggleCart() {
    const cartSidebar = document.getElementById('cartSidebar');
    const overlay = document.getElementById('overlay');
    
    cartSidebar.classList.toggle('open');
    overlay.classList.toggle('active');
}

function closeCart() {
    const cartSidebar = document.getElementById('cartSidebar');
    const overlay = document.getElementById('overlay');
    
    cartSidebar.classList.remove('open');
    overlay.classList.remove('active');
}

function proceedToCheckout() {
    if (cart.items.length === 0) {
        alert('Your cart is empty!');
        return;
    }
    
    closeCart();
    showCheckoutModal();
}

function showCheckoutModal() {
    const modal = document.getElementById('checkoutModal');
    const orderSummary = document.getElementById('orderSummary');
    const orderTotal = document.getElementById('orderTotal');
    
    // Populate order summary
    orderSummary.innerHTML = cart.items.map(item => `
        <div class="summary-item">
            <span>${item.name} x ${item.quantity}</span>
            <span>$${(item.price * item.quantity).toFixed(2)}</span>
        </div>
    `).join('');
    
    orderTotal.textContent = cart.getTotal().toFixed(2);
    
    modal.classList.add('active');
    
    // Setup payment method listeners after modal is shown
    // This ensures the radio buttons exist in the DOM
    setTimeout(() => {
        if (typeof setupPaymentMethodListeners === 'function') {
            setupPaymentMethodListeners();
            console.log('✅ Payment method listeners attached after modal open');
        }
    }, 100);
}

function closeCheckout() {
    const modal = document.getElementById('checkoutModal');
    modal.classList.remove('active');
}

function closeAll() {
    closeCart();
    closeCheckout();
    closeSuccess();
}

function closeSuccess() {
    const modal = document.getElementById('successModal');
    modal.classList.remove('active');
}

// Handle checkout form submission (will be handled in main.js)
// document.addEventListener('DOMContentLoaded', function() {
//     const checkoutForm = document.getElementById('checkoutForm');
//     
//     if (checkoutForm) {
//         checkoutForm.addEventListener('submit', handleCheckoutSubmit);
//     }
// });

async function handleCheckoutSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const customerData = Object.fromEntries(formData);
    
    // Prepare order data
    const orderData = {
        customer: customerData,
        items: cart.items,
        total: cart.getTotal(),
        order_date: new Date().toISOString()
    };
    
    console.log('Submitting order:', orderData);
    
    try {
        // Show loading state
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        submitBtn.disabled = true;
        
        // Submit order to backend
        const response = await fetch('api/place-order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(orderData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Show success modal
            showSuccessModal(result.order_id, orderData);
            
            // Clear cart
            cart.clearCart();
            
            // Reset form
            e.target.reset();
            
            // Close checkout modal
            closeCheckout();
        } else {
            throw new Error(result.message || 'Failed to place order');
        }
        
    } catch (error) {
        console.error('Order submission error:', error);
        alert('Failed to place order: ' + error.message + '\n\nPlease try again or contact support.');
        
        // Reset button
        const submitBtn = e.target.querySelector('button[type="submit"]');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

function showSuccessModal(orderId, orderData) {
    const modal = document.getElementById('successModal');
    const orderDetails = document.getElementById('successOrderDetails');
    
    orderDetails.innerHTML = `
        <div class="order-details">
            <p><strong>Order ID:</strong> #${orderId}</p>
            <p><strong>Customer:</strong> ${orderData.customer.name}</p>
            <p><strong>Email:</strong> ${orderData.customer.email}</p>
            <p><strong>Total Amount:</strong> $${orderData.total.toFixed(2)}</p>
            <p><strong>Items:</strong> ${orderData.items.length} medicine(s)</p>
        </div>
    `;
    
    modal.classList.add('active');
}
