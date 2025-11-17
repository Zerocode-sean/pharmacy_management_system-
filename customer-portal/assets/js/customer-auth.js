// Customer Authentication Handler
class CustomerAuth {
    constructor() {
        this.currentView = 'login';
        this.init();
    }

    init() {
        console.log('🔐 Customer Authentication System initialized');
        this.bindEvents();
        this.initPasswordValidation();
        this.showLogin(); // Start with login view
    }

    bindEvents() {
        // Login form submission
        document.getElementById('customerLoginForm').addEventListener('submit', (e) => {
            this.handleLogin(e);
        });

        // Signup form submission
        const signupForm = document.getElementById('customerSignupForm');
        if (signupForm) {
            console.log('✅ Signup form found, adding event listener');
            signupForm.addEventListener('submit', (e) => {
                console.log('🎯 Signup form submit event triggered');
                this.handleSignup(e);
            });
        } else {
            console.error('❌ Signup form not found: customerSignupForm');
        }

        // Password validation on input
        const signupPassword = document.getElementById('signupPassword');
        const confirmPassword = document.getElementById('confirmPassword');

        if (signupPassword) {
            signupPassword.addEventListener('input', () => {
                this.validatePassword(signupPassword.value);
                this.validatePasswordMatch();
            });
        }

        if (confirmPassword) {
            confirmPassword.addEventListener('input', () => {
                this.validatePasswordMatch();
            });
        }
    }

    initPasswordValidation() {
        // Initialize password requirements
        this.updateRequirement('lengthReq', false);
        this.updateRequirement('upperReq', false);
        this.updateRequirement('lowerReq', false);
        this.updateRequirement('numberReq', false);
    }

    async handleLogin(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const credentials = {
            email: formData.get('email')?.trim(),
            password: formData.get('password'),
            remember: formData.get('remember') === 'on'
        };

        console.log('🔑 Customer login attempt:', { email: credentials.email });

        // Show loading
        this.setButtonLoading(form, true);
        
        try {
            // Simulate API call for now (replace with actual backend later)
            const response = await this.simulateLogin(credentials);
            
            if (response.success) {
                console.log('✅ Customer login successful');
                
                // Store customer data
                localStorage.setItem('customer', JSON.stringify(response.customer));
                if (response.token) {
                    localStorage.setItem('customer_token', response.token);
                }
                
                this.showMessage('Login successful! Welcome back.', 'success');
                
                // Redirect to existing customer portal
                setTimeout(() => {
                    window.location.href = 'index.html';
                }, 1500);
                
            } else {
                this.showMessage(response.message || 'Invalid email or password.', 'error');
            }
            
        } catch (error) {
            console.error('Login error:', error);
            this.showMessage('Network error. Please check your connection and try again.', 'error');
        } finally {
            this.setButtonLoading(form, false);
        }
    }

    async handleSignup(event) {
        event.preventDefault();
        console.log('🚀 HandleSignup called - Form submission intercepted');
        
        const form = event.target;
        const formData = new FormData(form);
        
        // Extract form data
        const customerData = {
            firstName: formData.get('firstName')?.trim(),
            lastName: formData.get('lastName')?.trim(),
            email: formData.get('email')?.trim(),
            phone: formData.get('phone')?.trim(),
            dateOfBirth: formData.get('dateOfBirth'),
            password: formData.get('password'),
            confirmPassword: formData.get('confirmPassword'),
            terms: formData.get('terms') === 'on',
            newsletter: formData.get('newsletter') === 'on'
        };

        console.log('📝 Customer signup attempt:', { 
            email: customerData.email,
            firstName: customerData.firstName,
            lastName: customerData.lastName,
            terms: customerData.terms,
            hasPassword: !!customerData.password,
            passwordsMatch: customerData.password === customerData.confirmPassword
        });

        // Validate form
        console.log('🔍 Starting form validation...');
        if (!this.validateSignupForm(customerData)) {
            console.log('❌ Form validation failed');
            return;
        }
        console.log('✅ Form validation passed');

        // Show loading
        this.setButtonLoading(form, true);

        try {
            // Call actual registration API
            const response = await this.registerCustomer(customerData);
            
            if (response.success) {
                console.log('✅ Customer registration successful');
                
                this.showMessage('Account created successfully! Please check your email for verification.', 'success');
                
                // Switch to login view
                setTimeout(() => {
                    this.showLogin();
                    // Pre-fill email in login form
                    document.getElementById('loginEmail').value = customerData.email;
                }, 2000);
                
            } else {
                this.showMessage(response.message || 'Error creating account. Please try again.', 'error');
            }
            
        } catch (error) {
            console.error('Signup error:', error);
            this.showMessage('Network error. Please check your connection and try again.', 'error');
        } finally {
            this.setButtonLoading(form, false);
        }
    }

