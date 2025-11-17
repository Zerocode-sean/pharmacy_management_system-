// Customer Management JavaScript

class CustomerManager {
    constructor() {
        this.customers = [];
        this.currentFilter = 'all';
        this.currentPage = 1;
        this.itemsPerPage = 20;
        this.totalCustomers = 0;
        this.currentCustomerId = null;
        
        this.init();
    }
    
    init() {
        this.loadCustomers();
        this.loadCustomerStats();
        this.setupEventListeners();
    }
    
    setupEventListeners() {
        // Form submission
        document.getElementById('addCustomerForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveCustomer();
        });
        
        // Search functionality
        document.getElementById('searchCustomer').addEventListener('input', 
            this.debounce(() => this.searchCustomers(), 300)
        );
        
        // Enter key for search
        document.getElementById('searchCustomer').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.searchCustomers();
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
    
    async loadCustomers() {
        try {
            const params = {
                limit: this.itemsPerPage,
                offset: (this.currentPage - 1) * this.itemsPerPage,
                filter: this.currentFilter
            };
            
            const response = await APIService.getCustomers(params);
            
            if (response.success) {
                this.customers = response.data;
                this.totalCustomers = response.total;
                this.displayCustomers();
                this.updatePagination();
            }
        } catch (error) {
            console.error('Failed to load customers:', error);
            Utils.showMessage('Failed to load customers', 'error');
        }
    }
    
    async loadCustomerStats() {
        try {
            const response = await APIService.getCustomerStats();
            
            if (response.success) {
                const stats = response.data;
                
                document.getElementById('totalCustomers').textContent = stats.total_customers || '0';
                document.getElementById('newCustomersMonth').textContent = stats.new_this_month || '0';
                document.getElementById('activeCustomers').textContent = stats.active_customers || '0';
                document.getElementById('topCustomer').textContent = stats.top_customer || 'None';
            }
        } catch (error) {
            console.error('Failed to load customer stats:', error);
        }
    }
    
