// Simple Login Handler - Direct Redirect Version

class SimpleAuth {
    static async handleLogin(event) {
        event.preventDefault();
        
        console.log('🔐 Simple login handler started');
        
        const form = event.target;
        const formData = new FormData(form);
        const credentials = {
            username: formData.get('username')?.trim(),
            password: formData.get('password')
        };
        
        console.log('📝 Credentials:', { username: credentials.username });
        
        // Show loading
        const submitBtn = form.querySelector('.login-submit-btn') || form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Logging in...';
        }
        
        try {
            console.log('🌐 Making login request...');
            
            const response = await fetch('src/backend/api/login.php', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(credentials)
            });
            
            console.log('📡 Response received:', response.status);
            
            const data = await response.json();
            console.log('📊 Response data:', data);
            
            if (data.success) {
                console.log('✅ Login successful!');
                
                // Store user data
                localStorage.setItem('user', JSON.stringify(data.user));
                if (data.csrf_token) {
                    localStorage.setItem('csrf_token', data.csrf_token);
                }
                
                console.log('💾 User data stored');
                
                // Show success message
                this.showMessage('Login successful! Redirecting...', 'success');
                
                // ROLE-BASED REDIRECT - Route users to appropriate dashboards
                console.log('🔄 Role-based redirect for role:', data.user.role);
                
                // Determine redirect URL based on user role (using relative paths from current directory)
                let redirectUrl = 'dashboard_modern.html'; // Default for admin
                
                switch (data.user.role.toLowerCase()) {
                    case 'pharmacist':
                        redirectUrl = 'dashboard_pharmacist.html';
                        console.log('👨‍⚕️ Redirecting pharmacist to dedicated dashboard');
                        break;
                    case 'cashier':
                        redirectUrl = 'dashboard_cashier_modern.html';
                        console.log('🛒 Redirecting cashier to modern cashier dashboard');
                        break;
                    case 'admin':
                    default:
                        redirectUrl = 'dashboard_modern.html';
                        console.log('👑 Redirecting admin to modern dashboard');
                        break;
                }
                
                // Smart redirect with fallback paths
                console.log('🔄 Starting smart redirect process...');
                
                // Get current directory to build proper relative path
                const currentPath = window.location.pathname;
                const currentDir = currentPath.substring(0, currentPath.lastIndexOf('/'));
                console.log('📍 Current directory:', currentDir);
                
                // Build absolute URL for redirect
                const absoluteRedirectUrl = `${window.location.origin}${currentDir}/${redirectUrl}`;
                console.log('🌐 Absolute redirect URL:', absoluteRedirectUrl);
                
                // Method 1: Direct relative redirect
                setTimeout(() => {
                    console.log('🔄 Redirect attempt 1: Relative path to', redirectUrl);
                    window.location.href = redirectUrl;
                }, 500);
                
                // Method 2: Absolute URL redirect
                setTimeout(() => {
                    console.log('🔄 Redirect attempt 2: Absolute URL to', absoluteRedirectUrl);
                    window.location.href = absoluteRedirectUrl;
                }, 1500);
                
                // Method 3: Force replace with location
                setTimeout(() => {
                    console.log('🔄 Redirect attempt 3: Force replace');
                    window.location.replace(redirectUrl);
                }, 2500);
                
                // Method 4: Final emergency redirect to root
                setTimeout(() => {
                    console.log('🔄 Redirect attempt 4: Emergency fallback');
                    const rootUrl = window.location.origin + '/Phamarcy/src/frontend/' + redirectUrl;
                    console.log('� Emergency URL:', rootUrl);
                    window.location.href = rootUrl;
                }, 4000);
                
            } else {
                console.log('❌ Login failed:', data.message);
                this.showMessage(data.message || 'Login failed', 'error');
            }
            
        } catch (error) {
            console.log('💥 Login error:', error);
            this.showMessage('Login failed: ' + error.message, 'error');
        } finally {
            // Reset button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Login';
            }
        }
    }
    
    static showMessage(message, type = 'info') {
        console.log(`📢 Message: ${message} (${type})`);
        
        // Remove existing messages
        const existingMessages = document.querySelectorAll('.auth-message');
        existingMessages.forEach(msg => msg.remove());
        
        // Create new message
        const messageDiv = document.createElement('div');
        messageDiv.className = `auth-message ${type}`;
        messageDiv.textContent = message;
        messageDiv.style.cssText = `
            padding: 12px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid;
            font-weight: 500;
            position: relative;
            z-index: 1000;
            ${type === 'success' ? 
                'background-color: #d4edda; border-color: #c3e6cb; color: #155724;' :
                'background-color: #f8d7da; border-color: #f5c6cb; color: #721c24;'
            }
        `;
        
        // Find best place to show message
        const container = document.querySelector('.form-container') || 
                         document.querySelector('.login-form') || 
                         document.querySelector('form') || 
                         document.body;
        
        if (container.tagName === 'FORM') {
            container.parentNode.insertBefore(messageDiv, container);
        } else {
            container.insertBefore(messageDiv, container.firstChild);
        }
        
        // Auto-remove after delay
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }
    
    static init() {
        console.log('🚀 Simple Auth initialized');
        
        // Find login form
        const loginForm = document.getElementById('loginForm') || 
                         document.querySelector('form[id*="login"]') ||
                         document.querySelector('form');
        
        if (loginForm) {
            console.log('📋 Login form found:', loginForm.id || 'unnamed');
            
            // Remove existing listeners
            const newForm = loginForm.cloneNode(true);
            loginForm.parentNode.replaceChild(newForm, loginForm);
            
            // Add our listener
            newForm.addEventListener('submit', this.handleLogin.bind(this));
            console.log('👂 Event listener attached');
        } else {
            console.log('❌ No login form found');
        }
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => SimpleAuth.init());
} else {
    SimpleAuth.init();
}

// Also export for debugging
window.SimpleAuth = SimpleAuth;

console.log('🔧 Simple login handler loaded');
