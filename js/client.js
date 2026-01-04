let cart = [];
let allProducts = [];
let selectedProductId = null;

document.addEventListener('DOMContentLoaded', () => {
    console.log('Client page loaded');
    
    // Check if logged in (but DON'T redirect based on role - allow customers and guests)
    const user = getUser();
    
    // FIXED: Only redirect admins and staff, allow customers
    if (user && user.role === 'admin') {
        console.log('Admin detected, redirecting to admin.html');
        window.location.href = 'admin.html';
        return;
    }
    if (user && user.role === 'staff') {
        console.log('Staff detected, redirecting to staff.html');
        window.location.href = 'staff.html';
        return;
    }
    // Customers at guests allowed to stay on index.html

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

    const cartBtn = document.getElementById('cartBtn');
    if (cartBtn) cartBtn.addEventListener('click', toggleCart);
    
    const closeCart = document.querySelector('[onclick="closeCart()"]');
    if (closeCart) closeCart.addEventListener('click', () => toggleCart());
    
    const continueShopping = document.getElementById('continueShopping');
    if (continueShopping) continueShopping.addEventListener('click', () => toggleCart());

    const userBtn = document.getElementById('userBtn');
    if (userBtn) userBtn.addEventListener('click', toggleUserMenu);

    const logoutLink = document.getElementById('logoutLink');
    if (logoutLink) logoutLink.addEventListener('click', (e) => {
        e.preventDefault();
        logout();
    });

    const overlay = document.getElementById('overlay');
    if (overlay) overlay.addEventListener('click', closeAllModals);


    const searchInput = document.getElementById('searchInput');
    if (searchInput) searchInput.addEventListener('input', filterProducts);
    
    const categoryFilter = document.getElementById('categoryFilter');
    if (categoryFilter) categoryFilter.addEventListener('change', filterProducts);


    const checkoutBtn = document.getElementById('checkoutBtn');
    if (checkoutBtn) checkoutBtn.addEventListener('click', goToCheckout);


    const allCloseButtons = document.querySelectorAll('.close-modal');
    allCloseButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            const modalToClose = button.closest('.modal');
            if (modalToClose) {
                modalToClose.classList.remove('active');
            }
            const overlay = document.getElementById('overlay');
            if (overlay) {
                overlay.classList.remove('active');
            }
        });
    });

    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
                const overlay = document.getElementById('overlay');
                if (overlay) overlay.classList.remove('active');
            }
        });
    });


    const decreaseBtn = document.getElementById('decreaseQty');
    if (decreaseBtn) decreaseBtn.addEventListener('click', decreaseQuantity);
    
    const increaseBtn = document.getElementById('increaseQty');
    if (increaseBtn) increaseBtn.addEventListener('click', increaseQuantity);

    console.log('Event listeners setup complete');
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
        const productsList = document.getElementById('productsList');
        if (productsList) {
            productsList.innerHTML = '<p class="loading error">Error loading products</p>';
        }
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
    
    const product = allProducts.find(p => p.id == productId);
    
    if (!product) {
        console.error('Product not found with ID:', productId);
        showNotification('Product not found', 'error');
        return false;
    }

    selectedProductId = productId;

    // Update modal content
    document.getElementById('detailName').textContent = product.name;
    document.getElementById('detailCategory').textContent = product.category;
    document.getElementById('detailDescription').textContent = product.description || 'Premium gaming component';
    document.getElementById('detailPrice').textContent = '$' + parseFloat(product.price).toFixed(2);
    document.getElementById('detailStock').textContent = product.stock + ' available';
    
    const quantityInput = document.getElementById('quantityInput');
    if (quantityInput) {
        quantityInput.value = 1;
        quantityInput.max = product.stock;
    }

    // Add to cart button - FIXED para hindi mag-double
    const addToCartBtn = document.getElementById('addToCartBtn');
    if (addToCartBtn) {
        // Clone at replace para sa clean event handling
        const newBtn = addToCartBtn.cloneNode(true);
        addToCartBtn.parentNode.replaceChild(newBtn, addToCartBtn);
        
        // Attach event
        newBtn.onclick = function() {
            const quantity = parseInt(document.getElementById('quantityInput').value);
            
            if (product.stock < quantity) {
                showNotification('Not enough stock! Available: ' + product.stock, 'error');
                return;
            }
            
            addToCart(product, quantity);
            closeProductModal();
            showNotification('Added to cart!', 'success');
        };
    }

    // Show modal
    const productModal = document.getElementById('productModal');
    const overlay = document.getElementById('overlay');
    
    if (productModal) productModal.classList.add('active');
    if (overlay) overlay.classList.add('active');
    
    console.log('Product modal opened');
    return false;
}

function closeProductModal() {
    const productModal = document.getElementById('productModal');
    const overlay = document.getElementById('overlay');
    
    if (productModal) productModal.classList.remove('active');
    if (overlay) overlay.classList.remove('active');
    
    selectedProductId = null;
}

function increaseQuantity() {
    const input = document.getElementById('quantityInput');
    if (!input) return;
    
    const max = parseInt(input.max);
    if (parseInt(input.value) < max) {
        input.value = parseInt(input.value) + 1;
    }
}