    validateSignupForm(data) {
        console.log('🔍 Validating signup form data:', data);
        
        // Check required fields
        if (!data.firstName || !data.lastName || !data.email || !data.phone || !data.password) {
            console.log('❌ Required fields missing:', {
                firstName: !!data.firstName,
                lastName: !!data.lastName,
                email: !!data.email,
                phone: !!data.phone,
                password: !!data.password
            });
            this.showMessage('Please fill in all required fields.', 'error');
            return false;
        }

        // Validate email format
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(data.email)) {
            console.log('❌ Invalid email format:', data.email);
            this.showMessage('Please enter a valid email address.', 'error');
            return false;
        }

        // Validate phone format (basic)
        const phoneRegex = /^[\+]?[\d\s\-\(\)]{10,}$/;
        if (!phoneRegex.test(data.phone)) {
            console.log('❌ Invalid phone format:', data.phone);
            this.showMessage('Please enter a valid phone number.', 'error');
            return false;
        }

        // Validate password
        if (!this.validatePassword(data.password)) {
            console.log('❌ Password validation failed');
            this.showMessage('Please ensure your password meets all requirements.', 'error');
            return false;
        }

        // Check password confirmation
        if (data.password !== data.confirmPassword) {
            console.log('❌ Password confirmation failed:', {
                password: data.password?.length + ' chars',
                confirmPassword: data.confirmPassword?.length + ' chars'
            });
            this.showMessage('Passwords do not match.', 'error');
            return false;
        }

        // Check terms acceptance
        if (!data.terms) {
            console.log('❌ Terms not accepted:', data.terms);
            this.showMessage('Please accept the Terms of Service and Privacy Policy.', 'error');
            return false;
        }

