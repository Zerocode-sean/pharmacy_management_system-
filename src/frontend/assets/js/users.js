// User Management JavaScript
// Fallback helper: define `secureApiCall` if it's not provided by other scripts.
// This lightweight polyfill supports multiple call signatures used in this repo:
//  - secureApiCall(url)
//  - secureApiCall(url, optionsObject)
//  - secureApiCall(url, methodString, body)
// It normalizes "/api/..." paths to the local backend API path used in dev.
if (typeof secureApiCall === 'undefined') {
    async function secureApiCall(url, arg2, arg3) {
        // Normalize common repo API path conventions to a usable path for fetch.
        function normalize(u) {
            if (!u) return u;
            // If caller passed full absolute URL, leave it alone
            if (/^https?:\/\//i.test(u)) return u;
            // Convert leading /api/ to the local backend api folder used in this repo
            if (u.startsWith('/api/')) return '/Phamarcy/src/backend/api/' + u.slice(5);
            // If already points to Phamarcy backend, return as-is
            if (u.startsWith('/Phamarcy') || u.startsWith('../backend') || u.startsWith('/')) return u;
            // Fallback: treat as file under backend/api
            return '/Phamarcy/src/backend/api/' + u;
        }

        let options = { credentials: 'include', headers: {} };

        if (typeof arg2 === 'string') {
            options.method = arg2;
            options.body = arg3;
        } else if (typeof arg2 === 'object' && arg2 !== null) {
            options = Object.assign(options, arg2);
        }

        // If body is a plain object (not FormData), serialize to JSON and set header
        if (options.body && !(options.body instanceof FormData) && typeof options.body === 'object') {
            options.headers = options.headers || {};
            if (!options.headers['Content-Type'] && !options.headers['content-type']) {
                options.headers['Content-Type'] = 'application/json';
            }
            options.body = JSON.stringify(options.body);
        }

        const finalUrl = normalize(url);
        try {
            const res = await fetch(finalUrl, options);
            const ct = res.headers.get('content-type') || '';
            // Try parse JSON responses
            if (ct.includes('application/json')) {
                return await res.json();
            }
            // Otherwise return a generic shape
            const text = await res.text();
            return { success: res.ok, data: text };
        } catch (err) {
            // Bubble up the error so callers see it (they already catch in many places)
            throw err;
        }
    }
}
let users = [];
let filteredUsers = [];
let editingUser = null;
let currentUserTab = 'users';
let rolePermissions = {};
let performanceData = [];
let attendanceData = [];
let evaluationData = [];
let trainingData = [];
let performanceCharts = {};

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    loadUsers();
    loadUserStats();
    loadRolePermissions();
    initializeYearBasedFeatures();
    
    // Setup form submission handlers
    document.getElementById('userForm').addEventListener('submit', handleUserSubmit);
    document.getElementById('rolePermissionsForm').addEventListener('submit', handleRolePermissionsSubmit);
    document.getElementById('goalsForm').addEventListener('submit', handleGoalsSubmit);
    document.getElementById('attendanceForm').addEventListener('submit', handleAttendanceSubmit);
    document.getElementById('evaluationForm').addEventListener('submit', handleEvaluationSubmit);
    document.getElementById('trainingForm').addEventListener('submit', handleTrainingSubmit);
});

