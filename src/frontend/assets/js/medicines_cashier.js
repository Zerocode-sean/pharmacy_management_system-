/**
 * Medicine Inventory System for Cashiers
 * Focused on viewing stock levels, updating quantities, and monitoring expiry dates
 */

class MedicineCashierManager {
    constructor() {
        console.log('💊 MedicineCashierManager: Initializing...');
        this.medicines = [];
        this.filteredMedicines = [];
        this.currentPage = 1;
        this.itemsPerPage = 10;
        this.totalPages = 0;
        this.currentUser = null;
        this.currentMedicine = null;
        
        // Filter state
        this.searchTerm = '';
        this.categoryFilter = '';
        this.stockFilter = '';
        this.expiryFilter = '';
        
        this.init();
    }

    /**
     * Initialize the medicine inventory system
     */
    async init() {
        try {
            console.log('💊 Initializing Medicine Inventory for Cashier...');
            
            // Wait for session system to be ready
            await this.waitForSessionSystem();
            
            // Load user data and set up
            this.loadUserData();
            this.setupEventListeners();
            
            // Load medicines and stats
            await this.loadMedicines();
            await this.loadStats();
            
            this.updateUI();
            console.log('💊 ✅ Medicine Inventory initialized successfully');
        } catch (error) {
            console.error('💊 ❌ Error initializing medicine inventory:', error);
            this.showNotification('Error loading medicine inventory', 'error');
        }
    }

    /**
     * Wait for the main session system to be ready
     */
    async waitForSessionSystem() {
        return new Promise((resolve) => {
            const checkSystem = () => {
                if (typeof window.PharmacyApp !== 'undefined' && window.PharmacyApp.APIService) {
                    resolve();
                } else {
                    setTimeout(checkSystem, 100);
                }
            };
            checkSystem();
        });
    }

