/**
 * Point of Sale (POS) System - Clean Implementation
 * Handles sales transactions with robust session handling
 */

class POSManager {
    constructor() {
        this.medicines = [];
        this.filteredMedicines = [];
        this.cart = [];
        this.currentUser = null;
        this.paymentMethod = 'cash';
        this.taxRate = 0.08; // 8% tax
        
        this.init();
    }

    /**
     * Initialize the POS system
     */
    async init() {
        try {
            console.log('💰 POSManager: Initializing...');
            await this.loadUserData();
            console.log('💰 POSManager: User data loaded, setting up event listeners...');
            this.setupEventListeners();
            console.log('💰 POSManager: Loading POS data...');
            await this.loadPOSData();
            console.log('💰 POSManager: Updating UI...');
            this.updateUI();
            console.log('💰 POSManager: ✅ Initialized successfully');
        } catch (error) {
            console.error('💰 POSManager: ❌ Error initializing:', error);
            this.showNotification('Error initializing POS system', 'error');
        }
    }

    /**
     * Load user data - simplified to use main session management
     */
    async loadUserData() {
        try {
            console.log('💰 POSManager: Loading user data...');
            
            // Wait for main session system to be ready
            let attempts = 0;
            const maxAttempts = 10;
            
            while (attempts < maxAttempts) {
                const user = window.PharmacyApp?.Auth?.getUser();
                if (user) {
                    this.currentUser = user;
                    this.updateUserDisplay();
                    console.log('💰 POSManager: ✅ User data loaded:', this.currentUser.username);
                    return;
                }
                
                // Wait 100ms and try again
                await new Promise(resolve => setTimeout(resolve, 100));
                attempts++;
            }
            
            throw new Error('Main session system not available');
        } catch (error) {
            console.error('💰 POSManager: ❌ Error loading user data:', error);
            // Main session system will handle redirect if needed
        }
    }

    /**
     * Update user display in header
     */
    updateUserDisplay() {
        if (!this.currentUser) return;
        
        const headerUserName = document.getElementById('headerUserName');
        const headerUserRole = document.getElementById('headerUserRole');
        const headerUserAvatar = document.getElementById('headerUserAvatar');
        
        if (headerUserName) headerUserName.textContent = this.currentUser.full_name || this.currentUser.username;
        if (headerUserRole) headerUserRole.textContent = this.currentUser.role.charAt(0).toUpperCase() + this.currentUser.role.slice(1);
        if (headerUserAvatar) headerUserAvatar.textContent = (this.currentUser.full_name || this.currentUser.username).charAt(0).toUpperCase();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Medicine search
        const medicineSearch = document.getElementById('medicineSearch');
        if (medicineSearch) {
            medicineSearch.addEventListener('input', (e) => {
                this.filterMedicines(e.target.value);
            });
        }

        // Global functions for buttons
        window.toggleSidebar = () => {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.toggle('collapsed');
            }
        };

        window.logout = () => {
            this.handleLogout();
        };

        window.addToCart = (medicineId) => {
            this.addToCart(medicineId);
        };

        window.updateQuantity = (medicineId, quantity) => {
            this.updateQuantity(medicineId, quantity);
        };

        window.removeFromCart = (medicineId) => {
            this.removeFromCart(medicineId);
        };

        window.clearCart = () => {
            this.clearCart();
        };

        window.selectPaymentMethod = (method) => {
            this.selectPaymentMethod(method);
        };

