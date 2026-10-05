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

// Add user_id column
$sql = "ALTER TABLE orders ADD COLUMN user_id INT NOT NULL AFTER id";

if ($conn->query($sql) === TRUE) {
    echo "Column user_id added to orders successfully";
} else {
    echo "Error adding column: " . $conn->error;
}

$conn->close();
?>
