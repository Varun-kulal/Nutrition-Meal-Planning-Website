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

echo "<h2>Database Synchronization</h2>";

// Check if columns exist
$check_method = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'payment_method'");
$check_screenshot = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'payment_screenshot'");

if ($check_method->num_rows == 0) {
    $sql = "ALTER TABLE orders ADD COLUMN payment_method ENUM('COD', 'UPI') DEFAULT 'COD' AFTER landmark";
    if ($conn->query($sql)) {
        echo "<p style='color:green;'>✅ Column 'payment_method' added successfully.</p>";
    } else {
        echo "<p style='color:red;'>❌ Error adding 'payment_method': " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:blue;'>ℹ️ Column 'payment_method' already exists.</p>";
}

if ($check_screenshot->num_rows == 0) {
    $sql = "ALTER TABLE orders ADD COLUMN payment_screenshot VARCHAR(255) DEFAULT NULL AFTER payment_method";
    if ($conn->query($sql)) {
        echo "<p style='color:green;'>✅ Column 'payment_screenshot' added successfully.</p>";
    } else {
        echo "<p style='color:red;'>❌ Error adding 'payment_screenshot': " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:blue;'>ℹ️ Column 'payment_screenshot' already exists.</p>";
}

$conn->close();
echo "<br><p><b>Synchronization complete. Please return to the website and try again.</b></p>";
?>
