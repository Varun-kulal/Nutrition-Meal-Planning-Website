<?php
session_start();

// Strict Admin Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

if (!isset($_GET['user_id'])) {
    header("Location: admin_users.php");
    exit();
}

$user_id = intval($_GET['user_id']);

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'fitness_app';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Fetch User Info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();

if ($user_result->num_rows === 0) {
    header("Location: admin_users.php");
    exit();
}
$user = $user_result->fetch_assoc();

// Fetch Stats
$order_stats = $conn->query("SELECT COUNT(*) as count, SUM(total) as revenue FROM orders WHERE user_id = $user_id")->fetch_assoc();
$total_orders = $order_stats['count'];
$total_spent = $order_stats['revenue'] ?? 0;

// Fetch Recent Orders
$user_orders = $conn->query("SELECT * FROM orders WHERE user_id = $user_id ORDER BY created_at DESC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Details | NutriAdmin</title>
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
}

/* SIDEBAR */
.sidebar {
    width: 260px;
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
.menu a { text-decoration: none; color: var(--text-secondary); font-weight: 500; padding: 12px 16px; display: block; border-radius: 12px; transition: 0.2s; font-size: 15px; }
.menu a:hover, .menu a.active { background: rgba(255, 193, 7, 0.1); color: var(--accent-yellow); }

/* MAIN CONTENT */
.main-content {
    margin-left: 260px;
    padding: 40px 60px;
    width: calc(100% - 260px);
    min-height: 100vh;
}

.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 50px; }
.header h1 { font-size: 32px; font-weight: 800; margin: 0; }
.back-btn { text-decoration: none; color: var(--text-secondary); font-weight: 600; display: flex; align-items: center; gap: 8px; }
.back-btn:hover { color: var(--accent-yellow); }

/* INFO CARDS */
.info-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 20px; margin-bottom: 40px; }
.card { background: var(--bg-card); padding: 30px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.05); }
.card h3 { margin: 0 0 15px 0; font-size: 14px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
.user-info p { margin: 8px 0; font-size: 16px; }
.user-info span { color: var(--text-secondary); display: inline-block; width: 80px; }
.stat-val { font-size: 36px; font-weight: 800; color: var(--accent-yellow); }

/* TABLE STYLES */
.section-title { font-size: 20px; font-weight: 700; margin-bottom: 20px; }
.table-container { background: var(--bg-card); border-radius: 16px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05); }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 15px 20px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }
th { background: var(--table-header); color: var(--text-secondary); font-size: 12px; text-transform: uppercase; }

.logout-link { margin-top: auto; display: block; color: #ff6b6b; text-decoration: none; font-weight: 600; padding: 10px 15px; }
</style>
</head>
<body>

<div class="sidebar">
    <div class="brand">Nutri<span>Admin</span></div>
    <ul class="menu">
        <li><a href="admin.php">Dashboard</a></li>
        <li><a href="admin_users.php" class="active">Users</a></li>
        <li><a href="admin_product.php">Products</a></li>
        <li><a href="admin_feedback.php">Feedback</a></li>
        <li><a href="index.php">View Website</a></li>
    </ul>
    <a href="admin_logout.php" class="logout-link">Logout</a>
</div>

<div class="main-content">
    <div class="header">
        <div>
            <a href="admin_users.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Users</a>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 14px; color: #888;">User Profile</div>
            <h2 style="margin: 0;"><?= htmlspecialchars($user['fullname']) ?></h2>
        </div>
    </div>

    <div class="info-grid">
        <div class="card user-info">
            <h3>User Details</h3>
            <p><span>ID:</span> #<?= $user['id'] ?></p>
            <p><span>Email:</span> <?= htmlspecialchars($user['email']) ?></p>
            <p><span>Joined:</span> <?= date('F d, Y, h:i A', strtotime($user['created_at'])) ?></p>
        </div>
        <div class="card">
            <h3>Total Orders</h3>
            <div class="stat-val"><?= $total_orders ?></div>
        </div>
        <div class="card">
            <h3>Total Spent</h3>
            <div class="stat-val">₹<?= number_format($total_spent) ?></div>
        </div>
    </div>

    <div class="section-title">Order History</div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Product</th>
                    <th>Amount</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($user_orders->num_rows > 0): ?>
                    <?php while($order = $user_orders->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= $order['id'] ?></td>
                        <td><?= htmlspecialchars($order['product_name']) ?></td>
                        <td>₹<?= $order['total'] ?></td>
                        <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; color:#888;">No orders found for this user.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
