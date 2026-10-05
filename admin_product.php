<?php
session_start();

// Strict Admin Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "fitness_app");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);

// 🔧 SELF-HEALING DATABASE CHECK
$check_stock = $conn->query("SHOW COLUMNS FROM `products` LIKE 'stock_status'");
if ($check_stock->num_rows == 0) {
    $conn->query("ALTER TABLE products ADD COLUMN stock_status ENUM('In Stock', 'Out of Stock') DEFAULT 'In Stock' AFTER description");
}

// 🔧 QUANTITY COLUMN CHECK
$check_qty = $conn->query("SHOW COLUMNS FROM `products` LIKE 'quantity'");
if ($check_qty->num_rows == 0) {
    $conn->query("ALTER TABLE products ADD COLUMN quantity INT DEFAULT 0 AFTER price");
}

$msg = "";
$msg_type = "";

// Handle Delete
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    
    // Get image filename to delete from uploads folder
    $img_query = $conn->query("SELECT image FROM products WHERE id = $delete_id");
    if ($img_row = $img_query->fetch_assoc()) {
        $img_path = "uploads/" . $img_row['image'];
        if (file_exists($img_path)) {
            unlink($img_path);
        }
    }
    
    // Delete from database
    if ($conn->query("DELETE FROM products WHERE id = $delete_id")) {
        $msg = "Product deleted successfully!";
        $msg_type = "success";
    } else {
        $msg = "Error deleting product: " . $conn->error;
        $msg_type = "error";
    }
}

// Handle Stock Toggle
if (isset($_GET['toggle_stock'])) {
    $id = intval($_GET['toggle_stock']);
    $current_status = $conn->query("SELECT stock_status FROM products WHERE id = $id")->fetch_assoc()['stock_status'];
    $new_status = ($current_status === 'In Stock') ? 'Out of Stock' : 'In Stock';
    
    if ($conn->query("UPDATE products SET stock_status = '$new_status' WHERE id = $id")) {
        $msg = "Stock status updated to $new_status!";
        $msg_type = "success";
    } else {
        $msg = "Error updating stock: " . $conn->error;
        $msg_type = "error";
    }
}

if (isset($_POST['add'])) {
    if (!is_dir("uploads")) mkdir("uploads");

    $img = time() . "_" . $_FILES['image']['name'];
    move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $img);

    $stmt = $conn->prepare(
        "INSERT INTO products (name, price, quantity, protein, carbs, fat, calories, image, description, stock_status) VALUES (?,?,?,?,?,?,?,?,?,'In Stock')"
    );
    $stmt->bind_param(
        "siissssss",
        $_POST['name'],
        $_POST['price'],
        $_POST['quantity'],
        $_POST['protein'],
        $_POST['carbs'],
        $_POST['fat'],
        $_POST['calories'],
        $img,
        $_POST['description']
    );
    
    if ($stmt->execute()) {
        $msg = "Product added successfully!";
        $msg_type = "success";
    } else {
        $msg = "Error adding product: " . $conn->error;
        $msg_type = "error";
    }
}

// Handle Update
if (isset($_POST['update'])) {
    $id = intval($_POST['product_id']);
    $name = $_POST['name'];
    $price = $_POST['price'];
    $quantity = $_POST['quantity'];
    $protein = $_POST['protein'];
    $carbs = $_POST['carbs'];
    $fat = $_POST['fat'];
    $calories = $_POST['calories'];
    $description = $_POST['description'];

    $sql = "UPDATE products SET name=?, price=?, quantity=?, protein=?, carbs=?, fat=?, calories=?, description=? WHERE id=?";
    $params = [$name, $price, $quantity, $protein, $carbs, $fat, $calories, $description, $id];
    $types = "siisssssi";

    // Handle Image Update if provided
    if (!empty($_FILES['image']['name'])) {
        $img = time() . "_" . $_FILES['image']['name'];
        move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $img);
        $sql = "UPDATE products SET name=?, price=?, quantity=?, protein=?, carbs=?, fat=?, calories=?, description=?, image=? WHERE id=?";
        $params[] = $img;
        // Fix params order for image update
        $params = [$name, $price, $quantity, $protein, $carbs, $fat, $calories, $description, $img, $id];
        $types = "siissssssi";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $msg = "Product updated successfully!";
        $msg_type = "success";
        // Clear edit mode
        echo "<script>window.location.href='admin_product.php';</script>";
    } else {
        $msg = "Error updating product: " . $conn->error;
        $msg_type = "error";
    }
}

