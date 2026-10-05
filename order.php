<?php
session_start();

// Strict Login Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database Connection
$conn = new mysqli("localhost", "root", "", "fitness_app");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user's orders
$user_id = $_SESSION['user_id'];

// DEBUG: Output to console
echo "<script>console.log('DEBUG: Logged in user_id = ', $user_id);</script>";


$stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
    // DEBUG: Show each order's user_id
    echo "<script>console.log('DEBUG: Order #" . $row['id'] . " belongs to user_id = ', " . $row['user_id'] . ");</script>";
}
echo "<script>console.log('DEBUG: Orders found = ', " . count($orders) . ");</script>";
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Your Order | NutriPlan</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #121212;
            --bg-secondary: #1A1A1A;
            --bg-card: #1E1E1E;
            --accent-yellow: #FFC107;
            --text-primary: #FFFFFF;
            --text-secondary: #B0B0B0;
            --success: #4CAF50;
            --transition-fast: 0.2s ease;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 0 60px;
        }

        /* NAVBAR */
        .navbar {
            background-color: var(--bg-secondary);
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(255, 193, 7, 0.1);
        }

        .navbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-brand h2 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0;
            letter-spacing: -0.5px;
        }

        .nav-brand span {
            color: var(--accent-yellow);
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 40px;
            align-items: center;
            margin: 0;
            padding: 0;
        }

        .nav-menu li a {
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 15px;
            transition: var(--transition-fast);
        }

        .nav-menu li a:hover,
        .nav-menu li a.active {
            color: var(--accent-yellow);
        }

        .logout-btn {
            border: 2px solid var(--accent-yellow);
            padding: 8px 24px;
            border-radius: 6px;
            color: var(--accent-yellow) !important;
            transition: var(--transition-fast);
        }

        .logout-btn:hover {
            background-color: var(--accent-yellow);
            color: var(--bg-primary) !important;
        }

        /* PAGE HEADER */
        .page-header {
            text-align: center;
            padding: 80px 0 60px;
        }

        .page-header h1 {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 16px;
            letter-spacing: -1px;
        }

        .page-header span {
            color: var(--accent-yellow);
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 18px;
            max-width: 600px;
            margin: 0 auto 30px;
        }

        /* ORDERS CONTAINER */
        .orders-container {
            padding-bottom: 80px;
        }

        .order-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 32px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            margin-bottom: 24px;
            transition: 0.3s;
        }

        .order-card:hover {
            transform: translateY(-4px);
            border-color: rgba(255, 193, 7, 0.3);
            box-shadow: 0 10px 30px rgba(255, 193, 7, 0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .order-id {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .order-date {
            font-size: 14px;
            color: var(--text-secondary);
        }

        /* PROGRESS TRACKER */
        .progress-tracker {
            margin: 32px 0;
        }

        .current-status {
            text-align: center;
            margin-bottom: 32px;
        }

        .current-status-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .current-status-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--accent-yellow);
        }

        /* STEPPER */
        .stepper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            margin: 40px 0;
        }

        .stepper-line {
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 3px;
            background: #333;
            z-index: 0;
        }

        .stepper-progress {
            position: absolute;
            top: 20px;
            left: 0;
            height: 3px;
            background: var(--accent-yellow);
            z-index: 1;
            transition: width 0.5s ease;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #333;
            border: 3px solid #333;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }

        .step.completed .step-circle {
            background: var(--accent-yellow);
            border-color: var(--accent-yellow);
            color: #000;
        }

        .step.active .step-circle {
            background: var(--accent-yellow);
            border-color: var(--accent-yellow);
            color: #000;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7);
            }

            50% {
                box-shadow: 0 0 0 10px rgba(255, 193, 7, 0);
            }
        }

        .step-icon {
            font-size: 16px;
        }

        .step-label {
            font-size: 13px;
            font-weight: 600;
            color: #666;
            text-align: center;
            max-width: 100px;
        }

        .step.completed .step-label,
        .step.active .step-label {
            color: var(--text-primary);
        }

        /* ORDER DETAILS */
        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 24px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .detail-label {
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .detail-value {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 64px;
            color: var(--accent-yellow);
            margin-bottom: 20px;
        }

        .back-btn {
            background: var(--accent-yellow);
            color: #000;
            border: none;
            padding: 12px 24px;
            font-weight: 700;
            border-radius: 30px;
            cursor: pointer;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
            margin-top: 20px;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.2);
        }

        /* Footer */
        .footer {
            background-color: var(--bg-secondary);
            padding: 60px 0 30px;
            border-top: 1px solid rgba(255, 193, 7, 0.1);
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 60px;
            margin-bottom: 40px;
        }

        .footer-column h3 {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 20px;
            color: var(--accent-yellow);
        }

        .footer-column h4 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-primary);
        }

        .footer-column p {
            color: var(--text-secondary);
            line-height: 1.8;
            font-size: 15px;
        }

        .footer-column ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-column ul li {
            margin-bottom: 12px;
        }

        .footer-column ul li a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: var(--transition-fast);
            font-size: 15px;
        }

        .footer-column ul li a:hover {
            color: var(--accent-yellow);
            padding-left: 5px;
        }

        .social-links {
            display: flex;
            gap: 16px;
            margin-top: 16px;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            background-color: var(--bg-card);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: var(--transition-fast);
        }

        .social-links a:hover {
            background-color: var(--accent-yellow);
            color: var(--bg-primary);
            transform: translateY(-3px);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .footer-bottom p {
            color: var(--text-secondary);
            font-size: 14px;
            margin: 0;
        }

        @media(max-width: 768px) {
            .container {
                padding: 0 24px;
            }

            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .order-details {
                grid-template-columns: 1fr;
            }

            .stepper {
                margin: 30px 0;
            }

            .step-label {
                font-size: 11px;
                max-width: 80px;
            }

            .step-circle {
                width: 32px;
                height: 32px;
            }

            .step-icon {
                font-size: 14px;
            }

            .mobile-toggle {
                display: flex;
                flex-direction: column;
                gap: 5px;
                cursor: pointer;
            }

            .mobile-toggle span {
                width: 25px;
                height: 3px;
                background: white;
            }

            .nav-menu {
                display: none;
            }
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Track Your <span>Orders</span></h1>
            <p>Monitor your order status and delivery progress</p>
            <a href="product.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Shop</a>
        </div>

        <div class="orders-container">
            <?php if (count($orders) > 0): ?>
                <?php foreach ($orders as $order):
                    $statuses = ['Ordered', 'Shipped', 'Out for Delivery', 'Delivered'];
                    $current_status = $order['order_status'] ?? 'Ordered'; // Default fallback
                    $current_index = array_search($current_status, $statuses);
                    $progress_percent = ($current_index / (count($statuses) - 1)) * 100;
                    ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-id">Order #<?= $order['id'] ?></div>
                                <div class="order-date"><?= date('F d, Y', strtotime($order['created_at'])) ?></div>
                            </div>
                        </div>

                        <!-- Progress Tracker -->
                        <div class="progress-tracker">
                            <div class="current-status">
                                <div class="current-status-label">Current Status</div>
                                <div class="current-status-value"><?= $current_status ?></div>
                            </div>

                            <div class="stepper">
                                <div class="stepper-line"></div>
                                <div class="stepper-progress" style="width: <?= $progress_percent ?>%"></div>

                                <?php foreach ($statuses as $index => $status):
                                    $step_class = '';
                                    if ($index < $current_index) {
                                        $step_class = 'completed';
                                    } elseif ($index == $current_index) {
                                        $step_class = 'active';
                                    }

                                    $icons = [
                                        'Ordered' => 'check-circle',
                                        'Shipped' => 'shipping-fast',
                                        'Out for Delivery' => 'truck',
                                        'Delivered' => 'box-check'
                                    ];
                                    ?>
                                    <div class="step <?= $step_class ?>">
                                        <div class="step-circle">
                                            <?php if ($step_class == 'completed' || $step_class == 'active'): ?>
                                                <i class="fas fa-<?= $icons[$status] ?> step-icon"></i>
                                            <?php else: ?>
                                                <i class="far fa-circle step-icon"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="step-label"><?= $status ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Order Details -->
                        <div class="order-details">
                            <div class="detail-item">
                                <span class="detail-label">Product</span>
                                <span class="detail-value"><?= htmlspecialchars($order['product_name']) ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Quantity</span>
                                <span class="detail-value"><?= $order['quantity'] ?> units</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Total Amount</span>
                                <span class="detail-value">₹<?= number_format($order['total']) ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Delivery Location</span>
                                <span class="detail-value"><?= htmlspecialchars($order['city']) ?>,
                                    <?= htmlspecialchars($order['pin']) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h2>No Orders Yet</h2>
                    <p>You haven't placed any orders. Start shopping to see your orders here!</p>
                    <a href="product.php" class="back-btn"><i class="fas fa-shopping-cart"></i> Start Shopping</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-column">
                    <h3>NutriPlan</h3>
                    <p>Your trusted partner in nutrition and wellness. Transform your health with personalized meal
                        plans, expert guidance, and comprehensive tracking.</p>
                </div>
                <div class="footer-column">
                    <h4>Services</h4>
                    <ul>
                        <li><a href="dailychallenge.php">Daily Challenge</a></li>
                        <li><a href="product.php">Supplement Order</a></li>
                        <li><a href="personaldashbord.php">Dashboard</a></li>
                        <li><a href="workout.php">Workout Plans</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="#help">Help Center</a></li>
                        <li><a href="#faq">FAQ</a></li>
                        <li><a href="#privacy">Privacy Policy</a></li>
                        <li><a href="#terms">Terms of Service</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Contact</h4>
                    <ul>
                        <li><a href="mailto:support@nutriplan.com">support@nutriplan.com</a></li>
                        <li><a href="tel:+911234567890">+91 123 456 7890</a></li>
                        <li class="social-links">
                            <a href="#" aria-label="Facebook">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                                </svg>
                            </a>
                            <a href="#" aria-label="Twitter">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z">
                                    </path>
                                </svg>
                            </a>
                            <a href="#" aria-label="Instagram">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                                    <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                                    <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                                </svg>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2026 NutriPlan. All rights reserved.</p>
            </div>
        </div>
    </footer>

</body>

</html>