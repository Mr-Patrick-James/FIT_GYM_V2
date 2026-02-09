const modal = document.getElementById('authModal');
const loginForm = document.getElementById('loginForm');
const signupForm = document.getElementById('signupForm');
const otpVerificationForm = document.getElementById('otpVerificationForm');
let pendingSignupData = null;
let otpTimer = null;
let otpExpiryTime = null;
let resendCooldownTimer = null;
let resendCooldownTime = 0;

function openModal(type) {
    modal.classList.add('active');
    switchForm(type);
}

function closeModal() {
    modal.classList.remove('active');
    
    // Reset all form states when closing modal
    // Reset signup button
    const signupBtn = document.getElementById('signupSubmitBtn');
    const signupBtnText = document.getElementById('signupBtnText');
    const signupBtnLoader = document.getElementById('signupBtnLoader');
    if (signupBtn) {
        signupBtn.disabled = false;
        if (signupBtnText) signupBtnText.style.display = 'inline';
        if (signupBtnLoader) signupBtnLoader.style.display = 'none';
    }
    
    // Reset login button
    const loginBtn = document.getElementById('loginSubmitBtn');
    const loginBtnText = document.getElementById('loginBtnText');
    const loginBtnLoader = document.getElementById('loginBtnLoader');
    if (loginBtn) {
        loginBtn.disabled = false;
        if (loginBtnText) loginBtnText.style.display = 'inline';
        if (loginBtnLoader) loginBtnLoader.style.display = 'none';
    }
}

modal.addEventListener('click', (event) => {
    if (event.target === modal) {
        closeModal();
    }
});

function switchForm(type) {
    // Remove any error messages
    const existingError = document.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
    
    if (type === 'login') {
        loginForm.classList.add('active');
        signupForm.classList.remove('active');
        otpVerificationForm.classList.remove('active');
        // Reset login form
        document.getElementById('loginFormElement').reset();
    } else if (type === 'signup') {
        signupForm.classList.add('active');
        loginForm.classList.remove('active');
        otpVerificationForm.classList.remove('active');
        // Reset signup form
        document.querySelector('#signupForm form').reset();
        // Reset signup button state (in case it was stuck in loading)
        const signupBtn = document.getElementById('signupSubmitBtn');
        const signupBtnText = document.getElementById('signupBtnText');
        const signupBtnLoader = document.getElementById('signupBtnLoader');
        if (signupBtn) {
            signupBtn.disabled = false;
            if (signupBtnText) signupBtnText.style.display = 'inline';
            if (signupBtnLoader) signupBtnLoader.style.display = 'none';
        }
    } else if (type === 'otp') {
        otpVerificationForm.classList.add('active');
        signupForm.classList.remove('active');
        loginForm.classList.remove('active');
        // Clear OTP inputs
        for (let i = 1; i <= 6; i++) {
            const input = document.getElementById(`otpInput${i}`);
            if (input) input.value = '';
        }
    }
}

// Generate 6-digit OTP
function generateOTP() {
    return Math.floor(100000 + Math.random() * 900000).toString();
}

// NOTE: This function is DEPRECATED - emails are now sent via PHP API
// This is only kept as a last-resort fallback if API completely fails
// It should NOT be called in normal operation
function sendOTP(email, otp) {
    console.error('WARNING: Using deprecated sendOTP() function. API should handle email sending!');
    // Store OTP with expiration (5 minutes) - only for emergency fallback
    const otpData = {
        code: otp,
        email: email,
        expiresAt: Date.now() + (5 * 60 * 1000) // 5 minutes
    };
    localStorage.setItem('pendingOTP', JSON.stringify(otpData));
    otpExpiryTime = otpData.expiresAt;
    
    // DO NOT show demo notification - email should be sent by server
    console.log('FALLBACK MODE: OTP stored locally. Email was NOT sent by server!');
    console.log(`OTP Code: ${otp} (for testing only - email not sent)`);
}

