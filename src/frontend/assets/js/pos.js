// POS System JavaScript

class POSSystem {
    constructor() {
        this.cart = [];
        this.currentCustomer = null;
        this.paymentMethod = 'cash';
        this.products = [];
        this.subtotal = 0;
        this.discountAmount = 0;
        this.discountType = 'amount';
        this.taxRate = 0; // 0% tax for now
        this.total = 0;
        
        this.init();
    }
    
    init() {
        this.loadProducts();
        this.loadCustomers();
        this.setupEventListeners();
        this.updateCartDisplay();
    }
    
    setupEventListeners() {
        // Payment method selection
        document.querySelectorAll('.payment-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.payment-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                this.paymentMethod = e.target.dataset.method;
                this.updatePaymentDisplay();
            });
        });
        
        // Product search
        document.getElementById('productSearch').addEventListener('input', 
            this.debounce(() => this.searchProducts(), 300)
        );
        
        // Discount and amount received changes
        document.getElementById('discountAmount').addEventListener('change', () => this.calculateTotal());
        document.getElementById('discountType').addEventListener('change', () => this.calculateTotal());
        document.getElementById('amountReceived').addEventListener('change', () => this.calculateChange());
        
        // Enter key for product search
        document.getElementById('productSearch').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.searchProducts();
            }
        });
    }
    
    debounce(func, wait) {
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
    
    async loadProducts() {
        try {
            const response = await APIService.getMedicines({ limit: 100 });
            if (response.success) {
                this.products = response.data;
                this.displayProducts(this.products);
            }
        } catch (error) {
            console.error('Failed to load products:', error);
            Utils.showMessage('Failed to load products', 'error');
        }
    }
    
    async loadCustomers() {
        try {
            const response = await APIService.getCustomers();
            if (response.success) {
                const customerSelect = document.getElementById('customerSelect');
                customerSelect.innerHTML = '<option value="">Walk-in Customer</option>';
                
                response.data.forEach(customer => {
                    const option = document.createElement('option');
                    option.value = customer.id;
                    option.textContent = `${customer.name} - ${customer.phone || 'No phone'}`;
                    customerSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load customers:', error);
        }
    }
    
    displayProducts(products) {
        const productGrid = document.getElementById('productGrid');
        
        if (products.length === 0) {
            productGrid.innerHTML = '<div class="loading-message"><i class="fas fa-search"></i> No products found</div>';
            return;
        }
        
        productGrid.innerHTML = products.map(product => {
            const isLowStock = product.stock_quantity <= product.min_stock_level;
            const isOutOfStock = product.stock_quantity === 0;
            
            return `
                <div class="product-card ${isOutOfStock ? 'out-of-stock' : ''}" 
                     onclick="${isOutOfStock ? '' : `POS.addToCart(${product.id})`}">
                    <div class="product-name">${product.name}</div>
                    <div class="product-price">KSh ${parseFloat(product.selling_price).toFixed(2)}</div>
                    <div class="product-stock ${isLowStock ? 'stock-low' : ''}">
                        Stock: ${product.stock_quantity}
                        ${isLowStock && !isOutOfStock ? ' (Low)' : ''}
                        ${isOutOfStock ? ' (Out of Stock)' : ''}
                    </div>
                    ${product.generic_name ? `<div class="product-generic">${product.generic_name}</div>` : ''}
                </div>
            `;
        }).join('');
    }
    
    searchProducts() {
        const searchTerm = document.getElementById('productSearch').value.toLowerCase();
        
        if (!searchTerm) {
            this.displayProducts(this.products);
            return;
        }
        
        const filteredProducts = this.products.filter(product => 
            product.name.toLowerCase().includes(searchTerm) ||
            (product.generic_name && product.generic_name.toLowerCase().includes(searchTerm)) ||
            (product.brand && product.brand.toLowerCase().includes(searchTerm)) ||
            (product.barcode && product.barcode.includes(searchTerm))
        );
        
        this.displayProducts(filteredProducts);
    }
    
    clearSearch() {
        document.getElementById('productSearch').value = '';
        this.displayProducts(this.products);
    }
    
    filterByCategory(category) {
        // Update active category button
        document.querySelectorAll('.category-btn').forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
        
        if (!category) {
            this.displayProducts(this.products);
            return;
        }
        
        const filteredProducts = this.products.filter(product => 
            product.category_name === category
        );
        
        this.displayProducts(filteredProducts);
    }
    
    addToCart(productId) {
        const product = this.products.find(p => p.id === productId);
        
        if (!product || product.stock_quantity === 0) {
            Utils.showMessage('Product is out of stock', 'error');
            return;
        }
        
        const existingItem = this.cart.find(item => item.product_id === productId);
        
        if (existingItem) {
            if (existingItem.quantity >= product.stock_quantity) {
                Utils.showMessage('Cannot add more items than available in stock', 'error');
                return;
            }
            existingItem.quantity += 1;
            existingItem.total = existingItem.quantity * existingItem.price;
        } else {
            this.cart.push({
                product_id: productId,
                name: product.name,
                price: parseFloat(product.selling_price),
                quantity: 1,
                total: parseFloat(product.selling_price),
                max_quantity: product.stock_quantity
            });
        }
        
        this.updateCartDisplay();
        this.calculateTotal();
    }
    
    removeFromCart(productId) {
        this.cart = this.cart.filter(item => item.product_id !== productId);
        this.updateCartDisplay();
        this.calculateTotal();
    }
    
    updateQuantity(productId, change) {
        const item = this.cart.find(item => item.product_id === productId);
        
        if (!item) return;
        
        const newQuantity = item.quantity + change;
        
        if (newQuantity <= 0) {
            this.removeFromCart(productId);
            return;
        }
        
        if (newQuantity > item.max_quantity) {
            Utils.showMessage('Cannot exceed available stock', 'error');
            return;
        }
        
        item.quantity = newQuantity;
        item.total = item.quantity * item.price;
        
        this.updateCartDisplay();
        this.calculateTotal();
    }
    
    updateCartDisplay() {
        const cartItems = document.getElementById('cartItems');
        
        if (this.cart.length === 0) {
            cartItems.innerHTML = `
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Cart is empty</p>
                    <small>Search and add products to start a sale</small>
                </div>
            `;
            document.getElementById('completeSaleBtn').disabled = true;
            return;
        }
        
        cartItems.innerHTML = this.cart.map(item => `
            <div class="cart-item">
                <div class="cart-item-info">
                    <div class="cart-item-name">${item.name}</div>
                    <div class="cart-item-price">KSh ${item.price.toFixed(2)} each</div>
                </div>
                <div class="cart-item-controls">
                    <div class="quantity-control">
                        <button class="quantity-btn" onclick="POS.updateQuantity(${item.product_id}, -1)">-</button>
                        <span class="quantity-display">${item.quantity}</span>
                        <button class="quantity-btn" onclick="POS.updateQuantity(${item.product_id}, 1)">+</button>
                    </div>
                    <span class="item-total">KSh ${item.total.toFixed(2)}</span>
                    <i class="fas fa-times remove-item" onclick="POS.removeFromCart(${item.product_id})"></i>
                </div>
            </div>
        `).join('');
        
        document.getElementById('completeSaleBtn').disabled = false;
    }
    
    calculateTotal() {
        this.subtotal = this.cart.reduce((sum, item) => sum + item.total, 0);
        
        const discountAmount = parseFloat(document.getElementById('discountAmount').value) || 0;
        const discountType = document.getElementById('discountType').value;
        
        if (discountType === 'percentage') {
            this.discountAmount = (this.subtotal * discountAmount) / 100;
        } else {
            this.discountAmount = discountAmount;
        }
        
        const afterDiscount = this.subtotal - this.discountAmount;
        const taxAmount = afterDiscount * this.taxRate;
        this.total = afterDiscount + taxAmount;
        
        // Update display
        document.getElementById('subtotal').textContent = Utils.formatCurrency(this.subtotal);
        document.getElementById('tax').textContent = Utils.formatCurrency(taxAmount);
        document.getElementById('total').textContent = Utils.formatCurrency(this.total);
        
        this.calculateChange();
    }
    
    calculateChange() {
        if (this.paymentMethod !== 'cash') {
            document.getElementById('changeAmount').textContent = '$0.00';
            return;
        }
        
        const amountReceived = parseFloat(document.getElementById('amountReceived').value) || 0;
        const change = amountReceived - this.total;
        
        document.getElementById('changeAmount').textContent = Utils.formatCurrency(Math.max(0, change));
        document.getElementById('changeAmount').style.color = change >= 0 ? '#4CAF50' : '#d32f2f';
    }
    
    updatePaymentDisplay() {
        const cashPayment = document.getElementById('cashPayment');
        cashPayment.style.display = this.paymentMethod === 'cash' ? 'block' : 'none';
        
        if (this.paymentMethod !== 'cash') {
            document.getElementById('amountReceived').value = this.total.toFixed(2);
        }
        
        this.calculateChange();
    }
    
    clearCart() {
        if (this.cart.length === 0) return;
        
        if (confirm('Are you sure you want to clear the cart?')) {
            this.cart = [];
            this.updateCartDisplay();
            this.calculateTotal();
        }
    }
    
    holdSale() {
        if (this.cart.length === 0) {
            Utils.showMessage('Cart is empty', 'error');
            return;
        }
        
        const heldSales = JSON.parse(localStorage.getItem('heldSales') || '[]');
        const saleData = {
            id: Date.now(),
            cart: [...this.cart],
            customer: this.currentCustomer,
            timestamp: new Date().toISOString()
        };
        
        heldSales.push(saleData);
        localStorage.setItem('heldSales', JSON.stringify(heldSales));
        
        this.clearCart();
        Utils.showMessage('Sale held successfully');
    }
    
    async completeSale() {
        if (this.cart.length === 0) {
            Utils.showMessage('Cart is empty', 'error');
            return;
        }
        
        if (this.paymentMethod === 'cash') {
            const amountReceived = parseFloat(document.getElementById('amountReceived').value) || 0;
            if (amountReceived < this.total) {
                Utils.showMessage('Insufficient payment amount', 'error');
                return;
            }
        }
        
        try {
            const saleData = {
                customer_id: document.getElementById('customerSelect').value || null,
                items: this.cart.map(item => ({
                    medicine_id: item.product_id,
                    quantity: item.quantity,
                    unit_price: item.price,
                    total_price: item.total
                })),
                subtotal: this.subtotal,
                discount_amount: this.discountAmount,
                tax_amount: this.subtotal * this.taxRate,
                total_amount: this.total,
                payment_method: this.paymentMethod,
                amount_received: this.paymentMethod === 'cash' ? 
                    parseFloat(document.getElementById('amountReceived').value) : this.total
            };
            
            const response = await APIService.createSale(saleData);
            
            if (response.success) {
                this.showReceipt(response.sale);
                this.clearCart();
                Utils.showMessage('Sale completed successfully', 'success');
            } else {
                throw new Error(response.message);
            }
            
        } catch (error) {
            console.error('Sale completion failed:', error);
            Utils.showMessage('Failed to complete sale: ' + error.message, 'error');
        }
    }
    
    showReceipt(sale) {
        const receiptContent = document.getElementById('receiptContent');
        const now = new Date();
        
        receiptContent.innerHTML = `
            <div class="receipt">
                <div class="receipt-shop-info">
                    <h3>Pharmacy Management System</h3>
                    <p>123 Health Street<br>Medical City, MC 12345</p>
                    <p>Phone: (555) 123-4567</p>
                </div>
                
                <div class="receipt-sale-info">
                    <p><strong>Sale #${sale.id}</strong></p>
                    <p>Date: ${now.toLocaleDateString()}</p>
                    <p>Time: ${now.toLocaleTimeString()}</p>
                    <p>Cashier: ${Auth.getUser().full_name}</p>
                </div>
                
                <div class="receipt-items">
                    <table class="receipt-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${this.cart.map(item => `
                                <tr>
                                    <td>${item.name}</td>
                                    <td>${item.quantity}</td>
                                    <td>KSh ${item.price.toFixed(2)}</td>
                                    <td>KSh ${item.total.toFixed(2)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                
                <div class="receipt-totals">
                    <div class="receipt-line">
                        <span>Subtotal:</span>
                        <span>KSh ${this.subtotal.toFixed(2)}</span>
                    </div>
                    ${this.discountAmount > 0 ? `
                        <div class="receipt-line">
                            <span>Discount:</span>
                            <span>-KSh ${this.discountAmount.toFixed(2)}</span>
                        </div>
                    ` : ''}
                    <div class="receipt-line">
                        <span>Tax:</span>
                        <span>KSh ${(this.subtotal * this.taxRate).toFixed(2)}</span>
                    </div>
                    <div class="receipt-line total-line">
                        <span><strong>Total:</strong></span>
                        <span><strong>KSh ${this.total.toFixed(2)}</strong></span>
                    </div>
                    <div class="receipt-line">
                        <span>Payment (${this.paymentMethod}):</span>
                        <span>$${(this.paymentMethod === 'cash' ? 
                            parseFloat(document.getElementById('amountReceived').value) : 
                            this.total).toFixed(2)}</span>
                    </div>
                    ${this.paymentMethod === 'cash' ? `
                        <div class="receipt-line">
                            <span>Change:</span>
                            <span>KSh ${Math.max(0, parseFloat(document.getElementById('amountReceived').value) - this.total).toFixed(2)}</span>
                        </div>
                    ` : ''}
                </div>
                
                <div class="receipt-footer">
                    <p>Thank you for your business!</p>
                    <p>Please keep this receipt for your records.</p>
                </div>
            </div>
        `;
        
        document.getElementById('receiptModal').style.display = 'block';
    }
    
    newSale() {
        this.closeReceiptModal();
        this.cart = [];
        document.getElementById('customerSelect').value = '';
        document.getElementById('discountAmount').value = '0';
        document.getElementById('amountReceived').value = '';
        this.updateCartDisplay();
        this.calculateTotal();
    }
    
    closeReceiptModal() {
        document.getElementById('receiptModal').style.display = 'none';
    }
    
    printReceipt() {
        const receiptContent = document.getElementById('receiptContent').innerHTML;
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Receipt</title>
                    <style>
                        body { font-family: 'Courier New', monospace; max-width: 300px; margin: 0 auto; }
                        .receipt-table { width: 100%; border-collapse: collapse; }
                        .receipt-table th, .receipt-table td { text-align: left; padding: 2px; }
                        .receipt-line { display: flex; justify-content: space-between; margin: 2px 0; }
                        .total-line { border-top: 1px solid #000; padding-top: 5px; }
                        .receipt-shop-info, .receipt-footer { text-align: center; }
                    </style>
                </head>
                <body>${receiptContent}</body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
    
    emailReceipt() {
        Utils.showMessage('Email receipt functionality coming soon', 'info');
    }
}

// Global POS instance
let POS;

// Initialize POS when page loads
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.pathname.includes('sales.html')) {
        POS = new POSSystem();
    }
});

// Global functions for onclick handlers
function searchProducts() {
    if (POS) POS.searchProducts();
}

function clearSearch() {
    if (POS) POS.clearSearch();
}

function filterByCategory(category) {
    if (POS) POS.filterByCategory(category);
}

function clearCart() {
    if (POS) POS.clearCart();
}

function holdSale() {
    if (POS) POS.holdSale();
}

function completeSale() {
    if (POS) POS.completeSale();
}

function closeReceiptModal() {
    if (POS) POS.closeReceiptModal();
}

function printReceipt() {
    if (POS) POS.printReceipt();
}

function emailReceipt() {
    if (POS) POS.emailReceipt();
}

function newSale() {
    if (POS) POS.newSale();
}

function showNewCustomerForm() {
    // This will be implemented when we create customer management
    Utils.showMessage('Customer management coming next!', 'info');
}
