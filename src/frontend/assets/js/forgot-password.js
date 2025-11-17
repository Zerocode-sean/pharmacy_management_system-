// Forgot Password Handler
class ForgotPasswordHandler {
    constructor() {
        this.currentStep = 1;
        this.userIdentifier = '';
        this.verificationCode = '';
        this.resetToken = '';
        
        this.init();
    }

    init() {
        console.log('🔑 Forgot Password Handler initialized');
        this.bindEvents();
        this.initPasswordValidation();
    }

    bindEvents() {
        // Step 1: Send reset instructions
        document.getElementById('forgotPasswordForm').addEventListener('submit', (e) => {
            this.handleSendResetInstructions(e);
        });

        // Step 2: Verify code
        document.getElementById('verifyCodeForm').addEventListener('submit', (e) => {
            this.handleVerifyCode(e);
        });

        // Step 3: Reset password
        document.getElementById('resetPasswordForm').addEventListener('submit', (e) => {
            this.handleResetPassword(e);
        });

        // Resend code
        document.getElementById('resendCode').addEventListener('click', (e) => {
            e.preventDefault();
            this.handleResendCode();
        });

        // Verification code inputs
        this.initCodeInputs();
    }

    initCodeInputs() {
        const codeInputs = document.querySelectorAll('.code-input');
        
        codeInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value.replace(/\D/g, ''); // Only digits
                e.target.value = value;
                
                // Auto-focus next input
                if (value && index < codeInputs.length - 1) {
                    codeInputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', (e) => {
                // Handle backspace
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    codeInputs[index - 1].focus();
                }
            });

            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text').replace(/\D/g, '');
                
                // Fill inputs with pasted code
                for (let i = 0; i < Math.min(pastedData.length, codeInputs.length - index); i++) {
                    if (codeInputs[index + i]) {
                        codeInputs[index + i].value = pastedData[i];
                    }
                }
            });
        });
    }

    initPasswordValidation() {
        const passwordInput = document.getElementById('newPassword');
        const confirmInput = document.getElementById('confirmPassword');

        if (passwordInput) {
            passwordInput.addEventListener('input', () => {
                this.validatePassword(passwordInput.value);
            });
        }

        if (confirmInput) {
            confirmInput.addEventListener('input', () => {
                this.validatePasswordMatch();
            });
        }
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
        const password = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const confirmInput = document.getElementById('confirmPassword');

        if (confirmPassword && password !== confirmPassword) {
            confirmInput.setCustomValidity('Passwords do not match');
            confirmInput.style.borderColor = '#ef4444';
        } else {
            confirmInput.setCustomValidity('');
            confirmInput.style.borderColor = '#10b981';
        }
    }

    async handleSendResetInstructions(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const identifier = formData.get('identifier').trim();
        
        console.log('📧 Sending reset instructions for:', identifier);
        
        // Show loading
        this.setButtonLoading(form, true);
        this.hideMessage();

        try {
            // Determine the correct API URL based on current location
            let apiUrl;
            if (window.location.port === '5500') {
                // Running from VS Code Live Server - use XAMPP server
                apiUrl = 'http://localhost/Phamarcy/src/backend/api/forgot-password.php';
            } else {
                // Running from XAMPP - use relative path
                apiUrl = '/Phamarcy/src/backend/api/forgot-password.php';
            }
            
            const response = await fetch(apiUrl, {
                method: 'POST',
                mode: 'cors',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    action: 'send_reset',
                    identifier: identifier
                })
            });

            console.log('📧 Response status:', response.status);
            console.log('📧 Response headers:', response.headers);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const responseText = await response.text();
            console.log('📧 Raw response:', responseText);
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                throw new Error('Invalid response format from server');
            }
            
            console.log('📧 Reset response:', data);

            if (data.success) {
                this.userIdentifier = identifier;
                this.showMessage('Reset instructions sent successfully! Please check your email.', 'success');
                
                // Move to next step
                setTimeout(() => {
                    this.goToStep(2);
                }, 1500);
            } else {
                this.showMessage(data.message || 'Error sending reset instructions. Please try again.', 'error');
            }
        } catch (error) {
            console.error('Error sending reset instructions:', error);
            this.showMessage('Network error. Please check your connection and try again.', 'error');
        } finally {
            this.setButtonLoading(form, false);
        }
    }

    async handleVerifyCode(event) {
        event.preventDefault();
        
        const codeInputs = document.querySelectorAll('.code-input');
        const code = Array.from(codeInputs).map(input => input.value).join('');
        
        if (code.length !== 6) {
            this.showMessage('Please enter the complete 6-digit code.', 'error');
            return;
        }

        console.log('🔢 Verifying code:', code);
        
        // Show loading
        const form = event.target;
        this.setButtonLoading(form, true);
        this.hideMessage();

        try {
            // Determine the correct API URL based on current location
            let apiUrl;
            if (window.location.port === '5500') {
                // Running from VS Code Live Server - use XAMPP server
                apiUrl = 'http://localhost/Phamarcy/src/backend/api/forgot-password.php';
            } else {
                // Running from XAMPP - use relative path
                apiUrl = '/Phamarcy/src/backend/api/forgot-password.php';
            }
            
            const response = await fetch(apiUrl, {
                method: 'POST',
                mode: 'cors',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    action: 'verify_code',
                    identifier: this.userIdentifier,
                    code: code
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const responseText = await response.text();
            console.log('🔢 Raw verification response:', responseText);
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                throw new Error('Invalid response format from server');
            }
            
            console.log('🔢 Verification response:', data);

            if (data.success) {
                this.verificationCode = code;
                this.resetToken = data.reset_token;
                this.showMessage('Code verified successfully!', 'success');
                
                // Move to next step
                setTimeout(() => {
                    this.goToStep(3);
                }, 1500);
            } else {
                this.showMessage(data.message || 'Invalid verification code. Please try again.', 'error');
            }
        } catch (error) {
            console.error('Error verifying code:', error);
            this.showMessage('Network error. Please check your connection and try again.', 'error');
        } finally {
            this.setButtonLoading(form, false);
        }
    }

    async handleResetPassword(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const newPassword = formData.get('newPassword');
        const confirmPassword = formData.get('confirmPassword');
        
        // Validate passwords
        if (!this.validatePassword(newPassword)) {
            this.showMessage('Please ensure your password meets all requirements.', 'error');
            return;
        }

        if (newPassword !== confirmPassword) {
            this.showMessage('Passwords do not match.', 'error');
            return;
        }

        console.log('🔒 Resetting password...');
        
        // Show loading
        this.setButtonLoading(form, true);
        this.hideMessage();

        try {
            // Determine the correct API URL based on current location
            let apiUrl;
            if (window.location.port === '5500') {
                // Running from VS Code Live Server - use XAMPP server
                apiUrl = 'http://localhost/Phamarcy/src/backend/api/forgot-password.php';
            } else {
                // Running from XAMPP - use relative path
                apiUrl = '/Phamarcy/src/backend/api/forgot-password.php';
            }
            
            const response = await fetch(apiUrl, {
                method: 'POST',
                mode: 'cors',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    action: 'reset_password',
                    identifier: this.userIdentifier,
                    reset_token: this.resetToken,
                    new_password: newPassword
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const responseText = await response.text();
            console.log('🔒 Raw reset response:', responseText);
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                throw new Error('Invalid response format from server');
            }
            
            console.log('🔒 Reset response:', data);

            if (data.success) {
                this.showMessage('Password reset successfully!', 'success');
                
                // Move to success step
                setTimeout(() => {
                    this.goToStep(4);
                }, 1500);
            } else {
                this.showMessage(data.message || 'Error resetting password. Please try again.', 'error');
            }
        } catch (error) {
            console.error('Error resetting password:', error);
            this.showMessage('Network error. Please check your connection and try again.', 'error');
        } finally {
            this.setButtonLoading(form, false);
        }
    }

    async handleResendCode() {
        console.log('🔄 Resending verification code...');
        
        try {
            // Determine the correct API URL based on current location
            let apiUrl;
            if (window.location.port === '5500') {
                // Running from VS Code Live Server - use XAMPP server
                apiUrl = 'http://localhost/Phamarcy/src/backend/api/forgot-password.php';
            } else {
                // Running from XAMPP - use relative path
                apiUrl = '/Phamarcy/src/backend/api/forgot-password.php';
            }
            
            const response = await fetch(apiUrl, {
                method: 'POST',
                mode: 'cors',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    action: 'resend_code',
                    identifier: this.userIdentifier
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const responseText = await response.text();
            console.log('🔄 Raw resend response:', responseText);
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                throw new Error('Invalid response format from server');
            }
            
            if (data.success) {
                this.showMessage('Verification code sent again! Please check your email.', 'info');
            } else {
                this.showMessage(data.message || 'Error resending code. Please try again.', 'error');
            }
        } catch (error) {
            console.error('Error resending code:', error);
            this.showMessage('Network error. Please try again.', 'error');
        }
    }

    goToStep(stepNumber) {
        console.log(`🔄 Moving to step ${stepNumber}`);
        
        // Hide all steps
        document.querySelectorAll('.form-step').forEach(step => {
            step.classList.remove('active');
        });
        
        // Show current step
        document.getElementById(`formStep${stepNumber}`).classList.add('active');
        
        // Update step indicator
        const indicator = document.getElementById('stepIndicator');
        indicator.className = `step-indicator step-${stepNumber}`;
        
        // Update step status
        for (let i = 1; i <= 3; i++) {
            const step = document.getElementById(`step${i}`);
            if (i < stepNumber) {
                step.className = 'step completed';
                step.innerHTML = '<i class="fas fa-check"></i>';
            } else if (i === stepNumber) {
                step.className = 'step active';
                step.textContent = i;
            } else {
                step.className = 'step';
                step.textContent = i;
            }
        }
        
        this.currentStep = stepNumber;
        this.hideMessage();
    }

    showMessage(message, type = 'info') {
        const messageBox = document.getElementById('messageBox');
        messageBox.textContent = message;
        messageBox.className = `message-box ${type}`;
        messageBox.style.display = 'block';
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(() => {
                this.hideMessage();
            }, 3000);
        }
    }

    hideMessage() {
        const messageBox = document.getElementById('messageBox');
        messageBox.style.display = 'none';
    }

    setButtonLoading(form, isLoading) {
        const button = form.querySelector('.login-submit-btn');
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

// Password toggle function
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

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new ForgotPasswordHandler();
});