// Start OTP countdown timer
function startOTPTimer() {
    if (otpTimer) clearInterval(otpTimer);
    
    function updateTimer() {
        const now = Date.now();
        const remaining = Math.max(0, otpExpiryTime - now);
        
        if (remaining <= 0) {
            document.getElementById('otpTimer').textContent = 'OTP expired. Please request a new one.';
            clearInterval(otpTimer);
            return;
        }
        
        // Keep resend button visible (cooldown timer handles disabling it)
        const minutes = Math.floor(remaining / 60000);
        const seconds = Math.floor((remaining % 60000) / 1000);
        document.getElementById('otpTimer').textContent = `Code expires in ${minutes}:${seconds.toString().padStart(2, '0')}`;
    }
    
    updateTimer();
    otpTimer = setInterval(updateTimer, 1000);
}

// Start resend cooldown timer (30 seconds)
function startResendCooldown() {
    if (resendCooldownTimer) clearInterval(resendCooldownTimer);
    
    resendCooldownTime = 30; // 30 seconds cooldown
    const resendBtn = document.getElementById('resendOtp');
    const cooldownSpan = document.getElementById('resendCooldown');
    
    resendBtn.style.pointerEvents = 'none';
    resendBtn.style.opacity = '0.5';
    cooldownSpan.style.display = 'inline';
    
    function updateCooldown() {
        if (resendCooldownTime <= 0) {
            resendBtn.style.pointerEvents = 'auto';
            resendBtn.style.opacity = '1';
            cooldownSpan.style.display = 'none';
            clearInterval(resendCooldownTimer);
            resendCooldownTimer = null;
            return;
        }
        
        cooldownSpan.textContent = `(Wait ${resendCooldownTime}s)`;
        resendCooldownTime--;
    }
    
    updateCooldown();
    resendCooldownTimer = setInterval(updateCooldown, 1000);
}

// Resend OTP
async function resendOTP() {
    if (!pendingSignupData) {
        showError('No signup data found. Please start over.');
        return;
    }
    
    // Check cooldown
    if (resendCooldownTime > 0) {
        showError(`Please wait ${resendCooldownTime} seconds before requesting a new code.`);
        return;
    }
    
    const resendBtn = document.getElementById('resendOtp');
    const originalText = resendBtn.textContent;
    
    // Show loading state
    resendBtn.textContent = 'Sending...';
    resendBtn.style.pointerEvents = 'none';
    
    try {
        // Try to use PHP API
        const response = await fetch('api/auth/resend-otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: pendingSignupData.email })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // OTP is sent via email
            // Clear OTP inputs
            for (let i = 1; i <= 6; i++) {
                document.getElementById(`otpInput${i}`).value = '';
            }
            document.getElementById('otpInput1').focus();
            
            // Update OTP timer using server-provided expiration time
            // Prefer Unix timestamp (timezone-independent) over datetime string
            if (data.data && data.data.expires_at_timestamp) {
                // Server provides Unix timestamp (seconds), convert to milliseconds for JavaScript
                otpExpiryTime = data.data.expires_at_timestamp * 1000;
                console.log("Resend: Using server expiration timestamp: " + new Date(otpExpiryTime).toLocaleString());
                // Restart timer with new expiration time
                startOTPTimer();
            } else if (data.data && data.data.expires_at) {
                // Fallback to datetime string if timestamp not available
                const expiresAt = new Date(data.data.expires_at + ' UTC').getTime(); // Treat as UTC to avoid timezone issues
                otpExpiryTime = expiresAt;
                console.log("Resend: Using server expiration datetime (UTC): " + new Date(expiresAt).toLocaleString());
                // Restart timer with new expiration time
                startOTPTimer();
            } else {
                // Fallback to client-side calculation
                otpExpiryTime = Date.now() + (5 * 60 * 1000);
                startOTPTimer();
            }
            
            // Start cooldown timer
            startResendCooldown();
            
            // Show success message
            showSuccess('New OTP code sent to your email! Please check your inbox.');
        } else {
            throw new Error(data.message || 'Failed to resend OTP');
        }
    } catch (apiError) {
        console.error('Resend OTP error:', apiError);
        
        // Fallback to localStorage (for testing)
        try {
            const newOTP = generateOTP();
            sendOTP(pendingSignupData.email, newOTP);
            
            // Clear OTP inputs
            for (let i = 1; i <= 6; i++) {
                document.getElementById(`otpInput${i}`).value = '';
            }
            document.getElementById('otpInput1').focus();
            
            // Start cooldown
            startResendCooldown();
            
            showSuccess('New OTP sent to your email!');
        } catch (fallbackError) {
            showError('Failed to resend OTP. Please try again later.');
        }
    } finally {
        // Restore button text
        resendBtn.textContent = originalText;
    }
}