function decreaseQuantity() {
    const input = document.getElementById('quantityInput');
    if (!input) return;
    
    if (parseInt(input.value) > 1) {
        input.value = parseInt(input.value) - 1;
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
    console.log('Removing from cart:', productId);
    cart = cart.filter(item => item.id !== productId);
    saveCartToStorage();
    updateCartUI();
    setupCartEventDelegation();  // Re-attach after update
}


function updateCartItemQuantity(productId, quantity) {
    console.log('Updating quantity:', productId, quantity);
    const item = cart.find(item => item.id === productId);
    if (item) {
        item.quantity = parseInt(quantity);
        if (item.quantity <= 0) {
            removeFromCart(productId);
        } else {
            saveCartToStorage();
            updateCartUI();
            setupCartEventDelegation();  // Re-attach after update
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
                <button class="cart-item-remove" data-id="${item.id}" type="button">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="cart-item-price">$${parseFloat(item.price).toFixed(2)}</div>
            <div class="cart-item-qty">
                <button class="cart-item-qty-btn" data-action="decrease" data-id="${item.id}" type="button">-</button>
                <input type="number" class="cart-item-qty-input" data-id="${item.id}" value="${item.quantity}" min="1">
                <button class="cart-item-qty-btn" data-action="increase" data-id="${item.id}" type="button">+</button>
                <span style="margin-left: auto; color: var(--primary-red); font-weight: 700;">
                    $${(item.price * item.quantity).toFixed(2)}
                </span>
            </div>
        </div>
    `).join('');

    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    cartTotal.textContent = '$' + total.toFixed(2);
}

function setupCartEventDelegation() {
    const cartItems = document.getElementById('cartItems');
    
    if (!cartItems) {
        console.error('Cart items container not found');
        return;
    }

    // Remove old listeners by cloning (efficient way)
    const newCartItems = cartItems.cloneNode(true);
    cartItems.parentNode.replaceChild(newCartItems, cartItems);

    // Single event listener sa container
    newCartItems.addEventListener('click', (e) => {
        const button = e.target.closest('button');
        
        if (!button) return;

        e.preventDefault();
        e.stopPropagation();

        // ============================================
        // DELETE BUTTON
        // ============================================
        if (button.classList.contains('cart-item-remove')) {
            const productId = parseInt(button.getAttribute('data-id'));
            console.log('Removing product:', productId);
            removeFromCart(productId);
            return;
        }

        // ============================================
        // DECREASE BUTTON
        // ============================================
        if (button.classList.contains('cart-item-qty-btn') && 
            button.getAttribute('data-action') === 'decrease') {
            const productId = parseInt(button.getAttribute('data-id'));
            const currentInput = newCartItems.querySelector(
                `.cart-item-qty-input[data-id="${productId}"]`
            );
            const currentQty = parseInt(currentInput.value);
            
            if (currentQty > 1) {
                updateCartItemQuantity(productId, currentQty - 1);
            }
            return;
        }

        // ============================================
        // INCREASE BUTTON
        // ============================================
        if (button.classList.contains('cart-item-qty-btn') && 
            button.getAttribute('data-action') === 'increase') {
            const productId = parseInt(button.getAttribute('data-id'));
            const currentInput = newCartItems.querySelector(
                `.cart-item-qty-input[data-id="${productId}"]`
            );
            const currentQty = parseInt(currentInput.value);
            
            updateCartItemQuantity(productId, currentQty + 1);
            return;
        }
    });

    // Quantity input change
    newCartItems.addEventListener('change', (e) => {
        if (e.target.classList.contains('cart-item-qty-input')) {
            const productId = parseInt(e.target.getAttribute('data-id'));
            const newQty = parseInt(e.target.value);
            
            if (newQty > 0) {
                updateCartItemQuantity(productId, newQty);
            }
        }
    });
}

function toggleCart() {
    const cartSidebar = document.getElementById('cartSidebar');
    const overlay = document.getElementById('overlay');
    
    if (cartSidebar) cartSidebar.classList.toggle('active');
    if (overlay) overlay.classList.toggle('active');
}

function toggleUserMenu() {
    const userDropdown = document.querySelector('.user-dropdown-content');
    if (userDropdown) userDropdown.classList.toggle('active');
}

function closeAllModals() {
    const cartSidebar = document.getElementById('cartSidebar');
    const productModal = document.getElementById('productModal');
    const overlay = document.getElementById('overlay');
    
    if (cartSidebar) cartSidebar.classList.remove('active');
    if (productModal) productModal.classList.remove('active');
    if (overlay) overlay.classList.remove('active');
}

function saveCartToStorage() {
    localStorage.setItem('cart', JSON.stringify(cart));
}

function loadCartFromStorage() {
    const stored = localStorage.getItem('cart');
    if (stored) {
        try {
            cart = JSON.parse(stored);
            updateCartUI();
            setupCartEventDelegation();  // ADD THIS LINE
        } catch (error) {
            console.error('Error parsing cart:', error);
            cart = [];
        }
    }
}

function goToCheckout() {
    const user = getUser();
    
    if (!user || user.role !== 'customer') {
        showNotification('Please login as customer to checkout', 'error');
        setTimeout(() => {
            const loginModal = document.getElementById('loginModal');
            const overlay = document.getElementById('overlay');
            if (loginModal) loginModal.classList.add('active');
            if (overlay) overlay.classList.add('active');
        }, 500);
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

// Export global functions
window.openProductDetail = openProductDetail;
window.closeProductModal = closeProductModal;
window.increaseQuantity = increaseQuantity;
window.decreaseQuantity = decreaseQuantity;
window.removeFromCart = removeFromCart;
window.updateCartItemQuantity = updateCartItemQuantity;
window.goToCheckout = goToCheckout;
window.toggleCart = toggleCart;