/**
 * Role-based Navigation System
 * Controls what navigation items are visible based on user role
 */

class RoleNavigation {
    constructor() {
        this.userRole = null;
        this.init();
    }

    async init() {
        try {
            await this.getUserRole();
            this.applyRoleBasedNavigation();
            console.log(`Role-based navigation applied for: ${this.userRole}`);
        } catch (error) {
            console.error('Failed to initialize role navigation:', error);
            // Default to most restrictive role (cashier) if error
            this.userRole = 'cashier';
            this.applyRoleBasedNavigation();
        }
    }

    async getUserRole() {
        try {
            // First try to get from localStorage
            const userData = localStorage.getItem('user');
            if (userData) {
                const user = JSON.parse(userData);
                this.userRole = user.role;
                console.log('User role from localStorage:', this.userRole);
                return;
            }

            // Fallback to API call
            const response = await fetch('../backend/api/dashboard.php');
            const data = await response.json();
            
            if (data.success && data.user) {
                this.userRole = data.user.role;
                console.log('User role from API:', this.userRole);
                return;
            }
            
            // For testing purposes, let's check URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const testRole = urlParams.get('role');
            if (testRole) {
                this.userRole = testRole;
                console.log('User role from URL parameter (testing):', this.userRole);
                return;
            }
            
            // Default fallback
            this.userRole = 'admin'; // Changed default to admin for testing
            console.log('Using default role:', this.userRole);
        } catch (error) {
            console.error('Failed to get user role:', error);
            this.userRole = 'admin'; // Changed default to admin for testing
        }
    }

    applyRoleBasedNavigation() {
        // Role-based visibility rules
        const rolePermissions = {
            admin: {
                visible: ['dashboard', 'customers', 'suppliers', 'reports', 'users', 'settings'],
                hidden: ['medicines', 'pos'] // Admin cannot access medicines or POS
            },
            pharmacist: {
                visible: ['dashboard', 'medicines', 'pos', 'customers', 'suppliers', 'reports'],
                hidden: ['users', 'settings'] // Pharmacist cannot manage users or settings
            },
            cashier: {
                visible: ['dashboard', 'pos', 'customers'],
                hidden: ['medicines', 'suppliers', 'reports', 'users', 'settings']
            }
        };

        const permissions = rolePermissions[this.userRole] || rolePermissions.cashier;

        console.log(`Applying role navigation for: ${this.userRole}`, permissions);

        // Hide/show navigation sections
        this.hideInventorySection();
        this.hideSalesSection();
        this.hideCustomersSection();
        this.hideReportsSection();
        this.hideAdminSection();

        // Show sections based on role
        if (permissions.visible.includes('medicines') || permissions.visible.includes('suppliers')) {
            this.showInventorySection(permissions);
        }
        
        // Show sales section only for pharmacists and cashiers (who need POS access)
        if (permissions.visible.includes('pos')) {
            this.showSalesSection(permissions);
        }
        
        // Show dedicated customer section for admins
        if (this.userRole === 'admin' && permissions.visible.includes('customers')) {
            this.showCustomersSection();
        }
        
        if (permissions.visible.includes('reports')) {
            this.showReportsSection();
        }
        
        if (permissions.visible.includes('users') || permissions.visible.includes('settings')) {
            this.showAdminSection();
        }

        // Update customer links based on role
        this.updateCustomerLinks();

        // Update page title if needed
        this.updatePageTitle();
    }

    hideInventorySection() {
        const inventorySection = document.getElementById('inventorySection');
        if (inventorySection) {
            inventorySection.style.display = 'none';
        }
    }

    showInventorySection(permissions) {
        const inventorySection = document.getElementById('inventorySection');
        if (inventorySection) {
            inventorySection.style.display = 'block';
            
            // Hide medicines if not permitted
            if (!permissions.visible.includes('medicines')) {
                const medicinesLink = inventorySection.querySelector('a[href*="medicines"]');
                if (medicinesLink && medicinesLink.closest('li')) {
                    medicinesLink.closest('li').style.display = 'none';
                }
            }
        }
    }

    hideSalesSection() {
        const salesSection = document.getElementById('salesSection');
        if (salesSection) {
            salesSection.style.display = 'none';
        }
    }

