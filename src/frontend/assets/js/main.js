// Pharmacy Management System - Main JavaScript File

// API Configuration
const API_BASE_URL = 'http://localhost/Phamarcy/src/backend/api/';

// Utility Functions
class Utils {
    static showMessage(message, type = 'success') {
        const messageDiv = document.createElement('div');
        messageDiv.className = type === 'success' ? 'success-message' : 'error-message';
        messageDiv.textContent = message;
        
        // Find container to append message
        const container = document.querySelector('.form-container') || 
                         document.querySelector('.main-content') || 
                         document.body;
        
        container.insertBefore(messageDiv, container.firstChild);
        
        // Remove message after 5 seconds
        setTimeout(() => {
            messageDiv.remove();
        }, 5000);
    }
    
    static formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }
    
    static formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }
    
    static validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    static validatePhone(phone) {
        const phoneRegex = /^\d{10,15}$/;
        return phoneRegex.test(phone.replace(/\D/g, ''));
    }
}

// API Service Class
class APIService {
    static csrfToken = localStorage.getItem('csrf_token') || '';
    
    static async request(endpoint, options = {}) {
        const url = API_BASE_URL + endpoint;
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        };
        
        // Add CSRF token for write operations
        if (['POST', 'PUT', 'DELETE'].includes(options.method) && this.csrfToken) {
            defaultOptions.headers['X-CSRF-Token'] = this.csrfToken;
        }
        
        const finalOptions = { ...defaultOptions, ...options };
        
        console.log('Making API request to:', url);
        console.log('Request options:', finalOptions);
        
        try {
            const response = await fetch(url, finalOptions);
            console.log('Response status:', response.status);
            console.log('Response ok:', response.ok);
            
            const data = await response.json();
            console.log('Response data:', data);
            
            if (!response.ok) {
                throw new Error(data.message || 'Request failed');
            }
            
            return data;
        } catch (error) {
            console.error('API Request failed:', error);
            console.error('Error details:', error);
            throw error;
        }
    }
    
    static async login(credentials) {
        return this.request('login.php', {
            method: 'POST',
            body: JSON.stringify(credentials)
        });
    }
    
    static async logout() {
        return this.request('logout.php', {
            method: 'POST'
        });
    }
    
    static async getMedicines(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return this.request(`medicines.php?${queryString}`);
    }
    
    static async getMedicine(id) {
        return this.request(`medicines.php?id=${id}`);
    }
    
    static async addMedicine(medicineData) {
        return this.request('medicines.php', {
            method: 'POST',
            body: JSON.stringify(medicineData)
        });
    }
    
    static async updateMedicine(id, medicineData) {
        return this.request(`medicines.php?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify(medicineData)
        });
    }
    
    static async deleteMedicine(id) {
        return this.request(`medicines.php?id=${id}`, {
            method: 'DELETE'
        });
    }
    
    // Dashboard APIs
    static async getDashboardStats() {
        return this.request('dashboard.php?action=stats');
    }
    
    static async getRecentSales(limit = 10) {
        return this.request(`dashboard.php?action=recent_sales&limit=${limit}`);
    }
    
    static async getLowStockMedicines(limit = 20) {
        return this.request(`dashboard.php?action=low_stock&limit=${limit}`);
    }
    
    static async getExpiringMedicines(days = 30, limit = 20) {
        return this.request(`dashboard.php?action=expiring_medicines&days=${days}&limit=${limit}`);
    }
    
    // Sales APIs
    static async createSale(saleData) {
        return this.request('sales.php', {
            method: 'POST',
            body: JSON.stringify(saleData)
        });
    }
    
    static async getSales(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return this.request(`sales.php?${queryString}`);
    }
    
    static async getSaleDetails(id) {
        return this.request(`sales.php?action=details&id=${id}`);
    }
    
    // Customer APIs
    static async getCustomers(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return this.request(`customers.php?${queryString}`);
    }
    
    static async getCustomer(id) {
        return this.request(`customers.php?id=${id}`);
    }
    
    static async createCustomer(customerData) {
        return this.request('customers.php', {
            method: 'POST',
            body: JSON.stringify(customerData)
        });
    }
    
    static async updateCustomer(id, customerData) {
        return this.request(`customers.php?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify(customerData)
        });
    }
    
    static async deleteCustomer(id) {
        return this.request(`customers.php?id=${id}`, {
            method: 'DELETE'
        });
    }
    
    static async getCustomerStats() {
        return this.request('customers.php?action=stats');
    }
    
    // Security APIs
    static async changePassword(passwordData) {
        return this.request('change_password.php', {
            method: 'POST',
            body: JSON.stringify(passwordData)
        });
    }
}

