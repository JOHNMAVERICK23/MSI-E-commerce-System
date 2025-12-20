// File: js/register.js

document.addEventListener('DOMContentLoaded', () => {
    const registerForm = document.getElementById('registerForm');
    
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
    }

    checkIfLoggedIn();
});

async function handleRegister(e) {
    e.preventDefault();

    const firstName = document.getElementById('firstName').value.trim();
    const lastName = document.getElementById('lastName').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const username = document.getElementById('regUsername').value.trim();
    const password = document.getElementById('regPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const phone = document.getElementById('phone').value.trim();
    const agree = document.getElementById('agree').checked;

    const errorMsg = document.getElementById('errorMessage');
    const successMsg = document.getElementById('successMessage');

    errorMsg.classList.remove('show');
    successMsg.classList.remove('show');

    // Validation
    if (!firstName || !lastName || !email || !username || !password || !phone) {
        showError('All fields are required', errorMsg);
        return;
    }

    if (password.length < 6) {
        showError('Password must be at least 6 characters', errorMsg);
        return;
    }

    if (password !== confirmPassword) {
        showError('Passwords do not match', errorMsg);
        return;
    }

    if (!agree) {
        showError('You must agree to the terms and conditions', errorMsg);
        return;
    }

    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        showError('Invalid email format', errorMsg);
        return;
    }

    try {
        const response = await fetch('php/auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'register_customer',
                firstName: firstName,
                lastName: lastName,
                username: username,
                email: email,
                password: password,
                phone: phone
            })
        });

        const data = await response.json();

        if (data.status === 'success') {
            showSuccess('Account created successfully! Redirecting to login...', successMsg);
            
            setTimeout(() => {
                window.location.href = 'login.html';
            }, 2000);
        } else {
            showError(data.message || 'Registration failed', errorMsg);
        }
    } catch (error) {
        console.error('Error:', error);
        showError('An error occurred. Please try again.', errorMsg);
    }
}

function showError(message, element) {
    element.textContent = message;
    element.classList.add('show');
}

function showSuccess(message, element) {
    element.textContent = message;
    element.classList.add('show');
}

function checkIfLoggedIn() {
    const user = getUser();
    
    if (user) {
        // Redirect if already logged in
        if (user.role === 'admin') {
            window.location.href = 'admin.html';
        } else if (user.role === 'staff') {
            window.location.href = 'staff.html';
        } else if (user.role === 'customer') {
            window.location.href = 'index.html';
        }
    }
}

function getUser() {
    try {
        const user = localStorage.getItem('user');
        return user ? JSON.parse(user) : null;
    } catch (error) {
        return null;
    }
}