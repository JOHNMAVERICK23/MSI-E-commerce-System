// File: js/client.js
// CLIENT PRODUCT PAGE - COMPLETE FUNCTIONALITY

let cart = [];
let allProducts = [];
let selectedProductId = null;

document.addEventListener('DOMContentLoaded', () => {
    console.log('Client page loaded');
    
    // Check if logged in
    const user = getUser();
    if (user && user.role !== 'customer') {
        if (user.role === 'admin') window.location.href = 'admin.html';
        if (user.role === 'staff') window.location.href = 'staff.html';
    }

    // Load cart from storage
    loadCartFromStorage();
    
    // Load products
    loadProducts();

    // Setup event listeners
    setupEventListeners();
    
    console.log('Setup complete');
});

function setupEventListeners() {
    console.log('Setting up event listeners...');
    
    // Cart button
    const cartBtn = document.getElementById('cartBtn');
    if (cartBtn) cartBtn.addEventListener('click', toggleCart);
    
    const closeCart = document.getElementById('closeCart');
    if (closeCart) closeCart.addEventListener('click', () => toggleCart());
    
    const continueShopping = document.getElementById('continueShopping');
    if (continueShopping) continueShopping.addEventListener('click', () => toggleCart());

    // User button
    const userBtn = document.getElementById('userBtn');
    if (userBtn) userBtn.addEventListener('click', toggleUserMenu);

    // Logout
    const logoutLink = document.getElementById('logoutLink');
    if (logoutLink) logoutLink.addEventListener('click', (e) => {
        e.preventDefault();
        logout();
    });

    // Overlay click
    const overlay = document.getElementById('overlay');
    if (overlay) overlay.addEventListener('click', closeAllModals);

    // Search and filter
    const searchInput = document.getElementById('searchInput');
    if (searchInput) searchInput.addEventListener('input', filterProducts);
    
    const categoryFilter = document.getElementById('categoryFilter');
    if (categoryFilter) categoryFilter.addEventListener('change', filterProducts);

    // Checkout
    const checkoutBtn = document.getElementById('checkoutBtn');
    if (checkoutBtn) checkoutBtn.addEventListener('click', goToCheckout);

    // --- ITO ANG INAYOS NA BAHAGI ---
    // Ngayon, pinapakinggan na nito ang LAHAT ng close buttons
    const allCloseButtons = document.querySelectorAll('.close-modal');
    allCloseButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Isara ang pinakamalapit na modal na parent ng button
            const modalToClose = button.closest('.modal');
            if (modalToClose) {
                modalToClose.classList.remove('active');
            }
            // Isara din ang overlay
            const overlay = document.getElementById('overlay');
            if (overlay) {
                overlay.classList.remove('active');
            }
        });
    });
    // --- WAKAS NG INAYOS NA BAHAGI ---

    // Quantity buttons in modal
    const decreaseBtn = document.getElementById('decreaseQty');
    if (decreaseBtn) decreaseBtn.addEventListener('click', decreaseQuantity);
    
    const increaseBtn = document.getElementById('increaseQty');
    if (increaseBtn) increaseBtn.addEventListener('click', increaseQuantity);

    // Add to cart button
    const addToCartBtn = document.getElementById('addToCartBtn');
    if (addToCartBtn) addToCartBtn.addEventListener('click', addToCartFromModal);
}

async function loadProducts() {
    console.log('Loading products...');
    try {
        const response = await fetch('php/products.php?action=list');
        const data = await response.json();

        console.log('Products response:', data);

        if (data.status === 'success') {
            allProducts = data.data;
            console.log('Products loaded:', allProducts.length);
            displayProducts(allProducts);
        }
    } catch (error) {
        console.error('Error loading products:', error);
    }
}

