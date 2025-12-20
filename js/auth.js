// File: js/auth.js
// AUTHENTICATION - LOGIN & LOGOUT

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }

    checkIfLoggedIn();
});

async function handleLogin(e) {
    e.preventDefault();

    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();
    const errorMsg = document.getElementById('errorMessage');
    const successMsg = document.getElementById('successMessage');

    if (errorMsg) errorMsg.classList.remove('show');
    if (successMsg) successMsg.classList.remove('show');

    if (!username || !password) {
        if (errorMsg) {
            errorMsg.textContent = 'Please enter username and password';
            errorMsg.classList.add('show');
        }
        return;
    }

    try {
        console.log('Attempting login with username:', username);

        const response = await fetch('php/auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'login',
                username: username,
                password: password
            })
        });

        console.log('Response status:', response.status);

        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }

        const data = await response.json();
        console.log('Login response:', data);

        if (data.status === 'success') {
            if (successMsg) {
                successMsg.textContent = 'Login successful! Redirecting...';
                successMsg.classList.add('show');
            }

            localStorage.setItem('user', JSON.stringify(data.user));
            localStorage.setItem('token', data.token || '');
            localStorage.setItem('loginTime', new Date().getTime());

            console.log('User data stored:', data.user);

            setTimeout(() => {
                const redirectUrl = getRedirectUrl(data.user.role);
                console.log('Redirecting to:', redirectUrl);
                window.location.href = redirectUrl;
            }, 1500);
        } else {
            if (errorMsg) {
                errorMsg.textContent = data.message || 'Login failed';
                errorMsg.classList.add('show');
            }
            console.log('Login failed:', data.message);
        }
    } catch (error) {
        console.error('Error during login:', error);
        if (errorMsg) {
            errorMsg.textContent = 'An error occurred. Please try again.';
            errorMsg.classList.add('show');
        }
    }
}

function getRedirectUrl(role) {
    switch(role) {
        case 'admin':
            return 'admin.html';
        case 'staff':
            return 'staff.html';
        case 'customer':
        default:
            return 'index.html';
    }
}

function checkIfLoggedIn() {
    const user = getUser();
    
    if (user && window.location.pathname.includes('login.html')) {
        console.log('User already logged in, redirecting...');
        const redirectUrl = getRedirectUrl(user.role);
        window.location.href = redirectUrl;
    }
}

function logout() {
    console.log('Logging out...');
    localStorage.removeItem('user');
    localStorage.removeItem('token');
    localStorage.removeItem('loginTime');
    localStorage.removeItem('cart');
    window.location.href = 'login.html';
}

function getUser() {
    try {
        const user = localStorage.getItem('user');
        return user ? JSON.parse(user) : null;
    } catch (error) {
        console.error('Error parsing user data:', error);
        return null;
    }
}

function getToken() {
    return localStorage.getItem('token') || '';
}

function requireLogin(requiredRole = null) {
    const user = getUser();
    
    if (!user) {
        console.log('No user found, redirecting to login');
        window.location.href = 'login.html';
        return false;
    }

    if (requiredRole && user.role !== requiredRole) {
        console.log('User role mismatch. Required:', requiredRole, 'Got:', user.role);
        window.location.href = 'login.html';
        return false;
    }

    console.log('User authenticated:', user.username, 'Role:', user.role);
    return true;
}

function isLoggedIn() {
    return getUser() !== null;
}

// Customer login function for modal
async function handleQuickLogin(event) {
    if (event) {
        event.preventDefault();
    }
    
    const username = document.getElementById('loginUsername').value.trim();
    const password = document.getElementById('loginPassword').value.trim();
    const messageDiv = document.getElementById('loginMessage');

    console.log('Quick login attempt:', username);

    if (!username || !password) {
        if (messageDiv) {
            messageDiv.textContent = 'Please enter username and password';
            messageDiv.className = 'login-message show error';
        }
        return;
    }

    try {
        const response = await fetch('php/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'login_customer',
                username: username,
                password: password
            })
        });

        console.log('Response status:', response.status);

        if (!response.ok) {
            throw new Error('Network error: ' + response.status);
        }

        const data = await response.json();
        console.log('Login response:', data);

        if (data.status === 'success') {
            localStorage.setItem('user', JSON.stringify(data.user));
            localStorage.setItem('token', data.token);
            
            if (messageDiv) {
                messageDiv.textContent = 'Login successful! Redirecting...';
                messageDiv.className = 'login-message show success';
            }
            
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 1500);
        } else {
            if (messageDiv) {
                messageDiv.textContent = data.message || 'Login failed';
                messageDiv.className = 'login-message show error';
            }
        }
    } catch (error) {
        console.error('Login error:', error);
        if (messageDiv) {
            messageDiv.textContent = 'An error occurred: ' + error.message;
            messageDiv.className = 'login-message show error';
        }
    }
}

function goToLogin() {
    window.location.href = 'login.html';
}

function openLoginModal() {
    const loginModal = document.getElementById('loginModal');
    const overlay = document.getElementById('overlay');
    if (loginModal) loginModal.classList.add('active');
    if (overlay) overlay.classList.add('active');
}

function closeLoginModal() {
    const loginModal = document.getElementById('loginModal');
    const overlay = document.getElementById('overlay');
    if (loginModal) loginModal.classList.remove('active');
    if (overlay) overlay.classList.remove('active');
}

function showLoginMessage(message, type) {
    const messageDiv = document.getElementById('loginMessage');
    if (messageDiv) {
        messageDiv.textContent = message;
        messageDiv.className = 'login-message show ' + type;

        if (type === 'error') {
            setTimeout(() => {
                messageDiv.classList.remove('show');
            }, 4000);
        }
    }
}