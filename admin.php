<?php
session_start();

// Strict Admin Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// Database Connection
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'fitness_app';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Fetch Stats
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
$total_revenue = $conn->query("SELECT SUM(total) as revenue FROM orders")->fetch_assoc()['revenue'];
$total_workouts = $conn->query("SELECT COUNT(*) as count FROM workout_logs")->fetch_assoc()['count'];

// Fetch Recent Orders
$recent_orders = $conn->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5");

// Fetch Recent Users
$recent_users = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");

// Fetch All Orders for Orders Management
$all_orders = $conn->query("SELECT o.*, u.fullname as user_name, u.email as user_email FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | NutriPlan</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ... Reusing core variables ... */
:root {
    --bg-primary: #121212;
    --bg-secondary: #1A1A1A;
    --bg-card: #1E1E1E;
    --accent-yellow: #FFC107;
    --text-primary: #FFFFFF;
    --text-secondary: #B0B0B0;
    --table-header: #333;
}

/* GLOBAL RESET */
* { box-sizing: border-box; }

body {
    background-color: var(--bg-primary);
    color: var(--text-primary);
    font-family: 'Inter', sans-serif;
    margin: 0;
    /* Removed display: flex to fix fixed positioning interactions */
}

/* SIDEBAR */
.sidebar {
    width: 260px; /* Increased slightly */
    background: var(--bg-secondary);
    height: 100vh;
    padding: 30px;
    position: fixed;
    top: 0;
    left: 0;
    border-right: 1px solid rgba(255,255,255,0.05);
    z-index: 100;
    overflow-y: auto;
}

.brand { font-size: 24px; font-weight: 800; margin-bottom: 50px; color: #fff; display: flex; align-items: center; gap: 10px; }
.brand span { color: var(--accent-yellow); }

.menu { list-style: none; padding: 0; margin: 0; }
.menu li { margin-bottom: 8px; }
.menu a { text-decoration: none; color: var(--text-secondary); font-weight: 500; padding: 12px 16px; display: flex; align-items: center; gap: 10px; border-radius: 12px; transition: 0.2s; font-size: 15px; }
.menu a i { width: 20px; text-align: center; }
.menu a:hover, .menu a.active { background: rgba(255, 193, 7, 0.1); color: var(--accent-yellow); }

/* MAIN CONTENT */
.main-content {
    margin-left: 260px; /* Matches sidebar width */
    padding: 40px 60px; /* More horizontal breathing room */
    width: calc(100% - 260px); /* Correct width calculation */
    min-height: 100vh;
}

.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 50px; }
.header h1 { font-size: 32px; font-weight: 800; margin: 0; }
.admin-badge { padding: 8px 16px; background: rgba(255, 193, 7, 0.15); color: var(--accent-yellow); border: 1px solid rgba(255, 193, 7, 0.2); border-radius: 30px; font-weight: 700; font-size: 13px; display: inline-block; }

/* STATS CARDS */
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 40px; }
.stat-card { background: var(--bg-card); padding: 25px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.05); }
.stat-card h3 { margin: 0; font-size: 14px; color: var(--text-secondary); text-transform: uppercase; }
.stat-card .value { font-size: 32px; font-weight: 800; margin-top: 10px; color: var(--accent-yellow); }

/* TABLE STYLES */
.section-title { font-size: 20px; font-weight: 700; margin-bottom: 20px; }
.table-container { background: var(--bg-card); border-radius: 16px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05); margin-bottom: 40px; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 15px 20px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }
th { background: var(--table-header); color: var(--text-secondary); font-size: 12px; text-transform: uppercase; }
tr:hover { background: rgba(255,255,255,0.02); }