function displayProducts(products) {
    console.log('Displaying products:', products.length);
    const productsList = document.getElementById('productsList');

    if (!productsList) {
        console.error('productsList element not found');
        return;
    }

    if (!products || products.length === 0) {
        productsList.innerHTML = '<p class="loading">No products found</p>';
        return;
    }

    productsList.innerHTML = products.map(product => `
        <div class="product-card">
            <div class="product-image">
                ${product.image_url ? `<img src="${product.image_url}" alt="${product.name}">` : '<i class="fas fa-box"></i>'}
            </div>
            <div class="product-body">
                <h3 class="product-title">${product.name}</h3>
                <p class="product-category">${product.category}</p>
                <p class="product-description">${product.description || 'Premium gaming component'}</p>
                <div class="product-footer">
                    <span class="product-price">$${parseFloat(product.price).toFixed(2)}</span>
                    <button class="btn-view" onclick="openProductDetail(${product.id})">View Details</button>
                </div>
            </div>
        </div>
    `).join('');
    
    console.log('Products displayed');
}

function filterProducts() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const category = document.getElementById('categoryFilter').value;

    const filtered = allProducts.filter(product => {
        const matchesSearch = product.name.toLowerCase().includes(searchTerm) ||
                            product.category.toLowerCase().includes(searchTerm);
        const matchesCategory = !category || product.category === category;
        return matchesSearch && matchesCategory;
    });

    displayProducts(filtered);
}

function openProductDetail(productId) {
    console.log('Opening product detail for ID:', productId);
    
    // INAYOS DITO: Pinalitan ang parseInt at === ng mas simpleng ==
    const product = allProducts.find(p => p.id == productId);
    
    if (!product) {
        console.error('Produkto na may ID na', productId, 'ay hindi mahanap.');
        alert('Product not found');
        return false;
    }

    selectedProductId = productId; // Itago ang ID ng napiling produkto

    // I-populate ang modal ng product info
    document.getElementById('detailName').textContent = product.name;
    document.getElementById('detailCategory').textContent = product.category;
    document.getElementById('detailDescription').textContent = product.description || 'Premium gaming component';
    document.getElementById('detailPrice').textContent = '$' + parseFloat(product.price).toFixed(2);
    document.getElementById('detailStock').textContent = product.stock + ' in stock';
    
    const quantityInput = document.getElementById('quantityInput');
    quantityInput.value = 1;
    quantityInput.max = product.stock; // Itakda ang maximum na pwedeng bilhin base sa stock

    // Ipakita ang modal
    document.getElementById('productModal').classList.add('active');
    document.getElementById('overlay').classList.add('active');
    
    console.log('Product modal opened');
    return false;
}

function increaseQuantity() {
    const input = document.getElementById('quantityInput');
    const max = parseInt(input.max);
    if (parseInt(input.value) < max) {
        input.value = parseInt(input.value) + 1;
    }
}

function decreaseQuantity() {
    const input = document.getElementById('quantityInput');
    if (parseInt(input.value) > 1) {
        input.value = parseInt(input.value) - 1;
    }
}

function addToCartFromModal() {
    const quantity = parseInt(document.getElementById('quantityInput').value);
    
    // Siguraduhin na may napiling produkto
    if (!selectedProductId) {
        showNotification('Walang napiling produkto.', 'error');
        return;
    }
    
    // INAYOS DITO: Gumamit din ng == para consistent
    const product = allProducts.find(p => p.id == selectedProductId);

    if (product) {
        addToCart(product, quantity);
        closeProductModal();
        showNotification('Added to cart!', 'success');
    } else {
        console.error('Produkto na may ID na', selectedProductId, 'ay hindi mahanap.');
        showNotification('Hindi ma-add sa cart ang produkto.', 'error');
    }
}
function addToCart(product, quantity) {
    console.log('Adding to cart:', product.name, 'Qty:', quantity);
    const existingItem = cart.find(item => item.id === product.id);

    if (existingItem) {
        existingItem.quantity += quantity;
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: product.price,
            quantity: quantity
        });
    }

    saveCartToStorage();
    updateCartUI();
}

