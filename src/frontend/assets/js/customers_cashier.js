/**
 * Customers Management for Cashiers
 * Handles customer CRUD operations with cashier-specific features
 */

class CustomersCashier {
    constructor() {
        this.customers = [];
        this.filteredCustomers = [];
        this.currentEditId = null;
        this.apiBase = '../backend/api/customers.php';
        
        this.init();
    }

    async init() {
        try {
            // Validate session first
            await this.validateSession();
            
            // Load customers
            await this.loadCustomers();
            
            // Setup event listeners
            this.setupEventListeners();
            
            console.log('Customers Cashier module initialized successfully');
        } catch (error) {
            console.error('Failed to initialize customers module:', error);
            this.showNotification('Failed to initialize customer management', 'error');
        }
    }

    async validateSession() {
        try {
            const response = await fetch('../backend/api/dashboard.php');
            const data = await response.json();
            
            if (!data.success) {
                window.location.href = '../index.html';
                return;
            }
            
            // Update user info in header
            const userNameElement = document.getElementById('userName');
            const userRoleElement = document.getElementById('userRole');
            
            if (userNameElement && data.user) {
                userNameElement.textContent = data.user.username || 'User';
            }
            
            if (userRoleElement && data.user) {
                userRoleElement.textContent = this.capitalizeFirst(data.user.role || 'cashier');
            }
            
        } catch (error) {
            console.error('Session validation failed:', error);
            window.location.href = '../index.html';
        }
    }