// Authentication Handler
class Auth {
    static init() {
        console.log('Auth.init() called');
        const loginForm = document.getElementById('loginForm');
        console.log('Login form element:', loginForm);
        
        if (loginForm) {
            console.log('Attaching login form event listener');
            loginForm.addEventListener('submit', this.handleLogin.bind(this));
        } else {
            console.log('No login form found on this page');
        }
        
        // Check if user is already logged in
        this.checkAuthStatus();
    }
    
    static async handleLogin(event) {
        event.preventDefault();
        
        console.log('Login form submitted');
        
        const formData = new FormData(event.target);
        const credentials = {
            username: formData.get('username'),
            password: formData.get('password')
        };
        
        console.log('Login credentials:', credentials);
        
        const errorDiv = document.getElementById('loginError');
        errorDiv.style.display = 'none';
        
        try {
            console.log('Attempting API login...');
            const response = await APIService.login(credentials);
            console.log('API response:', response);
            
            if (response.success) {
                console.log('Login successful, storing user data');
                localStorage.setItem('user', JSON.stringify(response.user));
                if (response.csrf_token) {
                    localStorage.setItem('csrf_token', response.csrf_token);
                    APIService.csrfToken = response.csrf_token;
                }
                window.location.href = 'dashboard_modern.html';
            } else {
                console.log('Login failed:', response.message);
                this.showLoginError(response.message || 'Login failed');
            }
        } catch (error) {
            console.error('Login error:', error);
            this.showLoginError('Login failed. Please try again. Error: ' + error.message);
        }
    }
    
    static showLoginError(message) {
        const errorDiv = document.getElementById('loginError');
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
    }
    
    static checkAuthStatus() {
        const user = localStorage.getItem('user');
        const currentPage = window.location.pathname.split('/').pop();
        
        if (user && currentPage === 'index.html') {
            window.location.href = 'dashboard_modern.html';
        } else if (!user && currentPage !== 'index.html' && currentPage !== '') {
            window.location.href = 'index.html';
        }
    }
    
    static async logout() {
        try {
            await APIService.logout();
        } catch (error) {
            console.error('Logout request failed:', error);
        } finally {
            localStorage.removeItem('user');
            localStorage.removeItem('csrf_token');
            APIService.csrfToken = '';
            window.location.href = 'index.html';
        }
    }
    
    static getUser() {
        const user = localStorage.getItem('user');
        return user ? JSON.parse(user) : null;
    }
}

// Form Validation
class FormValidator {
    static validateRequired(fields) {
        const errors = [];
        
        fields.forEach(field => {
            const input = document.getElementById(field.id);
            if (!input || !input.value.trim()) {
                errors.push(`${field.label} is required`);
            }
        });
        
        return errors;
    }
    
    static validateMedicineForm(formData) {
        const errors = [];
        
        // Required fields validation
        const requiredFields = [
            { id: 'medicine_name', label: 'Medicine Name' },
            { id: 'unit_price', label: 'Unit Price' },
            { id: 'selling_price', label: 'Selling Price' }
        ];
        
        errors.push(...this.validateRequired(requiredFields));
        
        // Price validation
        const unitPrice = parseFloat(formData.get('unit_price'));
        const sellingPrice = parseFloat(formData.get('selling_price'));
        
        if (unitPrice <= 0) {
            errors.push('Unit price must be greater than 0');
        }
        
        if (sellingPrice <= 0) {
            errors.push('Selling price must be greater than 0');
        }
        
        if (sellingPrice < unitPrice) {
            errors.push('Selling price cannot be less than unit price');
        }
        
        return errors;
    }
}

// Initialize application when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    Auth.init();
    
    // Add event listeners for common elements
    const logoutButtons = document.querySelectorAll('.logout-btn');
    logoutButtons.forEach(btn => {
        btn.addEventListener('click', Auth.logout);
    });
    
    // Initialize page-specific functionality
    const currentPage = window.location.pathname.split('/').pop();
    
    switch (currentPage) {
        case 'dashboard_modern.html':
        case 'dashboard.html':
            initDashboard();
            break;
        case 'medicines.html':
            initMedicinesPage();
            break;
        case 'sales.html':
            initSalesPage();
            break;
        default:
            break;
    }
});

// Dashboard initialization
function initDashboard() {
    loadDashboardStats();
    loadRecentSales();
    loadLowStockMedicines();
    updateUserWelcome();
}