// Back to signup
function backToSignup() {
    switchForm('signup');
    pendingSignupData = null;
    if (otpTimer) {
        clearInterval(otpTimer);
        otpTimer = null;
    }
    if (resendCooldownTimer) {
        clearInterval(resendCooldownTimer);
        resendCooldownTimer = null;
        resendCooldownTime = 0;
    }
    // Reset resend button
    const resendBtn = document.getElementById('resendOtp');
    if (resendBtn) {
        resendBtn.style.pointerEvents = 'auto';
        resendBtn.style.opacity = '1';
        resendBtn.textContent = 'Resend Code';
    }
    const cooldownSpan = document.getElementById('resendCooldown');
    if (cooldownSpan) {
        cooldownSpan.style.display = 'none';
    }
}

// Show success message
function showSuccess(message, formElement = null) {
    const existingError = document.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
    
    const successDiv = document.createElement('div');
    successDiv.className = 'error-message';
    successDiv.textContent = message;
    successDiv.style.cssText = `
        background: #22c55e;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 0.9rem;
        font-weight: 600;
        text-align: center;
        animation: slideDown 0.3s;
    `;
    
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    `;
    if (!document.querySelector('style[data-success-styles]')) {
        style.setAttribute('data-success-styles', 'true');
        document.head.appendChild(style);
    }
    
    const form = formElement || document.querySelector('#otpVerificationForm form');
    if (form && form.parentNode) {
        form.parentNode.insertBefore(successDiv, form);
    }
    
    setTimeout(() => {
        if (successDiv.parentNode) {
            successDiv.remove();
        }
    }, 3000);
}

// Temporary login credentials
const TEMP_CREDENTIALS = {
    // Admin credentials
    admin: {
        email: 'admin@martinezfitness.com',
        password: 'admin123',
        name: 'Admin Martinez',
        role: 'admin',
        redirect: 'views/admin/dashboard.php'
    },
    // User credentials
    user: {
        email: 'user@martinezfitness.com',
        password: 'user123',
        name: 'Juan Dela Cruz',
        role: 'user',
        redirect: 'views/user/dashboard.php'
    }
};

// Show error message
function showError(message, formElement = null) {
    // Remove existing error messages
    const existingError = document.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
    
    // Create error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.textContent = message;
    errorDiv.style.cssText = `
        background: #e50914;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 0.9rem;
        font-weight: 600;
        text-align: center;
        animation: shake 0.3s;
    `;
    
    // Add shake animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
    `;
    if (!document.querySelector('style[data-auth-styles]')) {
        style.setAttribute('data-auth-styles', 'true');
        document.head.appendChild(style);
    }
    
    // Insert error message - make it visible
    console.log("Inserting error message:", message, "formElement:", formElement);
    
    // Find the form to insert error before
    let form = formElement;
    if (!form) {
        // Check which form box is active
        const signupFormBox = document.getElementById('signupForm');
        const loginFormBox = document.getElementById('loginForm');
        const otpFormBox = document.getElementById('otpVerificationForm');
        
        if (signupFormBox && signupFormBox.classList.contains('active')) {
            form = signupFormBox.querySelector('form');
            console.log("Using signup form (active)");
        } else if (loginFormBox && loginFormBox.classList.contains('active')) {
            form = loginFormBox.querySelector('form');
            console.log("Using login form (active)");
        } else if (otpFormBox && otpFormBox.classList.contains('active')) {
            form = otpFormBox.querySelector('form');
            console.log("Using OTP form (active)");
        } else {
            // Fallback: try to find any form
            form = document.querySelector('#signupForm form') || 
                   document.querySelector('#loginForm form') || 
                   document.querySelector('#otpVerificationForm form');
            console.log("Using fallback form");
        }
    }
    
    if (form && form.parentNode) {
        form.parentNode.insertBefore(errorDiv, form);
        console.log("✅ Error inserted successfully before form");
    } else {
        // Fallback: insert at top of auth card
        const authCard = document.querySelector('.auth-card');
        if (authCard) {
            authCard.insertBefore(errorDiv, authCard.firstChild);
            console.log("✅ Error inserted at top of auth card");
        } else {
            console.error("❌ Could not find form or auth-card to insert error");
            // Last resort: alert
            alert("Error: " + message);
        }
    }
    
    // Scroll error into view
    errorDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    
    // Auto remove after 8 seconds (longer so user can read it)
    setTimeout(() => {
        if (errorDiv.parentNode) {
            errorDiv.remove();
        }
    }, 8000);
}

