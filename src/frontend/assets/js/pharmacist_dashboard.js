/**
 * Pharmacist Dashboard Management
 * Focused on core pharmacist responsibilities: medicines, stock, and sales
 */

class PharmacistDashboard {
    constructor() {
        this.currentUser = null;
        this.medicineData = [];
        this.stockAlerts = [];
        this.todaySales = [];
        this.init();
    }

    async init() {
        try {
            await this.loadPharmacistData();
            this.setupUI();
            this.setupEventListeners();
            this.startDataRefresh();
            console.log('Pharmacist dashboard initialized successfully');
        } catch (error) {
            console.error('Failed to initialize pharmacist dashboard:', error);
            this.loadMockData();
        }
    }

    async loadPharmacistData() {
        try {
            // Load medicine inventory data
            await this.loadMedicineInventory();
            
            // Load stock alerts
            await this.loadStockAlerts();
            
            // Load today's sales data
            await this.loadTodaysSales();
            
            // Update dashboard statistics
            this.updateStatistics();
            
        } catch (error) {
            console.error('Error loading pharmacist data:', error);
            this.loadMockData();
        }
    }

    async loadMedicineInventory() {
        try {
            const response = await fetch('../../backend/api/medicines.php?action=getAll');
            if (response.ok) {
                const data = await response.json();
                this.medicineData = data.medicines || this.generateMockMedicines();
            } else {
                throw new Error('Failed to load medicine inventory');
            }
        } catch (error) {
            console.warn('Using mock medicine data:', error);
            this.medicineData = this.generateMockMedicines();
        }
    }

    async loadStockAlerts() {
        try {
            const response = await fetch('../../backend/api/medicines.php?action=getStockAlerts');
            if (response.ok) {
                const data = await response.json();
                this.stockAlerts = data.alerts || this.generateMockAlerts();
            } else {
                throw new Error('Failed to load stock alerts');
            }
        } catch (error) {
            console.warn('Using mock alert data:', error);
            this.stockAlerts = this.generateMockAlerts();
        }
    }

    async loadTodaysSales() {
        try {
            const response = await fetch('../../backend/api/sales.php?action=getTodaysSales');
            if (response.ok) {
                const data = await response.json();
                this.todaySales = data.sales || this.generateMockSales();
            } else {
                throw new Error('Failed to load today\'s sales');
            }
        } catch (error) {
            console.warn('Using mock sales data:', error);
            this.todaySales = this.generateMockSales();
        }
    }

    generateMockMedicines() {
        const medicines = [
            { id: 1, name: 'Paracetamol 500mg', category: 'Pain Relief', stock: 15, reorderLevel: 20, price: 5.99, expiry: '2024-12-15' },
            { id: 2, name: 'Amoxicillin 250mg', category: 'Antibiotics', stock: 45, reorderLevel: 30, price: 12.50, expiry: '2024-11-08' },
            { id: 3, name: 'Ibuprofen 400mg', category: 'Pain Relief', stock: 67, reorderLevel: 25, price: 8.75, expiry: '2025-03-20' },
            { id: 4, name: 'Aspirin 75mg', category: 'Cardiovascular', stock: 8, reorderLevel: 15, price: 6.30, expiry: '2025-01-30' },
            { id: 5, name: 'Omeprazole 20mg', category: 'Gastro', stock: 32, reorderLevel: 20, price: 15.80, expiry: '2025-02-14' }
        ];
        
        // Add more medicines to reach 324 total
        for (let i = 6; i <= 324; i++) {
            medicines.push({
                id: i,
                name: `Medicine ${i}`,
                category: ['Pain Relief', 'Antibiotics', 'Cardiovascular', 'Gastro', 'Respiratory'][Math.floor(Math.random() * 5)],
                stock: Math.floor(Math.random() * 100) + 10,
                reorderLevel: Math.floor(Math.random() * 30) + 10,
                price: (Math.random() * 50 + 5).toFixed(2),
                expiry: new Date(2024 + Math.floor(Math.random() * 2), Math.floor(Math.random() * 12), Math.floor(Math.random() * 28) + 1).toISOString().split('T')[0]
            });
        }
        
        return medicines;
    }