async function loadDashboardStats() {
    try {
        const response = await APIService.getDashboardStats();
        if (response.success) {
            const stats = response.data;
            
            document.getElementById('totalMedicines').textContent = stats.total_medicines || '0';
            document.getElementById('lowStockCount').textContent = stats.low_stock_count || '0';
            document.getElementById('expiringSoon').textContent = stats.expiring_soon || '0';
            document.getElementById('todaySales').textContent = Utils.formatCurrency(stats.today_sales || 0);
        }
    } catch (error) {
        console.error('Failed to load dashboard stats:', error);
        Utils.showMessage('Failed to load dashboard data', 'error');
    }
}

async function loadRecentSales() {
    try {
        const response = await APIService.getRecentSales(5);
        if (response.success) {
            displayRecentSales(response.data);
        }
    } catch (error) {
        console.error('Failed to load recent sales:', error);
    }
}

function displayRecentSales(sales) {
    const tableBody = document.querySelector('#recentSalesTable tbody');
    if (!tableBody) return;
    
    tableBody.innerHTML = '';
    
    if (sales.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="5" class="text-center">No recent sales</td></tr>';
        return;
    }
    
    sales.forEach(sale => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>#${sale.id}</td>
            <td>${sale.customer_name || 'Walk-in Customer'}</td>
            <td>${Utils.formatCurrency(sale.final_amount)}</td>
            <td>${Utils.formatDate(sale.sale_date)}</td>
            <td><span class="status-badge status-completed">Completed</span></td>
        `;
        tableBody.appendChild(row);
    });
}

async function loadLowStockMedicines() {
    try {
        const response = await APIService.getLowStockMedicines(10);
        if (response.success) {
            displayLowStockMedicines(response.data);
        }
    } catch (error) {
        console.error('Failed to load low stock medicines:', error);
    }
}

function displayLowStockMedicines(medicines) {
    const tableBody = document.querySelector('#lowStockTable tbody');
    if (!tableBody) return;
    
    tableBody.innerHTML = '';
    
    if (medicines.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="4" class="text-center">No low stock medicines</td></tr>';
        return;
    }
    
    medicines.forEach(medicine => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${medicine.name}</td>
            <td><span class="stock-warning">${medicine.stock_quantity}</span></td>
            <td>${medicine.min_stock_level}</td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="window.location.href='medicines.html'">
                    Restock
                </button>
            </td>
        `;
        tableBody.appendChild(row);
    });
}

function updateUserWelcome() {
    const user = Auth.getUser();
    const welcomeElements = document.querySelectorAll('#userWelcome');
    welcomeElements.forEach(element => {
        if (user) {
            element.textContent = `Welcome, ${user.full_name}`;
        }
    });
}

// Medicines page initialization
function initMedicinesPage() {
    loadMedicines();
    
    const addMedicineForm = document.getElementById('addMedicineForm');
    if (addMedicineForm) {
        addMedicineForm.addEventListener('submit', handleAddMedicine);
    }
}

async function loadMedicines() {
    try {
        const medicines = await APIService.getMedicines();
        displayMedicines(medicines);
    } catch (error) {
        Utils.showMessage('Failed to load medicines', 'error');
    }
}

function displayMedicines(medicines) {
    const tableBody = document.querySelector('#medicinesTable tbody');
    if (!tableBody) return;
    
    tableBody.innerHTML = '';
    
    medicines.forEach(medicine => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${medicine.name}</td>
            <td>${medicine.brand || '-'}</td>
            <td>${medicine.category_name || '-'}</td>
            <td>${Utils.formatCurrency(medicine.selling_price)}</td>
            <td>${medicine.stock_quantity}</td>
            <td>${Utils.formatDate(medicine.expiry_date)}</td>
            <td>
                <button onclick="editMedicine(${medicine.id})" class="btn btn-sm">Edit</button>
                <button onclick="deleteMedicine(${medicine.id})" class="btn btn-sm btn-danger">Delete</button>
            </td>
        `;
        tableBody.appendChild(row);
    });
}

async function handleAddMedicine(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const errors = FormValidator.validateMedicineForm(formData);
    
    if (errors.length > 0) {
        Utils.showMessage(errors.join(', '), 'error');
        return;
    }
    
    try {
        const medicineData = Object.fromEntries(formData);
        await APIService.addMedicine(medicineData);
        Utils.showMessage('Medicine added successfully');
        event.target.reset();
        loadMedicines();
    } catch (error) {
        Utils.showMessage('Failed to add medicine', 'error');
    }
}

// Sales page initialization
function initSalesPage() {
    // Initialize sales functionality
    console.log('Initializing sales page...');
}
