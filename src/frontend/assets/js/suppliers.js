/**
 * Suppliers Management System
 * Handles supplier CRUD operations, search, filtering, and role-based permissions
 */

// Declare global variables for TypeScript compatibility
let suppliers = [];
let filteredSuppliers = [];
let editingSupplier = null;
let poItemCount = 0;

class SuppliersManager {
    constructor() {
        this.suppliers = [];
        this.filteredSuppliers = [];
        this.currentPage = 1;
        this.itemsPerPage = 10;
        this.currentView = 'grid';
        this.currentUser = null;
        
        this.init();
    }

    init() {
        this.loadUserData();
        this.setupEventListeners();
        this.loadSuppliers();
        this.applyRoleBasedAccess();
        this.updateUI();
    }

    loadUserData() {
        const userData = localStorage.getItem('user');
        if (userData) {
            this.currentUser = JSON.parse(userData);
            this.updateUserDisplay();
        }
    }

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
     * Apply role-based access control to UI elements
     */
    applyRoleBasedAccess() {
        if (!this.currentUser) return;
        
        const userRole = this.currentUser.role;
        
        // Define role permissions for suppliers module
        const permissions = {
            admin: {
                canView: true,
                canCreate: true,
                canEdit: true,
                canDelete: true,
                canExport: true
            },
            pharmacist: {
                canView: true,
                canCreate: false,
                canEdit: false,
                canDelete: false,
                canExport: true
            },
            cashier: {
                canView: true,
                canCreate: true,
                canEdit: true,
                canDelete: false,  // Cashiers can't delete suppliers for safety
                canExport: false
            }
        };
        
        const userPermissions = permissions[userRole] || permissions.cashier;
        
        // Hide/show buttons based on permissions
        const addBtn = document.getElementById('addSupplierBtn');
        const exportBtn = document.getElementById('exportBtn');
        
        if (addBtn) {
            addBtn.style.display = userPermissions.canCreate ? 'inline-flex' : 'none';
        }
        
        if (exportBtn) {
            exportBtn.style.display = userPermissions.canExport ? 'inline-flex' : 'none';
        }
        
        // Store permissions for later use
        this.userPermissions = userPermissions;
        
        console.log(`👤 Suppliers access for ${userRole}:`, userPermissions);
    }
    
    /**
     * Check if current user has specific permission
     */
    hasPermission(permission) {
        return this.userPermissions && this.userPermissions[permission] === true;
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing suppliers page...');
    loadSuppliers();
    loadSupplierStats();

    // Set today's date for purchase order
    document.getElementById('poDate').value = new Date().toISOString().split('T')[0];

    // Setup form submission
    document.getElementById('supplierForm').addEventListener('submit', handleSupplierSubmit);
    document.getElementById('purchaseOrderForm').addEventListener('submit', handlePurchaseOrderSubmit);
    console.log('Suppliers page initialization complete.');
});

// Load all suppliers
async function loadSuppliers() {
    try {
        console.log('Loading suppliers...');
        // showLoading('Loading suppliers...');
        const response = await window.PharmacyApp.APIService.request('suppliers.php');
        console.log('Suppliers API response:', response);

        if (response.success) {
            suppliers = response.data;
            filteredSuppliers = [...suppliers];
            console.log('Loaded suppliers:', suppliers.length);
            displaySuppliers();
        } else {
            console.error('Failed to load suppliers:', response.message);
            window.PharmacyApp.Utils.showMessage('Failed to load suppliers: ' + response.message, 'error');
        }
    } catch (error) {
        console.error('Error loading suppliers:', error);
        window.PharmacyApp.Utils.showMessage('Error loading suppliers: ' + error.message, 'error');
    } finally {
        // hideLoading();
    }
}

// Load supplier statistics
async function loadSupplierStats() {
    try {
        const response = await window.PharmacyApp.APIService.request('suppliers.php?action=stats');
        
        if (response.success) {
            const stats = response.data;
            document.getElementById('totalSuppliers').textContent = stats.total || 0;
            document.getElementById('activeSuppliers').textContent = stats.active || 0;
            document.getElementById('pendingOrders').textContent = stats.pending_orders || 0;
            document.getElementById('monthlyOrders').textContent = stats.monthly_orders || 0;
        }
    } catch (error) {
        console.error('Error loading supplier stats:', error);
    }
}

