// Session-Fixed Login System JavaScript

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
    
    static async login(credentials) {
        const response = await this.request('login.php', {
            method: 'POST',
            body: JSON.stringify(credentials)
        });
        
        // Update CSRF token if provided
        if (response.csrf_token) {
            this.csrfToken = response.csrf_token;
        }
        
        return response;
    }
    
    static async checkSession() {
        return this.request('session_status.php');
    }
    
    static async getDashboardStats() {
        return this.request('dashboard.php?action=stats');
    }
    
    static async logout() {
        return this.request('logout.php', {
            method: 'POST'
        });
    }
}

// Enhanced Authentication Handler with session management
class Auth {
    static isInitialized = false;
    static checkingAuth = false;
    
    static init() {
        if (this.isInitialized) {
            Utils.log('Auth already initialized, skipping', 'debug');
            return;
        }
        
        Utils.log('Auth.init() called', 'debug');
        this.isInitialized = true;
        
        const loginForm = document.getElementById('loginForm');
        Utils.log(`Login form element: ${loginForm ? 'found' : 'not found'}`, 'debug');
        
        if (loginForm) {
            Utils.log('Attaching login form event listener', 'debug');
            loginForm.addEventListener('submit', this.handleLogin.bind(this));
        }
        
        // Check authentication status
        this.checkAuthStatus();
    }
    