    showSalesSection(permissions) {
        const salesSection = document.getElementById('salesSection');
        if (salesSection) {
            salesSection.style.display = 'block';
            
            // Hide POS if not permitted (especially for admin users)
            if (!permissions.visible.includes('pos')) {
                const posLinks = salesSection.querySelectorAll('a[href*="sales"]');
                posLinks.forEach(posLink => {
                    if (posLink && posLink.closest('li')) {
                        posLink.closest('li').style.display = 'none';
                        console.log('Hiding POS link for role:', this.userRole);
                    }
                });
            }
            
            // Show customers link for all roles that can access customers
            if (permissions.visible.includes('customers')) {
                const customerLinks = salesSection.querySelectorAll('a[href*="customers"]');
                customerLinks.forEach(customerLink => {
                    if (customerLink && customerLink.closest('li')) {
                        customerLink.closest('li').style.display = 'block';
                    }
                });
            }
        }
    }

    hideReportsSection() {
        const reportsSection = document.getElementById('reportsSection');
        if (reportsSection) {
            reportsSection.style.display = 'none';
        }
    }

    showReportsSection() {
        const reportsSection = document.getElementById('reportsSection');
        if (reportsSection) {
            reportsSection.style.display = 'block';
        }
    }

    hideAdminSection() {
        const adminSection = document.getElementById('adminSection');
        if (adminSection) {
            adminSection.style.display = 'none';
        }
    }

    showAdminSection() {
        const adminSection = document.getElementById('adminSection');
        if (adminSection) {
            adminSection.style.display = 'block';
        }
    }

    hideCustomersSection() {
        const customersSection = document.getElementById('customersSection');
        if (customersSection) {
            customersSection.style.display = 'none';
        }
    }

    showCustomersSection() {
        const customersSection = document.getElementById('customersSection');
        if (customersSection) {
            customersSection.style.display = 'block';
            console.log('Showing dedicated customers section for admin user');
        }
    }

    updateCustomerLinks() {
        const customerLinks = document.querySelectorAll('a[href*="customers"]');
        
        customerLinks.forEach(link => {
            const originalHref = link.getAttribute('href');
            
            if (this.userRole === 'cashier') {
                // Cashiers use the simplified customer interface
                if (originalHref.includes('customers_modern.html')) {
                    link.setAttribute('href', 'customers_cashier.html');
                } else if (originalHref.includes('customers.html') && !originalHref.includes('customers_cashier.html')) {
                    link.setAttribute('href', 'customers_cashier.html');
                }
            } else {
                // Admin and pharmacist use the full customer interface
                if (originalHref.includes('customers_cashier.html')) {
                    link.setAttribute('href', 'customers_modern.html');
                }
            }
        });
    }

    updatePageTitle() {
        const pageTitleElement = document.getElementById('pageTitle');
        const roleDisplay = {
            admin: 'Administrator',
            pharmacist: 'Pharmacist',
            cashier: 'Cashier'
        };

        if (pageTitleElement && this.userRole) {
            const currentTitle = pageTitleElement.textContent;
            if (!currentTitle.includes('(')) {
                pageTitleElement.textContent = `${currentTitle} (${roleDisplay[this.userRole]})`;
            }
        }
    }

    // Method to get current user role (for use by other scripts)
    getUserRole() {
        return this.userRole;
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Add a small delay to ensure all other scripts have loaded
    setTimeout(() => {
        window.roleNavigation = new RoleNavigation();
    }, 100);
});

// Also try to initialize on window load as a fallback
window.addEventListener('load', () => {
    if (!window.roleNavigation) {
        setTimeout(() => {
            window.roleNavigation = new RoleNavigation();
        }, 200);
    }
});

// Manual testing functions (can be called from browser console)
window.testAdminRole = function() {
    localStorage.setItem('user', JSON.stringify({
        role: 'admin',
        username: 'test_admin',
        full_name: 'Test Administrator'
    }));
    location.reload();
};

window.testPharmacistRole = function() {
    localStorage.setItem('user', JSON.stringify({
        role: 'pharmacist',
        username: 'test_pharmacist',
        full_name: 'Test Pharmacist'
    }));
    location.reload();
};

window.testCashierRole = function() {
    localStorage.setItem('user', JSON.stringify({
        role: 'cashier',
        username: 'test_cashier',
        full_name: 'Test Cashier'
    }));
    location.reload();
};

window.debugRoleNavigation = function() {
    if (window.roleNavigation) {
        console.log('Current role:', window.roleNavigation.userRole);
        console.log('Role navigation object:', window.roleNavigation);
        window.roleNavigation.applyRoleBasedNavigation();
    } else {
        console.log('Role navigation not initialized yet');
    }
};

// Export for use by other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = RoleNavigation;
}