        window.completeSale = () => {
            this.completeSale();
        };
    }

    /**
     * Load POS data (medicines, customers, stats)
     */
    async loadPOSData() {
        try {
            console.log('Loading POS data...');
            
            const response = await fetch('/Phamarcy/src/backend/api/sales.php', {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            
            if (result.success) {
                this.medicines = result.medicines || [];
                this.updateStats(result.stats || {});
                this.filterMedicines('');
                console.log(`Loaded ${this.medicines.length} medicines`);
            } else {
                throw new Error(result.message || 'Failed to load POS data');
            }
        } catch (error) {
            console.error('Error loading POS data:', error);
            this.showNotification('Error loading data: ' + error.message, 'error');
        }
    }

    /**
     * Filter medicines based on search
     */
    filterMedicines(searchTerm) {
        if (!searchTerm) {
            this.filteredMedicines = [...this.medicines];
        } else {
            const searchLower = searchTerm.toLowerCase();
            this.filteredMedicines = this.medicines.filter(medicine => 
                medicine.name.toLowerCase().includes(searchLower) ||
                medicine.generic_name.toLowerCase().includes(searchLower)
            );
        }
        this.updateMedicinesGrid();
    }

    /**
     * Update medicines grid display
     */
    updateMedicinesGrid() {
        const grid = document.getElementById('medicinesGrid');
        if (!grid) return;

        if (this.filteredMedicines.length === 0) {
            grid.innerHTML = '<p style="text-align: center; color: #64748b; padding: 20px;">No medicines found</p>';
            return;
        }

        grid.innerHTML = this.filteredMedicines.map(medicine => `
            <div class="medicine-card" onclick="addToCart(${medicine.id})">
                <div class="medicine-name">${this.escapeHtml(medicine.name)}</div>
                <div class="medicine-generic">${this.escapeHtml(medicine.generic_name)}</div>
                <div class="medicine-price">KSh ${parseFloat(medicine.unit_price).toFixed(2)}</div>
                <div class="medicine-stock">Stock: ${medicine.stock_quantity}</div>
            </div>
        `).join('');
    }

    /**
     * Update statistics display
     */
    updateStats(stats) {
        const todaySales = document.getElementById('todaySales');
        const todayRevenue = document.getElementById('todayRevenue');
        const avgSale = document.getElementById('avgSale');

        if (todaySales) todaySales.textContent = stats.total_sales || 0;
    if (todayRevenue) todayRevenue.textContent = 'KSh ' + (parseFloat(stats.total_revenue || 0).toFixed(2));
    if (avgSale) avgSale.textContent = 'KSh ' + (parseFloat(stats.avg_sale_amount || 0).toFixed(2));
    }

    /**
     * Add medicine to cart
     */
    addToCart(medicineId) {
        const medicine = this.medicines.find(m => m.id == medicineId);
        if (!medicine) return;

        const existingItem = this.cart.find(item => item.medicine_id == medicineId);
        
        if (existingItem) {
            // Check stock before increasing quantity
            if (existingItem.quantity >= medicine.stock_quantity) {
                this.showNotification('Not enough stock available', 'error');
                return;
            }
            existingItem.quantity += 1;
            existingItem.total_price = existingItem.quantity * existingItem.unit_price;
        } else {
            this.cart.push({
                medicine_id: medicineId,
                name: medicine.name,
                unit_price: parseFloat(medicine.unit_price),
                quantity: 1,
                total_price: parseFloat(medicine.unit_price)
            });
        }

        this.updateCartDisplay();
        this.updateCartSummary();
    }

    /**
     * Update item quantity in cart
     */
    updateQuantity(medicineId, quantity) {
        const item = this.cart.find(item => item.medicine_id == medicineId);
        const medicine = this.medicines.find(m => m.id == medicineId);
        
        if (!item || !medicine) return;

        const newQuantity = parseInt(quantity);
        
        if (newQuantity <= 0) {
            this.removeFromCart(medicineId);
            return;
        }

        if (newQuantity > medicine.stock_quantity) {
            this.showNotification('Not enough stock available', 'error');
            return;
        }

        item.quantity = newQuantity;
        item.total_price = item.quantity * item.unit_price;
        
        this.updateCartDisplay();
        this.updateCartSummary();
    }

    /**
     * Remove item from cart
     */
    removeFromCart(medicineId) {
        this.cart = this.cart.filter(item => item.medicine_id != medicineId);
        this.updateCartDisplay();
        this.updateCartSummary();
    }

    /**
     * Clear entire cart
     */
    clearCart() {
        this.cart = [];
        this.updateCartDisplay();
        this.updateCartSummary();
    }

    /**
     * Update cart display
     */
    updateCartDisplay() {
        const cartItems = document.getElementById('cartItems');
        const clearCartBtn = document.getElementById('clearCartBtn');
        const completeSaleBtn = document.getElementById('completeSaleBtn');
        
        if (!cartItems) return;

        if (this.cart.length === 0) {
            cartItems.innerHTML = `
                <div class="empty-cart">
                    <p style="text-align: center; color: #64748b; padding: 20px;">
                        Cart is empty. Select medicines to add.
                    </p>
                </div>
            `;
            if (clearCartBtn) clearCartBtn.disabled = true;
            if (completeSaleBtn) completeSaleBtn.disabled = true;
        } else {
            cartItems.innerHTML = this.cart.map(item => `
                <div class="cart-item">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${this.escapeHtml(item.name)}</div>
                        <div class="cart-item-price">KSh ${item.unit_price.toFixed(2)} each</div>
                    </div>
                    <div class="cart-item-controls">
                        <input type="number" class="qty-input" value="${item.quantity}" min="1" 
                               onchange="updateQuantity(${item.medicine_id}, this.value)">
                        <button class="remove-btn" onclick="removeFromCart(${item.medicine_id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');
            
            if (clearCartBtn) clearCartBtn.disabled = false;
            if (completeSaleBtn) completeSaleBtn.disabled = false;
        }
    }

    /**
     * Update cart summary (totals)
     */
    updateCartSummary() {
        const subtotal = this.cart.reduce((sum, item) => sum + item.total_price, 0);
        const tax = subtotal * this.taxRate;
        const total = subtotal + tax;

        const subtotalEl = document.getElementById('subtotal');
        const taxEl = document.getElementById('tax');
        const totalEl = document.getElementById('total');

    if (subtotalEl) subtotalEl.textContent = 'KSh ' + subtotal.toFixed(2);
    if (taxEl) taxEl.textContent = 'KSh ' + tax.toFixed(2);
    if (totalEl) totalEl.textContent = 'KSh ' + total.toFixed(2);
    }

    /**
     * Select payment method
     */
    selectPaymentMethod(method) {
        this.paymentMethod = method;
        
        // Update UI
        document.querySelectorAll('.payment-method').forEach(el => {
            el.classList.remove('active');
        });
        
        document.querySelector(`[data-method="${method}"]`).classList.add('active');
    }

    /**
     * Complete the sale
     */
    async completeSale() {
        if (this.cart.length === 0) {
            this.showNotification('Cart is empty', 'error');
            return;
        }

        try {
            const subtotal = this.cart.reduce((sum, item) => sum + item.total_price, 0);
            const taxAmount = subtotal * this.taxRate;
            const totalAmount = subtotal + taxAmount;

            const saleData = {
                customer_id: null, // Could be enhanced to support customer selection
                user_id: this.currentUser.user_id,
                items: this.cart,
                subtotal: subtotal.toFixed(2),
                discount_amount: 0,
                tax_amount: taxAmount.toFixed(2),
                total_amount: totalAmount.toFixed(2),
                payment_method: this.paymentMethod
            };

            const response = await fetch('/Phamarcy/src/backend/api/sales.php', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(saleData)
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Sale completed successfully!', 'success');
                this.clearCart();
                await this.loadPOSData(); // Refresh data to update stock
            } else {
                this.showNotification(result.message || 'Failed to complete sale', 'error');
            }
        } catch (error) {
            console.error('Error completing sale:', error);
            this.showNotification('Error completing sale', 'error');
        }
    }

    /**
     * Handle logout
     */
    async handleLogout() {
        try {
            const response = await fetch('/Phamarcy/src/backend/api/logout.php', {
                method: 'POST',
                credentials: 'include'
            });

            localStorage.removeItem('user');
            localStorage.removeItem('csrf_token');
            window.location.href = '/Phamarcy/src/frontend/index.html';
        } catch (error) {
            console.error('Error during logout:', error);
            window.location.href = '/Phamarcy/src/frontend/index.html';
        }
    }

    /**
     * Update UI
     */
    updateUI() {
        this.updateMedicinesGrid();
        this.updateCartDisplay();
        this.updateCartSummary();
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'success') {
        if (window.Utils) {
            Utils.showMessage(message, type);
        } else {
            alert(message);
        }
    }

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize when DOM is loaded with delay for main session system
document.addEventListener('DOMContentLoaded', () => {
    console.log('💰 POS: DOM loaded, waiting for session system...');
    setTimeout(() => {
        console.log('💰 POS: Initializing POSManager...');
        new POSManager();
    }, 500);
});
