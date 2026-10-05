<?php
session_start();

// Strict Login Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database Connection
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'fitness_app';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 🔧 SELF-HEALING DATABASE CHECK
$check_pay = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'payment_method'");
if ($check_pay->num_rows == 0) {
    $conn->query("ALTER TABLE orders ADD COLUMN payment_method ENUM('COD', 'UPI') DEFAULT 'COD' AFTER landmark");
}
$check_pay_img = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'payment_screenshot'");
if ($check_pay_img->num_rows == 0) {
    $conn->query("ALTER TABLE orders ADD COLUMN payment_screenshot VARCHAR(255) DEFAULT NULL AFTER payment_method");
}
$check_stock = $conn->query("SHOW COLUMNS FROM `products` LIKE 'stock_status'");
if ($check_stock->num_rows == 0) {
    $conn->query("ALTER TABLE products ADD COLUMN stock_status ENUM('In Stock', 'Out of Stock') DEFAULT 'In Stock' AFTER description");
}

/* PRODUCTS DATA - Fetch from Database */
$products_result = $conn->query("SELECT * FROM products ORDER BY id DESC");
$products = [];
while ($row = $products_result->fetch_assoc()) {
    $products[] = $row;
}

/* SAVE ORDER */
$order_msg = '';
if (isset($_POST['place_order'])) {
    $payment_method = $_POST['payment_method'];
    $payment_screenshot = null;

    if ($payment_method === 'UPI' && isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] === 0) {
        $ext = pathinfo($_FILES['payment_screenshot']['name'], PATHINFO_EXTENSION);
        $filename = 'pay_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $target = 'uploads/' . $filename;
        if (move_uploaded_file($_FILES['payment_screenshot']['tmp_name'], $target)) {
            $payment_screenshot = $filename;
        }
    }

    // Verify Stock Availability
    $prod_id = intval($_POST['product_id']); // Ensure product_id is passed in form
    $ordered_qty = intval($_POST['qty']);
    
    $stock_check = $conn->query("SELECT quantity, stock_status FROM products WHERE id = $prod_id");
    $prod_data = $stock_check->fetch_assoc();
    
    if (!$prod_data) {
        $order_msg = "❌ Error: Invalid product.";
    } elseif ($prod_data['stock_status'] === 'Out of Stock' || $prod_data['quantity'] < $ordered_qty) {
        $order_msg = "❌ Error: Insufficient stock available.";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO orders (user_id, product_name, price, quantity, total, city, pin, phone, landmark, payment_method, payment_screenshot)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

    $stmt->bind_param(
        "isiiissssss",
        $_SESSION['user_id'],
        $_POST['product'],
        $_POST['price'],
        $_POST['qty'],
        $_POST['total'],
        $_POST['city'],
        $_POST['pin'],
        $_POST['phone'],
        $_POST['landmark'],
        $payment_method,
        $payment_screenshot
    );

    if ($stmt->execute()) {
        // Decrease Stock
        $conn->query("UPDATE products SET quantity = quantity - $ordered_qty WHERE id = $prod_id");
        
        // Check if stock reached 0
        $new_qty_check = $conn->query("SELECT quantity FROM products WHERE id = $prod_id");
        if ($new_row = $new_qty_check->fetch_assoc()) {
            if ($new_row['quantity'] <= 0) {
                $conn->query("UPDATE products SET stock_status = 'Out of Stock' WHERE id = $prod_id");
            }
        }

        $order_msg = "✅ Order placed successfully! We'll contact you shortly.";
    } else {
        $order_msg = "❌ Error placing order. Please try again.";
    }
    } // End else
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplement Store | NutriPlan</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ==================== INHERITING VARIABLES FROM STYLES.CSS ==================== */
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

        /* NAVBAR COPY */
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
            margin: 0 auto;
        }

        /* Track Order Button - Matching Dashboard Re-Calculate Button */
        .track-order-btn {
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
            margin-bottom: 40px;
        }

        .track-order-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.2);
        }

        /* PRODUCT GRID */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 32px;
            margin-bottom: 80px;
        }

        .card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: 0.3s;
            display: flex;
            flex-direction: column;
        }

        .card:hover {
            transform: translateY(-8px);
            border-color: var(--accent-yellow);
            box-shadow: 0 10px 30px rgba(255, 193, 7, 0.1);
        }

        .image-box {
            background: #fff;
            border-radius: 12px;
            height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .image-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .price-tag {
            position: absolute;
            top: 12px;
            right: 12px;
            background: var(--accent-yellow);
            color: #000;
            padding: 6px 12px;
            font-weight: 700;
            border-radius: 8px;
            font-size: 14px;
        }

        .card h3 {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 10px;
            color: var(--text-primary);
        }

        .desc {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 20px;
            line-height: 1.5;
            flex-grow: 1;
        }

        .btns {
            display: flex;
            gap: 12px;
            margin-top: auto;
        }

        .btn-card {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            font-size: 13px;
            transition: 0.2s;
            text-transform: uppercase;
        }

        .btn-details {
            background: transparent;
            border: 1px solid var(--text-secondary);
            color: var(--text-primary);
        }

        .btn-details:hover {
            border-color: var(--text-primary);
            background: rgba(255, 255, 255, 0.05);
        }

        .btn-buy {
            background: var(--accent-yellow);
            color: #000;
        }

        .btn-buy:hover {
            background: #FFB300;
            transform: translateY(-2px);
        }

        /* MODAL STYLES */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(5px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal-content {
            background: #1C1C1C;
            width: 100%;
            max-width: 480px;
            padding: 32px;
            border-radius: 24px;
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
            max-height: 90vh;
            overflow-y: auto;
        }

        .close-modal {
            position: absolute;
            right: 24px;
            top: 24px;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-secondary);
            transition: 0.2s;
        }

        .close-modal:hover {
            color: var(--accent-yellow);
        }

        .modal-title {
            font-size: 24px;
            color: var(--accent-yellow);
            margin-bottom: 12px;
            margin-top: 0;
        }

        /* Nutrition Grid code reuse */
        .nutrition-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 24px;
            background: rgba(255, 255, 255, 0.03);
            padding: 16px;
            border-radius: 12px;
        }

        .nutri-item {
            text-align: center;
        }

        .nutri-val {
            display: block;
            font-weight: 700;
            color: #fff;
            font-size: 15px;
            margin-bottom: 4px;
        }

        .nutri-label {
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        /* Order Form */
        .qty-control {
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 24px 0;
            background: rgba(255, 255, 255, 0.03);
            padding: 12px;
            border-radius: 12px;
            justify-content: center;
        }

        .qty-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 1px solid var(--text-secondary);
            background: transparent;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }

        .qty-btn:hover {
            border-color: var(--accent-yellow);
            color: var(--accent-yellow);
        }

        #qtyDisplay {
            font-size: 18px;
            font-weight: 700;
            min-width: 24px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-input {
            width: 100%;
            padding: 14px;
            background: #000;
            border: 1px solid #333;
            border-radius: 12px;
            color: white;
            font-family: inherit;
            transition: 0.3s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-yellow);
        }

        .total-section {
            text-align: center;
            margin: 24px 0;
            padding: 16px;
            background: rgba(255, 193, 7, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        .total-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            display: block;
            margin-bottom: 4px;
        }

        .total-amount {
            font-size: 28px;
            font-weight: 800;
            color: var(--accent-yellow);
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: var(--accent-yellow);
            color: black;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
        }

        .submit-btn:hover {
            background: #FFB300;
            transform: translateY(-2px);
        }

        /* Payment Selector */
        .payment-selector {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }

        .pay-option {
            flex: 1;
            padding: 16px;
            background: rgba(255, 255, 255, 0.03);
            border: 2px solid #333;
            border-radius: 16px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .pay-option.active {
            border-color: var(--accent-yellow);
            background: rgba(255, 193, 7, 0.1);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 193, 7, 0.1);
        }

        .pay-option i {
            font-size: 24px;
            color: var(--text-secondary);
            transition: 0.3s;
        }

        .pay-option.active i {
            color: var(--accent-yellow);
            transform: scale(1.1);
        }

        .pay-option span {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-secondary);
        }

        .pay-option.active span {
            color: #fff;
        }

        /* UPI QR Section */
        #upiSection {
            display: none;
            background: #111;
            padding: 24px;
            border-radius: 20px;
            margin-bottom: 24px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.05);
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .qr-container {
            background: white;
            padding: 12px;
            border-radius: 16px;
            display: inline-block;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .qr-container img {
            width: 180px;
            height: 180px;
            display: block;
        }

        .upi-id-box {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.05);
            padding: 10px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .upi-id-text {
            font-family: monospace;
            font-size: 14px;
            color: var(--accent-yellow);
            font-weight: 600;
        }

        .copy-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            transition: 0.2s;
            padding: 4px;
        }

        .copy-btn:hover {
            color: var(--accent-yellow);
        }

        .upi-instructions {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .upload-box {
            position: relative;
            background: #000;
            border: 2px dashed #444;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            transition: 0.3s;
            cursor: pointer;
        }

        .upload-box:hover {
            border-color: var(--accent-yellow);
            background: rgba(255, 193, 7, 0.02);
        }

        .upload-box i {
            display: block;
            font-size: 32px;
            color: var(--text-secondary);
            margin-bottom: 12px;
        }

        .upload-box span {
            display: block;
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .upload-box small {
            font-size: 11px;
            color: #666;
        }

        .upload-box input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .file-name-display {
            margin-top: 10px;
            font-size: 12px;
            color: var(--accent-yellow);
            font-weight: 600;
            display: none;
        }

        /* STOCK BADGES */
        .stock-badge {
            position: absolute;
            top: 20px;
            left: 20px;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 10;
        }

        .in-stock {
            background: rgba(16, 185, 129, 0.9);
            color: white;
        }

        .out-of-stock {
            background: rgba(239, 68, 68, 0.9);
            color: white;
        }

        /* DISABLED BUY BUTTON */
        .btn-buy:disabled {
            background: #444;
            color: #888;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .alert {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #1C1C1C;
            border-left: 4px solid var(--accent-yellow);
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            animation: slideIn 0.3s ease;
            z-index: 3000;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
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

        /* Footer Copy */
        footer {
            padding: 40px;
            text-align: center;
            color: #666;
            font-size: 14px;
            border-top: 1px solid rgba(255, 193, 7, 0.1);
            background-color: var(--bg-secondary);
        }

        @media(max-width: 768px) {
            .container {
                padding: 0 24px;
            }

            .grid {
                grid-template-columns: 1fr;
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

    <!-- ALERT MESSAGE -->
    <?php if ($order_msg): ?>
        <div class="alert" id="alertBox">
            <h3><?= $order_msg ?></h3>
        </div>
        <script>setTimeout(() => document.getElementById('alertBox').style.display = 'none', 4000);</script>
    <?php endif; ?>

    <div class="container">
        <div class="page-header">
            <h1>Premium <span>Supplements</span></h1>
            <p>Curated selection of top-tier nutrition for your fitness journey.</p><br><br>
            <a href="order.php" class="track-order-btn"><i class="fas fa-shipping-fast"></i> Track Your Order</a>
        </div>

        <div class="grid">
            <?php foreach ($products as $p) { ?>
                <div class="card">
                    <div class="image-box">
                        <?php 
                        $stock_status = $p['stock_status'] ?? 'In Stock';
                        $quantity = $p['quantity'] ?? 0;
                        $is_out_of_stock = ($stock_status === 'Out of Stock' || $quantity <= 0);
                        ?>
                        <?php if ($is_out_of_stock): ?>
                            <span class="stock-badge out-of-stock">Out of Stock</span>
                        <?php else: ?>
                            <span class="stock-badge in-stock">In Stock</span>
                        <?php endif; ?>
                        
                        <span class="price-tag">₹<?= $p['price'] ?></span>
                        <img src="uploads/<?= $p['image'] ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                    </div>

                    <h3><?= $p['name'] ?></h3>
                    <div class="desc"><?= $p['description'] ?></div>

                    <div class="btns">
                        <button class="btn-card btn-details"
                            data-product='<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>'
                            onclick="openDetails(this)">
                            View Details
                        </button>
                        <button class="btn-card btn-buy"
                            data-product='<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>'
                            onclick="openOrder(this)"
                            <?= $is_out_of_stock ? 'disabled' : '' ?>>
                            <?= $is_out_of_stock ? 'Out of Stock' : 'Buy Now' ?>
                        </button>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- DETAILS MODAL -->
    <div class="modal" id="detailsModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeAll()">✖</span>
            <h3 id="dname" class="modal-title"></h3>
            <p id="ddesc" style="color:#aaa; line-height:1.6; margin-bottom:20px;"></p>

            <div class="nutrition-grid">
                <div class="nutri-item"><span class="nutri-val" id="dcal"></span><span
                        class="nutri-label">Calories</span></div>
                <div class="nutri-item"><span class="nutri-val" id="dpro"></span><span
                        class="nutri-label">Protein</span></div>
                <div class="nutri-item"><span class="nutri-val" id="dcarb"></span><span class="nutri-label">Carbs</span>
                </div>
                <div class="nutri-item"><span class="nutri-val" id="dfat"></span><span class="nutri-label">Fats</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ORDER MODAL -->
    <div class="modal" id="orderModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeAll()">✖</span>

            <h3 id="oname" class="modal-title"></h3>
            <p style="color:#aaa;">Price: <span style="color:white; font-weight:700;">₹<span id="oprice"></span></span>
                / unit</p>

            <form method="post" enctype="multipart/form-data">
                <div class="qty-control">
                    <button type="button" class="qty-btn" onclick="changeQty(-1)">−</button>
                    <span id="qtyDisplay">1</span>
                    <button type="button" class="qty-btn" onclick="changeQty(1)">+</button>
                </div>

                <input type="hidden" name="product" id="fproduct">
                <input type="hidden" name="product_id" id="fproduct_id"> <!-- HIDDEN ID -->
                <input type="hidden" name="price" id="fprice">
                <input type="hidden" name="qty" id="fqty" value="1">
                <input type="hidden" name="total" id="ftotal">
                <input type="hidden" name="payment_method" id="fpayment" value="COD">

                <div style="font-size: 12px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">Select Payment Method</div>
                <div class="payment-selector">
                    <div class="pay-option active" onclick="selectPayment('COD', this)">
                        <i class="fas fa-hand-holding-dollar"></i>
                        <span>Cash on Delivery</span>
                    </div>
                    <div class="pay-option" onclick="selectPayment('UPI', this)">
                        <i class="fas fa-qrcode"></i>
                        <span>UPI Payment</span>
                    </div>
                </div>

                <div id="upiSection">
                    <div class="qr-container">
                        <img src="images/upi_qr.png" alt="UPI QR Code">
                    </div>
                    
                    <div class="upi-id-box">
                        <span class="upi-id-text" id="upiId">nutriplan@upi</span>
                        <button type="button" class="copy-btn" onclick="copyUPI()" title="Copy UPI ID">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>

                    <div class="upi-instructions">
                        Scan the QR code or pay to the UPI ID above.<br>
                        <strong>Upload the payment screenshot</strong> to confirm.
                    </div>

                    <div class="upload-box" onclick="document.getElementById('screenshotInput').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Click to upload screenshot</span>
                        <small>JPG, PNG or WEBP (Max 2MB)</small>
                        <input type="file" name="payment_screenshot" id="screenshotInput" accept="image/*" onchange="handleFileSelect(this)">
                        <div class="file-name-display" id="fileName"></div>
                    </div>
                </div>

                <div class="form-group"><input name="city" class="form-input" placeholder="City" required></div>
                <div class="form-group"><input name="pin" class="form-input" placeholder="Pincode" required></div>
                <div class="form-group"><input name="phone" class="form-input" placeholder="Phone Number" required>
                </div>
                <div class="form-group"><input name="landmark" class="form-input" placeholder="Landmark" required></div>

                <div class="total-section">
                    <span class="total-label">Total Amount</span>
                    <span class="total-amount" id="totalDisplay">₹0</span>
                </div>

                <button class="submit-btn" name="place_order">Confirm Order</button>
            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        let price = 0, qty = 1;

        function openDetails(btn) {
            let p = JSON.parse(btn.dataset.product);
            document.getElementById('dname').innerText = p.name;
            document.getElementById('ddesc').innerText = p.description;
            document.getElementById('dcal').innerText = p.calories;
            document.getElementById('dpro').innerText = p.protein;
            document.getElementById('dcarb').innerText = p.carbs;
            document.getElementById('dfat').innerText = p.fat;
            document.getElementById('detailsModal').style.display = "flex";
        }

        function openOrder(btn) {
            let p = JSON.parse(btn.dataset.product);
            qty = 1;
            price = p.price;
            document.getElementById('oname').innerText = p.name;
            document.getElementById('oprice').innerText = p.price;
            document.getElementById('fproduct').value = p.name;
            document.getElementById('fproduct_id').value = p.id; // Populate ID
            document.getElementById('fprice').value = p.price;
            updateTotal();
            document.getElementById('orderModal').style.display = "flex";
        }

        function changeQty(n) {
            qty += n;
            if (qty < 1) qty = 1;
            document.getElementById("qtyDisplay").innerText = qty;
            updateTotal();
        }

        function updateTotal() {
            let total = qty * price;
            document.getElementById("totalDisplay").innerText = "₹" + total;
            document.getElementById("fqty").value = qty;
            document.getElementById("ftotal").value = total;
        }

        function selectPayment(method, el) {
            document.querySelectorAll('.pay-option').forEach(opt => opt.classList.remove('active'));
            el.classList.add('active');
            document.getElementById('fpayment').value = method;
            
            const upiSection = document.getElementById('upiSection');
            const screenshotInput = document.getElementById('screenshotInput');
            
            if (method === 'UPI') {
                upiSection.style.display = 'block';
                screenshotInput.required = true;
            } else {
                upiSection.style.display = 'none';
                screenshotInput.required = false;
            }
        }

        function copyUPI() {
            const upiText = document.getElementById('upiId').innerText;
            navigator.clipboard.writeText(upiText).then(() => {
                const btn = document.querySelector('.copy-btn i');
                btn.className = 'fas fa-check';
                btn.style.color = '#10B981';
                setTimeout(() => {
                    btn.className = 'fas fa-copy';
                    btn.style.color = '';
                }, 2000);
            });
        }

        function handleFileSelect(input) {
            const fileName = input.files[0] ? input.files[0].name : '';
            const display = document.getElementById('fileName');
            if (fileName) {
                display.innerText = "Selected: " + fileName;
                display.style.display = 'block';
            } else {
                display.style.display = 'none';
            }
        }

        function closeAll() {
            document.querySelectorAll(".modal").forEach(m => m.style.display = "none");
        }

        window.onclick = function (event) {
            if (event.target.classList.contains('modal')) {
                closeAll();
            }
        }
    </script>

</body>

</html>