    generateMockAlerts() {
        return [
            { type: 'low_stock', medicine: 'Paracetamol 500mg', current: 15, required: 20, urgency: 'high' },
            { type: 'expiring', medicine: 'Amoxicillin 250mg', expiry: '2024-11-08', days: 3, urgency: 'medium' },
            { type: 'low_stock', medicine: 'Aspirin 75mg', current: 8, required: 15, urgency: 'high' }
        ];
    }

    generateMockSales() {
        const sales = [];
        for (let i = 0; i < 47; i++) {
            sales.push({
                id: i + 1,
                time: new Date(Date.now() - Math.random() * 24 * 60 * 60 * 1000).toISOString(),
                amount: (Math.random() * 100 + 10).toFixed(2),
                items: Math.floor(Math.random() * 5) + 1,
                type: Math.random() > 0.5 ? 'prescription' : 'otc'
            });
        }
        return sales;
    }

    loadMockData() {
        this.medicineData = this.generateMockMedicines();
        this.stockAlerts = this.generateMockAlerts();
        this.todaySales = this.generateMockSales();
        this.updateStatistics();
    }

    updateStatistics() {
        // Update total medicines count
        const totalMedicines = this.medicineData.length;
        this.updateElement('totalMedicines', totalMedicines);
        this.updateElement('medicineCount', totalMedicines);

        // Update low stock count
        const lowStockItems = this.medicineData.filter(m => m.stock <= m.reorderLevel);
        this.updateElement('lowStockCount', lowStockItems.length);
        this.updateElement('lowStockItems', lowStockItems.length);

        // Update expiring items count
        const today = new Date();
        const nextWeek = new Date(today.getTime() + 7 * 24 * 60 * 60 * 1000);
        const expiringItems = this.medicineData.filter(m => {
            const expiryDate = new Date(m.expiry);
            return expiryDate <= nextWeek;
        });
        this.updateElement('expiringCount', expiringItems.length);
        this.updateElement('expiringItems', expiringItems.length);

        // Update today's sales
        const todayTotal = this.todaySales.reduce((sum, sale) => sum + parseFloat(sale.amount), 0);
    this.updateElement('todaySales', `KSh ${todayTotal.toFixed(2)}`);
        this.updateElement('todayTransactions', this.todaySales.length);

        // Update prescription count
        const prescriptionCount = this.todaySales.filter(s => s.type === 'prescription').length;
        this.updateElement('prescriptionCount', prescriptionCount);

        // Update categories count
        const categories = new Set(this.medicineData.map(m => m.category));
        this.updateElement('categoriesCount', categories.size);

        // Update notification badge
        const totalAlerts = lowStockItems.length + expiringItems.length;
        this.updateElement('notificationCount', totalAlerts);
        this.updateElement('lowStockBadge', lowStockItems.length);
    }

