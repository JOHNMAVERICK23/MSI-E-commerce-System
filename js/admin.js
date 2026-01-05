// File: js/admin.js
// UPDATED - Auto reload pagkatapos ng delete/update operations

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
    
    // ============================================
    // PRODUCT MODAL SETUP
    // ============================================
    const productModal = document.getElementById('productModal');
    const productForm = document.getElementById('productForm');
    const addProductBtn = document.getElementById('addProductBtn');
    const cancelProductBtn = document.getElementById('cancelProductBtn');

    // ADD PRODUCT BUTTON
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
    }

    // CANCEL PRODUCT BUTTON
    if (cancelProductBtn && productModal) {
        cancelProductBtn.addEventListener('click', () => {
            closeProductModal();
        });
    }

    // PRODUCT FORM SUBMIT
    if (productForm) {
        productForm.addEventListener('submit', handleProductSubmit);
    }

    // ============================================
    // STAFF MODAL SETUP
    // ============================================
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

    // ============================================
    // CLOSE BUTTONS (X button sa modals)
    // ============================================
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const modal = e.target.closest('.modal');
            if (modal) {
                modal.classList.remove('active');
            }
        });
    });
    
    // ============================================
    // CLICK OUTSIDE MODAL TO CLOSE
    // ============================================
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
    });
}

// ============================================
// PRODUCT MODAL FUNCTIONS
// ============================================

function closeProductModal() {
    const productModal = document.getElementById('productModal');
    if (productModal) {
        productModal.classList.remove('active');
    }
    const productForm = document.getElementById('productForm');
    if (productForm) {
        productForm.reset();
    }
    currentEditingProduct = null;
}

