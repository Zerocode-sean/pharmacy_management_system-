/**
 * Suppliers Management System - Complete Modern Implementation
 * Handles all supplier CRUD operations, search, filtering, pagination, and role-based permissions
 * 
 * Features:
 * - Real-time search and filtering
 * - Pagination with customizable page sizes
 * - Role-based access control (RBAC)
 * - Modal forms for add/edit operations
 * - Statistics dashboard
 * - Export functionality
 * - Responsive design
 */

class SuppliersManager {
    constructor() {
        this.suppliers = [];
        this.filteredSuppliers = [];
        this.currentPage = 1;
        this.itemsPerPage = 10;
        this.totalPages = 0;
        this.currentUser = null;
        this.currentEditId = null;
        this.currentDeleteId = null;
        
        // Search and filter state
        this.searchTerm = '';
        this.statusFilter = '';
        this.sortBy = 'name';
        this.sortOrder = 'asc';
        
        this.init();
    }

    /**
     * Initialize the suppliers manager
     */
    async init() {
        try {
            this.loadUserData(); // No await needed - synchronous now
            this.setupEventListeners();
            await this.loadSuppliers();
            await this.loadStats();
            this.applyRoleBasedAccess();
            this.updateUI();
        } catch (error) {
            console.error('Error initializing suppliers manager:', error);
            this.showNotification('Error initializing page', 'error');
        }
    }