// Fetch Product for Editing
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_data = $conn->query("SELECT * FROM products WHERE id = $edit_id")->fetch_assoc();
}

// Fetch all products
$products = $conn->query("SELECT * FROM products ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Product Management | NutriAdmin</title>
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
    --table-header: #333;
}

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
        .menu a { font-size: 15px; color: var(--text-secondary); text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 12px; transition: 0.2s; }
        .menu a i { width: 20px; text-align: center; }
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
.admin-badge { padding: 8px 16px; background: rgba(255, 193, 7, 0.15); color: var(--accent-yellow); border: 1px solid rgba(255, 193, 7, 0.2); border-radius: 30px; font-weight: 700; font-size: 13px; display: inline-block; }

/* FORM CARD */
.form-card {
    background: var(--bg-card);
    padding: 40px;
    border-radius: 16px;
    border: 1px solid rgba(255,255,255,0.05);
    margin-bottom: 40px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 600;
}

.form-group input, .form-group textarea {
    width: 100%;
    padding: 12px 16px;
    background: #252525;
    border: 1px solid #333;
    color: #fff;
    border-radius: 8px;
    font-family: inherit;
    font-size: 15px;
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.btn-submit {
    background: var(--accent-yellow);
    color: #000;
    border: none;
    padding: 14px 30px;
    border-radius: 30px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    transition: 0.2s;
}

.btn-submit:hover {
    background: #FFB300;
    transform: translateY(-2px);
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    font-weight: 600;
}

.alert.success {
    background: rgba(16, 185, 129, 0.2);
    color: #10B981;
    border: 1px solid #10B981;
}

.alert.error {
    background: rgba(239, 68, 68, 0.2);
    color: #EF4444;
    border: 1px solid #EF4444;
}

/* TABLE */
.section-title { font-size: 20px; font-weight: 700; margin-bottom: 20px; }
.table-container { background: var(--bg-card); border-radius: 16px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05); }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 15px 20px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }
th { background: var(--table-header); color: var(--text-secondary); font-size: 12px; text-transform: uppercase; }
tr:hover { background: rgba(255,255,255,0.02); }

.product-img { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; }

