<?php
// Debug Script
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'fitness_app';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h3>Table Schema (user_progress):</h3>";
$result = $conn->query("DESCRIBE user_progress");
if ($result) {
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $cell)
            echo "<td>$cell</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error describing table: " . $conn->error;
}

echo "<h3>Recent Entries (Last 5):</h3>";
$result = $conn->query("SELECT id, user_id, day_number, water_liters, date_completed FROM user_progress ORDER BY id DESC LIMIT 5");
if ($result) {
    echo "<table border='1'><tr><th>ID</th><th>User ID</th><th>Day</th><th>Water (L)</th><th>Date</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $cell)
            echo "<td>$cell</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error fetching entries: " . $conn->error;
}

// Test Query Validity for INSERT (Simulation)
echo "<h3>Test INSERT Statement Preparation:</h3>";
$stmt = $conn->prepare("INSERT INTO user_progress (user_id, day_number, diet_type, completed_meals, custom_meals, water_liters, workouts, total_burned, total_calories, total_carbs, total_protein, total_fat, date_completed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())");
if ($stmt) {
    echo "Prepare Success.<br>";
    // Bind verify
    $ret = $stmt->bind_param("iisssdsiiiii", $a, $b, $c, $d, $e, $f, $g, $h, $i, $j, $k, $l);
    if ($ret)
        echo "Bind Success.<br>";
    else
        echo "Bind Failed: " . $stmt->error . "<br>";
} else {
    echo "Prepare Failed: " . $conn->error . "<br>";
}

$conn->close();
?>