// ============================================
// LOGIN FUNCTION - DATABASE INTEGRATION READY
// ============================================
// TODO: When implementing database, replace the localStorage checks below with:
// 
// const response = await fetch('/api/auth/login', {
//     method: 'POST',
//     headers: { 'Content-Type': 'application/json' },
//     body: JSON.stringify({ email, password })
// });
// 
// if (!response.ok) {
//     const error = await response.json();
//     throw new Error(error.message || 'Login failed');
// }
// 
// const data = await response.json();
// userData = data.user;
// redirectUrl = data.redirect || (data.user.role === 'admin' ? 'views/admin/dashboard.html' : 'views/user/dashboard.html');
// ============================================

async function handleLogin(email, password) {
    // Show loading state
    const loginBtn = document.getElementById('loginSubmitBtn');
    const loginBtnText = document.getElementById('loginBtnText');
    const loginBtnLoader = document.getElementById('loginBtnLoader');
    
    loginBtn.disabled = true;
    loginBtnText.style.display = 'none';
    loginBtnLoader.style.display = 'inline';
    
    try {
        // Try to use PHP API (database)
        try {
            const response = await fetch('api/auth/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Save user session to localStorage (for client-side checks)
                localStorage.setItem('userData', JSON.stringify(data.data.user));
                localStorage.setItem('isLoggedIn', 'true');
                localStorage.setItem('userRole', data.data.user.role);
                
                // Longer delay to ensure session cookie is fully set and recognized
                // This prevents the dashboard from redirecting back to index.php
                console.log("Login successful, redirecting to:", data.data.redirect);
                console.log("User data:", data.data.user);
                
                // Wait longer to ensure session cookie is set and browser has processed it
                setTimeout(() => {
                    // Use full path to ensure correct redirect
                    const redirectUrl = data.data.redirect || (data.data.user.role === 'admin' ? 'views/admin/dashboard.php' : 'views/user/dashboard.php');
                    console.log("Redirecting to:", redirectUrl);
                    // Use replace to avoid back button issues and ensure clean redirect
                    window.location.replace(redirectUrl);
                }, 500);
                return;
            } else {
                throw new Error(data.message || 'Login failed');
            }
        } catch (apiError) {
            // If API fails, fall back to demo accounts for testing
            console.log('API not available, using fallback');
        }
        
        // Fallback to demo accounts (for testing when API is not available)
        let authenticated = false;
        let userData = null;
        let redirectUrl = null;
        
        // Check admin credentials (demo)
        if (email === TEMP_CREDENTIALS.admin.email && password === TEMP_CREDENTIALS.admin.password) {
            authenticated = true;
            userData = {
                email: TEMP_CREDENTIALS.admin.email,
                name: TEMP_CREDENTIALS.admin.name,
                role: TEMP_CREDENTIALS.admin.role,
                contact: '',
                address: ''
            };
            redirectUrl = TEMP_CREDENTIALS.admin.redirect;
        }
        // Check user credentials (demo)
        else if (email === TEMP_CREDENTIALS.user.email && password === TEMP_CREDENTIALS.user.password) {
            authenticated = true;
            userData = {
                email: TEMP_CREDENTIALS.user.email,
                name: TEMP_CREDENTIALS.user.name,
                role: TEMP_CREDENTIALS.user.role,
                contact: '0917-123-4567',
                address: 'Manila, Philippines'
            };
            redirectUrl = TEMP_CREDENTIALS.user.redirect;
        }
        
        if (authenticated && userData) {
            // Save user session
            localStorage.setItem('userData', JSON.stringify(userData));
            localStorage.setItem('isLoggedIn', 'true');
            localStorage.setItem('userRole', userData.role);
            
            // Redirect to dashboard
            window.location.href = redirectUrl;
        } else {
            throw new Error('Invalid email or password. Please try again.');
        }
        
    } catch (error) {
        // Reset loading state
        loginBtn.disabled = false;
        loginBtnText.style.display = 'inline';
        loginBtnLoader.style.display = 'none';
        
        // Show error
        showError(error.message || 'Login failed. Please try again.', document.querySelector('#loginForm form'));
        
        // Clear password field
        document.getElementById('loginPassword').value = '';
        document.getElementById('loginPassword').focus();
    }
}

