// Lightweight Session Validator for Non-Dashboard Pages
// This script provides minimal session validation without aggressive redirects

class PageSessionValidator {
    constructor() {
        this.currentUser = null;
        this.isInitialized = false;
        this.init();
    }

    async init() {
        console.log('[PageValidator] Initializing page session validator...');
        
        // Wait for main session system to be ready
        await this.waitForSessionSystem();
        
        try {
            // Check session status
            await this.validateSession();
            
            // Update UI with user info
            this.updateUserInterface();
            
            // Apply role-based navigation restrictions
            this.applyRoleBasedNavigation();
            
            this.isInitialized = true;
            console.log('[PageValidator] Page session validation complete');
            
        } catch (error) {
            console.error('[PageValidator] Session validation failed:', error);
            // Only redirect if we're certain the session is invalid
            this.handleSessionError(error);
        }
    }

    async waitForSessionSystem() {
        return new Promise((resolve) => {
            const checkSystem = () => {
                if (typeof window.PharmacyApp !== 'undefined' && window.PharmacyApp.APIService) {
                    console.log('[PageValidator] Session system ready');
                    resolve();
                } else {
                    console.log('[PageValidator] Waiting for session system...');
                    setTimeout(checkSystem, 100);
                }
            };
            checkSystem();
        });
    }

    async validateSession() {
        console.log('[PageValidator] Validating session...');
        
        // First try to get user from localStorage
        const userDataStr = localStorage.getItem('user');
        if (!userDataStr) {
            throw new Error('No user data in localStorage');
        }

        try {
            this.currentUser = JSON.parse(userDataStr);
            console.log('[PageValidator] User loaded from localStorage:', this.currentUser);
        } catch (parseError) {
            console.error('[PageValidator] Failed to parse user data:', parseError);
            throw new Error('Invalid user data in localStorage');
        }

        // Try to verify session with server (non-blocking)
        try {
            console.log('[PageValidator] Checking server session...');
            const sessionCheck = await window.PharmacyApp.APIService.checkSession();
            
            if (sessionCheck.logged_in && sessionCheck.session_valid) {
                console.log('[PageValidator] Server session valid');
                
                // Update user data if available from server
                if (sessionCheck.user_info) {
                    this.currentUser.id = sessionCheck.user_info.user_id || this.currentUser.id;
                    this.currentUser.username = sessionCheck.user_info.username || this.currentUser.username;
                    this.currentUser.role = sessionCheck.user_info.role || this.currentUser.role;
                }
            } else {
                console.warn('[PageValidator] Server session invalid, but continuing with localStorage data');
                // Don't throw error - allow page to work with localStorage data
            }
        } catch (apiError) {
            console.warn('[PageValidator] Session API check failed:', apiError.message);
            // Don't throw error - allow page to work with localStorage data
        }
    }

    updateUserInterface() {
        if (!this.currentUser) return;

        console.log('[PageValidator] Updating user interface');
        
        // Update header user info if elements exist
        const userNameEl = document.getElementById('headerUserName');
        const userRoleEl = document.getElementById('headerUserRole'); 
        const userAvatarEl = document.getElementById('headerUserAvatar');

        if (userNameEl) {
            userNameEl.textContent = this.currentUser.full_name || this.currentUser.username;
        }

        if (userRoleEl) {
            const roleDisplay = this.currentUser.role.charAt(0).toUpperCase() + this.currentUser.role.slice(1);
            userRoleEl.textContent = roleDisplay;
        }

        if (userAvatarEl) {
            const initials = (this.currentUser.full_name || this.currentUser.username).charAt(0).toUpperCase();
            userAvatarEl.textContent = initials;
        }

        console.log('[PageValidator] User interface updated');
    }

    applyRoleBasedNavigation() {
        if (!this.currentUser) return;

        console.log('[PageValidator] Applying role-based navigation for role:', this.currentUser.role);

        const userRole = this.currentUser.role;
        
        // Role permissions - same as dashboard.js but for navigation only
        const permissions = {
            admin: {
                canManageSuppliers: true,
                canManageCustomers: true,
                canViewReports: true,
                canManageUsers: true,
                canAccessSettings: true
            },
            pharmacist: {
                canManageSuppliers: false,
                canManageCustomers: true,
                canViewReports: true,
                canManageUsers: false,
                canAccessSettings: false
            },
            cashier: {
                canManageMedicines: true,  // ✅ Cashiers can access medicine inventory
                canManageSuppliers: true,  // ✅ Cashiers can access suppliers
                canManageCustomers: true,
                canViewReports: false,
                canManageUsers: false,
                canAccessSettings: false
            }
        };

        const userPermissions = permissions[userRole] || {};

        // Hide/show navigation sections based on permissions
        this.toggleNavItem('reportsSection', userPermissions.canViewReports);
        this.toggleNavItem('adminSection', userPermissions.canManageUsers || userPermissions.canAccessSettings);

        console.log('[PageValidator] Navigation permissions applied');
    }

    toggleNavItem(elementId, show) {
        const element = document.getElementById(elementId);
        if (element) {
            element.style.display = show ? 'block' : 'none';
            console.log(`[PageValidator] ${elementId} ${show ? 'shown' : 'hidden'}`);
        }
    }

    handleSessionError(error) {
        console.error('[PageValidator] Handling session error:', error.message);
        
        // Only redirect in severe cases
        if (error.message.includes('No user data') || 
            error.message.includes('Invalid user data')) {
            
            console.log('[PageValidator] Critical session error, redirecting to login');
            window.location.href = 'index.html';
        } else {
            console.warn('[PageValidator] Non-critical session error, continuing with limited functionality');
            // Could show a warning message to user instead of redirecting
        }
    }

    // Utility method for other scripts to check if user has permission
    hasPermission(permission) {
        if (!this.currentUser) return false;
        
        const permissions = {
            admin: {
                manage_suppliers: true,
                manage_customers: true,
                view_reports: true,
                manage_users: true,
                access_settings: true
            },
            pharmacist: {
                manage_suppliers: false,
                manage_customers: true,
                view_reports: true,
                manage_users: false,
                access_settings: false
            },
            cashier: {
                manage_suppliers: true,  // ✅ Cashiers can manage suppliers
                manage_customers: true,
                view_reports: false,
                manage_users: false,
                access_settings: false
            }
        };

        const userPermissions = permissions[this.currentUser.role] || {};
        return userPermissions[permission] === true;
    }

    getCurrentUser() {
        return this.currentUser;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('[PageValidator] DOM loaded, initializing page session validator');
    
    // Initialize with a small delay to ensure main session system is ready
    setTimeout(() => {
        window.pageSessionValidator = new PageSessionValidator();
    }, 500);
});

// Export for debugging
if (typeof window !== 'undefined') {
    window.PageSessionValidator = PageSessionValidator;
}
