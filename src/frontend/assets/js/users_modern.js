/**
 * User Management System - Complete Modern Implementation
 * Handles all user CRUD operations, role management, permissions, and statistics
 * 
 * Features:
 * - User account management (CRUD)
 * - Role-based access control
 * - Real-time search and filtering
 * - Pagination with customizable page sizes
 * - Statistics dashboard
 * - Password management
 * - Activity tracking
 * - Export functionality
 * - Responsive design
 */

class UsersManager {
    constructor() {
        this.users = [];
        this.filteredUsers = [];
        this.currentPage = 1;
        this.itemsPerPage = 10;
        this.totalPages = 0;
        this.currentUser = null;
        this.currentEditId = null;
        this.currentDeleteId = null;
        
        // Search and filter state
        this.searchTerm = '';
        this.roleFilter = '';
        this.statusFilter = '';
        this.sortBy = 'name';
        this.sortOrder = 'asc';
        
        this.init();
    }

    /**
     * Initialize the users manager
     */
    async init() {
        try {
            this.loadUserData(); // No await needed - synchronous now
            this.setupEventListeners();
            await this.loadUsers();
            await this.loadStats();
            this.applyRoleBasedAccess();
            this.updateUI();
        } catch (error) {
            console.error('Error initializing users manager:', error);
            this.showNotification('Error initializing page', 'error');
        }
    }