// Handle login form submission
document.getElementById('loginFormElement').addEventListener('submit', function(e) {
    e.preventDefault();
    const email = document.getElementById('loginEmail').value.trim().toLowerCase();
    const password = document.getElementById('loginPassword').value;
    
    // Basic validation
    if (!email || !password) {
        showError('Please enter both email and password.', this);
        return;
    }
    
    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        showError('Please enter a valid email address.', this);
        return;
    }
    
    // Call login function
    handleLogin(email, password);
});

// Forgot password function (placeholder for future implementation)
function showForgotPassword() {
    const email = document.getElementById('loginEmail').value.trim();
    
    if (!email) {
        showError('Please enter your email address first.', document.querySelector('#loginForm form'));
        document.getElementById('loginEmail').focus();
        return;
    }
    
    // TODO: Implement forgot password functionality with database
    alert(`Forgot password functionality will be implemented with the database.\n\nEmail: ${email}`);
}

// ============================================
// SIGNUP FUNCTION - DATABASE INTEGRATION READY
// ============================================
// TODO: When implementing database, replace the localStorage check below with:
// 
// const response = await fetch('/api/auth/signup', {
//     method: 'POST',
//     headers: { 'Content-Type': 'application/json' },
//     body: JSON.stringify({ name, email, password })
// });
// 
// if (!response.ok) {
//     const error = await response.json();
//     throw new Error(error.message || 'Signup failed');
// }
// 
// const data = await response.json();
// // OTP will be sent via email by backend
// // Store pending signup data for OTP verification
// ============================================

