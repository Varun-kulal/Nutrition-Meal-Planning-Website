<?php
// Database Connection
$conn = new mysqli("localhost", "root", "", "fitness_app");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Disable exception reporting to handle duplicate column error manually
mysqli_report(MYSQLI_REPORT_OFF);

// Add payment columns to orders table
$sqls = [
    "ALTER TABLE orders ADD COLUMN payment_method ENUM('COD', 'UPI') DEFAULT 'COD' AFTER landmark",
    "ALTER TABLE orders ADD COLUMN payment_screenshot VARCHAR(255) DEFAULT NULL AFTER payment_method"
];

foreach ($sqls as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "✅ SQL Success: " . $sql . "<br>";
    } else {
        if (strpos($conn->error, 'Duplicate column name') !== false) {
            echo "ℹ️ Info: Column already exists for: " . $sql . "<br>";
        } else {
            echo "❌ Error: " . $conn->error . "<br>";
        }
    }
}

$conn->close();
echo "<br><b>Database migration completed. You can now close this page.</b>";
?>
