<?php
// Database Connection
$conn = new mysqli("localhost", "root", "", "fitness_app");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Disable exception reporting to handle duplicate column error manually
mysqli_report(MYSQLI_REPORT_OFF);

// Add order_status column to orders table
$sql = "ALTER TABLE orders ADD COLUMN order_status ENUM('Ordered', 'Shipped', 'Out for Delivery', 'Delivered') DEFAULT 'Ordered' AFTER total";

if ($conn->query($sql) === TRUE) {
    echo "✅ Column 'order_status' added successfully to orders table.<br>";
    echo "Default value: 'Ordered'<br>";
    echo "Possible values: Ordered, Shipped, Out for Delivery, Delivered<br><br>";
    echo "You can now close this page and proceed to use the order management system.";
} else {
    // Check if column already exists
    if (strpos($conn->error, 'Duplicate column name') !== false) {
        echo "ℹ️ Column 'order_status' already exists in orders table.<br>";
        echo "No changes needed.";
    } else {
        echo "❌ Error adding column: " . $conn->error;
    }
}

$conn->close();
?>