// Initialize year-based features
function initializeYearBasedFeatures() {
    // Set current year as default
    const currentYear = new Date().getFullYear();
    const currentMonth = new Date().toISOString().slice(0, 7); // YYYY-MM format
    
    // Set default values for year selectors
    document.getElementById('performanceYear').value = currentYear;
    document.getElementById('evaluationYear').value = currentYear;
    document.getElementById('attendanceMonth').value = currentMonth;
    
    // Set default evaluation date to today
    document.getElementById('evaluationDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('evaluationYear').value = currentYear;
    
    // Set default attendance date to today
    document.getElementById('attendanceDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('trainingStartDate').value = new Date().toISOString().split('T')[0];
}

// Load all users
async function loadUsers() {
    try {
        showLoading('Loading users...');
        const response = await secureApiCall('/api/users.php');
        
        if (response.success) {
            users = response.data;
            filteredUsers = [...users];
            displayUsers();
        } else {
            showError('Failed to load users: ' + response.message);
        }
    } catch (error) {
        showError('Error loading users: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Load user statistics
async function loadUserStats() {
    try {
        const response = await secureApiCall('/api/users.php?action=stats');
        
        if (response.success) {
            const stats = response.data;
            document.getElementById('totalUsers').textContent = stats.total || 0;
            document.getElementById('activeUsers').textContent = stats.active || 0;
            document.getElementById('adminUsers').textContent = stats.admin || 0;
            document.getElementById('onlineUsers').textContent = stats.online || 0;
            
            // Update role counts
            document.getElementById('adminCount').textContent = `${stats.admin || 0} users`;
            document.getElementById('pharmacistCount').textContent = `${stats.pharmacist || 0} users`;
            document.getElementById('cashierCount').textContent = `${stats.cashier || 0} users`;
        }
    } catch (error) {
        console.error('Error loading user stats:', error);
    }
}

// Load role permissions
async function loadRolePermissions() {
    try {
        const response = await secureApiCall('/api/users.php?action=permissions');
        
        if (response.success) {
            rolePermissions = response.data;
        }
    } catch (error) {
        console.error('Error loading role permissions:', error);
    }
}

// Display users in table
function displayUsers() {
    const tbody = document.getElementById('usersTableBody');
    
    if (filteredUsers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No users found</td></tr>';
        return;
    }
    
    tbody.innerHTML = filteredUsers.map(user => `
        <tr>
            <td>
                <div class="user-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
            </td>
            <td>
                <div class="user-details">
                    <strong>${escapeHtml(user.full_name)}</strong>
                    <div class="user-meta">
                        <span class="username">@${escapeHtml(user.username)}</span>
                        <span class="email">${escapeHtml(user.email)}</span>
                        ${user.phone ? `<span class="phone">${escapeHtml(user.phone)}</span>` : ''}
                    </div>
                </div>
            </td>
            <td>
                <span class="role-badge ${user.role}">
                    <i class="${getRoleIcon(user.role)}"></i>
                    ${capitalizeFirst(user.role)}
                </span>
            </td>
            <td>
                <span class="status-badge ${user.is_active ? 'active' : 'inactive'}">
                    ${user.is_active ? 'Active' : 'Inactive'}
                </span>
            </td>
            <td>${user.last_login ? formatDateTime(user.last_login) : 'Never'}</td>
            <td>${formatDate(user.created_at)}</td>
            <td class="actions">
                <button class="btn btn-sm btn-outline" onclick="viewUser(${user.id})" title="View Details">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-sm btn-outline" onclick="editUser(${user.id})" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-warning" onclick="resetPassword(${user.id})" title="Reset Password">
                    <i class="fas fa-key"></i>
                </button>
                ${user.is_active ? 
                    `<button class="btn btn-sm btn-warning" onclick="suspendUser(${user.id})" title="Suspend">
                        <i class="fas fa-pause"></i>
                    </button>` :
                    `<button class="btn btn-sm btn-success" onclick="activateUser(${user.id})" title="Activate">
                        <i class="fas fa-play"></i>
                    </button>`
                }
                ${user.id !== getCurrentUserId() ? 
                    `<button class="btn btn-sm btn-danger" onclick="deleteUser(${user.id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>` : ''
                }
            </td>
        </tr>
    `).join('');
}

// Search users
function searchUsers() {
    const searchTerm = document.getElementById('searchUser').value.toLowerCase();
    
    filteredUsers = users.filter(user => 
        user.full_name.toLowerCase().includes(searchTerm) ||
        user.username.toLowerCase().includes(searchTerm) ||
        user.email.toLowerCase().includes(searchTerm) ||
        (user.phone && user.phone.includes(searchTerm))
    );
    
    filterUsers();
}

// Filter users by role and status
function filterUsers() {
    const roleFilter = document.getElementById('roleFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    let filtered = [...filteredUsers];
    
    // Apply search filter first
    const searchTerm = document.getElementById('searchUser').value.toLowerCase();
    if (searchTerm) {
        filtered = users.filter(user => 
            user.full_name.toLowerCase().includes(searchTerm) ||
            user.username.toLowerCase().includes(searchTerm) ||
            user.email.toLowerCase().includes(searchTerm) ||
            (user.phone && user.phone.includes(searchTerm))
        );
    } else {
        filtered = [...users];
    }
    
    // Apply role filter
    if (roleFilter !== 'all') {
        filtered = filtered.filter(user => user.role === roleFilter);
    }
    
    // Apply status filter
    if (statusFilter !== 'all') {
        if (statusFilter === 'active') {
            filtered = filtered.filter(user => user.is_active);
        } else if (statusFilter === 'inactive') {
            filtered = filtered.filter(user => !user.is_active);
        }
    }
    
    filteredUsers = filtered;
    displayUsers();
}

// Switch between user management tabs
function switchUserTab(tabName) {
    // Update active tab button
    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[onclick="switchUserTab('${tabName}')"]`).classList.add('active');
    
    // Update active tab content
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.getElementById(tabName + 'Tab').classList.add('active');
    
    currentUserTab = tabName;
    
    // Load specific tab data
    switch (tabName) {
        case 'performance':
            loadPerformanceData();
            break;
        case 'attendance':
            loadAttendanceData();
            break;
        case 'evaluations':
            loadEvaluationsData();
            break;
        case 'training':
            loadTrainingData();
            break;
        case 'activity':
            loadUserActivity();
            break;
        case 'sessions':
            loadActiveSessions();
            break;
    }
}

// Open add user modal
function openAddUserModal() {
    editingUser = null;
    document.getElementById('userModalTitle').textContent = 'Add New User';
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    document.getElementById('status').value = '1';
    
    // Make password fields required for new users
    document.getElementById('password').required = true;
    document.getElementById('confirmPassword').required = true;
    document.getElementById('passwordSection').style.display = 'block';
    document.getElementById('confirmPasswordSection').style.display = 'block';
    
    document.getElementById('userModal').style.display = 'block';
}

// Edit user
function editUser(userId) {
    const user = users.find(u => u.id === userId);
    if (!user) return;
    
    editingUser = user;
    document.getElementById('userModalTitle').textContent = 'Edit User';
    
    // Populate form fields
    document.getElementById('userId').value = user.id;
    document.getElementById('fullName').value = user.full_name;
    document.getElementById('username').value = user.username;
    document.getElementById('email').value = user.email;
    document.getElementById('phone').value = user.phone || '';
    document.getElementById('role').value = user.role;
    document.getElementById('status').value = user.is_active ? '1' : '0';
    
    // Make password fields optional for editing
    document.getElementById('password').required = false;
    document.getElementById('confirmPassword').required = false;
    document.getElementById('passwordSection').style.display = 'none';
    document.getElementById('confirmPasswordSection').style.display = 'none';
    
    updateRolePermissions();
    
    document.getElementById('userModal').style.display = 'block';
}

// View user details
function viewUser(userId) {
    const user = users.find(u => u.id === userId);
    if (!user) return;
    
    showInfo(`
        <div class="user-profile">
            <h3>${user.full_name}</h3>
            <div class="profile-details">
                <p><strong>Username:</strong> ${user.username}</p>
                <p><strong>Email:</strong> ${user.email}</p>
                ${user.phone ? `<p><strong>Phone:</strong> ${user.phone}</p>` : ''}
                <p><strong>Role:</strong> ${capitalizeFirst(user.role)}</p>
                <p><strong>Status:</strong> ${user.is_active ? 'Active' : 'Inactive'}</p>
                <p><strong>Created:</strong> ${formatDateTime(user.created_at)}</p>
                <p><strong>Last Login:</strong> ${user.last_login ? formatDateTime(user.last_login) : 'Never'}</p>
            </div>
        </div>
    `);
}

// Close user modal
function closeUserModal() {
    document.getElementById('userModal').style.display = 'none';
    editingUser = null;
}

// Handle user form submission
async function handleUserSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const userData = {};
    
    for (let [key, value] of formData.entries()) {
        userData[key] = value;
    }
    
    // Validate password confirmation for new users
    if (!editingUser && userData.password !== userData.confirm_password) {
        showError('Passwords do not match');
        return;
    }
    
    // Validate password strength
    if (!editingUser && !validatePasswordStrength(userData.password)) {
        showError('Password must be at least 8 characters with uppercase, lowercase, number and special character');
        return;
    }
    
    try {
        showLoading('Saving user...');
        
        const url = editingUser ? 
            `/api/users.php?id=${editingUser.id}` : 
            '/api/users.php';
        const method = editingUser ? 'PUT' : 'POST';
        
        const response = await secureApiCall(url, {
            method: method,
            body: JSON.stringify(userData)
        });
        
        if (response.success) {
            showSuccess(editingUser ? 'User updated successfully!' : 'User added successfully!');
            closeUserModal();
            loadUsers();
            loadUserStats();
        } else {
            showError('Failed to save user: ' + response.message);
        }
    } catch (error) {
        showError('Error saving user: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Reset user password
async function resetPassword(userId) {
    const user = users.find(u => u.id === userId);
    if (!user) return;
    
    if (!confirm(`Are you sure you want to reset password for "${user.full_name}"? A temporary password will be generated.`)) {
        return;
    }
    
    try {
        showLoading('Resetting password...');
        
        const response = await secureApiCall(`/api/users.php?action=reset_password&id=${userId}`, {
            method: 'POST'
        });
        
        if (response.success) {
            showSuccess(`Password reset successfully! Temporary password: ${response.data.temporary_password}`);
        } else {
            showError('Failed to reset password: ' + response.message);
        }
    } catch (error) {
        showError('Error resetting password: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Suspend user
async function suspendUser(userId) {
    const user = users.find(u => u.id === userId);
    if (!user) return;
    
    if (!confirm(`Are you sure you want to suspend "${user.full_name}"?`)) {
        return;
    }
    
    try {
        showLoading('Suspending user...');
        
        const response = await secureApiCall(`/api/users.php?action=suspend&id=${userId}`, {
            method: 'PUT'
        });
        
        if (response.success) {
            showSuccess('User suspended successfully!');
            loadUsers();
            loadUserStats();
        } else {
            showError('Failed to suspend user: ' + response.message);
        }
    } catch (error) {
        showError('Error suspending user: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Activate user
async function activateUser(userId) {
    const user = users.find(u => u.id === userId);
    if (!user) return;
    
    try {
        showLoading('Activating user...');
        
        const response = await secureApiCall(`/api/users.php?action=activate&id=${userId}`, {
            method: 'PUT'
        });
        
        if (response.success) {
            showSuccess('User activated successfully!');
            loadUsers();
            loadUserStats();
        } else {
            showError('Failed to activate user: ' + response.message);
        }
    } catch (error) {
        showError('Error activating user: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Delete user
async function deleteUser(userId) {
    const user = users.find(u => u.id === userId);
    if (!user) return;
    
    if (!confirm(`Are you sure you want to delete user "${user.full_name}"? This action cannot be undone.`)) {
        return;
    }
    
    try {
        showLoading('Deleting user...');
        
        const response = await secureApiCall(`/api/users.php?id=${userId}`, {
            method: 'DELETE'
        });
        
        if (response.success) {
            showSuccess('User deleted successfully!');
            loadUsers();
            loadUserStats();
        } else {
            showError('Failed to delete user: ' + response.message);
        }
    } catch (error) {
        showError('Error deleting user: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Open role management modal
function openRoleManagementModal() {
    showInfo('Role management functionality allows you to create custom roles and assign specific permissions. This feature provides granular control over what each user can access in the system.');
}

// Edit role permissions
function editRolePermissions(role) {
    document.getElementById('editingRole').value = role;
    document.getElementById('rolePermissionsTitle').textContent = `Edit ${capitalizeFirst(role)} Permissions`;
    
    // Load current permissions for this role
    const permissions = rolePermissions[role] || [];
    
    // Clear all checkboxes
    document.querySelectorAll('#rolePermissionsForm input[type="checkbox"]').forEach(checkbox => {
        checkbox.checked = false;
    });
    
    // Check permissions that this role has
    permissions.forEach(permission => {
        const checkbox = document.querySelector(`input[value="${permission}"]`);
        if (checkbox) {
            checkbox.checked = true;
        }
    });
    
    document.getElementById('rolePermissionsModal').style.display = 'block';
}

// Close role permissions modal
function closeRolePermissionsModal() {
    document.getElementById('rolePermissionsModal').style.display = 'none';
}

// Handle role permissions submission
async function handleRolePermissionsSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const role = formData.get('role');
    const permissions = formData.getAll('permissions[]');
    
    try {
        showLoading('Updating role permissions...');
        
        const response = await secureApiCall('/api/users.php?action=update_permissions', {
            method: 'PUT',
            body: JSON.stringify({ role, permissions })
        });
        
        if (response.success) {
            showSuccess('Role permissions updated successfully!');
            closeRolePermissionsModal();
            loadRolePermissions();
        } else {
            showError('Failed to update permissions: ' + response.message);
        }
    } catch (error) {
        showError('Error updating permissions: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Update role permissions display in user form
function updateRolePermissions() {
    const selectedRole = document.getElementById('role').value;
    const permissionsList = document.getElementById('rolePermissionsList');
    
    if (!selectedRole || !rolePermissions[selectedRole]) {
        permissionsList.innerHTML = '<p>Select a role to view permissions</p>';
        return;
    }
    
    const permissions = rolePermissions[selectedRole];
    permissionsList.innerHTML = permissions.map(permission => 
        `<div class="permission-item">
            <i class="fas fa-check text-success"></i>
            <span>${formatPermissionName(permission)}</span>
        </div>`
    ).join('');
}

// Load user activity
async function loadUserActivity() {
    try {
        const filter = document.getElementById('activityFilter').value;
        const period = document.getElementById('activityPeriod').value;
        
        const response = await secureApiCall(`/api/users.php?action=activity&filter=${filter}&period=${period}`);
        
        if (response.success) {
            displayUserActivity(response.data);
        }
    } catch (error) {
        console.error('Error loading user activity:', error);
    }
}

// Display user activity
function displayUserActivity(activities) {
    const timeline = document.getElementById('activityTimeline');
    
    if (activities.length === 0) {
        timeline.innerHTML = '<div class="no-activity">No activity found for selected period</div>';
        return;
    }
    
    timeline.innerHTML = activities.map(activity => `
        <div class="activity-item">
            <div class="activity-icon ${activity.type}">
                <i class="fas ${getActivityIcon(activity.type)}"></i>
            </div>
            <div class="activity-content">
                <div class="activity-header">
                    <strong>${escapeHtml(activity.user_name)}</strong>
                    <span class="activity-time">${formatDateTime(activity.created_at)}</span>
                </div>
                <div class="activity-description">
                    ${escapeHtml(activity.description)}
                </div>
                ${activity.ip_address ? `<div class="activity-meta">IP: ${activity.ip_address}</div>` : ''}
            </div>
        </div>
    `).join('');
}

// Load active sessions
async function loadActiveSessions() {
    try {
        const response = await secureApiCall('/api/users.php?action=sessions');
        
        if (response.success) {
            displayActiveSessions(response.data);
        }
    } catch (error) {
        console.error('Error loading active sessions:', error);
    }
}

// Display active sessions
function displayActiveSessions(sessions) {
    const tbody = document.getElementById('sessionsTableBody');
    
    if (sessions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No active sessions</td></tr>';
        return;
    }
    
    tbody.innerHTML = sessions.map(session => `
        <tr>
            <td>${escapeHtml(session.user_name)}</td>
            <td>${escapeHtml(session.ip_address)}</td>
            <td>${escapeHtml(session.location || 'Unknown')}</td>
            <td>${escapeHtml(session.user_agent || 'Unknown')}</td>
            <td>${formatDateTime(session.started_at)}</td>
            <td>${formatDateTime(session.last_activity)}</td>
            <td>
                <button class="btn btn-sm btn-danger" onclick="terminateSession('${session.session_id}')" title="Terminate">
                    <i class="fas fa-power-off"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// Terminate session
async function terminateSession(sessionId) {
    if (!confirm('Are you sure you want to terminate this session?')) {
        return;
    }
    
    try {
        const response = await secureApiCall(`/api/users.php?action=terminate_session`, {
            method: 'POST',
            body: JSON.stringify({ session_id: sessionId })
        });
        
        if (response.success) {
            showSuccess('Session terminated successfully!');
            loadActiveSessions();
        } else {
            showError('Failed to terminate session: ' + response.message);
        }
    } catch (error) {
        showError('Error terminating session: ' + error.message);
    }
}

// Terminate all sessions
async function terminateAllSessions() {
    if (!confirm('Are you sure you want to terminate ALL active sessions? This will log out all users except yourself.')) {
        return;
    }
    
    try {
        const response = await secureApiCall(`/api/users.php?action=terminate_all_sessions`, {
            method: 'POST'
        });
        
        if (response.success) {
            showSuccess('All sessions terminated successfully!');
            loadActiveSessions();
            loadUserStats();
        } else {
            showError('Failed to terminate sessions: ' + response.message);
        }
    } catch (error) {
        showError('Error terminating sessions: ' + error.message);
    }
}

// ==================== PERFORMANCE ANALYTICS ====================

// Load performance data
async function loadPerformanceData() {
    try {
        showLoading('Loading performance data...');
        const year = document.getElementById('performanceYear').value;
        const response = await secureApiCall(`/api/users.php?action=performance&year=${year}`);
        
        if (response.success) {
            performanceData = response.data;
            displayPerformanceData();
            createPerformanceCharts();
        } else {
            showError('Failed to load performance data: ' + response.message);
        }
    } catch (error) {
        showError('Error loading performance data: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Display performance data
function displayPerformanceData() {
    if (!performanceData.length) {
        document.getElementById('performanceTableBody').innerHTML = 
            '<tr><td colspan="8" class="text-center">No performance data available</td></tr>';
        return;
    }

    // Update summary cards
    const topPerformer = performanceData.reduce((max, user) => 
        user.performance_score > max.performance_score ? user : max
    );
    
    document.getElementById('topPerformer').innerHTML = `
        <div class="performer-avatar"><i class="fas fa-trophy"></i></div>
        <div class="performer-info">
            <strong>${escapeHtml(topPerformer.full_name)}</strong>
            <span>Score: ${topPerformer.performance_score}%</span>
        </div>
    `;

    const avgScore = (performanceData.reduce((sum, user) => sum + parseFloat(user.performance_score), 0) / performanceData.length).toFixed(1);
    document.getElementById('avgPerformanceScore').textContent = avgScore + '%';

    // Calculate goals achievement
    const goalsAchieved = performanceData.filter(user => user.goal_achievement >= 100).length;
    const goalsPercent = ((goalsAchieved / performanceData.length) * 100).toFixed(0);
    document.getElementById('goalsProgress').style.width = goalsPercent + '%';
    document.getElementById('goalsText').textContent = `${goalsPercent}% of goals met`;

    // Display performance table
    const tbody = document.getElementById('performanceTableBody');
    tbody.innerHTML = performanceData.map(user => `
        <tr>
            <td>
                <div class="user-info">
                    <strong>${escapeHtml(user.full_name)}</strong>
                    <span class="role-badge ${user.role}">${capitalizeFirst(user.role)}</span>
                </div>
            </td>
            <td>$${formatCurrency(user.sales_total)}</td>
            <td>${user.transactions_count}</td>
            <td>${user.customers_served}</td>
            <td>
                <div class="score-badge ${getScoreClass(user.performance_score)}">
                    ${user.performance_score}%
                </div>
            </td>
            <td>${user.attendance_rate}%</td>
            <td>
                <div class="progress-bar small">
                    <div class="progress-fill" style="width: ${Math.min(user.goal_achievement, 100)}%"></div>
                </div>
                <span class="progress-text">${user.goal_achievement}%</span>
            </td>
            <td>
                <button class="btn btn-sm btn-outline" onclick="viewPerformanceDetails(${user.user_id})">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-sm btn-primary" onclick="openGoalsModal(${user.user_id})">
                    <i class="fas fa-target"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// Create performance charts
function createPerformanceCharts() {
    // Destroy existing charts
    Object.values(performanceCharts).forEach(chart => {
        if (chart) chart.destroy();
    });

    // Performance trends chart
    const trendsCtx = document.getElementById('performanceTrendsChart').getContext('2d');
    performanceCharts.trends = new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: performanceData.map(user => user.full_name),
            datasets: [{
                label: 'Performance Score',
                data: performanceData.map(user => user.performance_score),
                borderColor: '#4f46e5',
                backgroundColor: '#4f46e5',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, max: 100 }
            }
        }
    });

    // Sales performance chart
    const salesCtx = document.getElementById('salesPerformanceChart').getContext('2d');
    performanceCharts.sales = new Chart(salesCtx, {
        type: 'bar',
        data: {
            labels: performanceData.map(user => user.full_name),
            datasets: [{
                label: 'Sales Total',
                data: performanceData.map(user => user.sales_total),
                backgroundColor: '#10b981'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } }
        }
    });
}

// Goals modal functions
function openGoalsModal(userId = null) {
    populateEmployeeSelect('goalUserId');
    if (userId) {
        document.getElementById('goalUserId').value = userId;
    }
    document.getElementById('goalsModal').style.display = 'block';
}

function closeGoalsModal() {
    document.getElementById('goalsModal').style.display = 'none';
    document.getElementById('goalsForm').reset();
}

async function handleGoalsSubmit(e) {
    e.preventDefault();
    
    try {
        showLoading('Setting goals...');
        const formData = new FormData(e.target);
        const goals = Object.fromEntries(formData);
        
        const response = await secureApiCall('/api/users.php?action=set_goals', 'POST', goals);
        
        if (response.success) {
            showSuccess('Goals set successfully');
            closeGoalsModal();
            if (currentUserTab === 'performance') {
                loadPerformanceData();
            }
        } else {
            showError('Failed to set goals: ' + response.message);
        }
    } catch (error) {
        showError('Error setting goals: ' + error.message);
    } finally {
        hideLoading();
    }
}

// ==================== ATTENDANCE TRACKING ====================

// Load attendance data
async function loadAttendanceData() {
    try {
        showLoading('Loading attendance data...');
        const month = document.getElementById('attendanceMonth').value;
        const response = await secureApiCall(`/api/users.php?action=attendance&month=${month}`);
        
        if (response.success) {
            attendanceData = response.data;
            displayAttendanceData();
            createAttendanceCalendar();
        } else {
            showError('Failed to load attendance data: ' + response.message);
        }
    } catch (error) {
        showError('Error loading attendance data: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Display attendance data
function displayAttendanceData() {
    if (!attendanceData.length) {
        document.getElementById('attendanceTableBody').innerHTML = 
            '<tr><td colspan="8" class="text-center">No attendance data available</td></tr>';
        return;
    }

    // Calculate summary statistics
    const presentDays = attendanceData.filter(record => record.status === 'present').length;
    const absentDays = attendanceData.filter(record => record.status === 'absent').length;
    const totalHours = attendanceData.reduce((sum, record) => sum + parseFloat(record.hours_worked || 0), 0);
    const attendanceRate = ((presentDays / attendanceData.length) * 100).toFixed(1);

    // Update summary cards
    document.getElementById('presentDays').textContent = presentDays;
    document.getElementById('absentDays').textContent = absentDays;
    document.getElementById('totalHours').textContent = totalHours.toFixed(1);
    document.getElementById('attendanceRate').textContent = attendanceRate + '%';

    // Display attendance table
    const tbody = document.getElementById('attendanceTableBody');
    tbody.innerHTML = attendanceData.map(record => `
        <tr>
            <td>
                <div class="user-info">
                    <strong>${escapeHtml(record.full_name)}</strong>
                    <span class="employee-id">${escapeHtml(record.employee_id || '')}</span>
                </div>
            </td>
            <td>${formatDate(record.date)}</td>
            <td>${record.check_in || 'N/A'}</td>
            <td>${record.check_out || 'N/A'}</td>
            <td>${record.hours_worked || 0} hrs</td>
            <td>
                <span class="status-badge ${record.status}">
                    ${capitalizeFirst(record.status.replace('_', ' '))}
                </span>
            </td>
            <td>${escapeHtml(record.notes || '')}</td>
            <td>
                <button class="btn btn-sm btn-outline" onclick="editAttendance(${record.id})">
                    <i class="fas fa-edit"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// Create attendance calendar
function createAttendanceCalendar() {
    const calendarGrid = document.getElementById('attendanceCalendarGrid');
    const month = document.getElementById('attendanceMonth').value;
    const date = new Date(month + '-01');
    const year = date.getFullYear();
    const monthIndex = date.getMonth();
    
    // Get days in month
    const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();
    const firstDayOfWeek = new Date(year, monthIndex, 1).getDay();
    
    let calendarHTML = '<div class="calendar-header">';
    ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(day => {
        calendarHTML += `<div class="day-header">${day}</div>`;
    });
    calendarHTML += '</div><div class="calendar-days">';
    
    // Empty cells for days before month start
    for (let i = 0; i < firstDayOfWeek; i++) {
        calendarHTML += '<div class="calendar-day empty"></div>';
    }
    
    // Days of the month
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${(monthIndex + 1).toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
        const dayRecords = attendanceData.filter(record => record.date === dateStr);
        
        let dayClass = 'calendar-day';
        let dayContent = day;
        
        if (dayRecords.length > 0) {
            const statuses = dayRecords.map(r => r.status);
            if (statuses.includes('absent')) dayClass += ' absent';
            else if (statuses.includes('late')) dayClass += ' late';
            else if (statuses.includes('sick_leave')) dayClass += ' sick';
            else if (statuses.includes('holiday')) dayClass += ' holiday';
            else dayClass += ' present';
        }
        
        calendarHTML += `<div class="${dayClass}">${dayContent}</div>`;
    }
    
    calendarHTML += '</div>';
    calendarGrid.innerHTML = calendarHTML;
}

// Attendance modal functions
function openAttendanceModal() {
    populateEmployeeSelect('attendanceUserId');
    document.getElementById('attendanceModal').style.display = 'block';
}

function closeAttendanceModal() {
    document.getElementById('attendanceModal').style.display = 'none';
    document.getElementById('attendanceForm').reset();
}

async function handleAttendanceSubmit(e) {
    e.preventDefault();
    
    try {
        showLoading('Recording attendance...');
        const formData = new FormData(e.target);
        const attendance = Object.fromEntries(formData);
        
        const response = await secureApiCall('/api/users.php?action=record_attendance', 'POST', attendance);
        
        if (response.success) {
            showSuccess('Attendance recorded successfully');
            closeAttendanceModal();
            if (currentUserTab === 'attendance') {
                loadAttendanceData();
            }
        } else {
            showError('Failed to record attendance: ' + response.message);
        }
    } catch (error) {
        showError('Error recording attendance: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Export attendance report
async function exportAttendance() {
    try {
        const month = document.getElementById('attendanceMonth').value;
        const response = await secureApiCall(`/api/users.php?action=export_attendance&month=${month}`);
        
        if (response.success) {
            // Create and download CSV
            const csv = convertToCSV(response.data);
            downloadCSV(csv, `attendance-report-${month}.csv`);
        } else {
            showError('Failed to export attendance data');
        }
    } catch (error) {
        showError('Error exporting attendance data: ' + error.message);
    }
}

// ==================== EVALUATIONS ====================

// Load evaluations data
async function loadEvaluationsData() {
    try {
        showLoading('Loading evaluations...');
        const year = document.getElementById('evaluationYear').value;
        const response = await secureApiCall(`/api/users.php?action=evaluations&year=${year}`);
        
        if (response.success) {
            evaluationData = response.data;
            displayEvaluationsData();
            createSkillsRadarChart();
        } else {
            showError('Failed to load evaluations: ' + response.message);
        }
    } catch (error) {
        showError('Error loading evaluations: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Display evaluations data
function displayEvaluationsData() {
    if (!evaluationData.length) {
        document.getElementById('evaluationsTableBody').innerHTML = 
            '<tr><td colspan="9" class="text-center">No evaluations available</td></tr>';
        return;
    }

    // Calculate statistics
    const avgScore = (evaluationData.reduce((sum, evaluation) => sum + parseFloat(evaluation.overall_score), 0) / evaluationData.length).toFixed(1);
    const completed = evaluationData.length;
    const pending = Math.max(0, users.length - completed); // Estimate pending evaluations

    // Update statistics
    document.getElementById('avgEvaluationScore').textContent = avgScore;
    document.getElementById('completedEvaluations').textContent = completed;
    document.getElementById('pendingEvaluations').textContent = pending;

    // Display evaluations table
    const tbody = document.getElementById('evaluationsTableBody');
    tbody.innerHTML = evaluationData.map(evaluation => `
        <tr>
            <td>
                <div class="user-info">
                    <strong>${escapeHtml(evaluation.employee_name)}</strong>
                    <span class="employee-id">${escapeHtml(evaluation.employee_id || '')}</span>
                </div>
            </td>
            <td>${escapeHtml(evaluation.evaluator_name)}</td>
            <td>${formatDate(evaluation.evaluation_date)}</td>
            <td>
                <div class="score-badge ${getScoreClass(evaluation.overall_score)}">
                    ${evaluation.overall_score}
                </div>
            </td>
            <td>${evaluation.technical_skills || 'N/A'}</td>
            <td>${evaluation.communication_skills || 'N/A'}</td>
            <td>${evaluation.teamwork || 'N/A'}</td>
            <td>${evaluation.customer_service || 'N/A'}</td>
            <td>
                <button class="btn btn-sm btn-outline" onclick="viewEvaluation(${evaluation.id})">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-sm btn-primary" onclick="editEvaluation(${evaluation.id})">
                    <i class="fas fa-edit"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// Create skills radar chart
function createSkillsRadarChart() {
    if (performanceCharts.skillsRadar) {
        performanceCharts.skillsRadar.destroy();
    }

    if (!evaluationData.length) return;

    // Calculate average scores for each skill
    const skills = ['technical_skills', 'communication_skills', 'teamwork', 'punctuality', 'customer_service'];
    const avgScores = skills.map(skill => {
        const scores = evaluationData.map(evaluation => parseFloat(evaluation[skill] || 0)).filter(score => score > 0);
        return scores.length ? (scores.reduce((sum, score) => sum + score, 0) / scores.length) : 0;
    });

    const ctx = document.getElementById('skillsRadarChart').getContext('2d');
    performanceCharts.skillsRadar = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: ['Technical', 'Communication', 'Teamwork', 'Punctuality', 'Customer Service'],
            datasets: [{
                label: 'Team Average',
                data: avgScores,
                backgroundColor: 'rgba(79, 70, 229, 0.2)',
                borderColor: '#4f46e5',
                pointBackgroundColor: '#4f46e5'
            }]
        },
        options: {
            responsive: true,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
}

// Evaluation modal functions
function openEvaluationModal(userId = null) {
    populateEmployeeSelect('evaluationUserId');
    if (userId) {
        document.getElementById('evaluationUserId').value = userId;
    }
    document.getElementById('evaluationModal').style.display = 'block';
}

function closeEvaluationModal() {
    document.getElementById('evaluationModal').style.display = 'none';
    document.getElementById('evaluationForm').reset();
}

async function handleEvaluationSubmit(e) {
    e.preventDefault();
    
    try {
        showLoading('Saving evaluation...');
        const formData = new FormData(e.target);
        const evaluation = Object.fromEntries(formData);
        
        const response = await secureApiCall('/api/users.php?action=save_evaluation', 'POST', evaluation);
        
        if (response.success) {
            showSuccess('Evaluation saved successfully');
            closeEvaluationModal();
            if (currentUserTab === 'evaluations') {
                loadEvaluationsData();
            }
        } else {
            showError('Failed to save evaluation: ' + response.message);
        }
    } catch (error) {
        showError('Error saving evaluation: ' + error.message);
    } finally {
        hideLoading();
    }
}

// ==================== TRAINING & CERTIFICATIONS ====================

// Load training data
async function loadTrainingData() {
    try {
        showLoading('Loading training data...');
        const response = await secureApiCall('/api/users.php?action=training');
        
        if (response.success) {
            trainingData = response.data;
            displayTrainingData();
            createTrainingProgressChart();
        } else {
            showError('Failed to load training data: ' + response.message);
        }
    } catch (error) {
        showError('Error loading training data: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Display training data
function displayTrainingData() {
    if (!trainingData.length) {
        document.getElementById('trainingTableBody').innerHTML = 
            '<tr><td colspan="9" class="text-center">No training records available</td></tr>';
        return;
    }

    // Calculate statistics
    const total = trainingData.length;
    const active = trainingData.filter(t => t.status === 'completed' && (!t.expiry_date || new Date(t.expiry_date) > new Date())).length;
    const inProgress = trainingData.filter(t => t.status === 'in_progress' || t.status === 'enrolled').length;
    const totalCost = trainingData.reduce((sum, t) => sum + parseFloat(t.cost || 0), 0);

    // Update statistics
    document.getElementById('totalTrainings').textContent = total;
    document.getElementById('activeCertifications').textContent = active;
    document.getElementById('inProgressTrainings').textContent = inProgress;
    document.getElementById('trainingBudgetUsed').textContent = 'KSh ' + formatCurrency(totalCost);

    // Display training table
    const tbody = document.getElementById('trainingTableBody');
    tbody.innerHTML = trainingData.map(training => `
        <tr>
            <td>
                <div class="user-info">
                    <strong>${escapeHtml(training.employee_name)}</strong>
                    <span class="employee-id">${escapeHtml(training.employee_id || '')}</span>
                </div>
            </td>
            <td>${escapeHtml(training.training_name)}</td>
            <td>
                <span class="training-type-badge ${training.training_type}">
                    ${capitalizeFirst(training.training_type.replace('_', ' '))}
                </span>
            </td>
            <td>${escapeHtml(training.provider || 'N/A')}</td>
            <td>${formatDate(training.start_date)}</td>
            <td>
                <span class="status-badge ${training.status}">
                    ${capitalizeFirst(training.status.replace('_', ' '))}
                </span>
            </td>
            <td>${training.completion_date ? formatDate(training.completion_date) : 'N/A'}</td>
            <td>$${formatCurrency(training.cost || 0)}</td>
            <td>
                <button class="btn btn-sm btn-outline" onclick="viewTraining(${training.id})">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-sm btn-primary" onclick="editTraining(${training.id})">
                    <i class="fas fa-edit"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// Create training progress chart
function createTrainingProgressChart() {
    if (performanceCharts.trainingProgress) {
        performanceCharts.trainingProgress.destroy();
    }

    if (!trainingData.length) return;

    // Group by employee and count completed trainings
    const employeeTraining = {};
    trainingData.forEach(training => {
        if (!employeeTraining[training.employee_name]) {
            employeeTraining[training.employee_name] = { completed: 0, inProgress: 0 };
        }
        if (training.status === 'completed') {
            employeeTraining[training.employee_name].completed++;
        } else if (training.status === 'in_progress' || training.status === 'enrolled') {
            employeeTraining[training.employee_name].inProgress++;
        }
    });

    const ctx = document.getElementById('trainingProgressChart').getContext('2d');
    performanceCharts.trainingProgress = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: Object.keys(employeeTraining),
            datasets: [
                {
                    label: 'Completed',
                    data: Object.values(employeeTraining).map(emp => emp.completed),
                    backgroundColor: '#10b981'
                },
                {
                    label: 'In Progress',
                    data: Object.values(employeeTraining).map(emp => emp.inProgress),
                    backgroundColor: '#f59e0b'
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true }
            }
        }
    });
}

// Training modal functions
function openTrainingModal(userId = null) {
    populateEmployeeSelect('trainingUserId');
    if (userId) {
        document.getElementById('trainingUserId').value = userId;
    }
    document.getElementById('trainingModal').style.display = 'block';
}

function closeTrainingModal() {
    document.getElementById('trainingModal').style.display = 'none';
    document.getElementById('trainingForm').reset();
}

async function handleTrainingSubmit(e) {
    e.preventDefault();
    
    try {
        showLoading('Saving training record...');
        const formData = new FormData(e.target);
        const training = Object.fromEntries(formData);
        
        const response = await secureApiCall('/api/users.php?action=save_training', 'POST', training);
        
        if (response.success) {
            showSuccess('Training record saved successfully');
            closeTrainingModal();
            if (currentUserTab === 'training') {
                loadTrainingData();
            }
        } else {
            showError('Failed to save training record: ' + response.message);
        }
    } catch (error) {
        showError('Error saving training record: ' + error.message);
    } finally {
        hideLoading();
    }
}

// Check for expiring certifications
async function checkExpiringCertifications() {
    try {
        const response = await secureApiCall('/api/users.php?action=expiring_certifications');
        
        if (response.success && response.data.length > 0) {
            const expiringList = response.data.map(cert => 
                `• ${cert.employee_name}: ${cert.training_name} (expires ${formatDate(cert.expiry_date)})`
            ).join('\n');
            
            showWarning(`Certifications expiring soon:\n${expiringList}`);
        } else {
            showSuccess('No certifications expiring in the next 30 days');
        }
    } catch (error) {
        showError('Error checking expiring certifications: ' + error.message);
    }
}

// ==================== HELPER FUNCTIONS ====================

// Populate employee select dropdown
function populateEmployeeSelect(selectId) {
    const select = document.getElementById(selectId);
    select.innerHTML = '<option value="">Select Employee</option>';
    
    users.forEach(user => {
        select.innerHTML += `<option value="${user.id}">${escapeHtml(user.full_name)} (${user.username})</option>`;
    });
}

// Get score class for color coding
function getScoreClass(score) {
    const numScore = parseFloat(score);
    if (numScore >= 90) return 'excellent';
    if (numScore >= 80) return 'good';
    if (numScore >= 70) return 'fair';
    return 'poor';
}

// Format currency
function formatCurrency(amount) {
    return parseFloat(amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Convert data to CSV
function convertToCSV(data) {
    if (!data.length) return '';
    
    const headers = Object.keys(data[0]);
    const csvContent = [
        headers.join(','),
        ...data.map(row => headers.map(header => `"${row[header] || ''}"`).join(','))
    ].join('\n');
    
    return csvContent;
}

// Download CSV file
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

// View performance details
function viewPerformanceDetails(userId) {
    const user = users.find(u => u.id == userId);
    const performance = performanceData.find(p => p.user_id == userId);
    
    if (user && performance) {
        const details = `
            Performance Details for ${user.full_name}:
            
            Sales Total: $${formatCurrency(performance.sales_total)}
            Transactions: ${performance.transactions_count}
            Customers Served: ${performance.customers_served}
            Work Hours: ${performance.work_hours}
            Performance Score: ${performance.performance_score}%
            Attendance Rate: ${performance.attendance_rate}%
        `;
        
        alert(details);
    }
}

// View evaluation details
function viewEvaluation(evaluationId) {
    const evaluation = evaluationData.find(e => e.id == evaluationId);
    if (evaluation) {
        const details = `
            Evaluation Details:
            
            Employee: ${evaluation.employee_name}
            Evaluator: ${evaluation.evaluator_name}
            Date: ${formatDate(evaluation.evaluation_date)}
            
            Scores:
            Overall: ${evaluation.overall_score}
            Technical: ${evaluation.technical_skills || 'N/A'}
            Communication: ${evaluation.communication_skills || 'N/A'}
            Teamwork: ${evaluation.teamwork || 'N/A'}
            Punctuality: ${evaluation.punctuality || 'N/A'}
            Customer Service: ${evaluation.customer_service || 'N/A'}
            
            Comments: ${evaluation.comments || 'None'}
            Recommendations: ${evaluation.recommendations || 'None'}
        `;
        
        alert(details);
    }
}

// View training details
function viewTraining(trainingId) {
    const training = trainingData.find(t => t.id == trainingId);
    if (training) {
        const details = `
            Training Details:
            
            Employee: ${training.employee_name}
            Training: ${training.training_name}
            Type: ${capitalizeFirst(training.training_type.replace('_', ' '))}
            Provider: ${training.provider || 'N/A'}
            
            Dates:
            Start: ${formatDate(training.start_date)}
            End: ${formatDate(training.end_date)}
            Completion: ${formatDate(training.completion_date)}
            
            Status: ${capitalizeFirst(training.status.replace('_', ' '))}
            Cost: $${formatCurrency(training.cost)}
            Certificate: ${training.certificate_number || 'N/A'}
            
            Notes: ${training.notes || 'None'}
        `;
        
        alert(details);
    }
}

// Edit functions (simplified - would open modals with populated data)
function editAttendance(attendanceId) {
    showInfo('Edit attendance functionality would be implemented here');
}

function editEvaluation(evaluationId) {
    showInfo('Edit evaluation functionality would be implemented here');
}

function editTraining(trainingId) {
    showInfo('Edit training functionality would be implemented here');
}

// Close modals when clicking outside
window.onclick = function(event) {
    const userModal = document.getElementById('userModal');
    const roleModal = document.getElementById('rolePermissionsModal');
    const goalsModal = document.getElementById('goalsModal');
    const attendanceModal = document.getElementById('attendanceModal');
    const evaluationModal = document.getElementById('evaluationModal');
    const trainingModal = document.getElementById('trainingModal');
    
    if (event.target === userModal) {
        closeUserModal();
    }
    if (event.target === roleModal) {
        closeRolePermissionsModal();
    }
    if (event.target === goalsModal) {
        closeGoalsModal();
    }
    if (event.target === attendanceModal) {
        closeAttendanceModal();
    }
    if (event.target === evaluationModal) {
        closeEvaluationModal();
    }
    if (event.target === trainingModal) {
        closeTrainingModal();
    }
};