// Display suppliers in table
function displaySuppliers() {
    const tbody = document.getElementById('suppliersTableBody');
    
    if (filteredSuppliers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">No suppliers found</td></tr>';
        return;
    }
    
    tbody.innerHTML = filteredSuppliers.map(supplier => `
        <tr>
            <td>${supplier.id}</td>
            <td>
                <div class="supplier-info">
                    <strong>${escapeHtml(supplier.company_name)}</strong>
                    ${supplier.tax_id ? `<small>Tax ID: ${escapeHtml(supplier.tax_id)}</small>` : ''}
                </div>
            </td>
            <td>${escapeHtml(supplier.contact_person)}</td>
            <td>${escapeHtml(supplier.phone)}</td>
            <td>${escapeHtml(supplier.email)}</td>
            <td>
                <span class="badge badge-category">${escapeHtml(supplier.category)}</span>
            </td>
            <td>
                <span class="badge ${supplier.status === 'active' ? 'badge-success' : 'badge-danger'}">
                    ${supplier.status}
                </span>
            </td>
            <td>${supplier.last_order_date ? formatDate(supplier.last_order_date) : 'Never'}</td>
            <td class="actions">
                <button class="btn btn-sm btn-outline" onclick="viewSupplier(${supplier.id})" title="View Details">
                    <i class="icon-eye"></i>
                </button>
                <button class="btn btn-sm btn-outline" onclick="editSupplier(${supplier.id})" title="Edit">
                    <i class="icon-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline" onclick="createPurchaseOrder(${supplier.id})" title="Create Order">
                    <i class="icon-shopping-cart"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteSupplier(${supplier.id})" title="Delete">
                    <i class="icon-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// Search suppliers
function searchSuppliers() {
    const searchTerm = document.getElementById('searchSupplier').value.toLowerCase();
    
    filteredSuppliers = suppliers.filter(supplier => 
        supplier.company_name.toLowerCase().includes(searchTerm) ||
        supplier.contact_person.toLowerCase().includes(searchTerm) ||
        supplier.email.toLowerCase().includes(searchTerm) ||
        supplier.phone.includes(searchTerm)
    );
    
    filterSuppliers();
}

// Filter suppliers by status and category
function filterSuppliers() {
    const statusFilter = document.getElementById('statusFilter').value;
    const categoryFilter = document.getElementById('categoryFilter').value;
    let filtered = [...filteredSuppliers];
    
    // Apply search filter first
    const searchTerm = document.getElementById('searchSupplier').value.toLowerCase();
    if (searchTerm) {
        filtered = suppliers.filter(supplier => 
            supplier.company_name.toLowerCase().includes(searchTerm) ||
            supplier.contact_person.toLowerCase().includes(searchTerm) ||
            supplier.email.toLowerCase().includes(searchTerm) ||
            supplier.phone.includes(searchTerm)
        );
    } else {
        filtered = [...suppliers];
    }
    
    // Apply status filter
    if (statusFilter !== 'all') {
        filtered = filtered.filter(supplier => supplier.status === statusFilter);
    }
    
    // Apply category filter
    if (categoryFilter !== 'all') {
        filtered = filtered.filter(supplier => supplier.category === categoryFilter);
    }
    
    filteredSuppliers = filtered;
    displaySuppliers();
}

// Open add supplier modal
function openAddSupplierModal() {
    editingSupplier = null;
    document.getElementById('modalTitle').textContent = 'Add New Supplier';
    document.getElementById('supplierForm').reset();
    document.getElementById('supplierId').value = '';
    document.getElementById('status').value = 'active';
    document.getElementById('supplierModal').style.display = 'block';
}

// Edit supplier
function editSupplier(supplierId) {
    const supplier = suppliers.find(s => s.id === supplierId);
    if (!supplier) return;
    
    editingSupplier = supplier;
    document.getElementById('modalTitle').textContent = 'Edit Supplier';
    
    // Populate form fields
    document.getElementById('supplierId').value = supplier.id;
    document.getElementById('companyName').value = supplier.company_name;
    document.getElementById('contactPerson').value = supplier.contact_person;
    document.getElementById('phone').value = supplier.phone;
    document.getElementById('email').value = supplier.email;
    document.getElementById('category').value = supplier.category;
    document.getElementById('status').value = supplier.status;
    document.getElementById('address').value = supplier.address || '';
    document.getElementById('creditTerms').value = supplier.credit_terms || '';
    document.getElementById('taxId').value = supplier.tax_id || '';
    document.getElementById('notes').value = supplier.notes || '';
    
    document.getElementById('supplierModal').style.display = 'block';
}

// View supplier details (future enhancement)
function viewSupplier(supplierId) {
    const supplier = suppliers.find(s => s.id === supplierId);
    if (!supplier) return;
    
    showInfo(`
        <strong>${supplier.company_name}</strong><br>
        Contact: ${supplier.contact_person}<br>
        Phone: ${supplier.phone}<br>
        Email: ${supplier.email}<br>
        Category: ${supplier.category}<br>
        Status: ${supplier.status}<br>
        ${supplier.address ? `Address: ${supplier.address}<br>` : ''}
        ${supplier.credit_terms ? `Credit Terms: ${supplier.credit_terms} days<br>` : ''}
        ${supplier.tax_id ? `Tax ID: ${supplier.tax_id}<br>` : ''}
        ${supplier.notes ? `Notes: ${supplier.notes}` : ''}
    `);
}

// Close supplier modal
function closeSupplierModal() {
    document.getElementById('supplierModal').style.display = 'none';
    editingSupplier = null;
}

// Handle supplier form submission
async function handleSupplierSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const supplierData = {};
    
    for (let [key, value] of formData.entries()) {
        supplierData[key] = value;
    }
    
    try {
        // showLoading('Saving supplier...');
        
        const url = editingSupplier ? 
            `/api/suppliers.php?id=${editingSupplier.id}` : 
            '/api/suppliers.php';
        const method = editingSupplier ? 'PUT' : 'POST';
        
        const response = await window.PharmacyApp.APIService.request('suppliers.php', {
            method: method,
            body: JSON.stringify(supplierData)
        });
        
        if (response.success) {
            window.PharmacyApp.Utils.showMessage(editingSupplier ? 'Supplier updated successfully!' : 'Supplier added successfully!', 'success');
            closeSupplierModal();
            loadSuppliers();
            loadSupplierStats();
        } else {
            window.PharmacyApp.Utils.showMessage('Failed to save supplier: ' + response.message, 'error');
        }
    } catch (error) {
        window.PharmacyApp.Utils.showMessage('Error saving supplier: ' + error.message, 'error');
    } finally {
        // hideLoading();
    }
}

// Delete supplier
async function deleteSupplier(supplierId) {
    const supplier = suppliers.find(s => s.id === supplierId);
    if (!supplier) return;
    
    if (!confirm(`Are you sure you want to delete supplier "${supplier.company_name}"?`)) {
        return;
    }
    
    try {
        // showLoading('Deleting supplier...');
        
        const response = await window.PharmacyApp.APIService.request(`suppliers.php?id=${supplierId}`, {
            method: 'DELETE'
        });
        
        if (response.success) {
            window.PharmacyApp.Utils.showMessage('Supplier deleted successfully!', 'success');
            loadSuppliers();
            loadSupplierStats();
        } else {
            window.PharmacyApp.Utils.showMessage('Failed to delete supplier: ' + response.message, 'error');
        }
    } catch (error) {
        window.PharmacyApp.Utils.showMessage('Error deleting supplier: ' + error.message, 'error');
    } finally {
        // hideLoading();
    }
}

// Create purchase order
function createPurchaseOrder(supplierId) {
    const supplier = suppliers.find(s => s.id === supplierId);
    if (!supplier) return;
    
    document.getElementById('poSupplierId').value = supplierId;
    
    // Set expected date to 7 days from now
    const expectedDate = new Date();
    expectedDate.setDate(expectedDate.getDate() + 7);
    document.getElementById('expectedDate').value = expectedDate.toISOString().split('T')[0];
    
    // Clear previous items
    document.getElementById('poItemsContainer').innerHTML = '';
    poItemCount = 0;
    
    // Add first item row
    addPOItem();
    
    document.getElementById('purchaseOrderModal').style.display = 'block';
}

// Close purchase order modal
function closePurchaseOrderModal() {
    document.getElementById('purchaseOrderModal').style.display = 'none';
    document.getElementById('purchaseOrderForm').reset();
}

// Add purchase order item row
function addPOItem() {
    poItemCount++;
    const container = document.getElementById('poItemsContainer');
    
    const itemRow = document.createElement('div');
    itemRow.className = 'po-item-row';
    itemRow.innerHTML = `
        <div class="form-row">
            <div class="form-group">
                <input type="text" name="items[${poItemCount}][description]" placeholder="Item description" required>
            </div>
            <div class="form-group">
                <input type="number" name="items[${poItemCount}][quantity]" placeholder="Qty" min="1" required onchange="calculatePOTotal()">
            </div>
            <div class="form-group">
                <input type="number" name="items[${poItemCount}][unit_price]" placeholder="Unit Price" step="0.01" min="0" required onchange="calculatePOTotal()">
            </div>
            <div class="form-group">
                <button type="button" class="btn btn-danger btn-sm" onclick="removePOItem(this)">Remove</button>
            </div>
        </div>
    `;
    
    container.appendChild(itemRow);
}

// Remove purchase order item row
function removePOItem(button) {
    button.closest('.po-item-row').remove();
    calculatePOTotal();
}

// Calculate purchase order total
function calculatePOTotal() {
    const quantities = document.querySelectorAll('input[name*="[quantity]"]');
    const unitPrices = document.querySelectorAll('input[name*="[unit_price]"]');
    
    let subtotal = 0;
    
    for (let i = 0; i < quantities.length; i++) {
        const qty = parseFloat(quantities[i].value) || 0;
        const price = parseFloat(unitPrices[i].value) || 0;
        subtotal += qty * price;
    }
    
    const tax = subtotal * 0.1; // 10% tax
    const total = subtotal + tax;
    
    document.getElementById('poSubtotal').textContent = 'KSh ' + subtotal.toFixed(2);
    document.getElementById('poTax').textContent = 'KSh ' + tax.toFixed(2);
    document.getElementById('poTotal').textContent = 'KSh ' + total.toFixed(2);
}

// Handle purchase order submission
async function handlePurchaseOrderSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const orderData = {
        supplier_id: formData.get('supplier_id'),
        order_date: formData.get('order_date'),
        expected_date: formData.get('expected_date'),
        items: []
    };
    
    // Collect items
    const quantities = document.querySelectorAll('input[name*="[quantity]"]');
    const unitPrices = document.querySelectorAll('input[name*="[unit_price]"]');
    const descriptions = document.querySelectorAll('input[name*="[description]"]');
    
    for (let i = 0; i < quantities.length; i++) {
        if (descriptions[i].value && quantities[i].value && unitPrices[i].value) {
            orderData.items.push({
                description: descriptions[i].value,
                quantity: parseInt(quantities[i].value),
                unit_price: parseFloat(unitPrices[i].value)
            });
        }
    }
    
    if (orderData.items.length === 0) {
        window.PharmacyApp.Utils.showMessage('Please add at least one item to the order', 'error');
        return;
    }
    
    try {
        // showLoading('Creating purchase order...');
        
        const response = await window.PharmacyApp.APIService.request('purchase_orders.php', {
            method: 'POST',
            body: JSON.stringify(orderData)
        });
        
        if (response.success) {
            window.PharmacyApp.Utils.showMessage('Purchase order created successfully!', 'success');
            closePurchaseOrderModal();
            loadSuppliers(); // Refresh to update last order dates
            loadSupplierStats();
        } else {
            window.PharmacyApp.Utils.showMessage('Failed to create purchase order: ' + response.message, 'error');
        }
    } catch (error) {
        window.PharmacyApp.Utils.showMessage('Error creating purchase order: ' + error.message, 'error');
    } finally {
        // hideLoading();
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    const supplierModal = document.getElementById('supplierModal');
    const poModal = document.getElementById('purchaseOrderModal');
    
    if (event.target === supplierModal) {
        closeSupplierModal();
    }
    if (event.target === poModal) {
        closePurchaseOrderModal();
    }
};