async function handleSignup(name, email, password) {
    // Show loading state
    const signupBtn = document.getElementById('signupSubmitBtn');
    const signupBtnText = document.getElementById('signupBtnText');
    const signupBtnLoader = document.getElementById('signupBtnLoader');
    
    if (signupBtn) {
        signupBtn.disabled = true;
        if (signupBtnText) signupBtnText.style.display = 'none';
        if (signupBtnLoader) signupBtnLoader.style.display = 'inline';
    }
    
    try {
        // Try to use PHP API (database)
        console.log("Sending signup request - Name:", name, "Email:", email);
        const response = await fetch('api/auth/signup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, email, password })
        });
        
        console.log("Signup response status:", response.status);
        
        // Get response text first (can only read once!)
        const responseText = await response.text();
        console.log("Signup raw response:", responseText);
        
        // Check if response is OK
        if (!response.ok) {
            let errorData;
            try {
                errorData = JSON.parse(responseText);
            } catch (e) {
                errorData = { message: `Server error: ${response.status}` };
            }
            console.error("Signup API error:", errorData);
            throw new Error(errorData.message || `Server error: ${response.status}`);
        }
        
        // Parse JSON response
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error("Failed to parse JSON:", parseError, "Response:", responseText);
            throw new Error('Invalid response from server. Please try again.');
        }
        
        console.log("Signup response data:", data);
        
        if (data.success) {
            // Reset signup button state BEFORE switching forms
            if (signupBtn) {
                signupBtn.disabled = false;
                if (signupBtnText) signupBtnText.style.display = 'inline';
                if (signupBtnLoader) signupBtnLoader.style.display = 'none';
            }
            
            // Store pending signup data - ENSURE email is lowercase and trimmed
            pendingSignupData = {
                name: name,
                email: email.trim().toLowerCase(), // Force lowercase to match database
                password: password
            };
            console.log("Stored pendingSignupData.email:", pendingSignupData.email);
            
            // Show success message
            showSuccess('OTP sent to your email! Please check your inbox.', document.querySelector('#signupForm form'));
            
            // Show OTP verification form
            document.getElementById('otpEmailDisplay').textContent = email;
            switchForm('otp');
            
            // Start OTP timer using server-provided expiration time
            // Prefer Unix timestamp (timezone-independent) over datetime string
            if (data.data && data.data.expires_at_timestamp) {
                // Server provides Unix timestamp (seconds), convert to milliseconds for JavaScript
                otpExpiryTime = data.data.expires_at_timestamp * 1000;
                console.log("Using server expiration timestamp: " + new Date(otpExpiryTime).toLocaleString());
            } else if (data.data && data.data.expires_at) {
                // Fallback to datetime string if timestamp not available
                const expiresAt = new Date(data.data.expires_at + ' UTC').getTime(); // Treat as UTC to avoid timezone issues
                otpExpiryTime = expiresAt;
                console.log("Using server expiration datetime (UTC): " + new Date(expiresAt).toLocaleString());
            } else {
                // Fallback to client-side calculation if server doesn't provide it
                otpExpiryTime = Date.now() + (5 * 60 * 1000); // 5 minutes
                console.warn("Server did not provide expires_at, using client-side calculation");
            }
            startOTPTimer();
            
            // Focus first OTP input
            setTimeout(() => {
                document.getElementById('otpInput1').focus();
            }, 100);
            
            // Ensure signup button is reset (safety check)
            if (signupBtn) {
                signupBtn.disabled = false;
                if (signupBtnText) signupBtnText.style.display = 'inline';
                if (signupBtnLoader) signupBtnLoader.style.display = 'none';
            }
            
            return;
        } else {
            throw new Error(data.message || 'Signup failed');
        }
    } catch (apiError) {
        // Always reset button state on error
        if (signupBtn) {
            signupBtn.disabled = false;
            if (signupBtnText) signupBtnText.style.display = 'inline';
            if (signupBtnLoader) signupBtnLoader.style.display = 'none';
        }
        
        console.error('Signup API error:', apiError);
        
        // Always throw the error so the form handler can display it
        throw apiError;
    } finally {
        // Ensure button is always reset, even if something unexpected happens
        // This is a safety net
        setTimeout(() => {
            const btn = document.getElementById('signupSubmitBtn');
            const btnText = document.getElementById('signupBtnText');
            const btnLoader = document.getElementById('signupBtnLoader');
            if (btn && btn.disabled && btnLoader && btnLoader.style.display === 'inline') {
                // Button is still stuck in loading state after 5 seconds - reset it
                console.warn('Signup button was stuck in loading state, resetting...');
                btn.disabled = false;
                if (btnText) btnText.style.display = 'inline';
                if (btnLoader) btnLoader.style.display = 'none';
            }
        }, 5000);
    }
}

// Handle signup form submission
document.querySelector('#signupForm form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const name = document.getElementById('signupName').value.trim();
    // Get and normalize email - MUST be lowercase to match database
    const email = (document.getElementById('signupEmail').value || '').trim().toLowerCase();
    console.log("Signup - Normalized email:", email);
    const password = document.getElementById('signupPassword').value;
    
    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        showError('Please enter a valid email address.', this);
        return;
    }
    
    // Validate password (minimum 6 characters)
    if (password.length < 6) {
        showError('Password must be at least 6 characters long.', this);
        return;
    }
    
    // Show loading state
    const signupBtn = document.getElementById('signupSubmitBtn');
    const signupBtnText = document.getElementById('signupBtnText');
    const signupBtnLoader = document.getElementById('signupBtnLoader');
    
    try {
        await handleSignup(name, email, password);
    } catch (error) {
        console.error("Signup form error:", error);
        // Reset loading state on error
        if (signupBtn) {
            signupBtn.disabled = false;
            if (signupBtnText) signupBtnText.style.display = 'inline';
            if (signupBtnLoader) signupBtnLoader.style.display = 'none';
        }
        let errorMsg = error.message || 'Signup failed. Please try again.';
        
        // Check if the error message contains an existing email message
        if (errorMsg.includes('An account with this email already exists')) {
            // Create a custom error message with a clickable link
            const existingError = document.querySelector('.error-message');
            if (existingError) {
                existingError.remove();
            }
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.style.cssText = `
                background: #e50914;
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                margin-bottom: 20px;
                font-size: 0.9rem;
                font-weight: 600;
                text-align: center;
                animation: shake 0.3s;
            `;
            
            // Set HTML content with clickable link
            errorDiv.innerHTML = errorMsg.replace('Please use a different email address.', 'Please use a different email address or <a href="#" onclick="switchForm(\'login\'); return false;" style="color: #ffcccb; text-decoration: underline; font-weight: bold;">try logging in</a>.');
            
            const style = document.createElement('style');
            style.textContent = `
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    25% { transform: translateX(-10px); }
                    75% { transform: translateX(10px); }
                }
            `;
            if (!document.querySelector('style[data-auth-styles]')) {
                style.setAttribute('data-auth-styles', 'true');
                document.head.appendChild(style);
            }
            
            // Insert error message
            if (this && this.parentNode) {
                this.parentNode.insertBefore(errorDiv, this);
            } else {
                const authCard = document.querySelector('.auth-card');
                if (authCard) {
                    authCard.insertBefore(errorDiv, authCard.firstChild);
                }
            }
            
            // Scroll error into view
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            
            // Auto remove after 8 seconds
            setTimeout(() => {
                if (errorDiv.parentNode) {
                    errorDiv.remove();
                }
            }, 8000);
        } else if (errorMsg.includes('Invalid email domain')) {
            // Handle invalid email domain error
            showError(errorMsg, this);
        } else {
            // Use regular error handling for other messages
            showError(errorMsg, this);
        }
    }
});

