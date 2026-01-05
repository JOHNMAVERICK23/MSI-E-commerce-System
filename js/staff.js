// File: js/staff.js
// UPDATED - Complete Order Approval System

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
    loadPendingOrders();
    loadActivity();

    // Setup event listeners
    document.getElementById('statusFilter').addEventListener('change', filterOrders);
    document.getElementById('updateStatusBtn').addEventListener('click', updateOrderStatus);
    document.querySelector('.close-modal').addEventListener('click', closeOrderModal);
    document.getElementById('overlay').addEventListener('click', closeOrderModal);
    
    // Logout
    document.getElementById('logoutBtn').addEventListener('click', logout);
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

// ============================================
// LOAD PENDING ORDERS (HINDI APPROVED PA)
// ============================================
async function loadPendingOrders() {
    try {
        const response = await fetch('php/orders.php?action=list_pending');
        const data = await response.json();

        if (data.status === 'success') {
            allOrders = data.data;
            displayPendingOrders(allOrders);
            updateOrderStats();
        }
    } catch (error) {
        console.error('Error loading orders:', error);
        toast.error('Error', 'Failed to load pending orders');
    }
}

// ============================================
// DISPLAY PENDING ORDERS - Pang sa approval
// ============================================
function displayPendingOrders(orders) {
    const ordersList = document.getElementById('ordersList');

    if (orders.length === 0) {
        ordersList.innerHTML = `
            <div style="text-align: center; padding: 40px; background: var(--bg-card); border-radius: 8px;">
                <i class="fas fa-check-circle" style="font-size: 40px; color: var(--status-success); margin-bottom: 15px; display: block;"></i>
                <h3>All Orders Approved!</h3>
                <p style="color: var(--text-foreground); opacity: 0.7;">No pending orders to review</p>
            </div>
        `;
        return;
    }

    ordersList.innerHTML = orders.map(order => `
        <div class="order-card" onclick="openOrderForApproval(${order.id})">
            <div class="order-card-header">
                <div>
                    <h3 class="order-number">#${order.order_number}</h3>
                    <p style="font-size: 12px; color: var(--text-foreground); opacity: 0.7; margin: 5px 0;">
                        Customer: ${order.first_name} ${order.last_name}
                    </p>
                </div>
                <span class="order-status pending">PENDING APPROVAL</span>
            </div>
            <div class="order-card-body">
                <div class="order-detail-item">
                    <span class="detail-label">Customer Email</span>
                    <span class="detail-value email">${order.email}</span>
                </div>
                <div class="order-detail-item">
                    <span class="detail-label">Amount</span>
                    <span class="detail-value price">$${parseFloat(order.total_amount).toFixed(2)}</span>
                </div>
                <div class="order-detail-item">
                    <span class="detail-label">Items</span>
                    <span class="detail-value">${order.item_count}</span>
                </div>
                <div class="order-detail-item">
                    <span class="detail-label">Date</span>
                    <span class="detail-value">${new Date(order.created_at).toLocaleDateString()}</span>
                </div>
            </div>
            <div style="margin-top: 15px; display: flex; gap: 10px;">
                <button class="btn-small btn-edit" onclick="approveOrderAction(event, ${order.id})" style="background: rgba(34, 197, 94, 0.2); border: 1px solid var(--status-success); color: var(--status-success); flex: 1;">
                    <i class="fas fa-check"></i> APPROVE
                </button>
                <button class="btn-small btn-delete" onclick="rejectOrderAction(event, ${order.id})" style="background: rgba(220, 38, 38, 0.2); border: 1px solid var(--status-destructive); color: var(--status-destructive); flex: 1;">
                    <i class="fas fa-times"></i> REJECT
                </button>
            </div>
        </div>
    `).join('');
}

// ============================================
// OPEN ORDER MODAL - VIEW FULL DETAILS
// ============================================
async function openOrderForApproval(orderId) {
    try {
        const response = await fetch(`php/orders.php?action=get_order_details&orderId=${orderId}`);
        const data = await response.json();

        if (data.status === 'success') {
            const order = data.data;
            selectedOrder = order;
            displayOrderDetails(order);
            document.getElementById('orderModal').classList.add('active');
            document.getElementById('overlay').classList.add('active');
        }
    } catch (error) {
        console.error('Error loading order:', error);
        toast.error('Error', 'Failed to load order details');
    }
}

