// File: js/staff.js

let allOrders = [];
let selectedOrder = null;

document.addEventListener('DOMContentLoaded', () => {
    // Check authentication
    if (!requireLogin('staff')) return;

    const user = getUser();
    document.getElementById('username').textContent = user.username;

    // Setup navigation
    setupNavigation();

    // Load data
    loadOrders();
    loadActivity();

    // Setup event listeners
    document.getElementById('statusFilter').addEventListener('change', filterOrders);
    document.getElementById('updateStatusBtn').addEventListener('click', updateOrderStatus);
    document.querySelector('.close-modal').addEventListener('click', closeOrderModal);
    document.getElementById('overlay').addEventListener('click', closeOrderModal);
});

function setupNavigation() {
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const tabName = item.getAttribute('data-tab');
            
            navItems.forEach(nav => nav.classList.remove('active'));
            item.classList.add('active');

            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            document.getElementById(tabName).classList.add('active');
        });
    });
}

async function loadOrders() {
    try {
        const response = await fetch('php/orders.php?action=list');
        const data = await response.json();

        if (data.status === 'success') {
            allOrders = data.data;
            displayOrders(allOrders);
            updateOrderStats();
        }
    } catch (error) {
        console.error('Error loading orders:', error);
    }
}

function displayOrders(orders) {
    const ordersList = document.getElementById('ordersList');

    if (orders.length === 0) {
        ordersList.innerHTML = '<p class="loading">No orders found</p>';
        return;
    }

    ordersList.innerHTML = orders.map(order => `
        <div class="order-card" onclick="openOrderDetail(${order.id})">
            <div class="order-card-header">
                <h3 class="order-number">#${order.order_number}</h3>
                <span class="order-status ${order.status}">${order.status}</span>
            </div>
            <div class="order-card-body">
                <div class="order-detail-item">
                    <span class="detail-label">Customer ID</span>
                    <span class="detail-value">${order.customer_id}</span>
                </div>
                <div class="order-detail-item">
                    <span class="detail-label">Amount</span>
                    <span class="detail-value price">$${parseFloat(order.total_amount).toFixed(2)}</span>
                </div>
                <div class="order-detail-item">
                    <span class="detail-label">Date</span>
                    <span class="detail-value">${new Date(order.created_at).toLocaleDateString()}</span>
                </div>
                <div class="order-detail-item">
                    <span class="detail-label">Time</span>
                    <span class="detail-value">${new Date(order.created_at).toLocaleTimeString()}</span>
                </div>
            </div>
        </div>
    `).join('');
}

function filterOrders() {
    const status = document.getElementById('statusFilter').value;
    
    if (!status) {
        displayOrders(allOrders);
    } else {
        const filtered = allOrders.filter(order => order.status === status);
        displayOrders(filtered);
    }
}

function updateOrderStats() {
    document.getElementById('totalOrders').textContent = allOrders.length;
    document.getElementById('pendingOrders').textContent = allOrders.filter(o => o.status === 'pending').length;
    document.getElementById('processingOrders').textContent = allOrders.filter(o => o.status === 'processing').length;
}

function openOrderDetail(orderId) {
    const order = allOrders.find(o => o.id === orderId);
    if (!order) return;

    selectedOrder = order;

    const detailContent = document.getElementById('orderDetail');
    
    detailContent.innerHTML = `
        <div class="order-info-section">
            <h4 class="section-title">Order Information</h4>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Order Number</span>
                    <span class="info-value">#${order.order_number}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status</span>
                    <span class="info-value">${order.status}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Total Amount</span>
                    <span class="info-value">$${parseFloat(order.total_amount).toFixed(2)}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Order Date</span>
                    <span class="info-value">${new Date(order.created_at).toLocaleDateString()}</span>
                </div>
            </div>
        </div>

        <div class="order-info-section">
            <h4 class="section-title">Customer Information</h4>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Customer ID</span>
                    <span class="info-value">${order.customer_id}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status</span>
                    <span class="info-value">Active</span>
                </div>
            </div>
        </div>

        <div class="order-info-section">
            <h4 class="section-title">Order Items</h4>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Sample Product</td>
                        <td>1</td>
                        <td>$99.99</td>
                        <td>$99.99</td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;

    document.getElementById('statusSelect').value = order.status;
    document.getElementById('orderModal').classList.add('active');
    document.getElementById('overlay').classList.add('active');
}

function closeOrderModal() {
    document.getElementById('orderModal').classList.remove('active');
    document.getElementById('overlay').classList.remove('active');
    selectedOrder = null;
}

async function updateOrderStatus() {
    if (!selectedOrder) return;

    const newStatus = document.getElementById('statusSelect').value;
    if (!newStatus) {
        alert('Please select a status');
        return;
    }

    try {
        const response = await fetch('php/orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_status',
                order_id: selectedOrder.id,
                status: newStatus
            })
        });

        const data = await response.json();

        if (data.status === 'success') {
            showNotification(`Order status updated to ${newStatus}`, 'success');
            closeOrderModal();
            loadOrders();
            logActivity(`updated_order`, `Updated order #${selectedOrder.order_number} to ${newStatus}`);
        } else {
            showNotification('Error updating order', 'error');
        }
    } catch (error) {
        console.error('Error updating order:', error);
        showNotification('An error occurred', 'error');
    }
}

async function loadActivity() {
    try {
        const response = await fetch('php/orders.php?action=get_staff_activity');
        const data = await response.json();

        if (data.status === 'success') {
            displayActivity(data.data);
        }
    } catch (error) {
        console.error('Error loading activity:', error);
    }
}

function displayActivity(activities) {
    const activityList = document.getElementById('activityList');

    if (!activities || activities.length === 0) {
        activityList.innerHTML = '<p class="loading">No activity recorded</p>';
        return;
    }

    activityList.innerHTML = activities.map(activity => `
        <div class="activity-card">
            <div class="activity-icon ${activity.type}">
                <i class="fas fa-${getActivityIcon(activity.type)}"></i>
            </div>
            <div class="activity-content">
                <p class="activity-title">${activity.title}</p>
                <p class="activity-desc">${activity.description}</p>
                <p class="activity-time">${formatTime(activity.timestamp)}</p>
            </div>
        </div>
    `).join('');
}

function getActivityIcon(type) {
    const icons = {
        'updated_order': 'edit',
        'viewed_order': 'eye',
        'created_shipment': 'box',
        'sent_notification': 'bell'
    };
    return icons[type] || 'info-circle';
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

function logActivity(type, description) {
    // In production, this would save to database
    console.log(`Activity logged: ${type} - ${description}`);
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
    }, 3000);
}

// Logout
document.getElementById('logoutBtn').addEventListener('click', logout);