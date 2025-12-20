// File: js/admin.js

let salesChart, orderStatusChart;
let currentEditingProduct = null;

document.addEventListener('DOMContentLoaded', () => {
    // Check authentication
    if (!requireLogin('admin')) return;

    const user = getUser();
    document.getElementById('username').textContent = user.username;

    // Setup navigation
    setupNavigation();

    // Setup modals
    setupModals();

    // Load initial data
    loadDashboard();
    loadProducts();
    loadStaff();
    loadOrders();
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
            document.getElementById(tabName).classList.add('active');

            // Trigger resize for charts
            if (salesChart) setTimeout(() => salesChart.resize(), 100);
            if (orderStatusChart) setTimeout(() => orderStatusChart.resize(), 100);
        });
    });
}

function setupModals() {
    // Product Modal
    const productModal = document.getElementById('productModal');
    const productForm = document.getElementById('productForm');
    const addProductBtn = document.getElementById('addProductBtn');
    const cancelProductBtn = document.getElementById('cancelProductBtn');

    addProductBtn.addEventListener('click', () => {
        currentEditingProduct = null;
        productForm.reset();
        document.getElementById('productId').value = '';
        productModal.classList.add('active');
    });

    cancelProductBtn.addEventListener('click', () => {
        productModal.classList.remove('active');
    });

    productForm.addEventListener('submit', handleProductSubmit);

    // Staff Modal
    const staffModal = document.getElementById('staffModal');
    const staffForm = document.getElementById('staffForm');
    const addStaffBtn = document.getElementById('addStaffBtn');
    const cancelStaffBtn = document.getElementById('cancelStaffBtn');

    addStaffBtn.addEventListener('click', () => {
        staffForm.reset();
        staffModal.classList.add('active');
    });

    cancelStaffBtn.addEventListener('click', () => {
        staffModal.classList.remove('active');
    });

    staffForm.addEventListener('submit', handleStaffSubmit);

    // Close modals on background click
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.target.closest('.modal').classList.remove('active');
        });
    });
}

async function loadDashboard() {
    try {
        // Fetch dashboard data
        const response = await fetch('php/dashboard.php?action=get_stats');
        const data = await response.json();

        if (data.status === 'success') {
            const stats = data.data;

            // Update stat cards
            document.getElementById('totalProducts').textContent = stats.totalProducts;
            document.getElementById('totalOrders').textContent = stats.totalOrders;
            document.getElementById('totalRevenue').textContent = '$' + parseFloat(stats.totalRevenue).toFixed(2);
            document.getElementById('activeStaff').textContent = stats.activeStaff;

            // Create charts
            createCharts(stats);

            // Load activity log
            loadActivityLog(stats.recentActivity);
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
    }
}

function createCharts(stats) {
    // Sales Chart
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    if (salesChart) salesChart.destroy();
    
    salesChart = new Chart(salesCtx, {
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
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    labels: { color: 'hsl(0 0% 95%)' }
                }
            },
            scales: {
                y: {
                    ticks: { color: 'hsl(0 0% 95%)' },
                    grid: { color: 'hsl(0 0% 18%)' }
                },
                x: {
                    ticks: { color: 'hsl(0 0% 95%)' },
                    grid: { color: 'hsl(0 0% 18%)' }
                }
            }
        }
    });

    // Order Status Chart
    const statusCtx = document.getElementById('orderStatusChart').getContext('2d');
    if (orderStatusChart) orderStatusChart.destroy();

    orderStatusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Processing', 'Completed', 'Cancelled'],
            datasets: [{
                data: [stats.ordersPending, stats.ordersProcessing, stats.ordersCompleted, stats.ordersCancelled],
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
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    labels: { color: 'hsl(0 0% 95%)' }
                }
            }
        }
    });
}

function loadActivityLog(activities) {
    const activityLog = document.getElementById('activityLog');
    
    if (!activities || activities.length === 0) {
        activityLog.innerHTML = '<p class="loading">No recent activity</p>';
        return;
    }

    activityLog.innerHTML = activities.map(activity => `
        <div class="activity-item">
            <div class="activity-icon ${activity.type}">
                <i class="fas fa-${getActivityIcon(activity.type)}"></i>
            </div>
            <div class="activity-details">
                <p class="activity-text">${activity.text}</p>
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
        'order': 'shopping-cart'
    };
    return icons[type] || 'info';
}

function formatTime(timestamp) {
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

async function loadProducts() {
    try {
        const response = await fetch('php/products.php?action=list');
        const data = await response.json();

        const productsList = document.getElementById('productsList');

        if (data.status === 'success' && data.data.length > 0) {
            productsList.innerHTML = data.data.map(product => `
                <div class="product-card">
                    <div class="product-image">
                        <i class="fas fa-box"></i>
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
            productsList.innerHTML = '<p class="loading">No products yet</p>';
        }
    } catch (error) {
        console.error('Error loading products:', error);
    }
}

function editProduct(productId) {
    // Mock edit - in real implementation fetch product data
    const productModal = document.getElementById('productModal');
    document.getElementById('productId').value = productId;
    currentEditingProduct = productId;
    productModal.classList.add('active');
}

async function deleteProduct(productId) {
    if (!confirm('Are you sure you want to delete this product?')) return;

    try {
        const response = await fetch('php/products.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: productId })
        });

        const data = await response.json();
        if (data.status === 'success') {
            loadProducts();
            loadDashboard();
        }
    } catch (error) {
        console.error('Error deleting product:', error);
    }
}

async function handleProductSubmit(e) {
    e.preventDefault();

    const formData = {
        action: currentEditingProduct ? 'update' : 'create',
        id: document.getElementById('productId').value || null,
        name: document.getElementById('productName').value,
        category: document.getElementById('productCategory').value,
        description: document.getElementById('productDesc').value,
        price: document.getElementById('productPrice').value,
        stock: document.getElementById('productStock').value
    };

    try {
        const response = await fetch('php/products.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const data = await response.json();
        
        if (data.status === 'success') {
            document.getElementById('productModal').classList.remove('active');
            loadProducts();
            loadDashboard();
        }
    } catch (error) {
        console.error('Error saving product:', error);
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
            staffList.innerHTML = '<p class="loading">No staff members yet</p>';
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
            document.getElementById('staffModal').classList.remove('active');
            document.getElementById('staffForm').reset();
            loadStaff();
        }
    } catch (error) {
        console.error('Error creating staff:', error);
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
            loadStaff();
        }
    } catch (error) {
        console.error('Error toggling staff status:', error);
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
            loadStaff();
        }
    } catch (error) {
        console.error('Error deleting staff:', error);
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
                        <h3 class="order-number">Order #${order.order_number}</h3>
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
            ordersList.innerHTML = '<p class="loading">No orders yet</p>';
        }
    } catch (error) {
        console.error('Error loading orders:', error);
    }
}

// Logout
document.getElementById('logoutBtn').addEventListener('click', logout);