.status-badge {
    padding: 6px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.status-instock { background: rgba(16, 185, 129, 0.15); color: #10B981; border: 1px solid rgba(16, 185, 129, 0.3); }
.status-outofstock { background: rgba(239, 68, 68, 0.15); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.3); }

.logout-link { margin-top: auto; display: block; color: #ff6b6b; text-decoration: none; font-weight: 600; padding: 10px 15px; }
</style>
</head>
<body>

<div class="sidebar">
    <div class="brand">Nutri<span>Admin</span></div>
    <ul class="menu">
        <li><a href="admin.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
        <li><a href="admin.php#orders"><i class="fas fa-box"></i> Orders</a></li>
        <li><a href="admin_users.php"><i class="fas fa-users"></i> Users</a></li>
        <li><a href="admin_product.php" class="active"><i class="fas fa-shopping-bag"></i> Products</a></li>
        <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback</a></li>
        <li><a href="index.php"><i class="fas fa-globe"></i> View Website</a></li>
    </ul>
    <a href="admin_logout.php" class="logout-link">Logout</a>
</div>

<div class="main-content">
    <div class="header">
        <h1>Product Management</h1>
        <div style="text-align: right;">
            <span class="admin-badge">Admin Access</span>
            <div style="margin-top: 5px; font-size: 12px; color: #888;">
                ID: <?= $_SESSION['admin_id'] ?> | <?= htmlspecialchars($_SESSION['admin_user']) ?>
            </div>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert <?= $msg_type ?>"><?= $msg ?></div>
    <?php endif; ?>

    <div class="form-card" id="product-form">
        <h2 style="margin-bottom: 30px; font-size: 24px;">
            <?= $edit_data ? 'Edit Product: ' . htmlspecialchars($edit_data['name']) : 'Add New Product' ?>
        </h2>
        
        <form method="post" enctype="multipart/form-data">
            <?php if ($edit_data): ?>
                <input type="hidden" name="product_id" value="<?= $edit_data['id'] ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label>Product Name</label>
                    <input name="name" placeholder="Enter product name" value="<?= $edit_data['name'] ?? '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Price (₹)</label>
                    <input name="price" type="number" placeholder="Enter price" value="<?= $edit_data['price'] ?? '' ?>" required>
                </div>

                <div class="form-group">
                    <label>Quantity</label>
                    <input name="quantity" type="number" placeholder="Enter stock quantity" value="<?= $edit_data['quantity'] ?? '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Protein (g)</label>
                    <input name="protein" placeholder="e.g., 25g" value="<?= $edit_data['protein'] ?? '' ?>">
                </div>
                
                <div class="form-group">
                    <label>Carbs (g)</label>
                    <input name="carbs" placeholder="e.g., 10g" value="<?= $edit_data['carbs'] ?? '' ?>">
                </div>
                
                <div class="form-group">
                    <label>Fat (g)</label>
                    <input name="fat" placeholder="e.g., 5g" value="<?= $edit_data['fat'] ?? '' ?>">
                </div>
                
                <div class="form-group">
                    <label>Calories</label>
                    <input name="calories" placeholder="e.g., 200" value="<?= $edit_data['calories'] ?? '' ?>">
                </div>

                
                <div class="form-group full-width">
                    <label>Description</label>
                    <textarea name="description" placeholder="Enter product description"><?= $edit_data['description'] ?? '' ?></textarea>
                </div>
                
                <div class="form-group full-width">
                    <label>Product Image <?= $edit_data ? '<small>(Leave empty to keep current)</small>' : '' ?></label>
                    <input type="file" name="image" accept="image/*" <?= $edit_data ? '' : 'required' ?>>
                    <?php if ($edit_data && $edit_data['image']): ?>
                        <div style="margin-top: 10px;">
                            <img src="uploads/<?= $edit_data['image'] ?>" style="height: 50px; border-radius: 5px;">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <button name="<?= $edit_data ? 'update' : 'add' ?>" class="btn-submit">
                <i class="fas <?= $edit_data ? 'fa-save' : 'fa-plus' ?>"></i> 
                <?= $edit_data ? 'Update Product' : 'Add Product' ?>
            </button>
            
            <?php if ($edit_data): ?>
                <a href="admin_product.php" style="margin-left: 15px; color: #888; text-decoration: none;">Cancel</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="section-title">Existing Products</div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th>Nutrition</th>
                    <th>Stock</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $products->fetch_assoc()): ?>
                <tr>
                    <td>#<?= $row['id'] ?></td>
                    <td><img src="uploads/<?= $row['image'] ?>" class="product-img" alt="Product"></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td>₹<?= $row['price'] ?></td>
                    <td><?= $row['quantity'] ?? 0 ?></td>
                    <td style="font-size: 12px; color: #888;">
                        P: <?= $row['protein'] ?> | C: <?= $row['carbs'] ?> | F: <?= $row['fat'] ?> | Cal: <?= $row['calories'] ?>
                    </td>
                    <td>
                        <?php 
                        // Auto-correct status display based on Quantity
                        if ($row['quantity'] <= 0) {
                            $stock_class = 'status-outofstock';
                            $stock_label = 'Out of Stock';
                        } else {
                            $stock_class = (isset($row['stock_status']) && $row['stock_status'] === 'Out of Stock') ? 'status-outofstock' : 'status-instock';
                            $stock_label = ($row['quantity'] > 0 && $stock_class === 'status-outofstock') ? 'Out of Stock (Manual)' : ($row['stock_status'] ?? 'In Stock');
                            if ($stock_class === 'status-instock') $stock_label = 'In Stock';
                        }
                        ?>
                        <span class="status-badge <?= $stock_class ?>"><?= $stock_label ?></span>
                    </td>
                    <td style="display: flex; gap: 10px;">
                        <a href="?toggle_stock=<?= $row['id'] ?>" 
                           style="background: #3B82F6; color: #fff; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 600;">
                            <i class="fas fa-sync"></i> Toggle Stock
                        </a>
                        <a href="?edit=<?= $row['id'] ?>#product-form" 
                           style="background: #F59E0B; color: #000; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 700;">
                            <i class="fas fa-edit"></i> Update
                        </a>
                        <a href="?delete=<?= $row['id'] ?>" 
                           onclick="return confirm('Are you sure you want to delete this product?')" 
                           style="background: #EF4444; color: #fff; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 600;">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