    /**
     * Load current user data from localStorage (managed by main_session_fixed.js)
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
     * Update user display in header and sidebar
     */
    updateUserDisplay() {
        if (!this.currentUser) return;
        
        // Header user display
        const headerUserName = document.getElementById('headerUserName');
        const headerUserRole = document.getElementById('headerUserRole');
        const headerUserAvatar = document.getElementById('headerUserAvatar');
        
        if (headerUserName) headerUserName.textContent = this.currentUser.full_name || this.currentUser.username;
        if (headerUserRole) headerUserRole.textContent = this.currentUser.role.charAt(0).toUpperCase() + this.currentUser.role.slice(1);
        if (headerUserAvatar) headerUserAvatar.textContent = (this.currentUser.full_name || this.currentUser.username).charAt(0).toUpperCase();
        
        // Sidebar user display
        const sidebarUserName = document.getElementById('sidebarUserName');
        const sidebarUserRole = document.getElementById('sidebarUserRole');
        const sidebarUserAvatar = document.getElementById('sidebarUserAvatar');
        
        if (sidebarUserName) sidebarUserName.textContent = this.currentUser.full_name || this.currentUser.username;
        if (sidebarUserRole) sidebarUserRole.textContent = this.currentUser.role.charAt(0).toUpperCase() + this.currentUser.role.slice(1);
        if (sidebarUserAvatar) sidebarUserAvatar.textContent = (this.currentUser.full_name || this.currentUser.username).charAt(0).toUpperCase();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Search input
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce(() => {
                this.searchTerm = searchInput.value;
                this.currentPage = 1;
                this.applyFiltersAndSearch();
            }, 300));
        }

        // Filter dropdowns
        const roleFilter = document.getElementById('roleFilter');
        if (roleFilter) {
            roleFilter.addEventListener('change', () => {
                this.roleFilter = roleFilter.value;
                this.currentPage = 1;
                this.applyFiltersAndSearch();
            });
        }

        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', () => {
                this.statusFilter = statusFilter.value;
                this.currentPage = 1;
                this.applyFiltersAndSearch();
            });
        }

        const sortFilter = document.getElementById('sortFilter');
        if (sortFilter) {
            sortFilter.addEventListener('change', () => {
                this.sortBy = sortFilter.value;
                this.applyFiltersAndSearch();
            });
        }

        // Form validation
        const userForm = document.getElementById('userForm');
        if (userForm) {
            userForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveUser();
            });
        }

        // Password confirmation
        const confirmPassword = document.getElementById('confirmPassword');
        if (confirmPassword) {
            confirmPassword.addEventListener('blur', this.validatePasswordMatch.bind(this));
        }

        // Close modals on outside click
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                this.closeUserModal();
                this.closeDeleteModal();
            }
        });
    }

    /**
     * Load users from API with better error handling
     */
    async loadUsers() {
        try {
            this.showLoading(true);
            console.log('🔄 Loading users from API...');
            
            // Try the main API first, fallback to simple API if needed
            let response;
            let apiUrl = 'http://localhost/Phamarcy/src/backend/api/users.php';
            
            try {
                response = await fetch(apiUrl, {
                    method: 'GET',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
            } catch (networkError) {
                console.log('🔄 Main API failed, trying simplified API...');
                apiUrl = 'http://localhost/Phamarcy/src/backend/api/users_simple.php';
                response = await fetch(apiUrl, {
                    method: 'GET',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
            }

            console.log(`📡 API Response [${apiUrl}]:`, response.status, response.statusText);

            if (!response.ok) {
                const errorText = await response.text();
                console.error('❌ API Error Response:', errorText);
                
                if (response.status === 401) {
                    throw new Error('Authentication required. Please login again.');
                } else if (response.status === 403) {
                    throw new Error('Insufficient permissions. Admin or Pharmacist role required.');
                } else {
                    throw new Error(`HTTP error! status: ${response.status}\nResponse: ${errorText}`);
                }
            }

            const result = await response.json();
            console.log('📊 API Result:', result);
            
            if (result.success) {
                this.users = result.data || [];
                this.applyFiltersAndSearch();
            } else {
                throw new Error(result.message || 'Failed to load users');
            }
        } catch (error) {
            console.error('Error loading users:', error);
            this.showNotification('Failed to load users: ' + error.message, 'error');
            this.users = [];
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
            const response = await fetch('http://localhost/Phamarcy/src/backend/api/users.php?action=stats', {
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
                this.updateStatsDisplay(result.data);
            } else {
                throw new Error(result.message || 'Failed to load statistics');
            }
        } catch (error) {
            console.error('Error loading stats:', error);
            // Don't show error notification for stats as it's not critical
        }
    }

    /**
     * Update statistics display
     */
    updateStatsDisplay(stats) {
        // Update total users
        const totalUsers = document.getElementById('totalUsers');
        if (totalUsers) totalUsers.textContent = stats.total_users || 0;
        
        const totalUsersChange = document.getElementById('totalUsersChange');
        if (totalUsersChange) totalUsersChange.textContent = `+${stats.users_growth || 0}%`;

        // Update active users
        const activeUsers = document.getElementById('activeUsers');
        if (activeUsers) activeUsers.textContent = stats.active_users || 0;
        
        const activeUsersChange = document.getElementById('activeUsersChange');
        if (activeUsersChange) activeUsersChange.textContent = `${stats.active_percentage || 0}%`;

        // Update new users
        const newUsers = document.getElementById('newUsers');
        if (newUsers) newUsers.textContent = stats.new_users_month || 0;
        
        const newUsersChange = document.getElementById('newUsersChange');
        if (newUsersChange) newUsersChange.textContent = `+${stats.new_users_month || 0}`;

        // Update admin users
        const adminUsers = document.getElementById('adminUsers');
        if (adminUsers) adminUsers.textContent = stats.admin_users || 0;
        
        const adminUsersChange = document.getElementById('adminUsersChange');
        if (adminUsersChange) adminUsersChange.textContent = stats.admin_users || 0;
    }

    /**
     * Apply role-based access control
     */
    applyRoleBasedAccess() {
        if (!this.currentUser) return;

        const isAdmin = this.currentUser.role === 'admin';
        const isManager = this.currentUser.role === 'manager';

        // Hide/show elements based on role
        const restrictedElements = document.querySelectorAll('.admin-only');
        restrictedElements.forEach(element => {
            element.style.display = isAdmin ? 'block' : 'none';
        });

        const managerElements = document.querySelectorAll('.manager-only');
        managerElements.forEach(element => {
            element.style.display = (isAdmin || isManager) ? 'block' : 'none';
        });

        // Disable certain actions for non-admin users
        if (!isAdmin) {
            const addUserBtn = document.querySelector('.btn-primary[onclick="openAddUserModal()"]');
            if (addUserBtn && !isManager) {
                addUserBtn.style.display = 'none';
            }
        }
    }

    /**
     * Apply filters and search
     */
    applyFiltersAndSearch() {
        let filtered = [...this.users];

        // Apply search filter
        if (this.searchTerm) {
            const searchLower = this.searchTerm.toLowerCase();
            filtered = filtered.filter(user => 
                user.full_name?.toLowerCase().includes(searchLower) ||
                user.username?.toLowerCase().includes(searchLower) ||
                user.email?.toLowerCase().includes(searchLower)
            );
        }

        // Apply role filter
        if (this.roleFilter) {
            filtered = filtered.filter(user => user.role === this.roleFilter);
        }

        // Apply status filter
        if (this.statusFilter) {
            filtered = filtered.filter(user => user.status === this.statusFilter);
        }

        // Apply sorting
        filtered.sort((a, b) => {
            let aVal, bVal;
            
            switch (this.sortBy) {
                case 'name':
                    aVal = a.full_name || a.username || '';
                    bVal = b.full_name || b.username || '';
                    break;
                case 'email':
                    aVal = a.email || '';
                    bVal = b.email || '';
                    break;
                case 'role':
                    aVal = a.role || '';
                    bVal = b.role || '';
                    break;
                case 'created':
                    aVal = new Date(a.created_at || 0);
                    bVal = new Date(b.created_at || 0);
                    break;
                case 'last_login':
                    aVal = new Date(a.last_login || 0);
                    bVal = new Date(b.last_login || 0);
                    break;
                default:
                    aVal = a.full_name || a.username || '';
                    bVal = b.full_name || b.username || '';
            }

            if (aVal < bVal) return this.sortOrder === 'asc' ? -1 : 1;
            if (aVal > bVal) return this.sortOrder === 'asc' ? 1 : -1;
            return 0;
        });

        this.filteredUsers = filtered;
        this.calculatePagination();
        this.updateUI();
    }

    /**
     * Calculate pagination
     */
    calculatePagination() {
        this.totalPages = Math.ceil(this.filteredUsers.length / this.itemsPerPage);
        if (this.currentPage > this.totalPages) {
            this.currentPage = Math.max(1, this.totalPages);
        }
    }

    /**
     * Update UI
     */
    updateUI() {
        this.renderUsersTable();
        this.renderPagination();
    }

    /**
     * Render users table
     */
    renderUsersTable() {
        const tbody = document.getElementById('usersTableBody');
        if (!tbody) return;

        if (this.filteredUsers.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">
                        <i class="fas fa-users" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i><br>
                        No users found
                    </td>
                </tr>
            `;
            return;
        }

        const startIndex = (this.currentPage - 1) * this.itemsPerPage;
        const endIndex = Math.min(startIndex + this.itemsPerPage, this.filteredUsers.length);
        const pageUsers = this.filteredUsers.slice(startIndex, endIndex);

        tbody.innerHTML = pageUsers.map(user => this.renderUserRow(user)).join('');
    }

    /**
     * Render individual user row
     */
    renderUserRow(user) {
        const avatar = (user.full_name || user.username || 'U').charAt(0).toUpperCase();
        const displayName = user.full_name || user.username;
        const lastLogin = user.last_login ? this.formatDate(user.last_login) : 'Never';
        const created = user.created_at ? this.formatDate(user.created_at) : '-';
        
        return `
            <tr>
                <td>
                    <div class="user-info">
                        <div class="user-avatar">${avatar}</div>
                        <div class="user-details">
                            <h4>${this.escapeHtml(displayName)}</h4>
                            <p>${this.escapeHtml(user.email || '')}</p>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="role-badge role-${user.role}">
                        ${user.role ? user.role.charAt(0).toUpperCase() + user.role.slice(1) : 'N/A'}
                    </span>
                </td>
                <td>
                    <span class="status-badge status-${user.status}">
                        ${user.status ? user.status.charAt(0).toUpperCase() + user.status.slice(1) : 'Unknown'}
                    </span>
                </td>
                <td>${lastLogin}</td>
                <td>${created}</td>
                <td>
                    <div class="actions-dropdown">
                        <button class="actions-btn" onclick="toggleActionsDropdown(${user.id})">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu" id="actionsDropdown${user.id}">
                            <button class="dropdown-item" onclick="viewUser(${user.id})">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                            <button class="dropdown-item" onclick="editUser(${user.id})">
                                <i class="fas fa-edit"></i> Edit User
                            </button>
                            <button class="dropdown-item" onclick="resetPassword(${user.id})">
                                <i class="fas fa-key"></i> Reset Password
                            </button>
                            ${user.status === 'active' 
                                ? `<button class="dropdown-item" onclick="toggleUserStatus(${user.id}, 'suspended')">
                                       <i class="fas fa-ban"></i> Suspend User
                                   </button>`
                                : `<button class="dropdown-item" onclick="toggleUserStatus(${user.id}, 'active')">
                                       <i class="fas fa-check"></i> Activate User
                                   </button>`
                            }
                            <button class="dropdown-item danger" onclick="deleteUser(${user.id})">
                                <i class="fas fa-trash"></i> Delete User
                            </button>
                        </div>
                    </div>
                </td>
            </tr>
        `;
    }

    /**
     * Render pagination
     */
    renderPagination() {
        const startIndex = (this.currentPage - 1) * this.itemsPerPage;
        const endIndex = Math.min(startIndex + this.itemsPerPage, this.filteredUsers.length);

        // Update pagination info
        document.getElementById('paginationStart').textContent = this.filteredUsers.length > 0 ? startIndex + 1 : 0;
        document.getElementById('paginationEnd').textContent = endIndex;
        document.getElementById('paginationTotal').textContent = this.filteredUsers.length;

        // Update pagination controls
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        
        if (prevBtn) prevBtn.disabled = this.currentPage <= 1;
        if (nextBtn) nextBtn.disabled = this.currentPage >= this.totalPages;

        // Render page numbers
        const pageNumbers = document.getElementById('pageNumbers');
        if (pageNumbers) {
            pageNumbers.innerHTML = this.renderPageNumbers();
        }
    }

    /**
     * Render page numbers
     */
    renderPageNumbers() {
        let html = '';
        const maxVisiblePages = 5;
        let startPage = Math.max(1, this.currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(this.totalPages, startPage + maxVisiblePages - 1);

        if (endPage - startPage + 1 < maxVisiblePages) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `
                <button class="page-btn ${i === this.currentPage ? 'active' : ''}" 
                        onclick="window.usersManager.goToPage(${i})">
                    ${i}
                </button>
            `;
        }

        return html;
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
     * Go to previous page
     */
    previousPage() {
        if (this.currentPage > 1) {
            this.currentPage--;
            this.updateUI();
        }
    }

    /**
     * Go to next page
     */
    nextPage() {
        if (this.currentPage < this.totalPages) {
            this.currentPage++;
            this.updateUI();
        }
    }

    /**
     * Open add user modal
     */
    openAddUserModal() {
        if (!this.hasPermission('create_users')) {
            this.showNotification('Access denied: Insufficient permissions', 'error');
            return;
        }

        this.currentEditId = null;
        document.getElementById('userModalTitle').textContent = 'Add New User';
        document.getElementById('passwordSection').style.display = 'block';
        document.getElementById('password').required = true;
        document.getElementById('confirmPassword').required = true;
        
        this.clearForm();
        this.showModal('userModal');
    }

    /**
     * Edit user
     */
    async editUser(userId) {
        if (!this.hasPermission('edit_users')) {
            this.showNotification('Access denied: Insufficient permissions', 'error');
            return;
        }

        const user = this.users.find(u => u.id === userId);
        if (!user) {
            this.showNotification('User not found', 'error');
            return;
        }

        this.currentEditId = userId;
        document.getElementById('userModalTitle').textContent = 'Edit User';
        document.getElementById('passwordSection').style.display = 'none';
        document.getElementById('password').required = false;
        document.getElementById('confirmPassword').required = false;
        
        // Populate form
        document.getElementById('fullName').value = user.full_name || '';
        document.getElementById('username').value = user.username || '';
        document.getElementById('email').value = user.email || '';
        document.getElementById('phone').value = user.phone || '';
        document.getElementById('role').value = user.role || '';
        document.getElementById('status').value = user.status || 'active';
        
        this.showModal('userModal');
    }

    /**
     * View user details
     */
    viewUser(userId) {
        const user = this.users.find(u => u.id === userId);
        if (!user) {
            this.showNotification('User not found', 'error');
            return;
        }

        const details = `
            <strong>Name:</strong> ${user.full_name || user.username}<br>
            <strong>Email:</strong> ${user.email}<br>
            <strong>Phone:</strong> ${user.phone || 'N/A'}<br>
            <strong>Role:</strong> ${user.role}<br>
            <strong>Status:</strong> ${user.status}<br>
            <strong>Created:</strong> ${this.formatDate(user.created_at)}<br>
            <strong>Last Login:</strong> ${user.last_login ? this.formatDate(user.last_login) : 'Never'}
        `;
        
        this.showNotification(details, 'info', 5000);
    }

    /**
     * Delete user
     */
    deleteUser(userId) {
        if (!this.hasPermission('delete_users')) {
            this.showNotification('Access denied: Insufficient permissions', 'error');
            return;
        }

        const user = this.users.find(u => u.id === userId);
        if (!user) {
            this.showNotification('User not found', 'error');
            return;
        }

        this.currentDeleteId = userId;
        document.getElementById('deleteUserName').textContent = user.full_name || user.username;
        this.showModal('deleteModal');
    }

    /**
     * Confirm user deletion
     */
    async confirmDelete() {
        if (!this.currentDeleteId) return;

        try {
            const response = await fetch(`http://localhost/Phamarcy/src/backend/api/users.php?id=${this.currentDeleteId}`, {
                method: 'DELETE',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('User deleted successfully', 'success');
                this.closeDeleteModal();
                await this.loadUsers();
                await this.loadStats();
            } else {
                throw new Error(result.message || 'Failed to delete user');
            }
        } catch (error) {
            console.error('Error deleting user:', error);
            this.showNotification('Failed to delete user: ' + error.message, 'error');
        }
    }

    /**
     * Save user (create or update)
     */
    async saveUser() {
        if (!this.validateForm()) {
            return;
        }

        const formData = this.getFormData();
        
        try {
            const url = this.currentEditId ? 
                `http://localhost/Phamarcy/src/backend/api/users.php?id=${this.currentEditId}` :
                'http://localhost/Phamarcy/src/backend/api/users.php';
            
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
                    this.currentEditId ? 'User updated successfully' : 'User created successfully',
                    'success'
                );
                this.closeUserModal();
                await this.loadUsers();
                await this.loadStats();
            } else {
                throw new Error(result.message || 'Failed to save user');
            }
        } catch (error) {
            console.error('Error saving user:', error);
            this.showNotification('Failed to save user: ' + error.message, 'error');
        }
    }

    /**
     * Get form data
     */
    getFormData() {
        const data = {
            full_name: document.getElementById('fullName').value.trim(),
            username: document.getElementById('username').value.trim(),
            email: document.getElementById('email').value.trim(),
            phone: document.getElementById('phone').value.trim(),
            role: document.getElementById('role').value,
            status: document.getElementById('status').value
        };

        // Add password only for new users or if provided
        if (!this.currentEditId) {
            data.password = document.getElementById('password').value;
        }

        return data;
    }

    /**
     * Validate form
     */
    validateForm() {
        let isValid = true;
        
        // Clear previous errors
        document.querySelectorAll('.error-message').forEach(el => el.textContent = '');

        // Validate full name
        const fullName = document.getElementById('fullName').value.trim();
        if (!fullName) {
            this.showFieldError('fullNameError', 'Full name is required');
            isValid = false;
        }

        // Validate username
        const username = document.getElementById('username').value.trim();
        if (!username) {
            this.showFieldError('usernameError', 'Username is required');
            isValid = false;
        } else if (username.length < 3) {
            this.showFieldError('usernameError', 'Username must be at least 3 characters');
            isValid = false;
        }

        // Validate email
        const email = document.getElementById('email').value.trim();
        if (!email) {
            this.showFieldError('emailError', 'Email is required');
            isValid = false;
        } else if (!this.isValidEmail(email)) {
            this.showFieldError('emailError', 'Please enter a valid email address');
            isValid = false;
        }

        // Validate role
        const role = document.getElementById('role').value;
        if (!role) {
            this.showFieldError('roleError', 'Role is required');
            isValid = false;
        }

        // Validate password for new users
        if (!this.currentEditId) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (!password) {
                this.showFieldError('passwordError', 'Password is required');
                isValid = false;
            } else if (password.length < 6) {
                this.showFieldError('passwordError', 'Password must be at least 6 characters');
                isValid = false;
            }

            if (password !== confirmPassword) {
                this.showFieldError('confirmPasswordError', 'Passwords do not match');
                isValid = false;
            }
        }

        return isValid;
    }

    /**
     * Validate password match
     */
    validatePasswordMatch() {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        if (confirmPassword && password !== confirmPassword) {
            this.showFieldError('confirmPasswordError', 'Passwords do not match');
        } else {
            this.showFieldError('confirmPasswordError', '');
        }
    }

    /**
     * Show field error
     */
    showFieldError(fieldId, message) {
        const errorElement = document.getElementById(fieldId);
        if (errorElement) {
            errorElement.textContent = message;
        }
    }

    /**
     * Toggle user status
     */
    async toggleUserStatus(userId, newStatus) {
        if (!this.hasPermission('edit_users')) {
            this.showNotification('Access denied: Insufficient permissions', 'error');
            return;
        }

        try {
            const response = await fetch(`http://localhost/Phamarcy/src/backend/api/users.php?id=${userId}`, {
                method: 'PUT',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ status: newStatus })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(`User ${newStatus} successfully`, 'success');
                await this.loadUsers();
                await this.loadStats();
            } else {
                throw new Error(result.message || 'Failed to update user status');
            }
        } catch (error) {
            console.error('Error updating user status:', error);
            this.showNotification('Failed to update user status: ' + error.message, 'error');
        }
    }

    /**
     * Reset user password
     */
    async resetPassword(userId) {
        if (!this.hasPermission('edit_users')) {
            this.showNotification('Access denied: Insufficient permissions', 'error');
            return;
        }

        const user = this.users.find(u => u.id === userId);
        if (!user) {
            this.showNotification('User not found', 'error');
            return;
        }

        if (confirm(`Reset password for ${user.full_name || user.username}?`)) {
            try {
                const response = await fetch(`http://localhost/Phamarcy/src/backend/api/users.php?id=${userId}&action=reset_password`, {
                    method: 'PUT',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    this.showNotification(`Password reset successfully. New password: ${result.new_password}`, 'success', 10000);
                } else {
                    throw new Error(result.message || 'Failed to reset password');
                }
            } catch (error) {
                console.error('Error resetting password:', error);
                this.showNotification('Failed to reset password: ' + error.message, 'error');
            }
        }
    }

    /**
     * Refresh users list
     */
    async refreshUsers() {
        await this.loadUsers();
        await this.loadStats();
        this.showNotification('Users refreshed successfully', 'success');
    }

    /**
     * Export users to CSV
     */
    exportUsers() {
        if (this.filteredUsers.length === 0) {
            this.showNotification('No users to export', 'warning');
            return;
        }

        try {
            const headers = ['Name', 'Username', 'Email', 'Phone', 'Role', 'Status', 'Created', 'Last Login'];
            const csvContent = [
                headers.join(','),
                ...this.filteredUsers.map(user => [
                    `"${user.full_name || ''}"`,
                    `"${user.username || ''}"`,
                    `"${user.email || ''}"`,
                    `"${user.phone || ''}"`,
                    `"${user.role || ''}"`,
                    `"${user.status || ''}"`,
                    `"${user.created_at ? this.formatDate(user.created_at) : ''}"`,
                    `"${user.last_login ? this.formatDate(user.last_login) : 'Never'}"`
                ].join(','))
            ].join('\n');

            this.downloadCSV(csvContent, 'users_export.csv');
            this.showNotification('Users exported successfully', 'success');
        } catch (error) {
            console.error('Error exporting users:', error);
            this.showNotification('Failed to export users', 'error');
        }
    }

    /**
     * Download CSV file
     */
    downloadCSV(content, filename) {
        const blob = new Blob([content], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    }

    /**
     * Show/hide loading spinner
     */
    showLoading(show) {
        const tbody = document.getElementById('usersTableBody');
        if (!tbody) return;

        if (show) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px;">
                        <div class="loading-spinner"></div>
                        <p style="margin-top: 16px; color: #64748b;">Loading users...</p>
                    </td>
                </tr>
            `;
        }
    }

    /**
     * Show modal
     */
    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            modal.style.display = 'flex';
        }
    }

    /**
     * Close user modal
     */
    closeUserModal() {
        const modal = document.getElementById('userModal');
        if (modal) {
            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
        this.clearForm();
        this.currentEditId = null;
    }

    /**
     * Close delete modal
     */
    closeDeleteModal() {
        const modal = document.getElementById('deleteModal');
        if (modal) {
            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
        this.currentDeleteId = null;
    }

    /**
     * Clear form
     */
    clearForm() {
        const form = document.getElementById('userForm');
        if (form) {
            form.reset();
        }
        
        // Clear error messages
        document.querySelectorAll('.error-message').forEach(el => el.textContent = '');
    }

    /**
     * Check if user has permission
     */
    hasPermission(action) {
        if (!this.currentUser) return false;
        
        const role = this.currentUser.role;
        
        switch (action) {
            case 'create_users':
            case 'edit_users':
            case 'delete_users':
                return role === 'admin' || role === 'manager';
            case 'view_users':
                return ['admin', 'manager', 'pharmacist'].includes(role);
            default:
                return false;
        }
    }

    /**
     * Logout user
     */
    async logout() {
        try {
            const response = await fetch('http://localhost/Phamarcy/src/backend/api/logout.php', {
                method: 'POST',
                credentials: 'include'
            });

            // Clear local storage
            localStorage.removeItem('user');
            localStorage.removeItem('auth_token');
            
            // Redirect to login
            window.location.href = 'http://localhost/Phamarcy/src/frontend/index.html';
        } catch (error) {
            console.error('Logout error:', error);
            // Force logout by clearing storage and redirecting
            localStorage.clear();
            window.location.href = 'http://localhost/Phamarcy/src/frontend/index.html';
        }
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

    /**
     * Get notification icon
     */
    getNotificationIcon(type) {
        switch (type) {
            case 'success': return 'fa-check-circle';
            case 'error': return 'fa-exclamation-circle';
            case 'warning': return 'fa-exclamation-triangle';
            default: return 'fa-info-circle';
        }
    }

    /**
     * Utility functions
     */
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

    formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }

    escapeHtml(text) {
        if (!text) return '';
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
}

/**
 * Global functions for HTML onclick handlers
 */

// Initialize when DOM is loaded
// Initialize users manager after main session system loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing users page...');
    
    // Wait a bit for main_session_fixed.js to initialize
    setTimeout(() => {
        try {
            window.usersManager = new UsersManager();
            console.log('Users manager initialized successfully');
        } catch (error) {
            console.error('Failed to initialize users manager:', error);
            if (typeof Utils !== 'undefined') {
                Utils.showMessage('Failed to initialize users page: ' + error.message, 'error');
            }
        }
    }, 100);
});

// Global functions for HTML onclick handlers
function toggleActionsDropdown(userId) {
    // Close all other dropdowns
    const allDropdowns = document.querySelectorAll('.dropdown-menu');
    allDropdowns.forEach(dropdown => {
        if (dropdown.id !== `actionsDropdown${userId}`) {
            dropdown.classList.remove('active');
        }
    });

    // Toggle the clicked dropdown
    const dropdown = document.getElementById(`actionsDropdown${userId}`);
    if (dropdown) {
        dropdown.classList.toggle('active');
    }
}

function viewUser(userId) {
    if (window.usersManager) {
        window.usersManager.viewUser(userId);
    }
}

function editUser(userId) {
    if (window.usersManager) {
        window.usersManager.editUser(userId);
    }
}

function deleteUser(userId) {
    if (window.usersManager) {
        window.usersManager.deleteUser(userId);
    }
}

function toggleUserStatus(userId, status) {
    if (window.usersManager) {
        window.usersManager.toggleUserStatus(userId, status);
    }
}

function resetPassword(userId) {
    if (window.usersManager) {
        window.usersManager.resetPassword(userId);
    }
}

function openAddUserModal() {
    if (window.usersManager) {
        window.usersManager.openAddUserModal();
    }
}

function closeUserModal() {
    if (window.usersManager) {
        window.usersManager.closeUserModal();
    }
}

function closeDeleteModal() {
    if (window.usersManager) {
        window.usersManager.closeDeleteModal();
    }
}

function saveUser() {
    if (window.usersManager) {
        window.usersManager.saveUser();
    }
}

function confirmDelete() {
    if (window.usersManager) {
        window.usersManager.confirmDelete();
    }
}

function refreshUsers() {
    if (window.usersManager) {
        window.usersManager.refreshUsers();
    }
}

function exportUsers() {
    if (window.usersManager) {
        window.usersManager.exportUsers();
    }
}

function previousPage() {
    if (window.usersManager) {
        window.usersManager.previousPage();
    }
}

function nextPage() {
    if (window.usersManager) {
        window.usersManager.nextPage();
    }
}

function logout() {
    if (window.usersManager) {
        window.usersManager.logout();
    }
}