    /**
     * Load user data from localStorage
     */
    loadUserData() {
        const userData = localStorage.getItem('user');
        if (userData) {
            this.currentUser = JSON.parse(userData);
            console.log('💊 User loaded:', this.currentUser.username, 'Role:', this.currentUser.role);
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
                this.debounceFilter();
            });
        }

        // Filter selects
        const categoryFilter = document.getElementById('categoryFilter');
        const stockFilter = document.getElementById('stockFilter');
        const expiryFilter = document.getElementById('expiryFilter');

        if (categoryFilter) {
            categoryFilter.addEventListener('change', (e) => {
                this.categoryFilter = e.target.value;
                this.applyFilters();
            });
        }

        if (stockFilter) {
            stockFilter.addEventListener('change', (e) => {
                this.stockFilter = e.target.value;
                this.applyFilters();
            });
        }

        if (expiryFilter) {
            expiryFilter.addEventListener('change', (e) => {
                this.expiryFilter = e.target.value;
                this.applyFilters();
            });
        }

        // Modal close on outside click
        window.addEventListener('click', (e) => {
            const modal = document.getElementById('medicineModal');
            if (e.target === modal) {
                this.closeMedicineModal();
            }
        });
    }

    /**
     * Debounced filter function for search
     */
    debounceFilter() {
        clearTimeout(this.filterTimeout);
        this.filterTimeout = setTimeout(() => {
            this.applyFilters();
        }, 300);
    }

    /**
     * Load medicines from API
     */
    async loadMedicines() {
        try {
            console.log('💊 Loading medicines...');
            const response = await window.PharmacyApp.APIService.request('medicines.php');
            
            if (response.success) {
                this.medicines = response.data || [];
                this.filteredMedicines = [...this.medicines];
                console.log('💊 Loaded', this.medicines.length, 'medicines');
                this.applyFilters();
            } else {
                throw new Error(response.message || 'Failed to load medicines');
            }
        } catch (error) {
            console.error('💊 Error loading medicines:', error);
            this.showNotification('Error loading medicines: ' + error.message, 'error');
        }
    }

    /**
     * Load inventory statistics
     */
    async loadStats() {
        try {
            console.log('💊 Loading medicine statistics...');
            const response = await window.PharmacyApp.APIService.request('medicines.php?action=stats');
            
            if (response.success) {
                const stats = response.data || {};
                this.updateStatistics(stats);
            } else {
                console.warn('Failed to load statistics:', response.message);
            }
        } catch (error) {
            console.warn('Error loading statistics:', error.message);
        }
    }

    /**
     * Update statistics display
     */
    updateStatistics(stats) {
        document.getElementById('totalMedicines').textContent = stats.total_medicines || 0;
        document.getElementById('lowStockCount').textContent = stats.low_stock || 0;
        document.getElementById('outOfStockCount').textContent = stats.out_of_stock || 0;
        document.getElementById('expiredCount').textContent = stats.expired || 0;
    }

    /**
     * Apply filters to medicine list
     */
    applyFilters() {
        let filtered = [...this.medicines];

        // Search filter
        if (this.searchTerm) {
            const search = this.searchTerm.toLowerCase();
            filtered = filtered.filter(medicine => 
                medicine.name.toLowerCase().includes(search) ||
                medicine.generic_name.toLowerCase().includes(search) ||
                medicine.batch_number.toLowerCase().includes(search) ||
                medicine.manufacturer.toLowerCase().includes(search)
            );
        }

        // Category filter
        if (this.categoryFilter) {
            filtered = filtered.filter(medicine => medicine.category === this.categoryFilter);
        }

        // Stock filter
        if (this.stockFilter) {
            filtered = filtered.filter(medicine => {
                const stockStatus = this.getStockStatus(medicine);
                return stockStatus === this.stockFilter;
            });
        }

        // Expiry filter
        if (this.expiryFilter) {
            filtered = filtered.filter(medicine => {
                const expiryStatus = this.getExpiryStatus(medicine);
                return expiryStatus === this.expiryFilter;
            });
        }

        this.filteredMedicines = filtered;
        this.currentPage = 1;
        this.updateUI();
    }

    /**
     * Update the user interface
     */
    updateUI() {
        this.updatePagination();
        this.renderMedicineTable();
    }

    /**
     * Update pagination
     */
    updatePagination() {
        this.totalPages = Math.ceil(this.filteredMedicines.length / this.itemsPerPage);
        if (this.currentPage > this.totalPages) {
            this.currentPage = 1;
        }
    }

    /**
     * Render the medicine table
     */
    renderMedicineTable() {
        const tableBody = document.getElementById('medicineTableBody');
        
        if (this.filteredMedicines.length === 0) {
            tableBody.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-pills"></i>
                    <h3>No Medicines Found</h3>
                    <p>Try adjusting your search or filter criteria</p>
                </div>
            `;
            return;
        }

        const startIndex = (this.currentPage - 1) * this.itemsPerPage;
        const endIndex = Math.min(startIndex + this.itemsPerPage, this.filteredMedicines.length);
        const pageData = this.filteredMedicines.slice(startIndex, endIndex);

        let tableHTML = `
            <table class="medicine-table">
                <thead>
                    <tr>
                        <th>Medicine Details</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Reorder Level</th>
                        <th>Unit Price</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        `;

        pageData.forEach(medicine => {
            const stockStatus = this.getStockStatus(medicine);
            const expiryStatus = this.getExpiryStatus(medicine);
            const stockBadgeClass = this.getStockBadgeClass(stockStatus);
            
            tableHTML += `
                <tr>
                    <td>
                        <div>
                            <strong>${medicine.name}</strong><br>
                            <small style="color: #666;">${medicine.generic_name}</small><br>
                            <small style="color: #999;">Batch: ${medicine.batch_number}</small>
                        </div>
                    </td>
                    <td>${medicine.category}</td>
                    <td>
                        <strong>${medicine.stock_quantity}</strong> ${medicine.unit}<br>
                        <small style="color: #666;">Min: ${medicine.reorder_level}</small>
                    </td>
                    <td>${medicine.reorder_level} ${medicine.unit}</td>
                    <td>KSh ${parseFloat(medicine.unit_price).toFixed(2)}</td>
                    <td>
                        <div class="expiry-status ${expiryStatus}">
                            ${this.formatDate(medicine.expiry_date)}<br>
                            <small>${this.getExpiryLabel(expiryStatus)}</small>
                        </div>
                    </td>
                    <td>
                        <span class="stock-badge ${stockBadgeClass}">
                            ${this.getStockLabel(stockStatus)}
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="action-btn view" onclick="medicineManager.viewMedicine(${medicine.id})" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="action-btn update" onclick="medicineManager.updateStock(${medicine.id})" title="Update Stock">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        tableHTML += `
                </tbody>
            </table>
        `;

        // Add pagination
        if (this.totalPages > 1) {
            tableHTML += this.renderPagination();
        }

        tableBody.innerHTML = tableHTML;
    }

    /**
     * Render pagination controls
     */
    renderPagination() {
        let paginationHTML = '<div class="pagination">';
        
        // Previous button
        paginationHTML += `
            <button class="pagination-btn" ${this.currentPage === 1 ? 'disabled' : ''} 
                    onclick="medicineManager.goToPage(${this.currentPage - 1})">
                <i class="fas fa-chevron-left"></i> Previous
            </button>
        `;

        // Page numbers
        const startPage = Math.max(1, this.currentPage - 2);
        const endPage = Math.min(this.totalPages, this.currentPage + 2);

        if (startPage > 1) {
            paginationHTML += `<button class="pagination-btn" onclick="medicineManager.goToPage(1)">1</button>`;
            if (startPage > 2) {
                paginationHTML += '<span>...</span>';
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            paginationHTML += `
                <button class="pagination-btn ${i === this.currentPage ? 'active' : ''}" 
                        onclick="medicineManager.goToPage(${i})">${i}</button>
            `;
        }

        if (endPage < this.totalPages) {
            if (endPage < this.totalPages - 1) {
                paginationHTML += '<span>...</span>';
            }
            paginationHTML += `<button class="pagination-btn" onclick="medicineManager.goToPage(${this.totalPages})">${this.totalPages}</button>`;
        }

        // Next button
        paginationHTML += `
            <button class="pagination-btn" ${this.currentPage === this.totalPages ? 'disabled' : ''} 
                    onclick="medicineManager.goToPage(${this.currentPage + 1})">
                Next <i class="fas fa-chevron-right"></i>
            </button>
        `;

        paginationHTML += '</div>';
        return paginationHTML;
    }

    /**
     * Go to specific page
     */
    goToPage(page) {
        if (page >= 1 && page <= this.totalPages) {
            this.currentPage = page;
            this.renderMedicineTable();
        }
    }

    /**
     * Get stock status for a medicine
     */
    getStockStatus(medicine) {
        if (this.getExpiryStatus(medicine) === 'expired') {
            return 'expired';
        }
        
        const stock = parseInt(medicine.stock_quantity);
        const reorderLevel = parseInt(medicine.reorder_level);
        
        if (stock === 0) {
            return 'out-of-stock';
        } else if (stock <= reorderLevel) {
            return 'low-stock';
        } else {
            return 'in-stock';
        }
    }

    /**
     * Get expiry status for a medicine
     */
    getExpiryStatus(medicine) {
        const today = new Date();
        const expiryDate = new Date(medicine.expiry_date);
        const daysUntilExpiry = Math.ceil((expiryDate - today) / (1000 * 60 * 60 * 24));
        
        if (daysUntilExpiry < 0) {
            return 'expired';
        } else if (daysUntilExpiry <= 30) {
            return 'expiring-soon';
        } else {
            return 'good';
        }
    }

    /**
     * Get CSS class for stock badge
     */
    getStockBadgeClass(status) {
        switch (status) {
            case 'in-stock': return 'in-stock';
            case 'low-stock': return 'low-stock';
            case 'out-of-stock': return 'out-of-stock';
            case 'expired': return 'expired';
            default: return 'in-stock';
        }
    }

    /**
     * Get label for stock status
     */
    getStockLabel(status) {
        switch (status) {
            case 'in-stock': return 'In Stock';
            case 'low-stock': return 'Low Stock';
            case 'out-of-stock': return 'Out of Stock';
            case 'expired': return 'Expired';
            default: return 'Unknown';
        }
    }

    /**
     * Get label for expiry status
     */
    getExpiryLabel(status) {
        switch (status) {
            case 'expired': return 'EXPIRED';
            case 'expiring-soon': return 'Expires Soon';
            case 'good': return 'Good';
            default: return '';
        }
    }

    /**
     * View medicine details
     */
    viewMedicine(medicineId) {
        const medicine = this.medicines.find(m => m.id === medicineId);
        if (!medicine) return;

        this.currentMedicine = medicine;
        
        const modalTitle = document.getElementById('modalTitle');
        const modalBody = document.getElementById('modalBody');
        const saveStockBtn = document.getElementById('saveStockBtn');

        modalTitle.textContent = 'Medicine Details';
        saveStockBtn.style.display = 'none';

        const stockStatus = this.getStockStatus(medicine);
        const expiryStatus = this.getExpiryStatus(medicine);

        modalBody.innerHTML = `
            <div class="form-group">
                <label>Medicine Name</label>
                <input type="text" class="form-input" value="${medicine.name}" readonly>
            </div>
            <div class="form-group">
                <label>Generic Name</label>
                <input type="text" class="form-input" value="${medicine.generic_name}" readonly>
            </div>
            <div class="form-group">
                <label>Category</label>
                <input type="text" class="form-input" value="${medicine.category}" readonly>
            </div>
            <div class="form-group">
                <label>Manufacturer</label>
                <input type="text" class="form-input" value="${medicine.manufacturer}" readonly>
            </div>
            <div class="form-group">
                <label>Batch Number</label>
                <input type="text" class="form-input" value="${medicine.batch_number}" readonly>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Current Stock</label>
                    <input type="text" class="form-input" value="${medicine.stock_quantity} ${medicine.unit}" readonly>
                </div>
                <div class="form-group">
                    <label>Reorder Level</label>
                    <input type="text" class="form-input" value="${medicine.reorder_level} ${medicine.unit}" readonly>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Unit Price</label>
                    <input type="text" class="form-input" value="KSh ${parseFloat(medicine.unit_price).toFixed(2)}" readonly>
                </div>
                <div class="form-group">
                    <label>Selling Price</label>
                    <input type="text" class="form-input" value="KSh ${parseFloat(medicine.selling_price).toFixed(2)}" readonly>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Manufacturing Date</label>
                    <input type="text" class="form-input" value="${this.formatDate(medicine.manufacturing_date)}" readonly>
                </div>
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="text" class="form-input" value="${this.formatDate(medicine.expiry_date)}" readonly>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Stock Status</label>
                    <span class="stock-badge ${this.getStockBadgeClass(stockStatus)}">
                        ${this.getStockLabel(stockStatus)}
                    </span>
                </div>
                <div class="form-group">
                    <label>Expiry Status</label>
                    <span class="expiry-status ${expiryStatus}">
                        ${this.getExpiryLabel(expiryStatus)}
                    </span>
                </div>
            </div>
            ${medicine.description ? `
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-input" readonly rows="3">${medicine.description}</textarea>
                </div>
            ` : ''}
        `;

        this.showModal();
    }

    /**
     * Update stock for a medicine
     */
    updateStock(medicineId) {
        const medicine = this.medicines.find(m => m.id === medicineId);
        if (!medicine) return;

        this.currentMedicine = medicine;
        
        const modalTitle = document.getElementById('modalTitle');
        const modalBody = document.getElementById('modalBody');
        const saveStockBtn = document.getElementById('saveStockBtn');

        modalTitle.textContent = 'Update Stock Quantity';
        saveStockBtn.style.display = 'block';

        modalBody.innerHTML = `
            <div class="form-group">
                <label>Medicine Name</label>
                <input type="text" class="form-input" value="${medicine.name}" readonly>
            </div>
            <div class="form-group">
                <label>Current Stock</label>
                <input type="text" class="form-input" value="${medicine.stock_quantity} ${medicine.unit}" readonly>
            </div>
            <div class="form-group">
                <label>Update Type</label>
                <select id="updateType" class="form-input">
                    <option value="add">Add to Stock (Receiving)</option>
                    <option value="subtract">Subtract from Stock (Adjustment)</option>
                    <option value="set">Set New Stock Level</option>
                </select>
            </div>
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" id="stockQuantity" class="form-input" min="0" step="1" placeholder="Enter quantity">
            </div>
            <div class="form-group">
                <label>Reason (Optional)</label>
                <input type="text" id="stockReason" class="form-input" placeholder="e.g., New shipment, Damaged goods, Stock count">
            </div>
            <div id="previewStock" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 10px; display: none;">
                <strong>Preview:</strong> <span id="previewText"></span>
            </div>
        `;

        // Add event listener for quantity preview
        const quantityInput = document.getElementById('stockQuantity');
        const updateTypeSelect = document.getElementById('updateType');
        
        const updatePreview = () => {
            const quantity = parseInt(quantityInput.value) || 0;
            const updateType = updateTypeSelect.value;
            const currentStock = parseInt(medicine.stock_quantity);
            
            if (quantity > 0) {
                let newStock;
                let action;
                
                switch (updateType) {
                    case 'add':
                        newStock = currentStock + quantity;
                        action = `Add ${quantity} ${medicine.unit}`;
                        break;
                    case 'subtract':
                        newStock = Math.max(0, currentStock - quantity);
                        action = `Subtract ${quantity} ${medicine.unit}`;
                        break;
                    case 'set':
                        newStock = quantity;
                        action = `Set to ${quantity} ${medicine.unit}`;
                        break;
                }
                
                document.getElementById('previewStock').style.display = 'block';
                document.getElementById('previewText').textContent = 
                    `${action} → New stock: ${newStock} ${medicine.unit}`;
            } else {
                document.getElementById('previewStock').style.display = 'none';
            }
        };

        quantityInput.addEventListener('input', updatePreview);
        updateTypeSelect.addEventListener('change', updatePreview);

        this.showModal();
    }

    /**
     * Save stock update
     */
    async saveStockUpdate() {
        const quantity = parseInt(document.getElementById('stockQuantity').value);
        const updateType = document.getElementById('updateType').value;
        const reason = document.getElementById('stockReason').value;
        
        if (!quantity || quantity <= 0) {
            this.showNotification('Please enter a valid quantity', 'error');
            return;
        }

        const currentStock = parseInt(this.currentMedicine.stock_quantity);
        let newStock;

        switch (updateType) {
            case 'add':
                newStock = currentStock + quantity;
                break;
            case 'subtract':
                newStock = Math.max(0, currentStock - quantity);
                break;
            case 'set':
                newStock = quantity;
                break;
        }

        try {
            const updateData = {
                id: this.currentMedicine.id,
                stock_quantity: newStock,
                reason: reason || `Stock ${updateType} by cashier`,
                updated_by: this.currentUser.id
            };

            const response = await window.PharmacyApp.APIService.request('medicines.php', {
                method: 'PUT',
                body: JSON.stringify(updateData)
            });

            if (response.success) {
                this.showNotification('Stock updated successfully', 'success');
                this.closeMedicineModal();
                await this.loadMedicines();
                await this.loadStats();
            } else {
                throw new Error(response.message || 'Failed to update stock');
            }
        } catch (error) {
            console.error('Error updating stock:', error);
            this.showNotification('Error updating stock: ' + error.message, 'error');
        }
    }

    /**
     * Show modal
     */
    showModal() {
        const modal = document.getElementById('medicineModal');
        modal.classList.add('show');
    }

    /**
     * Close modal
     */
    closeMedicineModal() {
        const modal = document.getElementById('medicineModal');
        modal.classList.remove('show');
        this.currentMedicine = null;
    }

    /**
     * Format date for display
     */
    formatDate(dateString) {
        if (!dateString) return 'Not set';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        if (window.PharmacyApp && window.PharmacyApp.Utils) {
            window.PharmacyApp.Utils.showMessage(message, type);
        } else {
            alert(message);
        }
    }
}

// Global functions for HTML onclick handlers
function applyFilters() {
    if (window.medicineManager) {
        window.medicineManager.applyFilters();
    }
}

function closeMedicineModal() {
    if (window.medicineManager) {
        window.medicineManager.closeMedicineModal();
    }
}

function saveStockUpdate() {
    if (window.medicineManager) {
        window.medicineManager.saveStockUpdate();
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('💊 DOM loaded, initializing Medicine Cashier Manager...');
    
    // Initialize with a small delay to ensure session system is ready
    setTimeout(() => {
        window.medicineManager = new MedicineCashierManager();
    }, 500);
});

// Export for debugging
if (typeof window !== 'undefined') {
    window.MedicineCashierManager = MedicineCashierManager;
}
