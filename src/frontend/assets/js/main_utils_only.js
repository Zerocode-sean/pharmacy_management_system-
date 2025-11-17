// Utility Functions and API Service Only (No Auth Auto-Init)

// API Configuration - Use full URL to XAMPP server
const API_BASE_URL = 'http://localhost/Phamarcy/src/backend/api/';

// Utility Functions
class Utils {
    static showMessage(message, type = 'success') {
        const messageDiv = document.createElement('div');
        messageDiv.className = type === 'success' ? 'success-message' : 'error-message';
        messageDiv.textContent = message;
        messageDiv.style.cssText = `
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            border: 1px solid;
            position: relative;
            z-index: 1000;
            ${type === 'success' ? 
                'background-color: #d4edda; border-color: #c3e6cb; color: #155724;' :
                'background-color: #f8d7da; border-color: #f5c6cb; color: #721c24;'
            }
        `;
        
        // Find container to append message
        const container = document.querySelector('.form-container') || 
                         document.querySelector('.main-content') || 
                         document.body;
        
        container.insertBefore(messageDiv, container.firstChild);
        
        // Remove message after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }

    static log(message, type = 'info') {
        const timestamp = new Date().toISOString();
        console.log(`[${timestamp}] [${type.toUpperCase()}] ${message}`);
    }
}

// Enhanced API Service Class with proper session handling
class APIService {
    static csrfToken = localStorage.getItem('csrf_token') || '';
    
    static async request(endpoint, options = {}) {
        const url = API_BASE_URL + endpoint;
        const defaultOptions = {
            method: 'GET',
            credentials: 'include', // Critical for session cookies!
            headers: {
                'Content-Type': 'application/json',
            }
        };
        
        // Add CSRF token for write operations
        if (options.method && options.method !== 'GET' && this.csrfToken) {
            defaultOptions.headers['X-CSRF-Token'] = this.csrfToken;
        }
        
        const finalOptions = { ...defaultOptions, ...options };
        
        Utils.log(`Making ${finalOptions.method} request to: ${url}`, 'debug');
        
        try {
            const response = await fetch(url, finalOptions);
            const responseText = await response.text();
            
            Utils.log(`Response status: ${response.status} ${response.statusText}`, 'debug');
            
            let data;
            try {
                data = JSON.parse(responseText);
                Utils.log(`Response data: ${JSON.stringify(data)}`, 'debug');
            } catch (parseError) {
                Utils.log(`Failed to parse JSON response: ${responseText}`, 'error');
                throw new Error(`Invalid JSON response: ${responseText.substring(0, 100)}...`);
            }
            
            if (!response.ok) {
                throw new Error(data.message || `HTTP ${response.status}: ${response.statusText}`);
            }
            
            return data;
        } catch (error) {
            Utils.log(`API request failed: ${error.message}`, 'error');
            throw error;
        }
    }
    
    static async checkSession() {
        return await this.request('session_status.php');
    }
}

// Export for debugging
if (typeof window !== 'undefined') {
    window.PharmacyApp = { APIService, Utils };
}