// ============================================
// DISPLAY FULL ORDER DETAILS IN MODAL
// ============================================
function displayOrderDetails(order) {
    const detailContent = document.getElementById('orderDetail');
    
    let itemsHTML = (order.items || []).map(item => `
        <tr style="border-bottom: 1px solid var(--border-color);">
            <td style="padding: 10px;">${item.product_name}</td>
            <td style="padding: 10px; text-align: center;">${item.quantity}</td>
            <td style="padding: 10px; text-align: right;">$${parseFloat(item.unit_price).toFixed(2)}</td>
            <td style="padding: 10px; text-align: right; color: var(--primary-red); font-weight: 600;">
                $${(item.quantity * item.unit_price).toFixed(2)}
            </td>
        </tr>
    `).join('');
    
    const subtotal = (order.items || []).reduce((sum, item) => sum + (item.quantity * item.unit_price), 0);
    const tax = subtotal * 0.10;
    const total = subtotal + 10 + tax;
    
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
                    <span class="info-value" style="color: var(--primary-orange); text-transform: uppercase;">PENDING APPROVAL</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Total Amount</span>
                    <span class="info-value" style="color: var(--primary-red); font-size: 16px;">$${parseFloat(order.total_amount).toFixed(2)}</span>
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
                    <span class="info-label">Full Name</span>
                    <span class="info-value">${order.customer_name}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email</span>
                    <span class="info-value">${order.email}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone</span>
                    <span class="info-value">${order.phone}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Customer ID</span>
                    <span class="info-value">#${order.customer_id}</span>
                </div>
            </div>
        </div>

        <div class="order-info-section">
            <h4 class="section-title">Payment Information</h4>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Payment Method</span>
                    <span class="info-value" style="text-transform: uppercase;">${order.payment_method || 'Not specified'}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Payment Status</span>
                    <span class="info-value">${order.payment_status || 'Pending'}</span>
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
                    ${itemsHTML}
                </tbody>
            </table>
        </div>

        <div class="order-info-section">
            <h4 class="section-title">Order Summary</h4>
            <div style="background: var(--bg-muted); padding: 15px; border-radius: 6px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span>Subtotal:</span>
                    <span>$${subtotal.toFixed(2)}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span>Shipping:</span>
                    <span>$10.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span>Tax (10%):</span>
                    <span>$${tax.toFixed(2)}</span>
                </div>
                <div style="display: flex; justify-content: space-between; border-top: 2px solid var(--primary-red); padding-top: 10px; font-size: 18px; font-weight: 700; color: var(--primary-red);">
                    <span>TOTAL:</span>
                    <span>$${total.toFixed(2)}</span>
                </div>
            </div>
        </div>

        <div style="margin-top: 20px; padding: 15px; background: rgba(255, 136, 0, 0.1); border-left: 4px solid var(--primary-orange); border-radius: 4px;">
            <p style="color: var(--text-foreground); margin: 0;">
                <strong>⚠️ ACTION REQUIRED:</strong> Please review this order carefully before approving.
                Once approved, the order will move to processing status.
            </p>
        </div>
    `;
}

// ============================================
// APPROVE ORDER (CLICK APPROVE BUTTON)
// ============================================
function approveOrderAction(e, orderId) {
    e.preventDefault();
    e.stopPropagation();
    
    if (confirm('Are you sure you want to APPROVE this order?\n\nIt will be moved to PROCESSING status.')) {
        approveOrder(orderId);
    }
}

// ============================================
// REJECT ORDER (CLICK REJECT BUTTON)
// ============================================
function rejectOrderAction(e, orderId) {
    e.preventDefault();
    e.stopPropagation();
    
    const reason = prompt('Why are you rejecting this order?\n\nEnter reason (required):');
    
    if (reason && reason.trim() !== '') {
        rejectOrder(orderId, reason);
    } else if (reason !== null) {
        toast.error('Required', 'Please provide a reason for rejection');
    }
}

// ============================================
// APPROVE ORDER - API CALL
// ============================================
async function approveOrder(orderId) {
    try {
        const response = await fetch('php/orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'approve_order',
                order_id: orderId
            })
        });

        const data = await response.json();

        if (data.status === 'success') {
            toast.success('Success', 'Order APPROVED! Moved to PROCESSING');
            closeOrderModal();
            loadPendingOrders();
            logActivity('Order approved', `Approved Order #${orderId}`);
        } else {
            toast.error('Error', data.message || 'Failed to approve order');
        }
    } catch (error) {
        console.error('Error approving order:', error);
        toast.error('Error', 'Failed to approve order');
    }
}

// ============================================
// REJECT ORDER - API CALL
// ============================================
async function rejectOrder(orderId, reason) {
    try {
        const response = await fetch('php/orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'reject_order',
                order_id: orderId,
                reason: reason
            })
        });

        const data = await response.json();

        if (data.status === 'success') {
            toast.success('Success', 'Order REJECTED and CANCELLED');
            closeOrderModal();
            loadPendingOrders();
            logActivity('Order rejected', `Rejected Order #${orderId} - Reason: ${reason}`);
        } else {
            toast.error('Error', data.message || 'Failed to reject order');
        }
    } catch (error) {
        console.error('Error rejecting order:', error);
        toast.error('Error', 'Failed to reject order');
    }
}

function filterOrders() {
    const status = document.getElementById('statusFilter').value;
    
    if (!status) {
        displayPendingOrders(allOrders);
    } else {
        const filtered = allOrders.filter(order => order.status === status);
        displayPendingOrders(filtered);
    }
}

function updateOrderStats() {
    document.getElementById('totalOrders').textContent = allOrders.length;
    document.getElementById('pendingOrders').textContent = allOrders.filter(o => o.approval_status === 'pending' || !o.approval_status).length;
}

function closeOrderModal() {
    document.getElementById('orderModal').classList.remove('active');
    document.getElementById('overlay').classList.remove('active');
    selectedOrder = null;
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
        'updated_order': 'check',
        'viewed_order': 'eye',
        'rejected_order': 'times',
        'approved_order': 'thumbs-up'
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
    console.log(`Activity: ${type} - ${description}`);
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

// Auth helpers
function getUser() {
    try {
        const user = localStorage.getItem('user');
        return user ? JSON.parse(user) : null;
    } catch (error) {
        return null;
    }
}

function requireLogin(requiredRole = null) {
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

document.getElementById('logoutBtn').addEventListener('click', logout);

// Global functions
window.openOrderForApproval = openOrderForApproval;
window.approveOrderAction = approveOrderAction;
window.rejectOrderAction = rejectOrderAction;
window.closeOrderModal = closeOrderModal;