        console.log('✅ All validation checks passed');
        return true;
    }

    validatePassword(password) {
        const requirements = {
            length: password.length >= 8,
            upper: /[A-Z]/.test(password),
            lower: /[a-z]/.test(password),
            number: /\d/.test(password)
        };

        // Update UI
        this.updateRequirement('lengthReq', requirements.length);
        this.updateRequirement('upperReq', requirements.upper);
        this.updateRequirement('lowerReq', requirements.lower);
        this.updateRequirement('numberReq', requirements.number);

        return Object.values(requirements).every(req => req);
    }

    updateRequirement(elementId, isValid) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        const icon = element.querySelector('i');
        
        if (isValid) {
            element.classList.add('valid');
            icon.className = 'fas fa-check';
            element.style.color = '#10b981';
        } else {
            element.classList.remove('valid');
            icon.className = 'fas fa-times';
            element.style.color = '#ef4444';
        }
    }

    validatePasswordMatch() {
        const password = document.getElementById('signupPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const confirmInput = document.getElementById('confirmPassword');

        if (confirmPassword && password !== confirmPassword) {
            confirmInput.setCustomValidity('Passwords do not match');
            confirmInput.style.borderColor = '#ef4444';
        } else {
            confirmInput.setCustomValidity('');
            confirmInput.style.borderColor = confirmPassword ? '#10b981' : '#e5e7eb';
        }
    }

    // Simulation methods (replace with actual API calls later)
    async simulateLogin(credentials) {
        try {
            console.log('🔑 Attempting login with API:', credentials.email);
            
            const response = await fetch('api/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: credentials.email,
                    password: credentials.password,
                    remember: credentials.remember
                })
            });

            const data = await response.json();
            console.log('🔐 API Login Response:', data);

            if (data.success) {
                // Store additional data from API
                if (data.token) {
                    localStorage.setItem('customer_token', data.token);
                }
                if (data.remember_token) {
                    localStorage.setItem('customer_remember_token', data.remember_token);
                }
                
                return {
                    success: true,
                    customer: data.customer,
                    token: data.token,
                    message: data.message
                };
            } else {
                return {
                    success: false,
                    message: data.message || 'Login failed'
                };
            }
        } catch (error) {
            console.error('Login API error:', error);
            return {
                success: false,
                message: 'Connection error. Please check your internet connection and try again.'
            };
        }
    }

    async registerCustomer(customerData) {
        try {
            const response = await fetch('./api/register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(customerData)
            });

            const data = await response.json();
            
            if (response.ok) {
                return data;
            } else {
                return {
                    success: false,
                    message: data.message || 'Registration failed'
                };
            }
        } catch (error) {
            console.error('Registration API error:', error);
            return {
                success: false,
                message: 'Network error. Please check your connection and try again.'
            };
        }
    }

    // UI Management Methods
    showLogin() {
        const loginCard = document.getElementById('loginCard');
        const signupCard = document.getElementById('signupCard');
        const loginTab = document.getElementById('loginTab');
        const signupTab = document.getElementById('signupTab');

        loginCard.classList.add('active');
        signupCard.classList.remove('active');
        loginTab.classList.add('active');
        signupTab.classList.remove('active');

        this.currentView = 'login';
        console.log('🔄 Switched to login view');
    }

    showSignup() {
        const loginCard = document.getElementById('loginCard');
        const signupCard = document.getElementById('signupCard');
        const loginTab = document.getElementById('loginTab');
        const signupTab = document.getElementById('signupTab');

        loginCard.classList.remove('active');
        signupCard.classList.add('active');
        loginTab.classList.remove('active');
        signupTab.classList.add('active');

        this.currentView = 'signup';
        console.log('🔄 Switched to signup view');
    }

    showMessage(message, type = 'info') {
        const modal = document.getElementById('messageModal');
        const icon = document.getElementById('messageIcon');
        const title = document.getElementById('messageTitle');
        const text = document.getElementById('messageText');

        // Set icon based on type
        icon.className = 'message-icon ' + type;
        switch (type) {
            case 'success':
                icon.querySelector('i').className = 'fas fa-check-circle';
                title.textContent = 'Success';
                break;
            case 'error':
                icon.querySelector('i').className = 'fas fa-exclamation-circle';
                title.textContent = 'Error';
                break;
            default:
                icon.querySelector('i').className = 'fas fa-info-circle';
                title.textContent = 'Information';
        }

        text.textContent = message;
        modal.classList.add('active');

        // Auto-close success messages
        if (type === 'success') {
            setTimeout(() => {
                this.closeMessage();
            }, 3000);
        }
    }

    closeMessage() {
        const modal = document.getElementById('messageModal');
        modal.classList.remove('active');
    }

    setButtonLoading(form, isLoading) {
        const button = form.querySelector('.auth-btn');
        const text = button.querySelector('.btn-text');
        const loader = button.querySelector('.btn-loader');
        const arrow = button.querySelector('.btn-arrow');

        if (isLoading) {
            button.disabled = true;
            text.style.display = 'none';
            arrow.style.display = 'none';
            loader.style.display = 'inline-block';
        } else {
            button.disabled = false;
            text.style.display = 'inline';
            arrow.style.display = 'inline';
            loader.style.display = 'none';
        }
    }


}

// Utility Functions
function showLogin() {
    window.customerAuth.showLogin();
}

function showSignup() {
    window.customerAuth.showSignup();
}

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.parentElement.querySelector('.password-toggle');
    const icon = button.querySelector('i');

    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}



function closeMessage() {
    window.customerAuth.closeMessage();
}

