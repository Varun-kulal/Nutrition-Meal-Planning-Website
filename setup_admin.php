<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fitness_app";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add is_admin column
$sql = "ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0";
if ($conn->query($sql) === TRUE) {
    echo "Column is_admin added successfully.<br>";
} else {
    echo "Error adding column (might already exist): " . $conn->error . "<br>";
}

// Set first user as admin (for testing purpose)
$sql = "UPDATE users SET is_admin = 1 ORDER BY id ASC LIMIT 1";
if ($conn->query($sql) === TRUE) {
    echo "First user promoted to ADMIN successfully.<br>";
} else {
    echo "Error updating user: " . $conn->error;
}

$conn->close();
?>
