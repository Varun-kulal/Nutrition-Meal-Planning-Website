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

// Create workout_logs table
$sql = "CREATE TABLE IF NOT EXISTS workout_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    exercise_name VARCHAR(100) NOT NULL,
    reps_completed INT,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table workout_logs created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>
