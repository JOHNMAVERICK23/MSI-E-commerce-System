// File: js/checkout.js
// CHECKOUT PAGE WITH PAYMENT METHODS

let cart = [];
const SHIPPING_FEE = 10;
const TAX_RATE = 0.10;

document.addEventListener('DOMContentLoaded', () => {
    // Check if customer logged in
    const user = getUser();
    if (!user || user.role !== 'customer') {
        alert('Please login as customer to checkout');
        window.location.href = 'login.html';
        return;
    }

    loadCart();
    setupPaymentMethods();
    setupFormValidation();
    document.getElementById('placeOrderBtn').addEventListener('click', handlePlaceOrder);
});

function loadCart() {
    const stored = localStorage.getItem('cart');
    if (stored) {
        cart = JSON.parse(stored);
    }

    if (cart.length === 0) {
        alert('Your cart is empty!');
        window.location.href = 'index.html';
        return;
    }

    displayOrderSummary();
}

function displayOrderSummary() {
    const summaryItems = document.getElementById('summaryItems');
    
    summaryItems.innerHTML = cart.map(item => `
        <div class="summary-item">
            <div class="item-details">
                <p class="item-name">${item.name}</p>
                <p class="item-qty">Qty: ${item.quantity}</p>
            </div>
            <span class="item-price">$${(item.price * item.quantity).toFixed(2)}</span>
        </div>
    `).join('');

    updateOrderTotals();
}

function updateOrderTotals() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const tax = subtotal * TAX_RATE;
    const total = subtotal + SHIPPING_FEE + tax;

    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('tax').textContent = '$' + tax.toFixed(2);
    document.getElementById('totalPrice').textContent = '$' + total.toFixed(2);
}

function setupPaymentMethods() {
    const paymentOptions = document.querySelectorAll('input[name="payment"]');
    
    paymentOptions.forEach(option => {
        option.addEventListener('change', (e) => {
            const selectedMethod = e.target.value;
            
            // Hide all forms
            const gcashForm = document.getElementById('gcashForm');
            const payMayaForm = document.getElementById('payMayaForm');
            const creditCardForm = document.getElementById('creditCardForm');
            
            if (gcashForm) gcashForm.classList.remove('active');
            if (payMayaForm) payMayaForm.classList.remove('active');
            if (creditCardForm) creditCardForm.classList.remove('active');
            
            // Remove active class from all options
            document.querySelectorAll('.payment-option').forEach(label => {
                label.classList.remove('active');
            });
            
            // Show selected form
            if (selectedMethod === 'gcash' && gcashForm) {
                gcashForm.classList.add('active');
            } else if (selectedMethod === 'paymaya' && payMayaForm) {
                payMayaForm.classList.add('active');
            } else if (selectedMethod === 'credit-card' && creditCardForm) {
                creditCardForm.classList.add('active');
            }
            
            // Add active class to selected option
            e.target.closest('label').classList.add('active');
        });
    });
}

function setupFormValidation() {
    // Format card number
    const cardNumberInput = document.getElementById('cardNumber');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\s/g, '');
            let formatted = value.replace(/(\d{4})/g, '$1 ').trim();
            e.target.value = formatted;
        });
    }

    // Format expiry date
    const expiryInput = document.getElementById('expiryDate');
    if (expiryInput) {
        expiryInput.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });
    }

    // CVV only numbers
    const cvvInput = document.getElementById('cvv');
    if (cvvInput) {
        cvvInput.addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 3);
        });
    }
}

