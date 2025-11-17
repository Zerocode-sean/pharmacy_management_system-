/**
 * Medicines Management System - Clean Implementation with Debug Logging
 * Handles medicine inventory management with robust session handling
 */

class MedicinesManager {
    constructor() {
        console.log('🏥 MedicinesManager: Constructor called');
        this.medicines = [];
        this.filteredMedicines = [];
        this.currentPage = 1;
        this.itemsPerPage = 10;
        this.totalPages = 0;
        this.currentUser = null;
        this.currentEditId = null;
        this.currentDeleteId = null;
        
        // Search and filter state
        this.searchTerm = '';
        this.categoryFilter = '';
        this.stockFilter = '';
        
        console.log('🏥 MedicinesManager: Starting initialization...');
        this.init();
    }

    /**
     * Initialize the medicines manager
     */
    async init() {
        try {
            console.log('🏥 MedicinesManager: Initializing...');
            await this.loadUserData();
            console.log('🏥 MedicinesManager: User data loaded, setting up event listeners...');
            this.setupEventListeners();
            console.log('🏥 MedicinesManager: Loading medicines...');
            await this.loadMedicines();
            console.log('🏥 MedicinesManager: Applying role-based access...');
            this.applyRoleBasedAccess();
            console.log('🏥 MedicinesManager: Updating UI...');
            this.updateUI();
            console.log('🏥 MedicinesManager: ✅ Initialized successfully');
        } catch (error) {
            console.error('🏥 MedicinesManager: ❌ Error initializing:', error);
            this.showNotification('Error initializing page', 'error');
        }
    }

    /**
     * Load user data - simplified to use main session management
     */
    async loadUserData() {
        try {
            console.log('🏥 MedicinesManager: Loading user data...');
            
            // Wait for main session system to be ready
            let attempts = 0;
            const maxAttempts = 10;
            
            while (attempts < maxAttempts) {
                const user = window.PharmacyApp?.Auth?.getUser();
                if (user) {
                    this.currentUser = user;
                    this.updateUserDisplay();
                    console.log('🏥 MedicinesManager: ✅ User data loaded:', this.currentUser.username);
                    return;
                }
                
                // Wait 100ms and try again
                await new Promise(resolve => setTimeout(resolve, 100));
                attempts++;
            }
            
            throw new Error('Main session system not available');
        } catch (error) {
            console.error('🏥 MedicinesManager: ❌ Error loading user data:', error);
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
     * Apply role-based access control
     */
    applyRoleBasedAccess() {
        if (!this.currentUser) return;
        
        const userRole = this.currentUser.role;
        
        // Restrict access based on role
        if (userRole === 'cashier') {
            // Cashiers can view but not add/edit/delete medicines
            const addBtn = document.getElementById('addMedicineBtn');
            if (addBtn) addBtn.style.display = 'none';
        }
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Search input
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.searchTerm = e.target.value;
                this.filterMedicines();
            });
        }

        // Category filter
        const categoryFilter = document.getElementById('categoryFilter');
        if (categoryFilter) {
            categoryFilter.addEventListener('change', (e) => {
                this.categoryFilter = e.target.value;
                this.filterMedicines();
            });
        }

        // Stock filter
        const stockFilter = document.getElementById('stockFilter');
        if (stockFilter) {
            stockFilter.addEventListener('change', (e) => {
                this.stockFilter = e.target.value;
                this.filterMedicines();
            });
        }

        // Medicine form
        const medicineForm = document.getElementById('medicineForm');
        if (medicineForm) {
            medicineForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleSubmit();
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

        window.openAddMedicineModal = () => {
            this.openMedicineModal();
        };

        window.editMedicine = (id) => {
            this.editMedicine(id);
        };

        window.deleteMedicine = (id) => {
            this.deleteMedicine(id);
        };

        window.closeMedicineModal = () => {
            this.closeMedicineModal();
        };

        window.closeDeleteModal = () => {
            this.closeDeleteModal();
        };

        window.confirmDelete = () => {
            this.confirmDelete();
        };

        window.previousPage = () => {
            this.previousPage();
        };

        window.nextPage = () => {
            this.nextPage();
        };
    }