    displayCustomers() {
        const tableBody = document.querySelector('#customersTable tbody');
        
        if (this.customers.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="7" class="text-center">No customers found</td></tr>';
            return;
        }
        
        tableBody.innerHTML = this.customers.map(customer => `
            <tr onclick="CustomerMgr.showCustomerDetails(${customer.id})" style="cursor: pointer;">
                <td>
                    <strong>${customer.name}</strong>
                    ${customer.date_of_birth ? `<br><small>DOB: ${Utils.formatDate(customer.date_of_birth)}</small>` : ''}
                </td>
                <td>${customer.phone || '-'}</td>
                <td>${customer.email || '-'}</td>
                <td>
                    <strong>${Utils.formatCurrency(customer.total_purchases || 0)}</strong>
                    <br><small>${customer.purchase_count || 0} purchases</small>
                </td>
                <td>${customer.last_purchase ? Utils.formatDate(customer.last_purchase) : 'Never'}</td>
                <td>${Utils.formatDate(customer.created_at)}</td>
                <td onclick="event.stopPropagation()">
                    <button class="btn btn-sm" onclick="CustomerMgr.editCustomer(${customer.id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="CustomerMgr.deleteCustomer(${customer.id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }
    
    updatePagination() {
        const totalPages = Math.ceil(this.totalCustomers / this.itemsPerPage);
        const startRecord = (this.currentPage - 1) * this.itemsPerPage + 1;
        const endRecord = Math.min(this.currentPage * this.itemsPerPage, this.totalCustomers);
        
        document.getElementById('showingFrom').textContent = startRecord;
        document.getElementById('showingTo').textContent = endRecord;
        document.getElementById('totalRecords').textContent = this.totalCustomers;
        document.getElementById('pageInfo').textContent = `Page ${this.currentPage} of ${totalPages}`;
        
        document.getElementById('prevPage').disabled = this.currentPage <= 1;
        document.getElementById('nextPage').disabled = this.currentPage >= totalPages;
        
        document.getElementById('paginationContainer').style.display = this.totalCustomers > 0 ? 'flex' : 'none';
    }
    
    changePage(direction) {
        const totalPages = Math.ceil(this.totalCustomers / this.itemsPerPage);
        const newPage = this.currentPage + direction;
        
        if (newPage >= 1 && newPage <= totalPages) {
            this.currentPage = newPage;
            this.loadCustomers();
        }
    }
    
    filterCustomers(filter) {
        // Update active filter button
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelector(`[data-filter="${filter}"]`).classList.add('active');
        
        this.currentFilter = filter;
        this.currentPage = 1;
        this.loadCustomers();
    }
    
    searchCustomers() {
        const searchTerm = document.getElementById('searchCustomer').value;
        this.currentPage = 1;
        
        // Add search parameter and reload
        const params = {
            limit: this.itemsPerPage,
            offset: 0,
            filter: this.currentFilter,
            search: searchTerm
        };
        
        this.loadCustomersWithParams(params);
    }
    
    async loadCustomersWithParams(params) {
        try {
            const response = await APIService.getCustomers(params);
            
            if (response.success) {
                this.customers = response.data;
                this.totalCustomers = response.total;
                this.displayCustomers();
                this.updatePagination();
            }
        } catch (error) {
            console.error('Failed to load customers:', error);
            Utils.showMessage('Failed to load customers', 'error');
        }
    }
    
    showAddCustomerForm() {
        document.getElementById('customerForm').style.display = 'block';
        document.getElementById('formTitle').textContent = 'Add New Customer';
        document.getElementById('addCustomerForm').reset();
        document.getElementById('customer_id').value = '';
        this.currentCustomerId = null;
    }
    
    hideCustomerForm() {
        document.getElementById('customerForm').style.display = 'none';
    }
    
    async editCustomer(id) {
        try {
            const response = await APIService.getCustomer(id);
            
            if (response.success) {
                const customer = response.data;
                
                document.getElementById('customerForm').style.display = 'block';
                document.getElementById('formTitle').textContent = 'Edit Customer';
                document.getElementById('customer_id').value = customer.id;
                document.getElementById('customer_name').value = customer.name;
                document.getElementById('customer_phone').value = customer.phone || '';
                document.getElementById('customer_email').value = customer.email || '';
                document.getElementById('date_of_birth').value = customer.date_of_birth || '';
                document.getElementById('customer_address').value = customer.address || '';
                
                this.currentCustomerId = id;
            }
        } catch (error) {
            console.error('Failed to load customer:', error);
            Utils.showMessage('Failed to load customer details', 'error');
        }
    }
    
    async saveCustomer() {
        const formData = new FormData(document.getElementById('addCustomerForm'));
        const customerData = Object.fromEntries(formData);
        
        // Validate required fields
        if (!customerData.name.trim()) {
            Utils.showMessage('Customer name is required', 'error');
            return;
        }
        
        // Validate email format if provided
        if (customerData.email && !Utils.validateEmail(customerData.email)) {
            Utils.showMessage('Please enter a valid email address', 'error');
            return;
        }
        
        // Validate phone format if provided
        if (customerData.phone && !Utils.validatePhone(customerData.phone)) {
            Utils.showMessage('Please enter a valid phone number', 'error');
            return;
        }
        
        try {
            let response;
            
            if (this.currentCustomerId) {
                // Update existing customer
                response = await APIService.updateCustomer(this.currentCustomerId, customerData);
            } else {
                // Create new customer
                response = await APIService.createCustomer(customerData);
            }
            
            if (response.success) {
                Utils.showMessage(
                    this.currentCustomerId ? 'Customer updated successfully' : 'Customer added successfully',
                    'success'
                );
                this.hideCustomerForm();
                this.loadCustomers();
                this.loadCustomerStats();
            } else {
                throw new Error(response.message);
            }
            
        } catch (error) {
            console.error('Failed to save customer:', error);
            Utils.showMessage('Failed to save customer: ' + error.message, 'error');
        }
    }
    
    async deleteCustomer(id) {
        const customer = this.customers.find(c => c.id === id);
        
        if (!customer) return;
        
        if (confirm(`Are you sure you want to delete customer "${customer.name}"?\n\nThis action cannot be undone.`)) {
            try {
                const response = await APIService.deleteCustomer(id);
                
                if (response.success) {
                    Utils.showMessage('Customer deleted successfully', 'success');
                    this.loadCustomers();
                    this.loadCustomerStats();
                } else {
                    throw new Error(response.message);
                }
                
            } catch (error) {
                console.error('Failed to delete customer:', error);
                Utils.showMessage('Failed to delete customer: ' + error.message, 'error');
            }
        }
    }
    
    async showCustomerDetails(id) {
        try {
            const response = await APIService.getCustomer(id);
            
            if (response.success) {
                const customer = response.data;
                this.displayCustomerDetails(customer);
                document.getElementById('customerDetailsModal').style.display = 'block';
                this.currentCustomerId = id;
            }
        } catch (error) {
            console.error('Failed to load customer details:', error);
            Utils.showMessage('Failed to load customer details', 'error');
        }
    }
    
    displayCustomerDetails(customer) {
        const content = document.getElementById('customerDetailsContent');
        
        content.innerHTML = `
            <div class="customer-details">
                <div class="customer-info-section">
                    <h3><i class="fas fa-user"></i> Personal Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Full Name:</label>
                            <span>${customer.name}</span>
                        </div>
                        <div class="info-item">
                            <label>Phone:</label>
                            <span>${customer.phone || 'Not provided'}</span>
                        </div>
                        <div class="info-item">
                            <label>Email:</label>
                            <span>${customer.email || 'Not provided'}</span>
                        </div>
                        <div class="info-item">
                            <label>Date of Birth:</label>
                            <span>${customer.date_of_birth ? Utils.formatDate(customer.date_of_birth) : 'Not provided'}</span>
                        </div>
                        <div class="info-item full-width">
                            <label>Address:</label>
                            <span>${customer.address || 'Not provided'}</span>
                        </div>
                        <div class="info-item">
                            <label>Customer Since:</label>
                            <span>${Utils.formatDate(customer.created_at)}</span>
                        </div>
                    </div>
                </div>
                
                <div class="customer-stats-section">
                    <h3><i class="fas fa-chart-bar"></i> Purchase History</h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-value">${customer.purchase_count || 0}</div>
                            <div class="stat-label">Total Purchases</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">${Utils.formatCurrency(customer.total_spent || 0)}</div>
                            <div class="stat-label">Total Spent</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">${Utils.formatCurrency(customer.average_purchase || 0)}</div>
                            <div class="stat-label">Average Purchase</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">${customer.last_purchase ? Utils.formatDate(customer.last_purchase) : 'Never'}</div>
                            <div class="stat-label">Last Purchase</div>
                        </div>
                    </div>
                </div>
                
                ${customer.recent_purchases && customer.recent_purchases.length > 0 ? `
                    <div class="recent-purchases-section">
                        <h3><i class="fas fa-history"></i> Recent Purchases</h3>
                        <div class="recent-purchases">
                            ${customer.recent_purchases.map(purchase => `
                                <div class="purchase-item">
                                    <div class="purchase-date">${Utils.formatDate(purchase.sale_date)}</div>
                                    <div class="purchase-amount">${Utils.formatCurrency(purchase.final_amount)}</div>
                                    <div class="purchase-items">${purchase.item_count} items</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
    }
    
    editCustomerFromModal() {
        this.closeCustomerDetailsModal();
        this.editCustomer(this.currentCustomerId);
    }
    
    closeCustomerDetailsModal() {
        document.getElementById('customerDetailsModal').style.display = 'none';
        this.currentCustomerId = null;
    }
}

// Global CustomerManager instance
let CustomerMgr;

// Initialize when page loads
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.pathname.includes('customers.html')) {
        CustomerMgr = new CustomerManager();
        updateUserWelcome(); // From main.js
    }
});

// Global functions for onclick handlers
function showAddCustomerForm() {
    if (CustomerMgr) CustomerMgr.showAddCustomerForm();
}

function hideCustomerForm() {
    if (CustomerMgr) CustomerMgr.hideCustomerForm();
}

function searchCustomers() {
    if (CustomerMgr) CustomerMgr.searchCustomers();
}

function filterCustomers(filter) {
    if (CustomerMgr) CustomerMgr.filterCustomers(filter);
}

function changePage(direction) {
    if (CustomerMgr) CustomerMgr.changePage(direction);
}

function closeCustomerDetailsModal() {
    if (CustomerMgr) CustomerMgr.closeCustomerDetailsModal();
}

function editCustomerFromModal() {
    if (CustomerMgr) CustomerMgr.editCustomerFromModal();
}
