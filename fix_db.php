<?php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'fitness_app';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add custom_meals column
$sql = "ALTER TABLE user_progress ADD COLUMN IF NOT EXISTS custom_meals TEXT";
if ($conn->query($sql) === TRUE) {
    echo "Column custom_meals added successfully (or already exists).\n";
} else {
    echo "Error adding column: " . $conn->error . "\n";
}

// Add any other potentially missing columns for safety
$conn->query("ALTER TABLE user_progress ADD COLUMN IF NOT EXISTS water_liters FLOAT DEFAULT 0");
$conn->query("ALTER TABLE user_progress ADD COLUMN IF NOT EXISTS workouts TEXT");
$conn->query("ALTER TABLE user_progress ADD COLUMN IF NOT EXISTS total_burned INT DEFAULT 0");

$conn->close();
?>