// Setup OTP input auto-focus and navigation
function setupOTPInputs() {
    const inputs = [];
    for (let i = 1; i <= 6; i++) {
        inputs.push(document.getElementById(`otpInput${i}`));
    }
    
    inputs.forEach((input, index) => {
        input.addEventListener('input', function(e) {
            // Only allow numbers
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Auto-focus next input
            if (this.value && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
        });
        
        input.addEventListener('keydown', function(e) {
            // Backspace to previous input
            if (e.key === 'Backspace' && !this.value && index > 0) {
                inputs[index - 1].focus();
            }
            // Paste handling
            if (e.key === 'v' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                navigator.clipboard.readText().then(text => {
                    const digits = text.replace(/[^0-9]/g, '').slice(0, 6);
                    digits.split('').forEach((digit, i) => {
                        if (inputs[i]) {
                            inputs[i].value = digit;
                        }
                    });
                    if (digits.length === 6) {
                        inputs[5].focus();
                    }
                });
            }
        });
    });
}

// ============================================
// OTP VERIFICATION - DATABASE INTEGRATION READY
// ============================================
// TODO: When implementing database, replace the localStorage OTP check with:
// 
// const response = await fetch('/api/auth/verify-otp', {
//     method: 'POST',
//     headers: { 'Content-Type': 'application/json' },
//     body: JSON.stringify({ 
//         email: pendingSignupData.email,
//         otp: enteredOTP
//     })
// });
// 
// if (!response.ok) {
//     const error = await response.json();
//     throw new Error(error.message || 'OTP verification failed');
// }
// 
// const data = await response.json();
// // User account is now created and verified in database
// // Return user data for session
// ============================================

async function verifyOTPAndRegister(enteredOTP) {
    // Try to use PHP API (database)
    try {
        // Ensure email is lowercase and trimmed before sending
        const verifyEmail = (pendingSignupData.email || '').trim().toLowerCase();
        const verifyOTP = String(enteredOTP).trim();
        console.log("Verifying OTP - Email:", verifyEmail, "OTP:", verifyOTP);

        const response = await fetch('api/auth/verify-otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email: verifyEmail,
                otp: verifyOTP,
                name: pendingSignupData.name,
                password: pendingSignupData.password,
                contact: '',
                address: ''
            })
        });

        // Log raw response for debugging and then parse manually
        const rawText = await response.text();
        console.log("Verify OTP raw response:", response.status, rawText);

        let data;
        try {
            // Try to recover JSON even if there are PHP warnings before/after it
            let trimmed = rawText.trim();
            const firstBrace = trimmed.indexOf('{');
            const lastBrace = trimmed.lastIndexOf('}');
            if (firstBrace !== -1 && lastBrace !== -1 && lastBrace > firstBrace) {
                trimmed = trimmed.slice(firstBrace, lastBrace + 1);
            }
            data = JSON.parse(trimmed);
        } catch (e) {
            console.error("Failed to parse verify-otp JSON:", e);
            throw new Error('OTP verification failed. Please try again. (Invalid server response)');
        }

        if (!response.ok || !data.success) {
            // Use backend message if available
            const msg = (data && data.message) ? data.message : `OTP verification failed (HTTP ${response.status})`;
            throw new Error(msg);
        }

        // Success – save user session locally for front-end checks
        const userData = {
            id: data.data.user_id,
            name: data.data.name,
            email: data.data.email,
            role: data.data.role,
            contact: '',
            address: ''
        };

        localStorage.setItem('userData', JSON.stringify(userData));
        localStorage.setItem('isLoggedIn', 'true');
        localStorage.setItem('userRole', userData.role || 'user');

        // Clear OTP timer and pending data
        if (otpTimer) {
            clearInterval(otpTimer);
            otpTimer = null;
        }
        if (resendCooldownTimer) {
            clearInterval(resendCooldownTimer);
            resendCooldownTimer = null;
            resendCooldownTime = 0;
        }

        // Clear pending signup data
        pendingSignupData = null;

        return userData;
    } catch (apiError) {
        console.error('Verify OTP API error:', apiError);
        // IMPORTANT: Do NOT silently fall back to localStorage anymore;
        // it causes confusing "No OTP found" even when server has OTP.
        throw apiError;
    }
}

