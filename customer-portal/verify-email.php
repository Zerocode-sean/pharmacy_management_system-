<?php
/**
 * Email Verification Page
 * Handles email verification via link or manual code entry
 */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Verify Email - PharmaConnect</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #333;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 20px;
    }
    
    .container {
      background: white;
      padding: 40px;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      width: 100%;
      max-width: 480px;
      animation: slideIn 0.4s ease-out;
    }
    
    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .header {
      text-align: center;
      margin-bottom: 30px;
    }
    
    .header-icon {
      width: 70px;
      height: 70px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      color: white;
      font-size: 32px;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    
    h1 {
      font-size: 28px;
      color: #333;
      margin-bottom: 10px;
    }
    
    .subtitle {
      color: #666;
      font-size: 14px;
      line-height: 1.5;
    }
    
    .status {
      padding: 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      display: none;
      animation: fadeIn 0.3s ease-in;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    
    .status.show {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .status i {
      font-size: 20px;
    }
    
    .status-message {
      flex: 1;
      font-size: 14px;
      line-height: 1.4;
    }
    
    .status.success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    
    .status.error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    
    .status.info {
      background: #d1ecf1;
      color: #0c5460;
      border: 1px solid #bee5eb;
    }
    
    .status.warning {
      background: #fff3cd;
      color: #856404;
      border: 1px solid #ffeaa7;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    label {
      display: block;
      font-size: 14px;
      font-weight: 600;
      color: #555;
      margin-bottom: 8px;
    }
    
    .input {
      width: 100%;
      padding: 14px 16px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 15px;
      transition: all 0.3s ease;
      font-family: inherit;
    }
    
    .input:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .input.code-input {
      font-family: 'Courier New', monospace;
      font-size: 18px;
      letter-spacing: 2px;
      text-align: center;
      text-transform: uppercase;
    }
    
    .btn {
      width: 100%;
      padding: 14px 20px;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    
    .btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
    
    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    
    .btn-primary:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
    }
    
    .btn-secondary {
      background: #f8f9fa;
      color: #555;
      border: 2px solid #e0e0e0;
      margin-top: 12px;
    }
    
    .btn-secondary:hover:not(:disabled) {
      background: #e9ecef;
      border-color: #ced4da;
    }
    
    .divider {
      text-align: center;
      margin: 24px 0;
      position: relative;
    }
    
    .divider::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 0;
      right: 0;
      height: 1px;
      background: #e0e0e0;
    }
    
    .divider span {
      background: white;
      padding: 0 12px;
      color: #999;
      font-size: 13px;
      position: relative;
    }
    
    .help-text {
      text-align: center;
      margin-top: 20px;
      padding-top: 20px;
      border-top: 1px solid #e0e0e0;
      color: #666;
      font-size: 13px;
      line-height: 1.5;
    }
    
    .help-text a {
      color: #667eea;
      text-decoration: none;
      font-weight: 600;
    }
    
    .help-text a:hover {
      text-decoration: underline;
    }
    
    .spinner {
      display: inline-block;
      width: 16px;
      height: 16px;
      border: 2px solid rgba(255,255,255,0.3);
      border-top-color: white;
      border-radius: 50%;
      animation: spin 0.6s linear infinite;
    }
    
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    
    .countdown {
      font-weight: 600;
      color: #667eea;
    }
    
    @media (max-width: 480px) {
      .container {
        padding: 30px 24px;
      }
      
      h1 {
        font-size: 24px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="header-icon">
        <i class="fas fa-envelope-circle-check"></i>
      </div>
      <h1>Verify Your Email</h1>
      <p class="subtitle">Enter the verification code sent to your email or click the link in your email</p>
    </div>

    <div id="status" class="status">
      <i class="fas fa-circle-notch fa-spin"></i>
      <div class="status-message"></div>
    </div>

    <form id="verifyForm">
      <div class="form-group">
        <label for="email">
          <i class="fas fa-envelope"></i> Email Address
        </label>
        <input 
          id="email" 
          class="input" 
          type="email" 
          placeholder="your@email.com" 
          required 
          autocomplete="email"
        />
      </div>

      <div class="form-group">
        <label for="code">
          <i class="fas fa-key"></i> Verification Code
        </label>
        <input 
          id="code" 
          class="input code-input" 
          type="text" 
          placeholder="XXXXXX" 
          required 
          maxlength="10"
          autocomplete="off"
        />
      </div>

      <button id="submitBtn" class="btn btn-primary" type="submit">
        <i class="fas fa-check-circle"></i>
        <span>Verify Email</span>
      </button>

      <button id="resendBtn" class="btn btn-secondary" type="button">
        <i class="fas fa-paper-plane"></i>
        <span>Resend Verification Code</span>
      </button>
    </form>

    <div class="help-text">
      <p><i class="fas fa-info-circle"></i> Didn't receive the email? Check your spam folder or <a href="#" id="contactSupport">contact support</a></p>
      <p style="margin-top: 10px;"><a href="./auth.html"><i class="fas fa-arrow-left"></i> Back to Login</a></p>
    </div>
  </div>

  <script>
    (function(){
      const qs = new URLSearchParams(window.location.search);
      const preEmail = qs.get('email') || '';
      const preCode = qs.get('code') || qs.get('token') || '';

      const emailInput = document.getElementById('email');
      const codeInput = document.getElementById('code');
      const statusEl = document.getElementById('status');
      const statusMessage = statusEl.querySelector('.status-message');
      const form = document.getElementById('verifyForm');
      const submitBtn = document.getElementById('submitBtn');
      const resendBtn = document.getElementById('resendBtn');
      
      let retryAttempts = 0;
      const MAX_RETRY_ATTEMPTS = 3;
      let resendCooldown = 0;

      function showStatus(message, type = 'info') {
        statusEl.className = 'status show ' + type;
        statusMessage.innerHTML = message;
        
        // Update icon based on type
        const icon = statusEl.querySelector('i');
        icon.className = '';
        switch(type) {
          case 'success':
            icon.className = 'fas fa-check-circle';
            break;
          case 'error':
            icon.className = 'fas fa-exclamation-circle';
            break;
          case 'warning':
            icon.className = 'fas fa-exclamation-triangle';
            break;
          case 'info':
          default:
            icon.className = 'fas fa-info-circle';
            break;
        }
      }

      function hideStatus() {
        statusEl.classList.remove('show');
      }

      function setLoading(button, loading, text) {
        if (loading) {
          button.disabled = true;
          button.innerHTML = '<span class="spinner"></span><span>' + text + '</span>';
        } else {
          button.disabled = false;
        }
      }

      async function verify(email, code, isAutoRetry = false) {
        if (!isAutoRetry) {
          hideStatus();
        }
        
        setLoading(submitBtn, true, 'Verifying...');
        
        try {
          const res = await fetch('./api/verify-email.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email, code: code })
          });
          
          const data = await res.json();
          
          if (res.ok && data.success) {
            showStatus(
              '<strong>✓ Success!</strong> ' + (data.message || 'Email verified successfully!') + 
              ' Redirecting to login in <span class="countdown">3</span> seconds...',
              'success'
            );
            
            // Countdown redirect
            let countdown = 3;
            const countdownEl = document.querySelector('.countdown');
            const interval = setInterval(() => {
              countdown--;
              if (countdownEl) countdownEl.textContent = countdown;
              if (countdown <= 0) {
                clearInterval(interval);
                window.location.href = './auth.html';
              }
            }, 1000);
            
            retryAttempts = 0; // Reset retry counter on success
          } else {
            // Check if code expired
            if (data.message && data.message.includes('expired')) {
              showStatus(
                '<strong>Code Expired!</strong> Your verification code has expired. ' +
                '<a href="#" onclick="document.getElementById(\'resendBtn\').click(); return false;">Click here to request a new code</a>',
                'warning'
              );
            } else if (data.message && data.message.includes('already verified')) {
              showStatus(
                '<strong>Already Verified!</strong> Your email is already verified. ' +
                '<a href="./auth.html">Click here to login</a>',
                'success'
              );
              setTimeout(() => {
                window.location.href = './auth.html';
              }, 3000);
            } else {
              // Handle retry logic for failed attempts
              retryAttempts++;
              
              if (retryAttempts < MAX_RETRY_ATTEMPTS) {
                showStatus(
                  '<strong>Verification Failed</strong> ' + (data.message || 'Invalid code.') + 
                  ' Retrying... (Attempt ' + retryAttempts + '/' + MAX_RETRY_ATTEMPTS + ')',
                  'error'
                );
                
                // Auto-retry after 2 seconds
                setTimeout(() => {
                  verify(email, code, true);
                }, 2000);
              } else {
                showStatus(
                  '<strong>Verification Failed</strong> ' + (data.message || 'Invalid code.') + 
                  ' Please check your email for the correct code or request a new one.',
                  'error'
                );
                retryAttempts = 0; // Reset for next manual attempt
              }
            }
          }
        } catch(err) {
          retryAttempts++;
          
          if (retryAttempts < MAX_RETRY_ATTEMPTS && !isAutoRetry) {
            showStatus(
              '<strong>Network Error</strong> Connection failed. Retrying... (' + retryAttempts + '/' + MAX_RETRY_ATTEMPTS + ')',
              'error'
            );
            
            setTimeout(() => {
              verify(email, code, true);
            }, 2000);
          } else {
            showStatus(
              '<strong>Connection Error</strong> Unable to connect to server. Please check your internet connection and try again.',
              'error'
            );
            retryAttempts = 0;
          }
        } finally {
          if (!isAutoRetry || retryAttempts >= MAX_RETRY_ATTEMPTS) {
            submitBtn.innerHTML = '<i class="fas fa-check-circle"></i><span>Verify Email</span>';
            submitBtn.disabled = false;
          }
        }
      }

      async function resend(email) {
        if (resendCooldown > 0) {
          showStatus(
            'Please wait ' + resendCooldown + ' seconds before requesting another code.',
            'warning'
          );
          return;
        }
        
        hideStatus();
        setLoading(resendBtn, true, 'Sending...');
        
        try {
          const res = await fetch('./api/resend-verification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email })
          });
          
          const data = await res.json();
          
          if (res.ok && data.success) {
            showStatus(
              '<strong>✓ Code Sent!</strong> ' + (data.message || 'A new verification code has been sent to your email.'),
              'success'
            );
            
            // Set cooldown
            resendCooldown = 60;
            updateResendButton();
          } else {
            showStatus(
              '<strong>Failed to Send</strong> ' + (data.message || 'Unable to send verification code. Please try again.'),
              'error'
            );
          }
        } catch(err) {
          showStatus(
            '<strong>Connection Error</strong> Unable to send code. Please check your internet connection.',
            'error'
          );
        } finally {
          resendBtn.innerHTML = '<i class="fas fa-paper-plane"></i><span>Resend Verification Code</span>';
          resendBtn.disabled = false;
        }
      }

      function updateResendButton() {
        if (resendCooldown > 0) {
          resendBtn.disabled = true;
          resendBtn.innerHTML = '<i class="fas fa-clock"></i><span>Resend in ' + resendCooldown + 's</span>';
          
          setTimeout(() => {
            resendCooldown--;
            updateResendButton();
          }, 1000);
        } else {
          resendBtn.disabled = false;
          resendBtn.innerHTML = '<i class="fas fa-paper-plane"></i><span>Resend Verification Code</span>';
        }
      }

      // Prefill from query string
      if (preEmail) {
        emailInput.value = preEmail;
      }
      if (preCode) {
        codeInput.value = preCode;
      }

      // Auto-submit if both present
      if (preEmail && preCode) {
        showStatus('Verifying your email automatically...', 'info');
        setTimeout(() => {
          verify(preEmail, preCode);
        }, 500);
      }

      // Form submit handler
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        const email = emailInput.value.trim();
        const code = codeInput.value.trim().toUpperCase();
        
        if (!email || !code) {
          showStatus('Please enter both your email address and verification code.', 'warning');
          return;
        }
        
        retryAttempts = 0; // Reset retry counter for manual submission
        verify(email, code);
      });

      // Resend button handler
      resendBtn.addEventListener('click', function() {
        const email = emailInput.value.trim();
        
        if (!email) {
          showStatus('Please enter your email address to resend the verification code.', 'warning');
          emailInput.focus();
          return;
        }
        
        resend(email);
      });

      // Contact support handler
      document.getElementById('contactSupport').addEventListener('click', function(e) {
        e.preventDefault();
        window.location.href = 'mailto:support@pharmacare.com?subject=Email Verification Help';
      });

      // Auto-format code input
      codeInput.addEventListener('input', function(e) {
        this.value = this.value.toUpperCase();
      });

    })();
  </script>
</body>
</html>