    updateElement(elementId, value) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = value;
        }
    }

    setupUI() {
        // Set user information
        this.updateElement('headerUserName', 'Dr. Sarah Johnson');
        this.updateElement('headerUserRole', 'Licensed Pharmacist');
        
        const avatar = document.getElementById('headerUserAvatar');
        if (avatar) {
            avatar.textContent = 'P';
        }
    }

    setupEventListeners() {
        // Quick action buttons
        this.setupQuickActions();
        
        // Navigation
        this.setupNavigation();
        
        // Notification handling
        this.setupNotifications();
    }

    setupQuickActions() {
        // Medicine management actions
        window.quickAddMedicine = () => this.quickAddMedicine();
        window.addNewMedicine = () => this.addNewMedicine();
        window.viewMedicineInventory = () => this.viewMedicineInventory();
        
        // Stock control actions
        window.viewStockLevels = () => this.viewStockLevels();
        window.generateStockReport = () => this.generateStockReport();
        
        // Sales actions
        window.openPOS = () => this.openPointOfSale();
        window.openPointOfSale = () => this.openPointOfSale();
        window.viewTodaysSales = () => this.viewTodaysSales();
    }

    setupNavigation() {
        // Sidebar toggle
        window.toggleSidebar = () => {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.toggle('collapsed');
            }
        };
    }

    setupNotifications() {
        window.showNotifications = () => {
            this.showNotificationsModal();
        };
    }

    // Action Methods
    quickAddMedicine() {
        this.showAddMedicineModal();
    }

    addNewMedicine() {
        window.location.href = 'medicines_modern.html?action=add';
    }

    viewMedicineInventory() {
        window.location.href = 'medicines_modern.html';
    }

    viewStockLevels() {
        window.location.href = 'stock_management.html';
    }

    generateStockReport() {
        this.showNotification('Generating stock report...', 'info');
        // Mock report generation
        setTimeout(() => {
            this.downloadStockReport();
        }, 2000);
    }

    openPointOfSale() {
        window.location.href = 'sales_modern.html';
    }

    viewTodaysSales() {
        this.showTodaysSalesModal();
    }

    // Modal Methods
    showAddMedicineModal() {
        const modal = this.createModal('Add New Medicine', `
            <form id="quickAddMedicineForm" class="form-grid">
                <div class="form-group">
                    <label>Medicine Name *</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category" required>
                        <option value="">Select Category</option>
                        <option value="Pain Relief">Pain Relief</option>
                        <option value="Antibiotics">Antibiotics</option>
                        <option value="Cardiovascular">Cardiovascular</option>
                        <option value="Gastro">Gastro</option>
                        <option value="Respiratory">Respiratory</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Stock Quantity *</label>
                    <input type="number" name="stock" required min="0">
                </div>
                <div class="form-group">
                    <label>Price ($) *</label>
                    <input type="number" name="price" required min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label>Reorder Level</label>
                    <input type="number" name="reorderLevel" min="0">
                </div>
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry">
                </div>
            </form>
        `, [
            { text: 'Add Medicine', action: () => this.saveNewMedicine(), class: 'btn-primary' },
            { text: 'Cancel', action: () => this.closeModal(), class: 'btn-secondary' }
        ]);
    }

    showTodaysSalesModal() {
        const salesSummary = this.generateSalesTable();
        const modal = this.createModal('Today\'s Sales Summary', salesSummary, [
            { text: 'Export Report', action: () => this.exportSalesReport(), class: 'btn-primary' },
            { text: 'Close', action: () => this.closeModal(), class: 'btn-secondary' }
        ]);
    }

    showNotificationsModal() {
        const notifications = this.generateNotificationsContent();
        const modal = this.createModal('Alerts & Notifications', notifications, [
            { text: 'Mark All Read', action: () => this.markAllNotificationsRead(), class: 'btn-primary' },
            { text: 'Close', action: () => this.closeModal(), class: 'btn-secondary' }
        ]);
    }

    generateSalesTable() {
        const totalSales = this.todaySales.reduce((sum, sale) => sum + parseFloat(sale.amount), 0);
        const prescriptionSales = this.todaySales.filter(s => s.type === 'prescription');
        const otcSales = this.todaySales.filter(s => s.type === 'otc');
        
        return `
            <div class="sales-summary">
                <div class="summary-stats">
                    <div class="stat-item">
                        <strong>Total Sales:</strong> KSh ${totalSales.toFixed(2)}
                    </div>
                    <div class="stat-item">
                        <strong>Total Transactions:</strong> ${this.todaySales.length}
                    </div>
                    <div class="stat-item">
                        <strong>Prescriptions:</strong> ${prescriptionSales.length}
                    </div>
                    <div class="stat-item">
                        <strong>OTC Sales:</strong> ${otcSales.length}
                    </div>
                </div>
                <div class="recent-sales">
                    <h4>Recent Transactions</h4>
                    <table class="sales-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Amount</th>
                                <th>Items</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${this.todaySales.slice(0, 10).map(sale => `
                                <tr>
                                    <td>${new Date(sale.time).toLocaleTimeString()}</td>
                                    <td>KSh ${sale.amount}</td>
                                    <td>${sale.items}</td>
                                    <td><span class="type-badge ${sale.type}">${sale.type.toUpperCase()}</span></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    generateNotificationsContent() {
        const lowStockItems = this.medicineData.filter(m => m.stock <= m.reorderLevel);
        const expiringItems = this.medicineData.filter(m => {
            const expiryDate = new Date(m.expiry);
            const nextWeek = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000);
            return expiryDate <= nextWeek;
        });

        return `
            <div class="notifications-content">
                <div class="notification-section">
                    <h4><i class="fas fa-exclamation-triangle text-warning"></i> Low Stock Alerts (${lowStockItems.length})</h4>
                    ${lowStockItems.slice(0, 5).map(item => `
                        <div class="notification-item warning">
                            <strong>${item.name}</strong><br>
                            Current: ${item.stock} units | Required: ${item.reorderLevel} units
                        </div>
                    `).join('')}
                </div>
                
                <div class="notification-section">
                    <h4><i class="fas fa-calendar-times text-danger"></i> Expiring Soon (${expiringItems.length})</h4>
                    ${expiringItems.slice(0, 5).map(item => {
                        const daysToExpiry = Math.ceil((new Date(item.expiry) - new Date()) / (1000 * 60 * 60 * 24));
                        return `
                            <div class="notification-item danger">
                                <strong>${item.name}</strong><br>
                                Expires: ${item.expiry} (${daysToExpiry} days)
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }

    // Utility Methods
    createModal(title, content, buttons) {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-container">
                <div class="modal-header">
                    <h3>${title}</h3>
                    <button class="modal-close" onclick="closeModal()">&times;</button>
                </div>
                <div class="modal-body">
                    ${content}
                </div>
                <div class="modal-footer">
                    ${buttons.map(btn => `
                        <button class="btn ${btn.class}" onclick="${btn.action.name}()">${btn.text}</button>
                    `).join('')}
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        this.addModalStyles();
        return modal;
    }

    closeModal() {
        const modal = document.querySelector('.modal-overlay');
        if (modal) {
            modal.remove();
        }
    }

    saveNewMedicine() {
        const form = document.getElementById('quickAddMedicineForm');
        const formData = new FormData(form);
        
        // Mock save - in real implementation, send to API
        const newMedicine = {
            id: this.medicineData.length + 1,
            name: formData.get('name'),
            category: formData.get('category'),
            stock: parseInt(formData.get('stock')),
            price: parseFloat(formData.get('price')),
            reorderLevel: parseInt(formData.get('reorderLevel')) || 10,
            expiry: formData.get('expiry') || '2025-12-31'
        };
        
        this.medicineData.push(newMedicine);
        this.updateStatistics();
        this.closeModal();
        this.showNotification(`${newMedicine.name} added successfully!`, 'success');
    }

    markAllNotificationsRead() {
        // Mock implementation
        this.showNotification('All notifications marked as read', 'info');
        this.closeModal();
    }

    exportSalesReport() {
        // Mock export
        this.showNotification('Exporting sales report...', 'info');
        setTimeout(() => {
            this.showNotification('Sales report exported successfully!', 'success');
        }, 1500);
    }

    downloadStockReport() {
        // Generate CSV content for stock report
        const headers = ['Medicine Name', 'Category', 'Current Stock', 'Reorder Level', 'Status', 'Expiry Date'];
        const csvContent = [
            headers.join(','),
            ...this.medicineData.slice(0, 20).map(med => [
                med.name,
                med.category,
                med.stock,
                med.reorderLevel,
                med.stock <= med.reorderLevel ? 'LOW STOCK' : 'OK',
                med.expiry
            ].join(','))
        ].join('\n');
        
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `stock-report-${new Date().toISOString().split('T')[0]}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);
        
        this.showNotification('Stock report downloaded successfully!', 'success');
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 6px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            max-width: 350px;
        `;
        
        switch (type) {
            case 'success':
                notification.style.backgroundColor = '#27ae60';
                break;
            case 'error':
                notification.style.backgroundColor = '#e74c3c';
                break;
            case 'warning':
                notification.style.backgroundColor = '#f39c12';
                break;
            default:
                notification.style.backgroundColor = '#3498db';
        }
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 4000);
    }

    addModalStyles() {
        if (document.getElementById('pharmacistModalStyles')) return;
        
        const styles = document.createElement('style');
        styles.id = 'pharmacistModalStyles';
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
                max-height: 60vh;
                overflow-y: auto;
            }
            
            .modal-footer {
                padding: 20px 25px;
                border-top: 2px solid #f0f0f0;
                display: flex;
                gap: 10px;
                justify-content: flex-end;
            }
            
            .form-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }
            
            .form-group {
                display: flex;
                flex-direction: column;
            }
            
            .form-group label {
                margin-bottom: 5px;
                font-weight: 500;
                color: #2c3e50;
            }
            
            .form-group input,
            .form-group select {
                padding: 10px;
                border: 2px solid #f0f0f0;
                border-radius: 6px;
                font-size: 0.9rem;
            }
            
            .sales-summary {
                max-height: 400px;
                overflow-y: auto;
            }
            
            .summary-stats {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 2px solid #f0f0f0;
            }
            
            .stat-item {
                padding: 10px;
                background: #f8f9fa;
                border-radius: 6px;
            }
            
            .sales-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
            }
            
            .sales-table th,
            .sales-table td {
                padding: 8px;
                text-align: left;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .type-badge {
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 0.8rem;
            }
            
            .type-badge.prescription {
                background: #e8f5e8;
                color: #27ae60;
            }
            
            .type-badge.otc {
                background: #fff3cd;
                color: #856404;
            }
            
            .notifications-content {
                max-height: 400px;
                overflow-y: auto;
            }
            
            .notification-section {
                margin-bottom: 20px;
            }
            
            .notification-item {
                padding: 10px;
                margin: 8px 0;
                border-radius: 6px;
                border-left: 4px solid;
            }
            
            .notification-item.warning {
                background: #fffbf0;
                border-left-color: #f39c12;
            }
            
            .notification-item.danger {
                background: #fff5f5;
                border-left-color: #e74c3c;
            }
            
            @keyframes slideIn {
                from { transform: translateX(100%); }
                to { transform: translateX(0); }
            }
        `;
        
        document.head.appendChild(styles);
    }

    startDataRefresh() {
        // Refresh data every 5 minutes
        setInterval(() => {
            console.log('Refreshing pharmacist dashboard data...');
            this.loadPharmacistData();
        }, 5 * 60 * 1000);
    }
}

// Global functions for button clicks
window.closeModal = () => {
    const modal = document.querySelector('.modal-overlay');
    if (modal) modal.remove();
};

window.logout = () => {
    if (confirm('Are you sure you want to logout?')) {
        localStorage.removeItem('user');
        window.location.href = 'index.html';
    }
};

// Initialize pharmacist dashboard when page loads
document.addEventListener('DOMContentLoaded', () => {
    console.log('Initializing Pharmacist Dashboard...');
    window.pharmacistDashboard = new PharmacistDashboard();
});

console.log('Pharmacist Dashboard JavaScript loaded successfully');