    setupEventListeners() {
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => this.handleSearch(e.target.value));
        }

        // Form submission
        const customerForm = document.getElementById('customerForm');
        if (customerForm) {
            customerForm.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }

        // Modal close on background click
        const modal = document.getElementById('customerModal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.closeModal();
                }
            });
        }
    }

    async loadCustomers() {
        try {
            const response = await fetch(this.apiBase);
            const data = await response.json();
            
            if (data.success && data.customers) {
                this.customers = data.customers;
                this.filteredCustomers = [...this.customers];
                this.renderCustomers();
            } else {
                console.error('Failed to load customers:', data.message);
                this.renderEmptyState();
            }
        } catch (error) {
            console.error('Error loading customers:', error);
            this.renderError('Failed to load customers. Please try again.');
        }
    }

    renderCustomers() {
        const container = document.getElementById('customersContainer');
        if (!container) return;

        if (this.filteredCustomers.length === 0) {
            this.renderEmptyState();
            return;
        }

        const customersHTML = `
            <div class="customers-grid">
                ${this.filteredCustomers.map(customer => this.renderCustomerCard(customer)).join('')}
            </div>
        `;

        container.innerHTML = customersHTML;
    }

    renderCustomerCard(customer) {
        const totalPurchases = customer.total_purchases || 0;
        const lastVisit = customer.last_visit ? this.formatDate(customer.last_visit) : 'Never';
        const totalSpent = customer.total_spent || 0;

        return `
            <div class="customer-card" data-customer-id="${customer.id}">
                <div class="customer-header">
                    <div>
                        <div class="customer-name">${this.escapeHtml(customer.name)}</div>
                        <div class="customer-id">ID: ${customer.id}</div>
                    </div>
                    <div class="customer-actions">
                        <button class="action-btn select-btn" onclick="customersCashier.selectCustomer(${customer.id})" title="Select for Sale">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="action-btn edit-btn" onclick="customersCashier.editCustomer(${customer.id})" title="Edit Customer">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn delete-btn" onclick="customersCashier.deleteCustomer(${customer.id})" title="Delete Customer">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                
                <div class="customer-info">
                    ${customer.email ? `
                        <div class="info-row">
                            <i class="fas fa-envelope info-icon"></i>
                            <span>${this.escapeHtml(customer.email)}</span>
                        </div>
                    ` : ''}
                    
                    ${customer.phone ? `
                        <div class="info-row">
                            <i class="fas fa-phone info-icon"></i>
                            <span>${this.escapeHtml(customer.phone)}</span>
                        </div>
                    ` : ''}
                    
                    ${customer.address ? `
                        <div class="info-row">
                            <i class="fas fa-map-marker-alt info-icon"></i>
                            <span>${this.escapeHtml(customer.address)}</span>
                        </div>
                    ` : ''}
                </div>

                <div class="customer-stats">
                    <div class="stat-item">
                        <div class="stat-value">${totalPurchases}</div>
                        <div class="stat-label">Purchases</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">KSh ${totalSpent.toFixed(2)}</div>
                        <div class="stat-label">Total Spent</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${lastVisit}</div>
                        <div class="stat-label">Last Visit</div>
                    </div>
                </div>
            </div>
        `;
    }

    renderEmptyState() {
        const container = document.getElementById('customersContainer');
        if (!container) return;

        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <h3>No customers found</h3>
                <p>Add your first customer or adjust your search criteria.</p>
            </div>
        `;
    }

    renderError(message) {
        const container = document.getElementById('customersContainer');
        if (!container) return;

        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Error Loading Customers</h3>
                <p>${message}</p>
                <button class="btn btn-primary" onclick="customersCashier.loadCustomers()">Try Again</button>
            </div>
        `;
    }

    handleSearch(query) {
        if (!query.trim()) {
            this.filteredCustomers = [...this.customers];
        } else {
            const searchTerm = query.toLowerCase();
            this.filteredCustomers = this.customers.filter(customer => 
                customer.name.toLowerCase().includes(searchTerm) ||
                (customer.email && customer.email.toLowerCase().includes(searchTerm)) ||
                (customer.phone && customer.phone.includes(searchTerm))
            );
        }
        this.renderCustomers();
    }

    openAddModal() {
        this.currentEditId = null;
        document.getElementById('modalTitle').textContent = 'Add New Customer';
        this.clearForm();
        this.showModal();
    }

    editCustomer(customerId) {
        const customer = this.customers.find(c => c.id === customerId);
        if (!customer) return;

        this.currentEditId = customerId;
        document.getElementById('modalTitle').textContent = 'Edit Customer';
        
        // Populate form
        document.getElementById('customerId').value = customer.id;
        document.getElementById('customerName').value = customer.name || '';
        document.getElementById('customerEmail').value = customer.email || '';
        document.getElementById('customerPhone').value = customer.phone || '';
        document.getElementById('customerAddress').value = customer.address || '';
        
        this.showModal();
    }

    async deleteCustomer(customerId) {
        const customer = this.customers.find(c => c.id === customerId);
        if (!customer) return;

        if (!confirm(`Are you sure you want to delete customer "${customer.name}"?`)) {
            return;
        }

        try {
            const response = await fetch(`${this.apiBase}?id=${customerId}`, {
                method: 'DELETE'
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Customer deleted successfully', 'success');
                await this.loadCustomers();
            } else {
                this.showNotification(data.message || 'Failed to delete customer', 'error');
            }
        } catch (error) {
            console.error('Error deleting customer:', error);
            this.showNotification('Failed to delete customer', 'error');
        }
    }

    async handleFormSubmit(e) {
        e.preventDefault();
        
        const formData = {
            name: document.getElementById('customerName').value.trim(),
            email: document.getElementById('customerEmail').value.trim(),
            phone: document.getElementById('customerPhone').value.trim(),
            address: document.getElementById('customerAddress').value.trim()
        };

        // Basic validation
        if (!formData.name || !formData.phone) {
            this.showNotification('Name and phone are required', 'error');
            return;
        }

        try {
            let response;
            
            if (this.currentEditId) {
                // Update existing customer
                response = await fetch(`${this.apiBase}?id=${this.currentEditId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });
            } else {
                // Create new customer
                response = await fetch(this.apiBase, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });
            }
            
            const data = await response.json();
            
            if (data.success) {
                const action = this.currentEditId ? 'updated' : 'created';
                this.showNotification(`Customer ${action} successfully`, 'success');
                this.closeModal();
                await this.loadCustomers();
            } else {
                this.showNotification(data.message || `Failed to ${this.currentEditId ? 'update' : 'create'} customer`, 'error');
            }
        } catch (error) {
            console.error('Error saving customer:', error);
            this.showNotification('Failed to save customer', 'error');
        }
    }

    selectCustomer(customerId) {
        const customer = this.customers.find(c => c.id === customerId);
        if (!customer) return;

        // Store selected customer in sessionStorage for POS system
        sessionStorage.setItem('selectedCustomer', JSON.stringify(customer));
        
        this.showNotification(`Customer "${customer.name}" selected for sale`, 'success');
        
        // Redirect to POS after a short delay
        setTimeout(() => {
            window.location.href = 'sales_modern.html';
        }, 1500);
    }

    showModal() {
        const modal = document.getElementById('customerModal');
        if (modal) {
            modal.style.display = 'block';
            // Focus on first input
            const firstInput = modal.querySelector('.form-input');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            }
        }
    }

    closeModal() {
        const modal = document.getElementById('customerModal');
        if (modal) {
            modal.style.display = 'none';
        }
        this.clearForm();
    }

    clearForm() {
        const form = document.getElementById('customerForm');
        if (form) {
            form.reset();
        }
        document.getElementById('customerId').value = '';
        this.currentEditId = null;
    }

    showNotification(message, type = 'success') {
        // Remove existing notifications
        const existing = document.querySelector('.notification');
        if (existing) {
            existing.remove();
        }

        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            <span>${message}</span>
        `;

        document.body.appendChild(notification);

        // Show notification
        setTimeout(() => notification.classList.add('show'), 100);

        // Hide notification
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Utility functions
    formatDate(dateString) {
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString();
        } catch (error) {
            return 'Invalid date';
        }
    }

    capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
    }

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
}

// Global functions for inline onclick handlers
let customersCashier;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    customersCashier = new CustomersCashier();
});

// Global functions for onclick handlers
window.openAddModal = () => customersCashier.openAddModal();
window.closeModal = () => customersCashier.closeModal();