/* STATUS BADGES */
.status { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; }
.status-ordered { background: rgba(255, 193, 7, 0.15); color: #FFC107; border: 1px solid rgba(255, 193, 7, 0.3); }
.status-shipped { background: rgba(59, 130, 246, 0.15); color: #3B82F6; border: 1px solid rgba(59, 130, 246, 0.3); }
.status-out-for-delivery { background: rgba(168, 85, 247, 0.15); color: #A855F7; border: 1px solid rgba(168, 85, 247, 0.3); }
.status-delivered { background: rgba(16, 185, 129, 0.15); color: #10B981; border: 1px solid rgba(16, 185, 129, 0.3); }

/* ACTION BUTTONS */
.btn-view { padding: 8px 16px; background: var(--accent-yellow); color: #000; border: none; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer; transition: 0.2s; }
.btn-view:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3); }

/* SECTION TOGGLE */
.content-section { display: none; }
.content-section.active { display: block; }

/* MODAL */
.modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(5px); z-index: 2000; justify-content: center; align-items: center; padding: 20px; }
.modal.active { display: flex; }
.modal-content { background: #1C1C1C; width: 100%; max-width: 600px; padding: 32px; border-radius: 24px; position: relative; border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 20px 60px rgba(0,0,0,0.6); max-height: 90vh; overflow-y: auto; }
.modal-close { position: absolute; right: 24px; top: 24px; font-size: 24px; cursor: pointer; color: var(--text-secondary); transition: 0.2s; background: none; border: none; }
.modal-close:hover { color: var(--accent-yellow); }
.modal-title { font-size: 24px; color: var(--accent-yellow); margin-bottom: 24px; margin-top: 0; }
.modal-section { margin-bottom: 24px; }
.modal-section h4 { font-size: 14px; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 12px; letter-spacing: 1px; }
.modal-detail { display: grid; grid-template-columns: 140px 1fr; gap: 12px; margin-bottom: 12px; }
.modal-label { color: var(--text-secondary); font-size: 14px; }
.modal-value { color: var(--text-primary); font-weight: 600; font-size: 14px; }

/* STATUS ACTIONS */
.status-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 20px; }
.status-btn { padding: 12px 24px; border-radius: 30px; font-weight: 700; font-size: 14px; cursor: pointer; transition: 0.3s; border: none; display: inline-flex; align-items: center; gap: 8px; }
.status-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.status-btn-ordered { background: var(--accent-yellow); color: #000; }
.status-btn-shipped { background: #3B82F6; color: #fff; }
.status-btn-out { background: #A855F7; color: #fff; }
.status-btn-delivered { background: #10B981; color: #fff; }
.status-btn:not(:disabled):hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(255, 193, 7, 0.3); }

/* PAYMENT VERIFICATION AREA */
.verification-card {
    background: #111;
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    padding: 20px;
    margin-top: 10px;
}

.screenshot-preview {
    position: relative;
    width: 100%;
    margin-top: 12px;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid #333;
    transition: 0.3s;
}

.screenshot-preview:hover {
    border-color: var(--accent-yellow);
}

.screenshot-preview img {
    width: 100%;
    display: block;
    cursor: zoom-in;
}

.zoom-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: 0.3s;
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    pointer-events: none;
}

.screenshot-preview:hover .zoom-overlay {
    opacity: 1;
}

.logout-link { margin-top: auto; display: block; color: #ff6b6b; text-decoration: none; font-weight: 600; padding: 10px 15px; }

</style>
</head>
<body>

<div class="sidebar">
    <div class="brand">Nutri<span>Admin</span></div>
    <ul class="menu">
        <li><a href="#" class="nav-link active" data-section="dashboard"><i class="fas fa-chart-line"></i> Dashboard</a></li>
        <li><a href="#" class="nav-link" data-section="orders"><i class="fas fa-box"></i> Orders</a></li>
        <li><a href="admin_users.php"><i class="fas fa-users"></i> Users</a></li>
        <li><a href="admin_product.php"><i class="fas fa-shopping-bag"></i> Products</a></li>
        <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback</a></li>
        <li><a href="login.php"><i class="fas fa-globe"></i> View Website</a></li>
    </ul>
    <a href="admin_logout.php" class="logout-link">Logout</a>
</div>

<div class="main-content">
    <!-- Dashboard Section -->
    <div class="content-section active" id="dashboard-section">
        <div class="header">
            <h1>Dashboard Overview</h1>
            <div style="text-align: right;">
                <span class="admin-badge">Admin Access</span>
                <div style="margin-top: 5px; font-size: 12px; color: #888;">
                    ID: <?= $_SESSION['admin_id'] ?> | <?= htmlspecialchars($_SESSION['admin_user']) ?>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="value"><?= $total_users ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Orders</h3>
                <div class="value"><?= $total_orders ?></div>
            </div>
            <div class="stat-card">
                <h3>Revenue</h3>
                <div class="value">₹<?= number_format($total_revenue) ?></div>
            </div>
            <div class="stat-card">
                <h3>Workouts Logged</h3>
                <div class="value"><?= $total_workouts ?></div>
            </div>
        </div>

        <div class="section-title">Recent Orders</div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Amount</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $recent_orders->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['product_name']) ?></td>
                        <td>₹<?= $row['total'] ?></td>
                        <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Orders Section -->
    <div class="content-section" id="orders-section">
        <div class="header">
            <h1>Order Management</h1>
            <span class="admin-badge">Total: <?= $total_orders ?> Orders</span>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>User</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Pay Method</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($order = $all_orders->fetch_assoc()): 
                        // Ensure order_status exists for both PHP display and JS modal (json_encode)
                        if (!isset($order['order_status'])) {
                            $order['order_status'] = 'Ordered'; 
                        }
                        $status_class = 'status-' . strtolower(str_replace(' ', '-', $order['order_status']));
                    ?>
                    <tr>
                        <td><strong>#<?= $order['id'] ?></strong></td>
                        <td><?= htmlspecialchars($order['user_name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($order['product_name']) ?></td>
                        <td><?= $order['quantity'] ?></td>
                        <td><strong>₹<?= number_format($order['total']) ?></strong></td>
                        <td>
                            <span class="badge" style="background: rgba(255,255,255,0.05); padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                                <?= $order['payment_method'] ?? 'COD' ?>
                            </span>
                        </td>
                        <td><span class="status <?= $status_class ?>"><?= $order['order_status'] ?></span></td>
                        <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                        <td>
                            <button class="btn-view" onclick='openOrderModal(<?= json_encode($order) ?>)'>
                                <i class="fas fa-eye"></i> View
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Order Details Modal -->
<div class="modal" id="orderModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal()">✖</button>
        <h3 class="modal-title">Order Details</h3>
        
        <div class="modal-section">
            <h4>Order Information</h4>
            <div class="modal-detail">
                <span class="modal-label">Order ID:</span>
                <span class="modal-value" id="modal-order-id"></span>
            </div>
            <div class="modal-detail">
                <span class="modal-label">Order Date:</span>
                <span class="modal-value" id="modal-order-date"></span>
            </div>
            <div class="modal-detail">
                <span class="modal-label">Current Status:</span>
                <span class="modal-value" id="modal-order-status"></span>
            </div>
        </div>

        <div class="modal-section">
            <h4>User Details</h4>
            <div class="modal-detail">
                <span class="modal-label">User ID:</span>
                <span class="modal-value" id="modal-user-id"></span>
            </div>
            <div class="modal-detail">
                <span class="modal-label">Name:</span>
                <span class="modal-value" id="modal-user-name"></span>
            </div>
            <div class="modal-detail">
                <span class="modal-label">Email:</span>
                <span class="modal-value" id="modal-user-email"></span>
            </div>
            <div class="modal-detail">
                <span class="modal-label">Phone:</span>
                <span class="modal-value" id="modal-phone"></span>
            </div>
        </div>

        <div class="modal-section">
            <h4>Product & Delivery</h4>
            <div class="modal-detail">
                <span class="modal-label">Product:</span>
                <span class="modal-value" id="modal-product"></span>
            </div>
            <div class="modal-detail">
                <span class="modal-label">Quantity:</span>
                <span class="modal-value" id="modal-quantity"></span>
            </div>
            <div class="modal-detail">
                <span class="modal-label">Price per Unit:</span>
                <span class="modal-value" id="modal-price"></span>
            </div>
            <div class="modal-detail">
                <span class="modal-label">Total Amount:</span>
                <span class="modal-value" id="modal-total"></span>
            </div>
            <div class="modal-detail">
                <span class="modal-label">City:</span>
                <span class="modal-value" id="modal-city"></span>
            </div>
            <div class="modal-detail">
                <span class="modal-label">Pincode:</span>
                <span class="modal-value" id="modal-pin"></span>
            </div>
            <div class="modal-detail">
                <span class="modal-label">Landmark:</span>
                <span class="modal-value" id="modal-landmark"></span>
            </div>
        </div>

        <div class="modal-section" id="modal-payment-section" style="display: none;">
            <h4>Payment Verification</h4>
            <div class="verification-card">
                <div class="modal-detail">
                    <span class="modal-label">Method:</span>
                    <span class="modal-value" id="modal-payment-method"></span>
                </div>
                <div id="modal-screenshot-container">
                    <div class="screenshot-preview" onclick="viewScreenshot()">
                        <img id="modal-screenshot-img" src="" alt="Payment Screenshot">
                        <div class="zoom-overlay">
                            <i class="fas fa-search-plus"></i> &nbsp; Click to Enlarge
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-section">
            <h4>Update Order Status</h4>
            <div class="status-actions" id="status-actions">
                <!-- Status buttons will be dynamically generated -->
            </div>
        </div>
    </div>
</div>

<script>
let currentOrderId = null;
let currentStatus = null;

// Section Toggle
document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const section = this.dataset.section;
        
        // Update active nav
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        this.classList.add('active');
        
        // Show/hide sections
        document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
        document.getElementById(section + '-section').classList.add('active');
    });
});

// Open Order Modal
function openOrderModal(order) {
    currentOrderId = order.id;
    currentStatus = order.order_status;
    
    // Populate order details
    document.getElementById('modal-order-id').textContent = '#' + order.id;
    document.getElementById('modal-order-date').textContent = new Date(order.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    document.getElementById('modal-order-status').innerHTML = `<span class="status status-${order.order_status.toLowerCase().replace(/ /g, '-')}">${order.order_status}</span>`;
    
    // User details
    document.getElementById('modal-user-id').textContent = order.user_id;
    document.getElementById('modal-user-name').textContent = order.user_name || 'N/A';
    document.getElementById('modal-user-email').textContent = order.user_email || 'N/A';
    document.getElementById('modal-phone').textContent = order.phone;
    
    // Product details
    document.getElementById('modal-product').textContent = order.product_name;
    document.getElementById('modal-quantity').textContent = order.quantity + ' units';
    document.getElementById('modal-price').textContent = '₹' + order.price;
    document.getElementById('modal-total').textContent = '₹' + order.total;
    document.getElementById('modal-pin').textContent = order.pin;
    document.getElementById('modal-landmark').textContent = order.landmark;
    
    // Payment details
    const paySection = document.getElementById('modal-payment-section');
    const payMethod = order.payment_method || 'COD';
    document.getElementById('modal-payment-method').textContent = payMethod;
    
    if (payMethod === 'UPI' && order.payment_screenshot) {
        paySection.style.display = 'block';
        document.getElementById('modal-screenshot-img').src = 'uploads/' + order.payment_screenshot;
    } else {
        paySection.style.display = payMethod === 'UPI' ? 'block' : 'none';
        if (payMethod === 'COD') paySection.style.display = 'none';
        document.getElementById('modal-screenshot-container').style.display = order.payment_screenshot ? 'block' : 'none';
    }
    
    // Generate status buttons
    generateStatusButtons(order.order_status);
    
    // Show modal
    document.getElementById('orderModal').classList.add('active');
}

// Generate Status Buttons
function generateStatusButtons(currentStatus) {
    const statuses = ['Ordered', 'Shipped', 'Out for Delivery', 'Delivered'];
    const currentIndex = statuses.indexOf(currentStatus);
    const container = document.getElementById('status-actions');
    container.innerHTML = '';
    
    statuses.forEach((status, index) => {
        const btn = document.createElement('button');
        btn.className = 'status-btn status-btn-' + status.toLowerCase().replace(/ /g, '-').substring(0, 3);
        btn.innerHTML = `<i class="fas fa-${getStatusIcon(status)}"></i> ${status}`;
        btn.disabled = index <= currentIndex;
        
        if (index === currentIndex + 1) {
            btn.onclick = () => updateOrderStatus(status);
        }
        
        container.appendChild(btn);
    });
}

// Get Status Icon
function getStatusIcon(status) {
    const icons = {
        'Ordered': 'check-circle',
        'Shipped': 'shipping-fast',
        'Out for Delivery': 'truck',
        'Delivered': 'box-check'
    };
    return icons[status] || 'circle';
}

// Update Order Status
function updateOrderStatus(newStatus) {
    if (!currentOrderId) return;
    
    const formData = new FormData();
    formData.append('order_id', currentOrderId);
    formData.append('status', newStatus);
    
    fetch('update_order_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Order status updated to: ' + newStatus);
            currentStatus = newStatus;
            generateStatusButtons(newStatus);
            document.getElementById('modal-order-status').innerHTML = `<span class="status status-${newStatus.toLowerCase().replace(/ /g, '-')}">${newStatus}</span>`;
            
            // Reload page to refresh order list
            setTimeout(() => location.reload(), 1000);
        } else {
            alert('❌ Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('❌ Error updating status');
        console.error(error);
    });
}

// Close Modal
function closeModal() {
    document.getElementById('orderModal').classList.remove('active');
}

// Open full screenshot in new tab
function viewScreenshot() {
    const src = document.getElementById('modal-screenshot-img').src;
    if (src) window.open(src, '_blank');
}

// Close modal on outside click
window.onclick = function(event) {
    const modal = document.getElementById('orderModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

</body>
</html>
