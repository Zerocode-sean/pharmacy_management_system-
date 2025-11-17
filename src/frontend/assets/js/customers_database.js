/**
 * Customer Database Management JavaScript
 * For Admin Customer Database Page (customers_database.html)
 */

// Global variables
let currentPage = 1;
let customersPerPage = 10;
let totalCustomers = 0;
let allCustomers = [];
let filteredCustomers = [];

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    console.log('Customer Database - Initializing...');
    loadCustomers();
    setupEventListeners();
    updateStats();
});

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Search input
    const searchInput = document.getElementById('searchCustomers');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(filterCustomers, 300));
    }

    // Filter dropdowns
    const statusFilter = document.getElementById('statusFilter');
    const locationFilter = document.getElementById('locationFilter');
    const sortBy = document.getElementById('sortBy');

    if (statusFilter) statusFilter.addEventListener('change', filterCustomers);
    if (locationFilter) locationFilter.addEventListener('change', filterCustomers);
    if (sortBy) sortBy.addEventListener('change', filterCustomers);

    // Export button
    const exportBtn = document.getElementById('exportCustomers');
    if (exportBtn) {
        exportBtn.addEventListener('click', exportCustomers);
    }
}

/**
 * Load customers from API
 */
async function loadCustomers() {
    try {
        showLoading();
        
        // Mock API call - replace with actual API endpoint
        const response = await fetch('../backend/api/customers.php?action=getAll');
        
        if (response.ok) {
            const data = await response.json();
            if (data.success) {
                allCustomers = data.customers || generateMockCustomers();
            } else {
                throw new Error(data.message || 'Failed to load customers');
            }
        } else {
            // Fallback to mock data if API fails
            console.warn('API unavailable, using mock data');
            allCustomers = generateMockCustomers();
        }
        
        filteredCustomers = [...allCustomers];
        totalCustomers = allCustomers.length;
        
        renderCustomers();
        updateStats();
        hideLoading();
        
    } catch (error) {
        console.error('Error loading customers:', error);
        // Use mock data as fallback
        allCustomers = generateMockCustomers();
        filteredCustomers = [...allCustomers];
        totalCustomers = allCustomers.length;
        renderCustomers();
        updateStats();
        hideLoading();
    }
}

/**
 * Generate mock customer data for demonstration
 */