// Coming Soon functionality for footer links
function showComingSoon(featureName) {
    const modal = document.getElementById('messageModal');
    const icon = document.getElementById('messageIcon');
    const title = document.getElementById('messageTitle');
    const text = document.getElementById('messageText');
    
    // Set coming soon content
    icon.innerHTML = '<i class="fas fa-clock"></i>';
    icon.className = 'message-icon';
    icon.style.background = '#f59e0b';
    icon.style.color = 'white';
    
    title.textContent = 'Coming Soon';
    text.textContent = `${featureName} feature is currently under development. We'll notify you when it's available!`;
    
    // Show modal
    modal.classList.add('show');
}        // Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 DOM loaded, initializing CustomerAuth...');
    window.customerAuth = new CustomerAuth();
    
    // Additional debug: Check if form and button exist
    const signupForm = document.getElementById('customerSignupForm');
    const signupButton = document.querySelector('#customerSignupForm button[type="submit"]');
    
    console.log('🔍 Form elements check:', {
        signupForm: !!signupForm,
        signupButton: !!signupButton,
        formChildren: signupForm ? signupForm.children.length : 0
    });
    
    // Add direct button click listener for debugging
    if (signupButton) {
        signupButton.addEventListener('click', function(e) {
            console.log('🎯 Signup button clicked directly!');
        });
    }
    
    console.log('🎉 Customer Portal ready!');
    console.log('📝 Demo login credentials:');
    console.log('   Email: john.doe@example.com');
    console.log('   Password: password123');
    console.log('   ---');
    console.log('   Email: jane.smith@example.com');
    console.log('   Password: password123');
});

// Forgot Password Modal Functions
function showForgotPasswordModal() {
    const modal = document.getElementById('forgotPasswordModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Clear any previous message
    const messageDiv = document.getElementById('forgotPasswordMessage');
    messageDiv.innerHTML = '';
    
    // Reset form
    document.getElementById('forgotPasswordForm').reset();
    
    // Focus on email input
    setTimeout(() => {
        document.getElementById('forgotEmail').focus();
    }, 100);
}

function closeForgotPasswordModal() {
    const modal = document.getElementById('forgotPasswordModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Handle forgot password form submission
document.addEventListener('DOMContentLoaded', function() {
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    
    if (forgotPasswordForm) {
        forgotPasswordForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('forgotEmail').value.trim();
            const submitBtn = document.getElementById('forgotPasswordBtn');
            const btnText = submitBtn.querySelector('.btn-text');
            const btnLoading = submitBtn.querySelector('.btn-loading');
            const messageDiv = document.getElementById('forgotPasswordMessage');
            
            if (!email) {
                showForgotPasswordMessage('Please enter your email address.', 'error');
                return;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline';
            messageDiv.innerHTML = '';
            
            try {
                const response = await fetch('./api/password-reset.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ email: email })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showForgotPasswordMessage(
                        'Password reset instructions have been sent to your email address. Please check your inbox and spam folder.',
                        'success'
                    );
                    
                    // Close modal after a delay
                    setTimeout(() => {
                        closeForgotPasswordModal();
                    }, 3000);
                } else {
                    showForgotPasswordMessage(result.message || 'Failed to send reset email. Please try again.', 'error');
                }
                
            } catch (error) {
                console.error('Password reset request error:', error);
                showForgotPasswordMessage('Network error. Please check your connection and try again.', 'error');
            } finally {
                // Reset loading state
                submitBtn.disabled = false;
                btnText.style.display = 'inline';
                btnLoading.style.display = 'none';
            }
        });
    }
});

function showForgotPasswordMessage(message, type) {
    const messageDiv = document.getElementById('forgotPasswordMessage');
    const alertClass = type === 'success' ? 'success' : type === 'error' ? 'error' : 'info';
    
    messageDiv.innerHTML = `
        <div class="auth-message ${alertClass}" style="margin-bottom: 1rem;">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('forgotPasswordModal');
    if (e.target === modal) {
        closeForgotPasswordModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('forgotPasswordModal');
        if (modal.style.display === 'flex') {
            closeForgotPasswordModal();
        }
    }
});