    /**
     * Load user data from localStorage (managed by main_session_fixed.js)
     */
    loadUserData() {
        try {
            // Get user data from localStorage (already validated by main_session_fixed.js)
            const userData = localStorage.getItem('user');
            if (userData) {
                this.currentUser = JSON.parse(userData);
                this.updateUserDisplay();
                console.log('User data loaded:', this.currentUser.username);
            } else {
                console.warn('No user data found in localStorage');
                // Don't redirect here - let main_session_fixed.js handle it
            }
        } catch (error) {
            console.error('Error loading user data:', error);
            // Don't redirect here - let main_session_fixed.js handle authentication
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
        if (headerUserAvatar) {
            const name = this.currentUser.full_name || this.currentUser.username;
            headerUserAvatar.textContent = name.charAt(0).toUpperCase();
        }
    }

    /**
     * Setup all event listeners
     */
    setupEventListeners() {
        // Search input
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', debounce((e) => {
                this.searchTerm = e.target.value.toLowerCase();
                this.currentPage = 1;
                this.applyFiltersAndSearch();
            }, 300));
        }

        // Filter selects
        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', (e) => {
                this.statusFilter = e.target.value;
                this.currentPage = 1;
                this.applyFiltersAndSearch();
            });
        }

        const sortFilter = document.getElementById('sortFilter');
        if (sortFilter) {
            sortFilter.addEventListener('change', (e) => {
                this.sortBy = e.target.value;
                this.applyFiltersAndSearch();
            });
        }

        // Form submission
        const supplierForm = document.getElementById('supplierForm');
        if (supplierForm) {
            supplierForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleFormSubmit();
            });
        }

        // Modal close events
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                this.closeAllModals();
            }
        });

        // Keyboard events
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });

        // Logout button
        const logoutBtns = document.querySelectorAll('.logout-btn');
        logoutBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleLogout();
            });
        });
    }

    /**
     * Load suppliers from API
     */
    async loadSuppliers() {
        try {
            this.showLoading(true);
            
            const response = await fetch('http://localhost/Phamarcy/src/backend/api/suppliers.php', {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            
            if (result.success) {
                this.suppliers = result.data || [];
                this.applyFiltersAndSearch();
            } else {
                throw new Error(result.message || 'Failed to load suppliers');
            }
        } catch (error) {
            console.error('Error loading suppliers:', error);
            this.showNotification('Failed to load suppliers: ' + error.message, 'error');
            this.suppliers = [];
            this.updateUI();
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Load statistics from API
     */
    async loadStats() {
        try {
            const response = await fetch('http://localhost/Phamarcy/src/backend/api/suppliers.php?action=stats', {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    this.updateStatsDisplay(result.data);
                }
            }
        } catch (error) {
            console.error('Error loading stats:', error);
            // Don't show error notification for stats, just use fallback values
        }
    }

    /**
     * Update statistics display
     */
    updateStatsDisplay(stats) {
        const elements = {
            totalSuppliers: document.getElementById('totalSuppliers'),
            activeSuppliers: document.getElementById('activeSuppliers'),
            pendingOrders: document.getElementById('pendingOrders'),
            monthlySuppliers: document.getElementById('monthlySuppliers')
        };

        if (elements.totalSuppliers) elements.totalSuppliers.textContent = stats.total || 0;
        if (elements.activeSuppliers) elements.activeSuppliers.textContent = stats.active || 0;
        if (elements.pendingOrders) elements.pendingOrders.textContent = stats.pending_orders || 0;
        if (elements.monthlySuppliers) elements.monthlySuppliers.textContent = stats.monthly_orders || 0;
    }

    /**
     * Apply role-based access control
     */
    applyRoleBasedAccess() {
        if (!this.currentUser) return;

        const userRole = this.currentUser.role;
        
        // Elements that require admin access
        const adminOnlyElements = [
            document.getElementById('addSupplierBtn'),
            ...document.querySelectorAll('.edit-btn'),
            ...document.querySelectorAll('.delete-btn')
        ];

        // Hide admin-only elements for non-admin users
        if (userRole !== 'admin') {
            adminOnlyElements.forEach(element => {
                if (element) {
                    element.style.display = 'none';
                }
            });
        }

        // Pharmacists can view but not modify
        if (userRole === 'pharmacist') {
            // Show view-only access
        }

        // Cashiers have limited access
        if (userRole === 'cashier') {
            // Very limited access, mostly read-only
        }
    }

    /**
     * Apply filters and search
     */
    applyFiltersAndSearch() {
        let filtered = [...this.suppliers];

        // Apply search filter
        if (this.searchTerm) {
            filtered = filtered.filter(supplier => 
                supplier.company_name.toLowerCase().includes(this.searchTerm) ||
                supplier.contact_person.toLowerCase().includes(this.searchTerm) ||
                supplier.email.toLowerCase().includes(this.searchTerm) ||
                supplier.phone.includes(this.searchTerm)
            );
        }

        // Apply status filter
        if (this.statusFilter) {
            filtered = filtered.filter(supplier => supplier.status === this.statusFilter);
        }

        // Apply sorting
        filtered.sort((a, b) => {
            let aValue = a[this.sortBy];
            let bValue = b[this.sortBy];

            if (this.sortBy === 'name') {
                aValue = a.company_name;
                bValue = b.company_name;
            } else if (this.sortBy === 'date') {
                aValue = a.created_at;
                bValue = b.created_at;
            }

            if (typeof aValue === 'string') {
                aValue = aValue.toLowerCase();
                bValue = bValue.toLowerCase();
            }

            if (this.sortOrder === 'desc') {
                return bValue > aValue ? 1 : -1;
            } else {
                return aValue > bValue ? 1 : -1;
            }
        });

        this.filteredSuppliers = filtered;
        this.calculatePagination();
        this.updateUI();
    }

    /**
     * Calculate pagination
     */
    calculatePagination() {
        this.totalPages = Math.ceil(this.filteredSuppliers.length / this.itemsPerPage);
        if (this.currentPage > this.totalPages) {
            this.currentPage = 1;
        }
    }

    /**
     * Get current page suppliers
     */
    getCurrentPageSuppliers() {
        const startIndex = (this.currentPage - 1) * this.itemsPerPage;
        const endIndex = startIndex + this.itemsPerPage;
        return this.filteredSuppliers.slice(startIndex, endIndex);
    }

    /**
     * Update the UI
     */
    updateUI() {
        this.renderSuppliersTable();
        this.renderPagination();
        this.updateEmptyState();
    }

    /**
     * Render suppliers table
     */
    renderSuppliersTable() {
        const tableBody = document.getElementById('suppliersTableBody');
        const table = document.getElementById('suppliersTable');
        
        if (!tableBody || !table) return;

        const currentSuppliers = this.getCurrentPageSuppliers();
        
        if (currentSuppliers.length === 0) {
            table.style.display = 'none';
            return;
        }

        table.style.display = 'table';
        
        tableBody.innerHTML = currentSuppliers.map(supplier => {
            const avatar = supplier.company_name.charAt(0).toUpperCase();
            const lastOrder = supplier.last_order_date ? 
                new Date(supplier.last_order_date).toLocaleDateString() : 'Never';
            const pendingOrders = supplier.pending_orders_count || 0;
            
            return `
                <tr>
                    <td>
                        <div class="supplier-info">
                            <div class="supplier-avatar">${avatar}</div>
                            <div>
                                <p class="supplier-name">${this.escapeHtml(supplier.company_name)}</p>
                                <p class="supplier-code">Contact: ${this.escapeHtml(supplier.contact_person)}</p>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div>
                            <div style="margin-bottom: 4px;">
                                <i class="fas fa-phone" style="color: #64748b; margin-right: 6px;"></i>
                                ${this.escapeHtml(supplier.phone)}
                            </div>
                            <div>
                                <i class="fas fa-envelope" style="color: #64748b; margin-right: 6px;"></i>
                                ${this.escapeHtml(supplier.email)}
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="status-badge status-${supplier.status}">
                            ${supplier.status}
                        </span>
                    </td>
                    <td>${lastOrder}</td>
                    <td>${pendingOrders}</td>
                    <td>
                        <div class="actions-dropdown">
                            <button class="btn btn-secondary btn-sm" onclick="toggleActionsDropdown(${supplier.id})">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu" id="actionsDropdown${supplier.id}">
                                <a href="#" class="dropdown-item" onclick="suppliersManager.viewSupplier(${supplier.id})">
                                    <i class="fas fa-eye"></i>
                                    View Details
                                </a>
                                ${this.currentUser?.role === 'admin' ? `
                                    <a href="#" class="dropdown-item edit-btn" onclick="suppliersManager.editSupplier(${supplier.id})">
                                        <i class="fas fa-edit"></i>
                                        Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a href="#" class="dropdown-item text-danger delete-btn" onclick="suppliersManager.deleteSupplier(${supplier.id})">
                                        <i class="fas fa-trash"></i>
                                        Delete
                                    </a>
                                ` : ''}
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    /**
     * Render pagination controls
     */
    renderPagination() {
        const paginationContainer = document.getElementById('paginationContainer');
        const paginationControls = document.getElementById('paginationControls');
        const paginationInfo = document.getElementById('paginationInfo');
        
        if (!paginationContainer || !paginationControls || !paginationInfo) return;

        if (this.filteredSuppliers.length === 0) {
            paginationContainer.style.display = 'none';
            return;
        }

        paginationContainer.style.display = 'flex';
        
        // Update pagination info
        const startItem = (this.currentPage - 1) * this.itemsPerPage + 1;
        const endItem = Math.min(this.currentPage * this.itemsPerPage, this.filteredSuppliers.length);
        paginationInfo.textContent = `Showing ${startItem}-${endItem} of ${this.filteredSuppliers.length} suppliers`;
        
        // Generate pagination buttons
        let paginationHTML = '';
        
        // Previous button
        if (this.currentPage > 1) {
            paginationHTML += `
                <button class="pagination-btn" onclick="suppliersManager.goToPage(${this.currentPage - 1})">
                    <i class="fas fa-chevron-left"></i>
                </button>
            `;
        }
        
        // Page numbers
        const maxVisiblePages = 5;
        let startPage = Math.max(1, this.currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(this.totalPages, startPage + maxVisiblePages - 1);
        
        if (endPage - startPage + 1 < maxVisiblePages) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        for (let i = startPage; i <= endPage; i++) {
            paginationHTML += `
                <button class="pagination-btn ${i === this.currentPage ? 'active' : ''}" 
                        onclick="suppliersManager.goToPage(${i})">
                    ${i}
                </button>
            `;
        }
        
        // Next button
        if (this.currentPage < this.totalPages) {
            paginationHTML += `
                <button class="pagination-btn" onclick="suppliersManager.goToPage(${this.currentPage + 1})">
                    <i class="fas fa-chevron-right"></i>
                </button>
            `;
        }
        
        paginationControls.innerHTML = paginationHTML;
    }

    /**
     * Update empty state
     */
    updateEmptyState() {
        const emptyState = document.getElementById('emptyState');
        const table = document.getElementById('suppliersTable');
        
        if (!emptyState || !table) return;

        if (this.filteredSuppliers.length === 0) {
            emptyState.style.display = 'block';
            table.style.display = 'none';
        } else {
            emptyState.style.display = 'none';
        }
    }

    /**
     * Go to specific page
     */
    goToPage(page) {
        if (page >= 1 && page <= this.totalPages) {
            this.currentPage = page;
            this.updateUI();
        }
    }

    /**
     * Open add supplier modal
     */
    openAddSupplierModal() {
        if (this.currentUser?.role !== 'admin') {
            this.showNotification('Access denied: Admin privileges required', 'error');
            return;
        }

        this.currentEditId = null;
        const modal = document.getElementById('supplierModal');
        const modalTitle = document.getElementById('modalTitle');
        const submitBtn = document.getElementById('submitBtn');
        
        if (modalTitle) modalTitle.textContent = 'Add New Supplier';
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Add Supplier';
        }
        
        this.resetForm();
        if (modal) {
            modal.style.display = 'flex';
            // Focus first input
            setTimeout(() => {
                const firstInput = modal.querySelector('input');
                if (firstInput) firstInput.focus();
            }, 100);
        }
    }

    /**
     * Edit supplier
     */
    async editSupplier(supplierId) {
        if (this.currentUser?.role !== 'admin') {
            this.showNotification('Access denied: Admin privileges required', 'error');
            return;
        }

        try {
            const supplier = this.suppliers.find(s => s.id == supplierId);
            if (!supplier) {
                throw new Error('Supplier not found');
            }

            this.currentEditId = supplierId;
            
            const modal = document.getElementById('supplierModal');
            const modalTitle = document.getElementById('modalTitle');
            const submitBtn = document.getElementById('submitBtn');
            
            if (modalTitle) modalTitle.textContent = 'Edit Supplier';
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Supplier';
            }
            
            // Populate form
            this.populateForm(supplier);
            
            if (modal) {
                modal.style.display = 'flex';
            }
        } catch (error) {
            console.error('Error editing supplier:', error);
            this.showNotification('Error loading supplier details', 'error');
        }
    }

    /**
     * View supplier details
     */
    viewSupplier(supplierId) {
        const supplier = this.suppliers.find(s => s.id == supplierId);
        if (!supplier) {
            this.showNotification('Supplier not found', 'error');
            return;
        }

        // For now, open in edit mode but make it read-only for non-admins
        if (this.currentUser?.role === 'admin') {
            this.editSupplier(supplierId);
        } else {
            // Show a view-only modal or notification
            this.showSupplierDetails(supplier);
        }
    }

    /**
     * Show supplier details in a notification or modal
     */
    showSupplierDetails(supplier) {
        const details = `
            Company: ${supplier.company_name}
            Contact: ${supplier.contact_person}
            Phone: ${supplier.phone}
            Email: ${supplier.email}
            Status: ${supplier.status}
            Address: ${supplier.address || 'Not provided'}
        `;
        this.showNotification(details, 'info', 5000);
    }

    /**
     * Delete supplier
     */
    deleteSupplier(supplierId) {
        if (this.currentUser?.role !== 'admin') {
            this.showNotification('Access denied: Admin privileges required', 'error');
            return;
        }

        const supplier = this.suppliers.find(s => s.id == supplierId);
        if (!supplier) {
            this.showNotification('Supplier not found', 'error');
            return;
        }

        this.currentDeleteId = supplierId;
        
        const modal = document.getElementById('deleteModal');
        const deleteSupplierName = document.getElementById('deleteSupplierName');
        
        if (deleteSupplierName) {
            deleteSupplierName.textContent = supplier.company_name;
        }
        
        if (modal) {
            modal.style.display = 'flex';
        }
    }

    /**
     * Confirm delete supplier
     */
    async confirmDelete() {
        if (!this.currentDeleteId) return;

        try {
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            if (confirmBtn) {
                confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
                confirmBtn.disabled = true;
            }

            const response = await fetch(`http://localhost/Phamarcy/src/backend/api/suppliers.php?id=${this.currentDeleteId}`, {
                method: 'DELETE',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Supplier deleted successfully', 'success');
                this.closeDeleteModal();
                await this.loadSuppliers();
                await this.loadStats();
            } else {
                throw new Error(result.message || 'Failed to delete supplier');
            }
        } catch (error) {
            console.error('Error deleting supplier:', error);
            this.showNotification('Failed to delete supplier: ' + error.message, 'error');
        } finally {
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            if (confirmBtn) {
                confirmBtn.innerHTML = '<i class="fas fa-trash"></i> Delete Supplier';
                confirmBtn.disabled = false;
            }
        }
    }

    /**
     * Handle form submission
     */
    async handleFormSubmit() {
        try {
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;

            const formData = this.getFormData();
            
            // Validate form data
            if (!this.validateFormData(formData)) {
                return;
            }

            const url = this.currentEditId ? 
                `http://localhost/Phamarcy/src/backend/api/suppliers.php?id=${this.currentEditId}` :
                'http://localhost/Phamarcy/src/backend/api/suppliers.php';
            
            const method = this.currentEditId ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method: method,
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(
                    this.currentEditId ? 'Supplier updated successfully' : 'Supplier added successfully',
                    'success'
                );
                this.closeSupplierModal();
                await this.loadSuppliers();
                await this.loadStats();
            } else {
                throw new Error(result.message || 'Failed to save supplier');
            }
        } catch (error) {
            console.error('Error saving supplier:', error);
            this.showNotification('Failed to save supplier: ' + error.message, 'error');
        } finally {
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.innerHTML = this.currentEditId ? 
                    '<i class="fas fa-save"></i> Update Supplier' : 
                    '<i class="fas fa-save"></i> Add Supplier';
                submitBtn.disabled = false;
            }
        }
    }

    /**
     * Get form data
     */
    getFormData() {
        return {
            company_name: document.getElementById('supplierName')?.value.trim() || '',
            contact_person: document.getElementById('contactPerson')?.value.trim() || '',
            phone: document.getElementById('phone')?.value.trim() || '',
            email: document.getElementById('email')?.value.trim() || '',
            status: document.getElementById('status')?.value || 'active',
            address: document.getElementById('address')?.value.trim() || '',
            category: document.getElementById('category')?.value || 'medicines',
            notes: document.getElementById('notes')?.value.trim() || ''
        };
    }

    /**
     * Validate form data
     */
    validateFormData(data) {
        const errors = [];

        if (!data.company_name) {
            errors.push('Company name is required');
        }

        if (!data.contact_person) {
            errors.push('Contact person is required');
        }

        if (!data.phone) {
            errors.push('Phone number is required');
        }

        if (!data.email) {
            errors.push('Email address is required');
        } else if (!this.isValidEmail(data.email)) {
            errors.push('Please enter a valid email address');
        }

        if (errors.length > 0) {
            this.showNotification(errors.join('. '), 'error');
            return false;
        }

        return true;
    }

    /**
     * Populate form with supplier data
     */
    populateForm(supplier) {
        const fields = {
            supplierName: supplier.company_name,
            contactPerson: supplier.contact_person,
            phone: supplier.phone,
            email: supplier.email,
            status: supplier.status,
            address: supplier.address,
            category: supplier.category,
            notes: supplier.notes
        };

        Object.entries(fields).forEach(([fieldId, value]) => {
            const element = document.getElementById(fieldId);
            if (element && value !== null && value !== undefined) {
                element.value = value;
            }
        });
    }

    /**
     * Reset form
     */
    resetForm() {
        const form = document.getElementById('supplierForm');
        if (form) {
            form.reset();
        }
    }

    /**
     * Close supplier modal
     */
    closeSupplierModal() {
        const modal = document.getElementById('supplierModal');
        if (modal) {
            modal.style.display = 'none';
        }
        this.resetForm();
        this.currentEditId = null;
    }

    /**
     * Close delete modal
     */
    closeDeleteModal() {
        const modal = document.getElementById('deleteModal');
        if (modal) {
            modal.style.display = 'none';
        }
        this.currentDeleteId = null;
    }

    /**
     * Close all modals
     */
    closeAllModals() {
        this.closeSupplierModal();
        this.closeDeleteModal();
    }

    /**
     * Show loading state
     */
    showLoading(show) {
        const loadingContainer = document.getElementById('loadingContainer');
        const table = document.getElementById('suppliersTable');
        
        if (loadingContainer) {
            loadingContainer.style.display = show ? 'flex' : 'none';
        }
        
        if (table && !show) {
            table.style.display = this.filteredSuppliers.length > 0 ? 'table' : 'none';
        }
    }

    /**
     * Refresh suppliers
     */
    async refreshSuppliers() {
        await this.loadSuppliers();
        await this.loadStats();
        this.showNotification('Suppliers refreshed successfully', 'success');
    }

    /**
     * Export suppliers
     */
    exportSuppliers() {
        if (this.filteredSuppliers.length === 0) {
            this.showNotification('No suppliers to export', 'warning');
            return;
        }

        try {
            const csvContent = this.generateCSV();
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            
            const link = document.createElement('a');
            link.href = url;
            link.download = `suppliers_${new Date().toISOString().split('T')[0]}.csv`;
            link.click();
            
            window.URL.revokeObjectURL(url);
            this.showNotification('Suppliers exported successfully', 'success');
        } catch (error) {
            console.error('Error exporting suppliers:', error);
            this.showNotification('Failed to export suppliers', 'error');
        }
    }

    /**
     * Generate CSV content
     */
    generateCSV() {
        const headers = ['Company Name', 'Contact Person', 'Phone', 'Email', 'Status', 'Address', 'Created Date'];
        const rows = this.filteredSuppliers.map(supplier => [
            supplier.company_name,
            supplier.contact_person,
            supplier.phone,
            supplier.email,
            supplier.status,
            supplier.address || '',
            new Date(supplier.created_at).toLocaleDateString()
        ]);

        const csvContent = [headers, ...rows]
            .map(row => row.map(field => `"${field}"`).join(','))
            .join('\n');

        return csvContent;
    }

    /**
     * Handle logout
     */
    async handleLogout() {
        try {
            const response = await fetch('http://localhost/Phamarcy/src/backend/api/logout.php', {
                method: 'POST',
                credentials: 'include'
            });

            // Clear local storage
            localStorage.removeItem('user');
            
            // Redirect to login
            window.location.href = '/Phamarcy/src/frontend/index.html';
        } catch (error) {
            console.error('Error during logout:', error);
            // Force redirect even if logout fails
            window.location.href = '/Phamarcy/src/frontend/index.html';
        }
    }

    /**
     * Utility functions
     */
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

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'info', duration = 3000) {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(notification => notification.remove());

        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas ${this.getNotificationIcon(type)}"></i>
                <span>${message}</span>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        // Add to page
        document.body.appendChild(notification);

        // Auto remove after duration
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, duration);
    }

    getNotificationIcon(type) {
        switch (type) {
            case 'success': return 'fa-check-circle';
            case 'error': return 'fa-exclamation-circle';
            case 'warning': return 'fa-exclamation-triangle';
            default: return 'fa-info-circle';
        }
    }
}