    static async handleLogin(event) {
        event.preventDefault();
        
        Utils.log('Login form submitted', 'debug');
        
        const form = event.target;
        const submitBtn = form.querySelector('.login-submit-btn');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoader = submitBtn.querySelector('.btn-loader');
        const errorDiv = document.getElementById('loginError');
        
        // Show loading state
        this.setLoginButtonState(submitBtn, btnText, btnLoader, true);
        if (errorDiv) errorDiv.style.display = 'none';
        
        const formData = new FormData(form);
        const credentials = {
            username: formData.get('username')?.trim(),
            password: formData.get('password')
        };
        
        Utils.log(`Login credentials: username=${credentials.username}`, 'debug');
        
        // Basic validation
        if (!credentials.username || !credentials.password) {
            this.showLoginError('Please enter both username and password');
            this.setLoginButtonState(submitBtn, btnText, btnLoader, false);
            return;
        }
        
        try {
            Utils.log('Attempting API login...', 'debug');
            const response = await APIService.login(credentials);
            Utils.log(`Login response: ${JSON.stringify(response)}`, 'debug');
            
            if (response.success) {
                Utils.log('Login successful, storing user data', 'debug');
                
                // Store user data
                localStorage.setItem('user', JSON.stringify(response.user));
                
                if (response.csrf_token) {
                    localStorage.setItem('csrf_token', response.csrf_token);
                    APIService.csrfToken = response.csrf_token;
                }
                
                // Show success message
                Utils.showMessage('Login successful! Redirecting...', 'success');
                
                // IMMEDIATE REDIRECT - Don't wait for session verification
                Utils.log('Redirecting to dashboard after login', 'debug');
                
                // Role-based redirect
                let redirectUrl = 'dashboard_modern.html'; // Default for admin
                
                switch (response.user.role.toLowerCase()) {
                    case 'pharmacist':
                        redirectUrl = 'pharmacist_dashboard_clean.html';
                        Utils.log('👨‍⚕️ Redirecting pharmacist to clean dedicated dashboard', 'debug');
                        break;
                    case 'cashier':
                        redirectUrl = 'dashboard_cashier_modern.html';
                        Utils.log('🛒 Redirecting cashier to modern cashier dashboard', 'debug');
                        break;
                    case 'admin':
                    default:
                        // Redirect admins to the new admin interface
                        redirectUrl = 'admin/index.html';
                        Utils.log('👑 Redirecting admin to Admin interface', 'debug');
                        break;
                }
                
                Utils.log(`Final redirect URL: ${redirectUrl}`, 'debug');
                
                // Redirect immediately - no session check, no delay
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 500); // Short delay just to show success message
                
            } else {
                Utils.log(`Login failed: ${response.message}`, 'error');
                this.showLoginError(response.message || 'Login failed');
                this.setLoginButtonState(submitBtn, btnText, btnLoader, false);
            }
        } catch (error) {
            Utils.log(`Login error: ${error.message}`, 'error');
            this.showLoginError('Login failed: ' + error.message);
            this.setLoginButtonState(submitBtn, btnText, btnLoader, false);
        }
    }
    
    static setLoginButtonState(submitBtn, btnText, btnLoader, loading) {
        if (submitBtn) {
            submitBtn.disabled = loading;
        }
        if (btnText) {
            btnText.style.display = loading ? 'none' : 'inline';
        }
        if (btnLoader) {
            btnLoader.style.display = loading ? 'inline' : 'none';
        }
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
    
    static async checkAuthStatus() {
        if (this.checkingAuth) {
            Utils.log('Auth check already in progress, skipping', 'debug');
            return;
        }
        
        this.checkingAuth = true;
        
        try {
            const user = localStorage.getItem('user');
            const currentPage = window.location.pathname.split('/').pop();
            
            Utils.log(`Checking auth status: user=${!!user}, page=${currentPage}`, 'debug');
            
            // Skip auth check for debug and test pages
            if (currentPage.includes('debug') || currentPage.includes('test')) {
                Utils.log('Skipping auth check for debug/test page', 'debug');
                return;
            }
            
            // If on login page and user exists, check if session is still valid
            if (user && (currentPage === 'index.html' || currentPage === '')) {
                try {
                    const sessionCheck = await APIService.checkSession();
                    if (sessionCheck.logged_in && sessionCheck.session_valid) {
                        Utils.log('User already logged in with valid session, redirecting to dashboard', 'debug');
                        
                        // Role-based redirect for existing sessions
                        const userData = JSON.parse(user);
                        let redirectUrl = 'dashboard_modern.html'; // Default
                        
                        if (userData.role) {
                            switch (userData.role.toLowerCase()) {
                                case 'pharmacist':
                                    redirectUrl = 'pharmacist_dashboard_clean.html';
                                    Utils.log('👨‍⚕️ Redirecting pharmacist to clean dedicated dashboard', 'debug');
                                    break;
                                case 'cashier':
                                    redirectUrl = 'dashboard_cashier_modern.html';
                                    Utils.log('🛒 Redirecting cashier to modern cashier dashboard', 'debug');
                                    break;
                                case 'admin':
                                default:
                                    redirectUrl = 'dashboard_modern.html';
                                    Utils.log('👑 Redirecting admin to modern dashboard', 'debug');
                                    break;
                            }
                        }
                        
                        window.location.href = redirectUrl;
                        return;
                    } else {
                        Utils.log('User data exists but session invalid, clearing local storage', 'debug');
                        this.clearUserData();
                    }
                } catch (error) {
                    Utils.log(`Session check failed: ${error.message}`, 'error');
                    this.clearUserData();
                }
            }
            
            // If not on login page and no user or invalid session, redirect to login
            if (currentPage !== 'index.html' && currentPage !== '') {
                if (!user) {
                    Utils.log('No user data found, redirecting to login', 'debug');
                    window.location.href = 'index.html';
                    return;
                }
                
                try {
                    const sessionCheck = await APIService.checkSession();
                    if (!sessionCheck.logged_in || !sessionCheck.session_valid) {
                        Utils.log('Session invalid, redirecting to login', 'debug');
                        this.clearUserData();
                        window.location.href = 'index.html';
                        return;
                    } else {
                        Utils.log('Session valid, user can stay on current page', 'debug');
                    }
                } catch (error) {
                    Utils.log(`Session check failed: ${error.message}, redirecting to login`, 'error');
                    this.clearUserData();
                    window.location.href = 'index.html';
                    return;
                }
            }
        } catch (error) {
            Utils.log(`Auth status check error: ${error.message}`, 'error');
        } finally {
            this.checkingAuth = false;
        }
    }
    
    static clearUserData() {
        localStorage.removeItem('user');
        localStorage.removeItem('csrf_token');
        APIService.csrfToken = '';
    }
    
    static async logout() {
        Utils.log('Logout initiated', 'debug');
        
        try {
            await APIService.logout();
            Utils.log('Logout API call successful', 'debug');
        } catch (error) {
            Utils.log(`Logout request failed: ${error.message}`, 'error');
        } finally {
            this.clearUserData();
            Utils.log('User data cleared, redirecting to login', 'debug');
            window.location.href = 'index.html';
        }
    }
    
    static getUser() {
        const user = localStorage.getItem('user');
        return user ? JSON.parse(user) : null;
    }
    
    // Periodic session validation
    static startSessionMonitoring() {
        setInterval(async () => {
            try {
                const currentPage = window.location.pathname.split('/').pop();
                if (currentPage === 'index.html' || currentPage === '' || 
                    currentPage.includes('debug') || currentPage.includes('test')) {
                    return;
                }
                
                const user = localStorage.getItem('user');
                if (!user) return;
                
                const sessionCheck = await APIService.checkSession();
                if (!sessionCheck.logged_in || !sessionCheck.session_valid) {
                    Utils.log('Session expired during monitoring, redirecting to login', 'warning');
                    this.clearUserData();
                    window.location.href = 'index.html';
                }
            } catch (error) {
                Utils.log(`Session monitoring error: ${error.message}`, 'error');
            }
        }, 30000); // Check every 30 seconds
    }
}

// Export classes immediately so they're available to other scripts
// This must be BEFORE DOMContentLoaded to avoid race conditions
if (typeof window !== 'undefined') {
    window.PharmacyApp = { Auth, APIService, Utils };
    console.log('PharmacyApp exported:', window.PharmacyApp);
}

// Initialize application when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    Utils.log('DOM loaded, initializing session-fixed auth system', 'debug');
    Auth.init();
    
    // Start session monitoring after a delay
    setTimeout(() => {
        Auth.startSessionMonitoring();
    }, 5000);
    
    // Add event listeners for common elements
    const logoutButtons = document.querySelectorAll('.logout-btn');
    logoutButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            Auth.logout();
        });
    });
});
