<?php
// Database Connection
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'fitness_app';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("<h3 style='color:red;'>❌ Connection failed: " . $conn->connect_error . "</h3>");
}

echo "<h2>Database Update: Stock Management</h2>";

// Check if stock_status column exists
$check_sql = "SHOW COLUMNS FROM `products` LIKE 'stock_status'";
$result = $conn->query($check_sql);

if ($result->num_rows == 0) {
    // Add column
    $sql = "ALTER TABLE products ADD COLUMN stock_status ENUM('In Stock', 'Out of Stock') DEFAULT 'In Stock' AFTER description";
    if ($conn->query($sql)) {
        echo "<p style='color:green;'>✅ Column 'stock_status' added successfully.</p>";
    } else {
        echo "<p style='color:red;'>❌ Error adding 'stock_status': " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:blue;'>ℹ️ Column 'stock_status' already exists.</p>";
}

$conn->close();
echo "<br><p><b>Update complete. <a href='admin_product.php'>Go back to Admin Product Management</a></b></p>";
?>
