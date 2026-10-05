<?php
$conn = new mysqli('localhost', 'root', '', 'fitness_app');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$res = $conn->query("SELECT id, name, stock_status, quantity FROM products");
echo "PRODUCT_LIST_START\n";
while($row = $res->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | Status: " . $row['stock_status'] . " | Qty: " . $row['quantity'] . "\n";
}
echo "PRODUCT_LIST_END";
?>