/**
 * Global functions for HTML onclick handlers
 */
// Global variable for access from HTML onclick handlers
window.suppliersManager = null;

// Global functions
function handleSearch() {
    // This is handled by the event listener in setupEventListeners
}

function applyFilters() {
    // This is handled by the event listeners in setupEventListeners
}

function refreshSuppliers() {
    if (suppliersManager) {
        suppliersManager.refreshSuppliers();
    }
}

function exportSuppliers() {
    if (suppliersManager) {
        suppliersManager.exportSuppliers();
    }
}

function openAddSupplierModal() {
    if (suppliersManager) {
        suppliersManager.openAddSupplierModal();
    }
}

function closeSupplierModal() {
    if (suppliersManager) {
        suppliersManager.closeSupplierModal();
    }
}

function closeDeleteModal() {
    if (suppliersManager) {
        suppliersManager.closeDeleteModal();
    }
}

function confirmDelete() {
    if (suppliersManager) {
        suppliersManager.confirmDelete();
    }
}

function toggleUserDropdown() {
    const dropdown = document.getElementById('userDropdown');
    if (dropdown) {
        dropdown.classList.toggle('active');
    }
}

function toggleActionsDropdown(supplierId) {
    // Close all other dropdowns
    const allDropdowns = document.querySelectorAll('.dropdown-menu');
    allDropdowns.forEach(dropdown => {
        if (dropdown.id !== `actionsDropdown${supplierId}`) {
            dropdown.classList.remove('active');
        }
    });

    // Toggle the clicked dropdown
    const dropdown = document.getElementById(`actionsDropdown${supplierId}`);
    if (dropdown) {
        dropdown.classList.toggle('active');
    }
}

// Utility function for debouncing
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

// Close dropdowns when clicking outside
document.addEventListener('click', (e) => {
    if (!e.target.closest('.actions-dropdown') && !e.target.closest('.user-dropdown')) {
        const dropdowns = document.querySelectorAll('.dropdown-menu');
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('active');
        });
    }
});

// Initialize the suppliers manager when DOM is loaded
// Initialize suppliers manager after main session system loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing suppliers page...');
    
    // Wait a bit for main_session_fixed.js to initialize
    setTimeout(() => {
        try {
            window.suppliersManager = new SuppliersManager();
            console.log('Suppliers manager initialized successfully');
        } catch (error) {
            console.error('Failed to initialize suppliers manager:', error);
            if (typeof Utils !== 'undefined') {
                Utils.showMessage('Failed to initialize suppliers page: ' + error.message, 'error');
            }
        }
    }, 100);
});
