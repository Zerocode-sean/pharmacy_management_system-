/**
 * Customer Navigation Helper
 * Updates dashboard customer links based on user role
 */

class CustomerNavigation {
    constructor() {
        this.init();
    }

    async init() {
        try {
            // Get user role from session
            const userRole = await this.getUserRole();
            this.updateCustomerLinks(userRole);
        } catch (error) {
            console.error('Failed to initialize customer navigation:', error);
        }
    }

    async getUserRole() {
        try {
            const response = await fetch('../backend/api/dashboard.php');
            const data = await response.json();
            
            if (data.success && data.user) {
                return data.user.role;
            }
            return 'cashier'; // Default to cashier
        } catch (error) {
            console.error('Failed to get user role:', error);
            return 'cashier'; // Default to cashier
        }
    }

    updateCustomerLinks(userRole) {
        const customerLinks = document.querySelectorAll('a[href*="customers"]');
        
        customerLinks.forEach(link => {
            const originalHref = link.getAttribute('href');
            
            if (userRole === 'cashier') {
                // Use cashier-specific customer page
                if (originalHref.includes('customers_modern.html')) {
                    link.setAttribute('href', 'customers_cashier.html');
                } else if (originalHref.includes('customers.html') && !originalHref.includes('customers_cashier.html')) {
                    link.setAttribute('href', 'customers_cashier.html');
                }
            } else {
                // Use admin/pharmacist customer page
                if (originalHref.includes('customers_cashier.html')) {
                    link.setAttribute('href', 'customers_modern.html');
                }
            }
        });

        console.log(`Updated customer navigation for role: ${userRole}`, customerLinks.length, 'links updated');
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new CustomerNavigation();
});