function removeFromCart(productId) {
    cart = cart.filter(item => item.id !== productId);
    saveCartToStorage();
    updateCartUI();
}

function updateCartItemQuantity(productId, quantity) {
    const item = cart.find(item => item.id === productId);
    if (item) {
        item.quantity = parseInt(quantity);
        if (item.quantity <= 0) {
            removeFromCart(productId);
        } else {
            saveCartToStorage();
            updateCartUI();
        }
    }
}

function updateCartUI() {
    console.log('Updating cart UI. Items:', cart.length);
    const cartCount = document.getElementById('cartCount');
    const cartItems = document.getElementById('cartItems');
    const cartTotal = document.getElementById('cartTotal');

    if (!cartCount || !cartItems || !cartTotal) {
        console.error('Cart UI elements not found');
        return;
    }

    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    cartCount.textContent = totalItems;

    if (cart.length === 0) {
        cartItems.innerHTML = '<p class="empty-cart">Your cart is empty</p>';
        cartTotal.textContent = '$0.00';
        return;
    }

    cartItems.innerHTML = cart.map(item => `
        <div class="cart-item">
            <div class="cart-item-header">
                <p class="cart-item-name">${item.name}</p>
                <button class="cart-item-remove" onclick="removeFromCart(${item.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="cart-item-price">$${parseFloat(item.price).toFixed(2)}</div>
            <div class="cart-item-qty">
                <button onclick="updateCartItemQuantity(${item.id}, ${item.quantity - 1})">-</button>
                <input type="number" value="${item.quantity}" min="1" onchange="updateCartItemQuantity(${item.id}, this.value)">
                <button onclick="updateCartItemQuantity(${item.id}, ${item.quantity + 1})">+</button>
                <span style="margin-left: auto; color: var(--primary-red); font-weight: 700;">
                    $${(item.price * item.quantity).toFixed(2)}
                </span>
            </div>
        </div>
    `).join('');

    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    cartTotal.textContent = '$' + total.toFixed(2);
}

function toggleCart() {
    const cartSidebar = document.getElementById('cartSidebar');
    const overlay = document.getElementById('overlay');
    
    if (cartSidebar) cartSidebar.classList.toggle('active');
    if (overlay) overlay.classList.toggle('active');
}

function toggleUserMenu() {
    const userMenu = document.getElementById('userMenu');
    if (userMenu) userMenu.classList.toggle('active');
}

function closeAllModals() {
    const cartSidebar = document.getElementById('cartSidebar');
    const userMenu = document.getElementById('userMenu');
    const overlay = document.getElementById('overlay');
    
    if (cartSidebar) cartSidebar.classList.remove('active');
    if (userMenu) userMenu.classList.remove('active');
    if (overlay) overlay.classList.remove('active');
}

function saveCartToStorage() {
    localStorage.setItem('cart', JSON.stringify(cart));
}

function loadCartFromStorage() {
    const stored = localStorage.getItem('cart');
    if (stored) {
        cart = JSON.parse(stored);
        updateCartUI();
    }
}

function goToCheckout() {
    const user = getUser();
    
    if (!user || user.role !== 'customer') {
        showNotification('Please login as customer to checkout', 'error');
        setTimeout(() => {
            window.location.href = 'login.html';
        }, 1500);
        return;
    }
    
    if (cart.length === 0) {
        showNotification('Cart is empty!', 'error');
        return;
    }
    window.location.href = 'checkout.html';
}

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? 'hsl(142 76% 36%)' : 'hsl(0 72% 50%)'};
        color: white;
        border-radius: 6px;
        z-index: 1000;
        animation: slideIn 0.3s ease;
        font-weight: 600;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 2000);
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

function logout() {
    localStorage.removeItem('user');
    localStorage.removeItem('token');
    window.location.href = 'login.html';
}