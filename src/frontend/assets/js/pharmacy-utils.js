/**
 * Kenyan Shilling (KES) Currency Formatter and Dashboard Utilities
 */

class PharmacyUtils {
    /**
     * Format amount in Kenyan Shillings (KES)
     * @param {number} amount - The amount to format
     * @param {boolean} showSymbol - Whether to show KES symbol (default: true)
     * @returns {string} Formatted KES amount
     */
    static formatKES(amount, showSymbol = true) {
        if (typeof amount !== 'number' || isNaN(amount)) {
            return showSymbol ? 'KES 0.00' : '0.00';
        }

        const formatted = new Intl.NumberFormat('en-KE', {
            style: 'decimal',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(Math.abs(amount));

        return showSymbol ? `KES ${formatted}` : formatted;
    }

    /**
     * Format large numbers with K, M suffixes
     * @param {number} num - Number to format
     * @returns {string} Formatted number
     */
    static formatLargeNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        }
        if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toString();
    }

    /**
     * Format date for display
     * @param {string|Date} date - Date to format
     * @returns {string} Formatted date
     */
    static formatDate(date) {
        const d = new Date(date);
        return d.toLocaleDateString('en-KE', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    /**
     * Format relative time (e.g., "2 minutes ago")
     * @param {string|Date} date - Date to calculate from
     * @returns {string} Relative time string
     */
    static formatTimeAgo(date) {
        const now = new Date();
        const past = new Date(date);
        const diffMs = now - past;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
        if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
        
        return this.formatDate(date);
    }

    /**
     * Animate counter to target value
     * @param {HTMLElement} element - Element to animate
     * @param {number} target - Target value
     * @param {number} duration - Animation duration in ms
     * @param {boolean} isCurrency - Whether to format as currency
     */
    static animateCounter(element, target, duration = 2000, isCurrency = false) {
        const start = parseInt(element.textContent.replace(/[^\d]/g, '')) || 0;
        const increment = (target - start) / (duration / 16);
        let current = start;

        const timer = setInterval(() => {
            current += increment;
            if ((increment > 0 && current >= target) || (increment < 0 && current <= target)) {
                current = target;
                clearInterval(timer);
            }
            
            if (isCurrency) {
                element.textContent = PharmacyUtils.formatKES(Math.floor(current));
            } else {
                element.textContent = Math.floor(current).toLocaleString();
            }
        }, 16);
    }

    /**
     * Show success message
     * @param {string} message - Message to show
     */
    static showSuccess(message) {
        this.showNotification(message, 'success');
    }

    /**
     * Show error message
     * @param {string} message - Message to show
     */
    static showError(message) {
        this.showNotification(message, 'error');
    }

    /**
     * Show notification
     * @param {string} message - Message to show
     * @param {string} type - Type (success, error, info, warning)
     */
    static showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${this.getNotificationIcon(type)}"></i>
                <span>${message}</span>
            </div>
            <button class="notification-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;

        // Add to page
        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    /**
     * Get notification icon based on type
     * @param {string} type - Notification type
     * @returns {string} Icon class
     */
    static getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    /**
     * Format stock status
     * @param {number} quantity - Stock quantity
     * @returns {object} Status object with class and text
     */
    static getStockStatus(quantity) {
        if (quantity <= 0) {
            return { class: 'out-of-stock', text: 'Out of Stock' };
        } else if (quantity <= 10) {
            return { class: 'low-stock', text: 'Low Stock' };
        } else if (quantity <= 50) {
            return { class: 'moderate-stock', text: 'Moderate Stock' };
        } else {
            return { class: 'good-stock', text: 'Good Stock' };
        }
    }

    /**
     * Validate KES amount for M-Pesa
     * @param {number} amount - Amount to validate
     * @returns {object} Validation result
     */
    static validateKESAmount(amount) {
        if (typeof amount !== 'number' || isNaN(amount)) {
            return { valid: false, message: 'Invalid amount' };
        }
        
        if (amount < 1) {
            return { valid: false, message: 'Amount must be at least KES 1' };
        }
        
        if (amount > 70000) {
            return { valid: false, message: 'Amount cannot exceed KES 70,000 (M-Pesa limit)' };
        }
        
        return { valid: true, message: 'Valid amount' };
    }

    /**
     * Format phone number for M-Pesa
     * @param {string} phone - Phone number to format
     * @returns {string} Formatted phone number
     */
    static formatPhoneNumber(phone) {
        // Remove all non-digits
        phone = phone.replace(/\D/g, '');
        
        // Handle Kenyan numbers
        if (phone.startsWith('0')) {
            phone = '254' + phone.substring(1);
        } else if (!phone.startsWith('254')) {
            phone = '254' + phone;
        }
        
        return phone;
    }
}

// Add CSS for notifications
const notificationCSS = `
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        border-radius: 8px;
        padding: 16px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        justify-content: space-between;
        max-width: 400px;
        z-index: 10000;
        margin-bottom: 10px;
        animation: slideInRight 0.3s ease-out;
    }

    .notification-success {
        border-left: 4px solid #10b981;
        color: #065f46;
    }

    .notification-error {
        border-left: 4px solid #ef4444;
        color: #991b1b;
    }

    .notification-warning {
        border-left: 4px solid #f59e0b;
        color: #92400e;
    }

    .notification-info {
        border-left: 4px solid #3b82f6;
        color: #1e40af;
    }

    .notification-content {
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 1;
    }

    .notification-close {
        background: none;
        border: none;
        cursor: pointer;
        color: inherit;
        opacity: 0.7;
        padding: 4px;
    }

    .notification-close:hover {
        opacity: 1;
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;

// Inject CSS if not already present
if (!document.getElementById('pharmacy-utils-css')) {
    const style = document.createElement('style');
    style.id = 'pharmacy-utils-css';
    style.textContent = notificationCSS;
    document.head.appendChild(style);
}

// Export for use in other scripts
window.PharmacyUtils = PharmacyUtils;