async function handleProductSubmit(e) {
    e.preventDefault();
    console.log('Product form submitted');

    // DISABLE BUTTON TO PREVENT DOUBLE SUBMIT
    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
    }

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
            toast.success('Success', data.message || 'Product saved successfully');
            
            // CLOSE MODAL AUTOMATICALLY at RELOAD
            setTimeout(() => {
                closeProductModal();
                loadProducts(); // AUTO RELOAD PRODUCTS
                loadDashboard(); // AUTO RELOAD DASHBOARD
            }, 500);
        } else {
            toast.error('Error', data.message || 'Failed to save product');
        }
    } catch (error) {
        console.error('Error saving product:', error);
        toast.error('Error', 'An error occurred while saving the product');
    } finally {
        // RE-ENABLE BUTTON
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = currentEditingProduct ? 'Update Product' : 'Save Product';
        }
    }
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
    console.log('Creating charts with stats:', stats);
    
    // ============================================
    // SALES CHART - LINE CHART
    // ============================================
    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        // Destroy existing chart if it exists
        if (salesChart) {
            salesChart.destroy();
        }
        
        // Get chart data
        const salesData = stats.salesData || { labels: [], revenues: [] };
        console.log('Sales Data:', salesData);
        
        // Ensure we have valid data arrays
        let labels = salesData.labels && Array.isArray(salesData.labels) && salesData.labels.length > 0
            ? salesData.labels
            : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        
        let revenues = salesData.revenues && Array.isArray(salesData.revenues) && salesData.revenues.length > 0
            ? salesData.revenues.map(val => parseFloat(val) || 0)
            : [0, 0, 0, 0, 0, 0, 0];
        
        console.log('Final Sales Chart Data:', { labels, revenues });
        
        try {
            salesChart = new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Daily Sales Revenue ($)',
                        data: revenues,
                        borderColor: '#ff4444',
                        backgroundColor: 'rgba(255, 68, 68, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 6,
                        pointBackgroundColor: '#ff4444',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverRadius: 8,
                        pointHoverBackgroundColor: '#ff6666',
                        segment: {
                            borderColor: ctx => ctx.p0DataIndex === ctx.p1DataIndex - 1 ? '#ff4444' : '#ff4444'
                        }
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                color: '#efefef',
                                font: {
                                    size: 13,
                                    weight: '600',
                                    family: 'Segoe UI'
                                },
                                padding: 15,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#ff4444',
                            borderWidth: 1,
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return '$ ' + context.parsed.y.toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#efefef',
                                font: {
                                    size: 12
                                },
                                callback: function(value) {
                                    return '$' + value.toFixed(0);
                                }
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.05)',
                                drawBorder: false
                            },
                            border: {
                                display: false
                            }
                        },
                        x: {
                            ticks: {
                                color: '#efefef',
                                font: {
                                    size: 12
                                }
                            },
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            border: {
                                display: false
                            }
                        }
                    }
                }
            });
            
            console.log('✅ Sales chart created successfully');
        } catch (error) {
            console.error('❌ Error creating sales chart:', error);
        }
    }

    // ============================================
    // ORDER STATUS CHART - DOUGHNUT CHART
    // ============================================
    const statusCtx = document.getElementById('orderStatusChart');
    if (statusCtx) {
        // Destroy existing chart if it exists
        if (orderStatusChart) {
            orderStatusChart.destroy();
        }

        // Get order status data
        const statusData = stats.orderStatusData || {
            pending: 0,
            processing: 0,
            completed: 0,
            cancelled: 0
        };
        
        console.log('Order Status Data:', statusData);
        
        // Ensure all values are numbers
        const pending = parseInt(statusData.pending) || 0;
        const processing = parseInt(statusData.processing) || 0;
        const completed = parseInt(statusData.completed) || 0;
        const cancelled = parseInt(statusData.cancelled) || 0;
        
        const dataValues = [pending, processing, completed, cancelled];
        const totalOrders = dataValues.reduce((a, b) => a + b, 0);
        
        console.log('Final Order Status Chart Data:', {
            pending, processing, completed, cancelled, total: totalOrders
        });
        
        try {
            orderStatusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'Processing', 'Completed', 'Cancelled'],
                    datasets: [{
                        label: 'Order Count',
                        data: [pending, processing, completed, cancelled],
                        backgroundColor: [
                            '#ff8800',    // Orange - Pending
                            '#3b82f6',    // Blue - Processing
                            '#22c55e',    // Green - Completed
                            '#dc2626'     // Red - Cancelled
                        ],
                        borderColor: '#131313',
                        borderWidth: 3,
                        hoverBorderColor: '#ffffff',
                        hoverBorderWidth: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                color: '#efefef',
                                font: {
                                    size: 12,
                                    weight: '600',
                                    family: 'Segoe UI'
                                },
                                padding: 20,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#ff4444',
                            borderWidth: 1,
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const percentage = totalOrders > 0 ? ((value / totalOrders) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
            
            console.log('Order status chart created successfully');
        } catch (error) {
            console.error(' Error creating order status chart:', error);
        }
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

// ============================================
// PRODUCTS MANAGEMENT
// ============================================

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
    
    toast.info('Loading', 'Loading product details...');
    
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
            // AUTO RELOAD IMMEDIATELY
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

// ============================================
// STAFF MANAGEMENT
// ============================================

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

    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Creating...';
    }

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
            
            const staffModal = document.getElementById('staffModal');
            const staffForm = document.getElementById('staffForm');
            
            if (staffModal) staffModal.classList.remove('active');
            if (staffForm) staffForm.reset();
            
            loadStaff(); // AUTO RELOAD
        } else {
            toast.error('Error', data.message || 'Failed to create staff account');
        }
    } catch (error) {
        console.error('Error creating staff:', error);
        toast.error('Error', 'Failed to create staff account');
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create Account';
        }
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
            loadStaff(); // AUTO RELOAD
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
            loadStaff(); // AUTO RELOAD
        } else {
            toast.error('Error', data.message || 'Failed to delete staff');
        }
    } catch (error) {
        console.error('Error deleting staff:', error);
        toast.error('Error', 'Failed to delete staff');
    }
}

// ============================================
// ORDERS MANAGEMENT
// ============================================

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

// File: js/admin.js - PARTIAL (ADD THESE FUNCTIONS AT THE END)
// ============================================
// REPORTS FUNCTIONALITY (NEW - ADD AT END OF FILE)
// ============================================

async function generateReport() {
    const reportType = document.getElementById('reportType').value;
    const reportPeriod = document.getElementById('reportPeriod').value;

    try {
        const response = await fetch(`php/reports.php?action=${reportType}_report&period=${reportPeriod}`);
        const data = await response.json();

        if (data.status === 'success') {
            displayReportData(data, reportType);
            toast.success('Success', 'Report generated successfully');
        } else {
            toast.error('Error', data.message || 'Failed to generate report');
        }
    } catch (error) {
        console.error('Error:', error);
        toast.error('Error', 'Failed to generate report');
    }
}

