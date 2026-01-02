// File: js/admin.js

let salesChart, orderStatusChart;
let currentEditingProduct = null;

document.addEventListener('DOMContentLoaded', () => {
    console.log('Admin page loaded');
    
    // Check authentication
    if (!requireLogin('admin')) return;

    const user = getUser();
    if (user && user.username) {
        document.getElementById('username').textContent = user.username;
    }

    // Setup navigation
    setupNavigation();

    // Setup modals
    setupModals();

    // Load initial data
    loadDashboard();
    loadProducts();
    loadStaff();
    loadOrders();
    
    // Add event listeners
    document.getElementById('logoutBtn').addEventListener('click', logout);
});

function setupNavigation() {
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const tabName = item.getAttribute('data-tab');
            
            // Remove active from all items
            navItems.forEach(nav => nav.classList.remove('active'));
            item.classList.add('active');

            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab
            const targetTab = document.getElementById(tabName);
            if (targetTab) {
                targetTab.classList.add('active');
            }

            // Trigger resize for charts
            if (salesChart) setTimeout(() => salesChart.resize(), 100);
            if (orderStatusChart) setTimeout(() => orderStatusChart.resize(), 100);
        });
    });
}

function setupModals() {
    console.log('Setting up modals...');
    
    // Product Modal
    const productModal = document.getElementById('productModal');
    const productForm = document.getElementById('productForm');
    const addProductBtn = document.getElementById('addProductBtn');
    const cancelProductBtn = document.getElementById('cancelProductBtn');

    if (addProductBtn) {
        console.log('Add product button found');
        addProductBtn.addEventListener('click', () => {
            console.log('Add product button clicked');
            currentEditingProduct = null;
            if (productForm) productForm.reset();
            if (productModal) {
                productModal.classList.add('active');
                console.log('Product modal opened');
            }
        });
    } else {
        console.error('Add product button not found!');
    }

    if (cancelProductBtn && productModal) {
        cancelProductBtn.addEventListener('click', () => {
            productModal.classList.remove('active');
        });
    }

    if (productForm) {
        productForm.addEventListener('submit', handleProductSubmit);
    }

    // Staff Modal
    const staffModal = document.getElementById('staffModal');
    const staffForm = document.getElementById('staffForm');
    const addStaffBtn = document.getElementById('addStaffBtn');
    const cancelStaffBtn = document.getElementById('cancelStaffBtn');

    if (addStaffBtn && staffModal) {
        addStaffBtn.addEventListener('click', () => {
            if (staffForm) staffForm.reset();
            staffModal.classList.add('active');
        });
    }

    if (cancelStaffBtn && staffModal) {
        cancelStaffBtn.addEventListener('click', () => {
            staffModal.classList.remove('active');
        });
    }

    if (staffForm) {
        staffForm.addEventListener('submit', handleStaffSubmit);
    }

    // Close modals on background click
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const modal = e.target.closest('.modal');
            if (modal) modal.classList.remove('active');
        });
    });
    
    // Close modal when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
    });
}


