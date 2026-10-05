<?php
// Unified Database Fix Script
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'fitness_app';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("<h3 style='color:red;'>❌ Connection failed: " . $conn->connect_error . "</h3>");
}

echo "<h2>🔧 Comprehensive Database Fix</h2>";

function addColumnIfMissing($conn, $table, $column, $definition, $after) {
    $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($check->num_rows == 0) {
        $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition AFTER `$after`";
        if ($conn->query($sql)) {
            echo "<p style='color:green;'>✅ Column '$column' added to '$table' successfully.</p>";
        } else {
            echo "<p style='color:red;'>❌ Error adding '$column' to '$table': " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color:blue;'>ℹ️ Column '$column' already exists in '$table'.</p>";
    }
}

// Fix Orders table
addColumnIfMissing($conn, 'orders', 'payment_method', "ENUM('COD', 'UPI') DEFAULT 'COD'", 'landmark');
addColumnIfMissing($conn, 'orders', 'payment_screenshot', "VARCHAR(255) DEFAULT NULL", 'payment_method');

// Fix Products table
addColumnIfMissing($conn, 'products', 'stock_status', "ENUM('In Stock', 'Out of Stock') DEFAULT 'In Stock'", 'description');

$conn->close();
echo "<br><p><b>Synchronization complete!</b> Your website and admin panel should now work perfectly.</p>";
echo "<p><a href='admin_product.php' style='padding: 10px 20px; background: #FFC107; color: black; text-decoration: none; border-radius: 5px; font-weight: bold;'>Go to Admin Products</a></p>";
?>