function displayReportData(data, reportType) {
    const reportContent = document.getElementById('reportContent');
    
    if (reportType === 'sales') {
        const summary = data.summary || {};
        let html = `
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon revenue">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <p class="stat-label">Total Revenue</p>
                        <h3>$${parseFloat(summary.total_revenue || 0).toFixed(2)}</h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orders">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <p class="stat-label">Total Orders</p>
                        <h3>${summary.total_orders || 0}</h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon products">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <p class="stat-label">Avg Order Value</p>
                        <h3>$${parseFloat(summary.avg_order_value || 0).toFixed(2)}</h3>
                    </div>
                </div>
            </div>
            <table style="width:100%; margin-top:20px; border-collapse:collapse;">
                <thead>
                    <tr style="background: var(--bg-muted); border-bottom: 2px solid var(--primary-red);">
                        <th style="padding:12px; text-align:left;">Date</th>
                        <th style="padding:12px; text-align:right;">Orders</th>
                        <th style="padding:12px; text-align:right;">Revenue</th>
                        <th style="padding:12px; text-align:right;">Avg Order</th>
                    </tr>
                </thead>
                <tbody>
        `;
        (data.data || []).forEach(row => {
            html += `
                <tr style="border-bottom:1px solid var(--border-color);">
                    <td style="padding:12px;">${row.date}</td>
                    <td style="padding:12px; text-align:right;">${row.order_count}</td>
                    <td style="padding:12px; text-align:right; color:var(--primary-red); font-weight:600;">$${parseFloat(row.total_revenue).toFixed(2)}</td>
                    <td style="padding:12px; text-align:right;">$${parseFloat(row.avg_order_value).toFixed(2)}</td>
                </tr>
            `;
        });
        html += `</tbody></table>`;
        reportContent.innerHTML = html;
    } 
    else if (reportType === 'orders') {
        const breakdown = data.status_breakdown || {};
        let html = `
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-info">
                        <p class="stat-label">Pending</p>
                        <h3>${breakdown.pending || 0}</h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-spinner"></i></div>
                    <div class="stat-info">
                        <p class="stat-label">Processing</p>
                        <h3>${breakdown.processing || 0}</h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <p class="stat-label">Completed</p>
                        <h3>${breakdown.completed || 0}</h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-info">
                        <p class="stat-label">Cancelled</p>
                        <h3>${breakdown.cancelled || 0}</h3>
                    </div>
                </div>
            </div>
        `;
        reportContent.innerHTML = html;
    }
    else if (reportType === 'products') {
        let html = `
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background: var(--bg-muted); border-bottom: 2px solid var(--primary-red);">
                        <th style="padding:12px; text-align:left;">Product</th>
                        <th style="padding:12px; text-align:right;">Price</th>
                        <th style="padding:12px; text-align:right;">Sales</th>
                        <th style="padding:12px; text-align:right;">Revenue</th>
                    </tr>
                </thead>
                <tbody>
        `;
        (data.data || []).forEach(row => {
            html += `
                <tr style="border-bottom:1px solid var(--border-color);">
                    <td style="padding:12px;">${row.name}</td>
                    <td style="padding:12px; text-align:right;">$${parseFloat(row.price).toFixed(2)}</td>
                    <td style="padding:12px; text-align:right;">${row.sales_count || 0}</td>
                    <td style="padding:12px; text-align:right; color:var(--primary-red); font-weight:600;">$${parseFloat(row.revenue || 0).toFixed(2)}</td>
                </tr>
            `;
        });
        html += `</tbody></table>`;
        reportContent.innerHTML = html;
    }
    else if (reportType === 'customers') {
        let html = `
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background: var(--bg-muted); border-bottom: 2px solid var(--primary-red);">
                        <th style="padding:12px; text-align:left;">Customer</th>
                        <th style="padding:12px; text-align:center;">Orders</th>
                        <th style="padding:12px; text-align:right;">Total Spent</th>
                    </tr>
                </thead>
                <tbody>
        `;
        (data.data || []).forEach(row => {
            html += `
                <tr style="border-bottom:1px solid var(--border-color);">
                    <td style="padding:12px;">${row.first_name} ${row.last_name}</td>
                    <td style="padding:12px; text-align:center;">${row.order_count || 0}</td>
                    <td style="padding:12px; text-align:right; color:var(--primary-red); font-weight:600;">$${parseFloat(row.total_spent || 0).toFixed(2)}</td>
                </tr>
            `;
        });
        html += `</tbody></table>`;
        reportContent.innerHTML = html;
    }
}

function exportReport(format) {
    const reportType = document.getElementById('reportType').value;
    const reportPeriod = document.getElementById('reportPeriod').value;

    if (format === 'csv') {
        window.location.href = `php/reports.php?action=export_csv&type=${reportType}&period=${reportPeriod}`;
    } else if (format === 'html') {
        window.location.href = `php/reports.php?action=export_html&type=${reportType}&period=${reportPeriod}`;
    }

    toast.success('Exporting', `Exporting ${reportType} report as ${format.toUpperCase()}...`);
}

// Export global functions
window.generateReport = generateReport;
window.exportReport = exportReport;
window.editProduct = editProduct;
window.deleteProduct = deleteProduct;
window.toggleStaffStatus = toggleStaffStatus;
window.deleteStaff = deleteStaff;

// Export functions for global access
window.editProduct = editProduct;
window.deleteProduct = deleteProduct;
window.toggleStaffStatus = toggleStaffStatus;
window.deleteStaff = deleteStaff;