function generateMockCustomers() {
    const mockCustomers = [];
    const firstNames = ['John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 'Robert', 'Lisa', 'James', 'Mary', 'William', 'Patricia', 'Richard', 'Jennifer', 'Charles', 'Linda'];
    const lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas'];
    const cities = ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose'];
    const statuses = ['Active', 'Inactive', 'VIP', 'New'];

    for (let i = 1; i <= 50; i++) {
        const firstName = firstNames[Math.floor(Math.random() * firstNames.length)];
        const lastName = lastNames[Math.floor(Math.random() * lastNames.length)];
        const city = cities[Math.floor(Math.random() * cities.length)];
        const status = statuses[Math.floor(Math.random() * statuses.length)];
        
        mockCustomers.push({
            id: i,
            name: `${firstName} ${lastName}`,
            email: `${firstName.toLowerCase()}.${lastName.toLowerCase()}@email.com`,
            phone: `+1-${Math.floor(Math.random() * 900) + 100}-${Math.floor(Math.random() * 900) + 100}-${Math.floor(Math.random() * 9000) + 1000}`,
            address: `${Math.floor(Math.random() * 9999) + 1} ${lastName} St, ${city}`,
            registrationDate: new Date(2020 + Math.floor(Math.random() * 4), Math.floor(Math.random() * 12), Math.floor(Math.random() * 28) + 1).toISOString().split('T')[0],
            totalPurchases: Math.floor(Math.random() * 50) + 1,
            totalSpent: (Math.random() * 5000 + 100).toFixed(2),
            lastPurchase: new Date(2024, Math.floor(Math.random() * 12), Math.floor(Math.random() * 28) + 1).toISOString().split('T')[0],
            status: status,
            city: city
        });
    }

    return mockCustomers;
}

/**
 * Filter customers based on search and filter criteria
 */
function filterCustomers() {
    const searchTerm = (document.getElementById('searchCustomers')?.value || '').toLowerCase();
    const statusFilter = document.getElementById('statusFilter')?.value || 'all';
    const locationFilter = document.getElementById('locationFilter')?.value || 'all';
    const sortBy = document.getElementById('sortBy')?.value || 'name';

    // Filter customers
    filteredCustomers = allCustomers.filter(customer => {
        const matchesSearch = searchTerm === '' || 
            customer.name.toLowerCase().includes(searchTerm) ||
            customer.email.toLowerCase().includes(searchTerm) ||
            customer.phone.includes(searchTerm);
        
        const matchesStatus = statusFilter === 'all' || customer.status.toLowerCase() === statusFilter.toLowerCase();
        const matchesLocation = locationFilter === 'all' || customer.city === locationFilter;

        return matchesSearch && matchesStatus && matchesLocation;
    });

    // Sort customers
    filteredCustomers.sort((a, b) => {
        switch (sortBy) {
            case 'name':
                return a.name.localeCompare(b.name);
            case 'email':
                return a.email.localeCompare(b.email);
            case 'registration':
                return new Date(b.registrationDate) - new Date(a.registrationDate);
            case 'purchases':
                return b.totalPurchases - a.totalPurchases;
            case 'spent':
                return parseFloat(b.totalSpent) - parseFloat(a.totalSpent);
            default:
                return 0;
        }
    });

    currentPage = 1; // Reset to first page
    renderCustomers();
    updatePagination();
}

/**
 * Render customers in the table
 */
function renderCustomers() {
    const tbody = document.getElementById('customersTableBody');
    if (!tbody) return;

    // Calculate pagination
    const startIndex = (currentPage - 1) * customersPerPage;
    const endIndex = startIndex + customersPerPage;
    const customersToShow = filteredCustomers.slice(startIndex, endIndex);

    if (customersToShow.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="empty-state">
                    <i class="fas fa-users" style="font-size: 3rem; color: #ddd; margin-bottom: 10px;"></i>
                    <p>No customers found</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = customersToShow.map(customer => `
        <tr>
            <td>
                <div class="customer-info">
                    <div class="customer-avatar">
                        ${customer.name.split(' ').map(n => n[0]).join('').toUpperCase()}
                    </div>
                    <div>
                        <div class="customer-name">${customer.name}</div>
                        <div class="customer-email">${customer.email}</div>
                    </div>
                </div>
            </td>
            <td>
                <div class="contact-info">
                    <div>${customer.phone}</div>
                    <div class="customer-address">${customer.address}</div>
                </div>
            </td>
            <td>
                <div class="registration-info">
                    <div>${formatDate(customer.registrationDate)}</div>
                    <div class="days-ago">${getDaysAgo(customer.registrationDate)} days ago</div>
                </div>
            </td>
            <td>
                <div class="purchase-info">
                    <div class="purchase-count">${customer.totalPurchases} orders</div>
                    <div class="purchase-amount">KSh ${customer.totalSpent}</div>
                </div>
            </td>
            <td>
                <span class="status-badge status-${customer.status.toLowerCase()}">${customer.status}</span>
            </td>
            <td>
                <div class="action-buttons">
                    <button class="btn-action btn-view" onclick="viewCustomer(${customer.id})" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-action btn-edit" onclick="editCustomer(${customer.id})" title="Edit Customer">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-action btn-delete" onclick="deleteCustomer(${customer.id})" title="Delete Customer">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');

    updatePagination();
}

/**
 * Update statistics
 */
function updateStats() {
    // Update total customers
    const totalElement = document.getElementById('totalCustomers');
    if (totalElement) {
        totalElement.textContent = allCustomers.length;
    }

    // Update active customers
    const activeCustomers = allCustomers.filter(c => c.status === 'Active').length;
    const activeElement = document.getElementById('activeCustomers');
    if (activeElement) {
        activeElement.textContent = activeCustomers;
    }

    // Update VIP customers
    const vipCustomers = allCustomers.filter(c => c.status === 'VIP').length;
    const vipElement = document.getElementById('vipCustomers');
    if (vipElement) {
        vipElement.textContent = vipCustomers;
    }

    // Update new customers (registered in last 30 days)
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    const newCustomers = allCustomers.filter(c => new Date(c.registrationDate) > thirtyDaysAgo).length;
    const newElement = document.getElementById('newCustomers');
    if (newElement) {
        newElement.textContent = newCustomers;
    }
}

/**
 * Update pagination controls
 */
function updatePagination() {
    const totalPages = Math.ceil(filteredCustomers.length / customersPerPage);
    const pageInfo = document.getElementById('pageInfo');
    
    if (pageInfo) {
        pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
    }

    // Update pagination buttons
    const prevBtn = document.querySelector('.pagination-btn:first-child');
    const nextBtn = document.querySelector('.pagination-btn:last-child');

    if (prevBtn) {
        prevBtn.disabled = currentPage === 1;
    }

    if (nextBtn) {
        nextBtn.disabled = currentPage === totalPages;
    }
}

/**
 * Pagination functions
 */
function previousPage() {
    if (currentPage > 1) {
        currentPage--;
        renderCustomers();
    }
}

function nextPage() {
    const totalPages = Math.ceil(filteredCustomers.length / customersPerPage);
    if (currentPage < totalPages) {
        currentPage++;
        renderCustomers();
    }
}

/**
 * Customer action functions
 */
function viewCustomer(customerId) {
    const customer = allCustomers.find(c => c.id === customerId);
    if (!customer) return;

    // Create and show modal with customer details
    showCustomerModal(customer, 'view');
}

function editCustomer(customerId) {
    const customer = allCustomers.find(c => c.id === customerId);
    if (!customer) return;

    // Create and show modal with editable customer form
    showCustomerModal(customer, 'edit');
}

function deleteCustomer(customerId) {
    const customer = allCustomers.find(c => c.id === customerId);
    if (!customer) return;

    if (confirm(`Are you sure you want to delete ${customer.name}?`)) {
        // Mock delete - replace with actual API call
        allCustomers = allCustomers.filter(c => c.id !== customerId);
        filterCustomers();
        updateStats();
        showNotification('Customer deleted successfully', 'success');
    }
}

/**
 * Show customer modal
 */
function showCustomerModal(customer, mode) {
    const isEdit = mode === 'edit';
    
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-container">
            <div class="modal-header">
                <h3>${isEdit ? 'Edit Customer' : 'Customer Details'}</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="customerForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="name" value="${customer.name}" ${!isEdit ? 'readonly' : ''}>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="${customer.email}" ${!isEdit ? 'readonly' : ''}>
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="phone" value="${customer.phone}" ${!isEdit ? 'readonly' : ''}>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" ${!isEdit ? 'disabled' : ''}>
                                <option value="Active" ${customer.status === 'Active' ? 'selected' : ''}>Active</option>
                                <option value="Inactive" ${customer.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
                                <option value="VIP" ${customer.status === 'VIP' ? 'selected' : ''}>VIP</option>
                                <option value="New" ${customer.status === 'New' ? 'selected' : ''}>New</option>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label>Address</label>
                            <textarea name="address" ${!isEdit ? 'readonly' : ''}>${customer.address}</textarea>
                        </div>
                    </div>
                    
                    <div class="customer-stats">
                        <div class="stat-item">
                            <span class="stat-label">Registration Date:</span>
                            <span class="stat-value">${formatDate(customer.registrationDate)}</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Total Orders:</span>
                            <span class="stat-value">${customer.totalPurchases}</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Total Spent:</span>
                            <span class="stat-value">KSh ${customer.totalSpent}</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Last Purchase:</span>
                            <span class="stat-value">${formatDate(customer.lastPurchase)}</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                ${isEdit ? `
                    <button class="btn btn-primary" onclick="saveCustomer(${customer.id})">Save Changes</button>
                    <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                ` : `
                    <button class="btn btn-secondary" onclick="closeModal()">Close</button>
                `}
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    
    // Add modal styles
    addModalStyles();
}

/**
 * Add customer modal
 */
function addNewCustomer() {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-container">
            <div class="modal-header">
                <h3>Add New Customer</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="newCustomerForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Phone *</label>
                            <input type="text" name="phone" required>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="New">New</option>
                                <option value="Active">Active</option>
                                <option value="VIP">VIP</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label>Address</label>
                            <textarea name="address"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="saveNewCustomer()">Add Customer</button>
                <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    addModalStyles();
}

/**
 * Save customer changes
 */
function saveCustomer(customerId) {
    const form = document.getElementById('customerForm');
    const formData = new FormData(form);
    
    // Mock save - replace with actual API call
    const customer = allCustomers.find(c => c.id === customerId);
    if (customer) {
        customer.name = formData.get('name');
        customer.email = formData.get('email');
        customer.phone = formData.get('phone');
        customer.address = formData.get('address');
        customer.status = formData.get('status');
        
        renderCustomers();
        updateStats();
        closeModal();
        showNotification('Customer updated successfully', 'success');
    }
}

/**
 * Save new customer
 */
function saveNewCustomer() {
    const form = document.getElementById('newCustomerForm');
    const formData = new FormData(form);
    
    // Basic validation
    if (!formData.get('name') || !formData.get('email') || !formData.get('phone')) {
        showNotification('Please fill in all required fields', 'error');
        return;
    }
    
    // Mock save - replace with actual API call
    const newCustomer = {
        id: Math.max(...allCustomers.map(c => c.id)) + 1,
        name: formData.get('name'),
        email: formData.get('email'),
        phone: formData.get('phone'),
        address: formData.get('address') || '',
        registrationDate: new Date().toISOString().split('T')[0],
        totalPurchases: 0,
        totalSpent: '0.00',
        lastPurchase: null,
        status: formData.get('status') || 'New',
        city: 'New York' // Default city
    };
    
    allCustomers.push(newCustomer);
    filterCustomers();
    updateStats();
    closeModal();
    showNotification('Customer added successfully', 'success');
}

/**
 * Export customers
 */
function exportCustomers() {
    const csvContent = generateCSV(filteredCustomers);
    downloadCSV(csvContent, 'customers_export.csv');
    showNotification('Customer data exported successfully', 'success');
}

/**
 * Generate CSV content
 */
function generateCSV(customers) {
    const headers = ['ID', 'Name', 'Email', 'Phone', 'Address', 'Registration Date', 'Total Orders', 'Total Spent', 'Status'];
    const rows = customers.map(customer => [
        customer.id,
        customer.name,
        customer.email,
        customer.phone,
        customer.address.replace(/,/g, ';'), // Replace commas to avoid CSV issues
        customer.registrationDate,
        customer.totalPurchases,
        customer.totalSpent,
        customer.status
    ]);
    
    return [headers, ...rows].map(row => row.join(',')).join('\n');
}

/**
 * Download CSV file
 */
function downloadCSV(csvContent, filename) {
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

/**
 * Clear all filters and reset to default view
 */
function clearFilters() {
    const searchInput = document.getElementById('searchCustomers');
    const statusFilter = document.getElementById('statusFilter');
    const locationFilter = document.getElementById('locationFilter');
    const sortBy = document.getElementById('sortBy');

    if (searchInput) searchInput.value = '';
    if (statusFilter) statusFilter.value = 'all';
    if (locationFilter) locationFilter.value = 'all';
    if (sortBy) sortBy.value = 'name';

    // Reset to show all customers
    filteredCustomers = [...allCustomers];
    currentPage = 1;
    renderCustomers();
    showNotification('Filters cleared', 'info');
}

/**
 * Utility functions
 */
function closeModal() {
    const modal = document.querySelector('.modal-overlay');
    if (modal) {
        modal.remove();
    }
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function getDaysAgo(dateString) {
    if (!dateString) return 0;
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
}

function showLoading() {
    const tbody = document.getElementById('customersTableBody');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="empty-state">
                    <div class="loading-spinner"></div>
                    Loading customers...
                </td>
            </tr>
        `;
    }
}

function hideLoading() {
    // Loading is hidden when renderCustomers() is called
}

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

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 24px;
        border-radius: 6px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    
    switch (type) {
        case 'success':
            notification.style.backgroundColor = '#10b981';
            break;
        case 'error':
            notification.style.backgroundColor = '#ef4444';
            break;
        case 'warning':
            notification.style.backgroundColor = '#f59e0b';
            break;
        default:
            notification.style.backgroundColor = '#3b82f6';
    }
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

function addModalStyles() {
    if (document.getElementById('modalStyles')) return;
    
    const styles = document.createElement('style');
    styles.id = 'modalStyles';
    styles.textContent = `
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-container {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            padding: 20px 25px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            padding: 5px;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #f0f0f0;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            resize: vertical;
            height: 80px;
        }
        
        .customer-stats {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stat-label {
            font-weight: 500;
            color: #666;
        }
        
        .stat-value {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .modal-footer {
            padding: 20px 25px;
            border-top: 2px solid #f0f0f0;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .customer-stats {
                grid-template-columns: 1fr;
            }
        }
    `;
    
    document.head.appendChild(styles);
}

// Global functions for button clicks
window.addNewCustomer = addNewCustomer;
window.previousPage = previousPage;
window.nextPage = nextPage;
window.viewCustomer = viewCustomer;
window.editCustomer = editCustomer;
window.deleteCustomer = deleteCustomer;
window.closeModal = closeModal;
window.saveCustomer = saveCustomer;
window.saveNewCustomer = saveNewCustomer;
window.clearFilters = clearFilters;

console.log('Customer Database JavaScript loaded successfully');