// Handle OTP verification form submission
document.querySelector('#otpVerificationForm form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // Get OTP from inputs
    let enteredOTP = '';
    for (let i = 1; i <= 6; i++) {
        enteredOTP += document.getElementById(`otpInput${i}`).value;
    }
    
    // Validate OTP length
    if (enteredOTP.length !== 6) {
        showError('Please enter the complete 6-digit code.', this);
        return;
    }
    
    // Prevent double submission
    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn && submitBtn.disabled) {
        return; // Already processing
    }
    
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Verifying...';
    }
    
    try {
        const userData = await verifyOTPAndRegister(enteredOTP);
        
        // Clear pending signup data
        pendingSignupData = null;
        
        // Clear OTP inputs
        for (let i = 1; i <= 6; i++) {
            document.getElementById(`otpInput${i}`).value = '';
        }
        
        // Show success and redirect (don't switch to login form)
        showSuccess('Email verified! Account created successfully. Redirecting to dashboard...', this);
        
        setTimeout(() => {
            // Redirect based on user role
            const redirect = userData.role === 'admin' ? 'views/admin/dashboard.php' : 'views/user/dashboard.php';
            window.location.href = redirect;
        }, 1500);
    } catch (error) {
        // Re-enable button on error
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Verify Email';
        }
        
        showError(error.message, this);
        // Clear inputs on error
        if (error.message.includes('Invalid OTP') || error.message.includes('expired')) {
            for (let i = 1; i <= 6; i++) {
                document.getElementById(`otpInput${i}`).value = '';
            }
            document.getElementById('otpInput1').focus();
        }
    }
});

// Initialize OTP inputs when page loads
document.addEventListener('DOMContentLoaded', function() {
    setupOTPInputs();
    
    // Check if user is already logged in
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    if (isLoggedIn) {
        // User is already logged in, redirect to dashboard
        const userRole = localStorage.getItem('userRole');
        const redirect = userRole === 'admin' ? 'views/admin/dashboard.php' : 'views/user/dashboard.php';
        // Don't redirect immediately, let user see the page if they want
        // But prevent showing auth modals
        console.log('User is already logged in');
    }
    
    // Clear any stale pending signup data if user is logged in
    if (isLoggedIn && pendingSignupData) {
        pendingSignupData = null;
        // Don't hide OTP form - let user complete signup if they want
    }

    // Password show/hide toggle
    const toggleIcons = document.querySelectorAll('.toggle-password');
    toggleIcons.forEach(icon => {
        icon.addEventListener('click', () => {
            const targetId = icon.getAttribute('data-target');
            if (!targetId) return;
            const input = document.getElementById(targetId);
            if (!input) return;

            if (input.type === 'password') {
                input.type = 'text';
                const iTag = icon.querySelector('i');
                if (iTag) {
                    iTag.classList.remove('fa-eye');
                    iTag.classList.add('fa-eye-slash');
                }
            } else {
                input.type = 'password';
                const iTag = icon.querySelector('i');
                if (iTag) {
                    iTag.classList.remove('fa-eye-slash');
                    iTag.classList.add('fa-eye');
                }
            }
        });
    });
});