async function handlePlaceOrder() {
    if (!validateForms()) {
        return;
    }

    const btn = document.getElementById('placeOrderBtn');
    btn.disabled = true;

    try {
        const paymentMethod = document.querySelector('input[name="payment"]:checked').value;
        
        const subtotalText = document.getElementById('subtotal').textContent.replace('$', '');
        const taxText = document.getElementById('tax').textContent.replace('$', '');
        const totalText = document.getElementById('totalPrice').textContent.replace('$', '');
        
        const orderData = {
            action: 'create_order',
            customer_info: {
                fullName: document.getElementById('fullName').value,
                email: document.getElementById('email').value,
                phone: document.getElementById('phone').value,
                address: document.getElementById('address').value,
                city: document.getElementById('city').value,
                postalCode: document.getElementById('postalCode').value
            },
            items: cart,
            payment: {
                method: paymentMethod,
                details: getPaymentDetails(paymentMethod)
            },
            totals: {
                subtotal: parseFloat(subtotalText),
                shipping: SHIPPING_FEE,
                tax: parseFloat(taxText),
                total: parseFloat(totalText)
            }
        };

        const response = await fetch('php/orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(orderData)
        });

        const data = await response.json();

        if (data.status === 'success') {
            showSuccess('Order placed successfully!');
            localStorage.removeItem('cart');
            
            setTimeout(() => {
                window.location.href = 'order-confirmation.html?orderId=' + data.orderId;
            }, 2000);
        } else {
            showError(data.message || 'Error processing order');
            btn.disabled = false;
        }
    } catch (error) {
        console.error('Error:', error);
        showError('An error occurred');
        btn.disabled = false;
    }
}

function getPaymentDetails(method) {
    switch(method) {
        case 'gcash':
            const gcashNum = document.getElementById('gcashNumber');
            return { gcashNumber: gcashNum ? gcashNum.value : '' };
        case 'paymaya':
            const payMayaNum = document.getElementById('payMayaNumber');
            return { payMayaNumber: payMayaNum ? payMayaNum.value : '' };
        case 'credit-card':
            const cardNum = document.getElementById('cardNumber');
            const expiry = document.getElementById('expiryDate');
            const cvv = document.getElementById('cvv');
            return {
                cardNumber: cardNum ? cardNum.value : '',
                expiryDate: expiry ? expiry.value : '',
                cvv: cvv ? cvv.value : ''
            };
        default:
            return {};
    }
}

function validateForms() {
    const shippingForm = document.getElementById('shippingForm');
    if (!shippingForm.checkValidity()) {
        showError('Please fill in all shipping information');
        return false;
    }

    const termsCheckbox = document.getElementById('termsCheckbox');
    if (!termsCheckbox.checked) {
        showError('Please agree to the terms and conditions');
        return false;
    }

    const method = document.querySelector('input[name="payment"]:checked').value;
    
    if (method === 'gcash') {
        const gcashNumber = document.getElementById('gcashNumber').value.trim();
        if (!gcashNumber) {
            showError('Please enter GCash number');
            return false;
        }
    } else if (method === 'paymaya') {
        const payMayaNumber = document.getElementById('payMayaNumber').value.trim();
        if (!payMayaNumber) {
            showError('Please enter PayMaya email or mobile number');
            return false;
        }
    } else if (method === 'credit-card') {
        const cardNumber = document.getElementById('cardNumber').value.replace(/\s/g, '');
        if (cardNumber.length !== 16) {
            showError('Invalid card number');
            return false;
        }
        const expiry = document.getElementById('expiryDate').value;
        if (!/^\d{2}\/\d{2}$/.test(expiry)) {
            showError('Invalid expiry date');
            return false;
        }
        const cvv = document.getElementById('cvv').value;
        if (cvv.length !== 3) {
            showError('Invalid CVV');
            return false;
        }
    }

    return true;
}

function showError(message) {
    const errorDiv = document.getElementById('errorMessage');
    if (errorDiv) {
        errorDiv.textContent = message;
        errorDiv.classList.add('show');
        
        setTimeout(() => {
            errorDiv.classList.remove('show');
        }, 4000);
    }
}

function showSuccess(message) {
    const successDiv = document.getElementById('successMessage');
    if (successDiv) {
        successDiv.textContent = message;
        successDiv.classList.add('show');
    }
}

function getUser() {
    try {
        const user = localStorage.getItem('user');
        return user ? JSON.parse(user) : null;
    } catch (error) {
        console.error('Error parsing user:', error);
        return null;
    }
}