async function loadDashboard() {
    try {
        const response = await fetch('php/dashboard.php?action=get_stats');
        const data = await response.json();

        if (data.status === 'success') {
            const stats = data.data;

            // Update stat cards
            updateElementText('totalProducts', stats.totalProducts || 0);
            updateElementText('totalOrders', stats.totalOrders || 0);
            updateElementText('totalRevenue', '$' + parseFloat(stats.totalRevenue || 0).toFixed(2));
            updateElementText('activeStaff', stats.activeStaff || 0);

            // Create charts if data available
            if (stats.monthlyRevenue && stats.monthlyRevenue.length > 0) {
                createCharts(stats);
            }
            
            // Load activity log
            if (stats.recentActivity) {
                loadActivityLog(stats.recentActivity);
            }
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
    }
}

function updateElementText(id, text) {
    const element = document.getElementById(id);
    if (element) element.textContent = text;
}

function createCharts(stats) {
    // Sales Chart
    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        if (salesChart) salesChart.destroy();
        
        salesChart = new Chart(salesCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Sales',
                    data: [1200, 1900, 3000, 2500, 2200, 2900, 3200],
                    borderColor: 'hsl(0 85% 55%)',
                    backgroundColor: 'rgba(255, 68, 68, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    // Order Status Chart
    const statusCtx = document.getElementById('orderStatusChart');
    if (statusCtx) {
        if (orderStatusChart) orderStatusChart.destroy();

        orderStatusChart = new Chart(statusCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Processing', 'Completed', 'Cancelled'],
                datasets: [{
                    data: [
                        stats.ordersPending || 0,
                        stats.ordersProcessing || 0,
                        stats.ordersCompleted || 0,
                        stats.ordersCancelled || 0
                    ],
                    backgroundColor: [
                        'hsl(38 92% 50%)',
                        'hsl(200 100% 50%)',
                        'hsl(142 76% 36%)',
                        'hsl(0 72% 50%)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
}

function loadActivityLog(activities) {
    const activityLog = document.getElementById('activityLog');
    if (!activityLog) return;
    
    if (!activities || activities.length === 0) {
        activityLog.innerHTML = '<p class="loading">No recent activity</p>';
        return;
    }

    activityLog.innerHTML = activities.map(activity => `
        <div class="activity-item">
            <div class="activity-icon ${activity.type || 'info'}">
                <i class="fas fa-${getActivityIcon(activity.type)}"></i>
            </div>
            <div class="activity-details">
                <p class="activity-text">${activity.text || 'Activity'}</p>
                <p class="activity-time">${formatTime(activity.timestamp)}</p>
            </div>
        </div>
    `).join('');
}


function getActivityIcon(type) {
    const icons = {
        'add': 'plus',
        'edit': 'edit',
        'delete': 'trash',
        'staff': 'user',
        'order': 'shopping-cart',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}


function formatTime(timestamp) {
    if (!timestamp) return 'Recently';
    
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);

    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    if (hours < 24) return `${hours}h ago`;
    if (days < 7) return `${days}d ago`;
    
    return date.toLocaleDateString();
}

function requireLogin(requiredRole) {
    const user = getUser();
    
    if (!user) {
        window.location.href = 'login.html';
        return false;
    }

    if (requiredRole && user.role !== requiredRole) {
        window.location.href = 'login.html';
        return false;
    }

    return true;
}

function getUser() {
    try {
        const user = localStorage.getItem('user');
        return user ? JSON.parse(user) : null;
    } catch (error) {
        return null;
    }
}

function logout() {
    localStorage.removeItem('user');
    localStorage.removeItem('token');
    localStorage.removeItem('cart');
    window.location.href = 'login.html';
}

async function loadProducts() {
    try {
        const response = await fetch('php/products.php?action=list');
        const data = await response.json();

        const productsList = document.getElementById('productsList');
        if (!productsList) {
            console.error('Products list element not found');
            return;
        }

        if (data.status === 'success' && data.data.length > 0) {
            productsList.innerHTML = data.data.map(product => `
                <div class="product-card">
                    <div class="product-image">
                        ${product.image_url && product.image_url !== 'assets/default-product.png' 
                            ? `<img src="${product.image_url}" alt="${product.name}" style="width:100%; height:100%; object-fit:cover;">`
                            : `<i class="fas fa-box"></i>`
                        }
                    </div>
                    <div class="product-info">
                        <h3 class="product-name">${product.name}</h3>
                        <p class="product-category">${product.category}</p>
                        <p class="product-price">$${parseFloat(product.price).toFixed(2)}</p>
                        <p class="product-stock">Stock: ${product.stock}</p>
                        <div class="product-actions">
                            <button class="btn-small btn-edit" onclick="editProduct(${product.id})">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-small btn-delete" onclick="deleteProduct(${product.id})">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            productsList.innerHTML = '<p class="loading">No products found</p>';
        }
    } catch (error) {
        console.error('Error loading products:', error);
        const productsList = document.getElementById('productsList');
        if (productsList) {
            productsList.innerHTML = '<p class="loading error">Error loading products</p>';
        }
    }
}

function editProduct(productId) {
    console.log('Editing product:', productId);
    
    // Show loading state
    toast.info('Loading', 'Loading product details...');
    
    // Fetch product details
    fetch(`php/products.php?action=get&id=${productId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const product = data.data;
                currentEditingProduct = productId;
                
                // Populate form
                document.getElementById('productId').value = product.id;
                document.getElementById('productName').value = product.name;
                document.getElementById('productCategory').value = product.category;
                document.getElementById('productDesc').value = product.description || '';
                document.getElementById('productPrice').value = product.price;
                document.getElementById('productStock').value = product.stock;
                
                // Open modal
                const productModal = document.getElementById('productModal');
                if (productModal) {
                    productModal.classList.add('active');
                    toast.success('Loaded', 'Product details loaded');
                }
            } else {
                toast.error('Error', data.message || 'Failed to load product');
            }
        })
        .catch(error => {
            console.error('Error loading product:', error);
            toast.error('Error', 'Failed to load product details');
        });
}

async function deleteProduct(productId) {
    if (!confirm('Are you sure you want to delete this product?')) return;

    try {
        const response = await fetch('php/products.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'delete',
                id: productId 
            })
        });

        const data = await response.json();
        if (data.status === 'success') {
            toast.success('Success', 'Product deleted successfully');
            loadProducts();
            loadDashboard();
        } else {
            toast.error('Error', data.message || 'Failed to delete product');
        }
    } catch (error) {
        console.error('Error deleting product:', error);
        toast.error('Error', 'Failed to delete product');
    }
}

async function handleProductSubmit(e) {
    e.preventDefault();
    console.log('Product form submitted');

    const formData = new FormData();
    formData.append('action', currentEditingProduct ? 'update' : 'create');
    formData.append('id', document.getElementById('productId').value || '');
    formData.append('name', document.getElementById('productName').value);
    formData.append('category', document.getElementById('productCategory').value);
    formData.append('description', document.getElementById('productDesc').value);
    formData.append('price', document.getElementById('productPrice').value);
    formData.append('stock', document.getElementById('productStock').value);

    // Handle image upload
    const imageInput = document.getElementById('productImage');
    if (imageInput && imageInput.files[0]) {
        formData.append('image', imageInput.files[0]);
    }

    try {
        const response = await fetch('php/products.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        console.log('Product save response:', data);

        if (data.status === 'success') {
            toast.success('Success', data.message);
            document.getElementById('productModal').classList.remove('active');
            productForm.reset();
            loadProducts();
            loadDashboard();
        } else {
            toast.error('Error', data.message || 'Failed to save product');
        }
    } catch (error) {
        console.error('Error saving product:', error);
        toast.error('Error', 'An error occurred while saving the product');
    }
}

async function loadStaff() {
    try {
        const response = await fetch('php/auth.php?action=list_staff');
        const data = await response.json();

        const staffList = document.getElementById('staffList');

        if (data.status === 'success' && data.data.length > 0) {
            staffList.innerHTML = data.data.map(staff => `
                <div class="staff-card">
                    <div class="staff-info">
                        <h3 class="staff-name">${staff.username}</h3>
                        <p class="staff-email">${staff.email}</p>
                        <div class="staff-status">
                            <div class="status-indicator ${staff.status === 'active' ? 'active' : 'inactive'}"></div>
                            <span>${staff.status === 'active' ? 'Active' : 'Inactive'}</span>
                        </div>
                    </div>
                    <div class="staff-actions">
                        <button class="btn-toggle" onclick="toggleStaffStatus(${staff.id})">
                            ${staff.status === 'active' ? 'Deactivate' : 'Activate'}
                        </button>
                        <button class="btn-delete-staff" onclick="deleteStaff(${staff.id})">Delete</button>
                    </div>
                </div>
            `).join('');
        } else {
            staffList.innerHTML = '<p class="loading">No staff members found</p>';
        }
    } catch (error) {
        console.error('Error loading staff:', error);
    }
}

async function handleStaffSubmit(e) {
    e.preventDefault();

    const formData = {
        action: 'create_staff',
        username: document.getElementById('staffUsername').value,
        email: document.getElementById('staffEmail').value,
        password: document.getElementById('staffPassword').value
    };

    try {
        const response = await fetch('php/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const data = await response.json();

        if (data.status === 'success') {
            toast.success('Success', 'Staff account created successfully');
            document.getElementById('staffModal').classList.remove('active');
            document.getElementById('staffForm').reset();
            loadStaff();
        } else {
            toast.error('Error', data.message || 'Failed to create staff account');
        }
    } catch (error) {
        console.error('Error creating staff:', error);
        toast.error('Error', 'Failed to create staff account');
    }
}

async function toggleStaffStatus(staffId) {
    try {
        const response = await fetch('php/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'toggle_staff_status',
                id: staffId
            })
        });

        const data = await response.json();
        if (data.status === 'success') {
            toast.success('Success', 'Staff status updated');
            loadStaff();
        } else {
            toast.error('Error', data.message || 'Failed to update status');
        }
    } catch (error) {
        console.error('Error toggling staff status:', error);
        toast.error('Error', 'Failed to update staff status');
    }
}

async function deleteStaff(staffId) {
    if (!confirm('Are you sure you want to delete this staff member?')) return;

    try {
        const response = await fetch('php/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete_staff',
                id: staffId
            })
        });

        const data = await response.json();
        if (data.status === 'success') {
            toast.success('Success', 'Staff deleted successfully');
            loadStaff();
        } else {
            toast.error('Error', data.message || 'Failed to delete staff');
        }
    } catch (error) {
        console.error('Error deleting staff:', error);
        toast.error('Error', 'Failed to delete staff');
    }
}

async function loadOrders() {
    try {
        const response = await fetch('php/orders.php?action=list');
        const data = await response.json();

        const ordersList = document.getElementById('ordersList');

        if (data.status === 'success' && data.data.length > 0) {
            ordersList.innerHTML = data.data.map(order => `
                <div class="order-card">
                    <div class="order-header">
                        <h3 class="order-number">${order.order_number}</h3>
                        <span class="order-status ${order.status}">${order.status}</span>
                    </div>
                    <div class="order-details">
                        <div class="detail-item">
                            <span class="detail-label">Customer ID</span>
                            <span class="detail-value">${order.customer_id}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Total Amount</span>
                            <span class="detail-value">$${parseFloat(order.total_amount).toFixed(2)}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Date</span>
                            <span class="detail-value">${new Date(order.created_at).toLocaleDateString()}</span>
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            ordersList.innerHTML = '<p class="loading">No orders found</p>';
        }
    } catch (error) {
        console.error('Error loading orders:', error);
    }
}


// Logout
document.getElementById('logoutBtn').addEventListener('click', logout);

window.editProduct = editProduct;
window.deleteProduct = deleteProduct;
window.toggleStaffStatus = toggleStaffStatus;
window.deleteStaff = deleteStaff;