    /**
     * Load medicines from API
     */
    async loadMedicines() {
        try {
            console.log('🏥 MedicinesManager: Loading medicines from API...');
            
            const response = await fetch('/Phamarcy/src/backend/api/medicines.php', {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            console.log('🏥 MedicinesManager: Medicines API response:', response.status, response.statusText);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            console.log('🏥 MedicinesManager: Medicines API result:', result);
            
            if (result.success) {
                this.medicines = result.medicines || [];
                this.updateStats(result.stats || {});
                this.filterMedicines();
                console.log(`🏥 MedicinesManager: ✅ Loaded ${this.medicines.length} medicines`);
            } else {
                throw new Error(result.message || 'Failed to load medicines');
            }
        } catch (error) {
            console.error('🏥 MedicinesManager: ❌ Error loading medicines:', error);
            this.showNotification('Error loading medicines: ' + error.message, 'error');
        }
    }

    /**
     * Filter medicines based on search and filters
     */
    filterMedicines() {
        let filtered = [...this.medicines];

        // Apply search filter
        if (this.searchTerm) {
            const searchLower = this.searchTerm.toLowerCase();
            filtered = filtered.filter(medicine => 
                medicine.name.toLowerCase().includes(searchLower) ||
                medicine.generic_name.toLowerCase().includes(searchLower) ||
                medicine.manufacturer.toLowerCase().includes(searchLower)
            );
        }

        // Apply category filter
        if (this.categoryFilter) {
            filtered = filtered.filter(medicine => medicine.category === this.categoryFilter);
        }

        // Apply stock filter
        if (this.stockFilter) {
            filtered = filtered.filter(medicine => {
                const stock = parseInt(medicine.stock_quantity);
                const reorder = parseInt(medicine.reorder_level) || 10;
                
                switch (this.stockFilter) {
                    case 'in_stock':
                        return stock > reorder;
                    case 'low_stock':
                        return stock > 0 && stock <= reorder;
                    case 'out_of_stock':
                        return stock === 0;
                    default:
                        return true;
                }
            });
        }

        this.filteredMedicines = filtered;
        this.currentPage = 1;
        this.updateTable();
        this.updatePagination();
    }

    /**
     * Update statistics display
     */
    updateStats(stats) {
        const totalMedicines = document.getElementById('totalMedicines');
        const lowStock = document.getElementById('lowStock');
        const expiringSoon = document.getElementById('expiringSoon');
        const totalValue = document.getElementById('totalValue');

        if (totalMedicines) totalMedicines.textContent = stats.total_medicines || 0;
        if (lowStock) lowStock.textContent = stats.low_stock || 0;
        if (expiringSoon) expiringSoon.textContent = stats.expiring_soon || 0;
        
        // Calculate total value
        const value = this.medicines.reduce((total, medicine) => {
            return total + (parseFloat(medicine.unit_price) * parseInt(medicine.stock_quantity));
        }, 0);
    if (totalValue) totalValue.textContent = 'KSh ' + value.toFixed(2);
    }

    /**
     * Update table display
     */
    updateTable() {
        const tableBody = document.getElementById('medicinesTableBody');
        if (!tableBody) return;

        const startIndex = (this.currentPage - 1) * this.itemsPerPage;
        const endIndex = startIndex + this.itemsPerPage;
        const pageData = this.filteredMedicines.slice(startIndex, endIndex);

        if (pageData.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center">No medicines found</td>
                </tr>
            `;
            return;
        }

        tableBody.innerHTML = pageData.map(medicine => `
            <tr>
                <td>${this.escapeHtml(medicine.name)}</td>
                <td>${this.escapeHtml(medicine.generic_name)}</td>
                <td>${this.escapeHtml(medicine.category)}</td>
                <td>${this.escapeHtml(medicine.manufacturer)}</td>
                <td>
                    <span class="stock-badge ${this.getStockBadgeClass(medicine)}">
                        ${medicine.stock_quantity}
                    </span>
                </td>
                <td>KSh ${parseFloat(medicine.unit_price).toFixed(2)}</td>
                <td>${medicine.expiry_date ? new Date(medicine.expiry_date).toLocaleDateString() : 'N/A'}</td>
                <td>
                    <div class="action-buttons">
                        ${this.currentUser && this.currentUser.role !== 'cashier' ? `
                            <button class="btn-icon btn-edit" onclick="editMedicine(${medicine.id})" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon btn-delete" onclick="deleteMedicine(${medicine.id})" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join('');
    }

    /**
     * Get stock badge class based on stock level
     */
    getStockBadgeClass(medicine) {
        const stock = parseInt(medicine.stock_quantity);
        const reorder = parseInt(medicine.reorder_level) || 10;
        
        if (stock === 0) return 'stock-out';
        if (stock <= reorder) return 'stock-low';
        return 'stock-good';
    }

    /**
     * Update pagination
     */
    updatePagination() {
        this.totalPages = Math.ceil(this.filteredMedicines.length / this.itemsPerPage);
        
        const paginationInfo = document.getElementById('paginationInfo');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        
        if (paginationInfo) {
            const startIndex = (this.currentPage - 1) * this.itemsPerPage + 1;
            const endIndex = Math.min(this.currentPage * this.itemsPerPage, this.filteredMedicines.length);
            paginationInfo.textContent = `Showing ${startIndex}-${endIndex} of ${this.filteredMedicines.length} medicines`;
        }
        
        if (prevBtn) prevBtn.disabled = this.currentPage === 1;
        if (nextBtn) nextBtn.disabled = this.currentPage === this.totalPages || this.totalPages === 0;
    }

    /**
     * Navigate to previous page
     */
    previousPage() {
        if (this.currentPage > 1) {
            this.currentPage--;
            this.updateTable();
            this.updatePagination();
        }
    }

    /**
     * Navigate to next page
     */
    nextPage() {
        if (this.currentPage < this.totalPages) {
            this.currentPage++;
            this.updateTable();
            this.updatePagination();
        }
    }

    /**
     * Open medicine modal for adding
     */
    openMedicineModal() {
        this.currentEditId = null;
        document.getElementById('modalTitle').textContent = 'Add New Medicine';
        document.getElementById('medicineForm').reset();
        document.getElementById('medicineModal').classList.add('active');
    }

    /**
     * Open medicine modal for editing
     */
    editMedicine(id) {
        const medicine = this.medicines.find(m => m.id == id);
        if (!medicine) return;

        this.currentEditId = id;
        document.getElementById('modalTitle').textContent = 'Edit Medicine';
        
        // Fill form with medicine data
        document.getElementById('medicineName').value = medicine.name;
        document.getElementById('genericName').value = medicine.generic_name;
        document.getElementById('manufacturer').value = medicine.manufacturer;
        document.getElementById('category').value = medicine.category;
        document.getElementById('unitPrice').value = medicine.unit_price;
        document.getElementById('stockQuantity').value = medicine.stock_quantity;
        document.getElementById('reorderLevel').value = medicine.reorder_level;
        document.getElementById('expiryDate').value = medicine.expiry_date;
        document.getElementById('description').value = medicine.description || '';
        
        document.getElementById('medicineModal').classList.add('active');
    }

    /**
     * Close medicine modal
     */
    closeMedicineModal() {
        document.getElementById('medicineModal').classList.remove('active');
        this.currentEditId = null;
    }

    /**
     * Handle form submission
     */
    async handleSubmit() {
        try {
            const formData = new FormData(document.getElementById('medicineForm'));
            const data = {};
            
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }

            if (this.currentEditId) {
                data.id = this.currentEditId;
            }

            const method = this.currentEditId ? 'PUT' : 'POST';
            
            const response = await fetch('/Phamarcy/src/backend/api/medicines.php', {
                method: method,
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification(result.message, 'success');
                this.closeMedicineModal();
                await this.loadMedicines();
            } else {
                this.showNotification(result.message, 'error');
            }
        } catch (error) {
            console.error('Error submitting form:', error);
            this.showNotification('Error saving medicine', 'error');
        }
    }

    /**
     * Delete medicine
     */
    deleteMedicine(id) {
        const medicine = this.medicines.find(m => m.id == id);
        if (!medicine) return;

        this.currentDeleteId = id;
        document.getElementById('deleteMedicineName').textContent = medicine.name;
        document.getElementById('deleteModal').classList.add('active');
    }

    /**
     * Close delete modal
     */
    closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('active');
        this.currentDeleteId = null;
    }

    /**
     * Confirm delete
     */
    async confirmDelete() {
        if (!this.currentDeleteId) return;

        try {
            const response = await fetch('/Phamarcy/src/backend/api/medicines.php', {
                method: 'DELETE',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: this.currentDeleteId })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification(result.message, 'success');
                this.closeDeleteModal();
                await this.loadMedicines();
            } else {
                this.showNotification(result.message, 'error');
            }
        } catch (error) {
            console.error('Error deleting medicine:', error);
            this.showNotification('Error deleting medicine', 'error');
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
        this.updateTable();
        this.updatePagination();
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
    console.log('🏥 Medicines: DOM loaded, waiting for session system...');
    setTimeout(() => {
        console.log('🏥 Medicines: Initializing MedicinesManager...');
        new MedicinesManager();
    }, 500);
});
