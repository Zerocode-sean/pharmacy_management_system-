// Fixed Login System JavaScript

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
            messageDiv.remove();
        }, 5000);
    }
}

// Enhanced API Service Class
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
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response:', text);
                throw new Error(`Server returned non-JSON response. Status: ${response.status}`);
            }
            
            const data = await response.json();
            console.log('Response data:', data);
            
            if (!response.ok) {
                throw new Error(data.message || `HTTP ${response.status}: ${response.statusText}`);
            }
            
            return data;
        } catch (error) {
            console.error('API Request failed:', error);
            
            // Provide more specific error messages
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                throw new Error('Cannot connect to server. Please check if the API is running.');
            }
            
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
}

// Enhanced Authentication Handler
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
        
        const form = event.target;
        const submitBtn = form.querySelector('.login-submit-btn');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoader = submitBtn.querySelector('.btn-loader');
        const errorDiv = document.getElementById('loginError');
        
        // Show loading state
        submitBtn.disabled = true;
        if (btnText) btnText.style.display = 'none';
        if (btnLoader) btnLoader.style.display = 'inline';
        errorDiv.style.display = 'none';
        
        const formData = new FormData(form);
        const credentials = {
            username: formData.get('username')?.trim(),
            password: formData.get('password')
        };
        
        console.log('Login credentials:', { username: credentials.username, password: '[HIDDEN]' });
        
        // Basic validation
        if (!credentials.username || !credentials.password) {
            this.showLoginError('Please enter both username and password');
            this.resetLoginButton(submitBtn, btnText, btnLoader);
            return;
        }
        
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
                
                // Show success message briefly before redirect
                Utils.showMessage('Login successful! Redirecting...', 'success');
                
                setTimeout(() => {
                    window.location.href = 'dashboard_modern.html';
                }, 500);
                
            } else {
                console.log('Login failed:', response.message);
                this.showLoginError(response.message || 'Login failed');
                this.resetLoginButton(submitBtn, btnText, btnLoader);
            }
        } catch (error) {
            console.error('Login error:', error);
            this.showLoginError('Login failed: ' + error.message);
            this.resetLoginButton(submitBtn, btnText, btnLoader);
        }
    }
    
    static resetLoginButton(submitBtn, btnText, btnLoader) {
        submitBtn.disabled = false;
        if (btnText) btnText.style.display = 'inline';
        if (btnLoader) btnLoader.style.display = 'none';
    }
    
    static showLoginError(message) {
        const errorDiv = document.getElementById('loginError');
        if (errorDiv) {
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            
            // Auto-hide error after 10 seconds
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 10000);
        } else {
            // Fallback if error div not found
            Utils.showMessage(message, 'error');
        }
    }
    
    static checkAuthStatus() {
        const user = localStorage.getItem('user');
        const currentPage = window.location.pathname.split('/').pop();
        
        console.log('Checking auth status:', { user: !!user, currentPage });
        
        if (user && (currentPage === 'index.html' || currentPage === '')) {
            console.log('User already logged in, redirecting to dashboard');
            window.location.href = 'dashboard_modern.html';
        } else if (!user && currentPage !== 'index.html' && currentPage !== '' && !currentPage.includes('debug') && !currentPage.includes('test')) {
            console.log('User not logged in, redirecting to login');
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

// Initialize application when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing auth system');
    Auth.init();
    
    // Add event listeners for common elements
    const logoutButtons = document.querySelectorAll('.logout-btn');
    logoutButtons.forEach(btn => {
        btn.addEventListener('click', Auth.logout);
    });
});

// Export for debugging
if (typeof window !== 'undefined') {
    window.PharmacyApp = { Auth, APIService, Utils };
}
