<?php
session_start();

// Strict Admin Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Database Connection
$conn = new mysqli("localhost", "root", "", "fitness_app");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get POST data
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$new_status = isset($_POST['status']) ? $_POST['status'] : '';

// Validate inputs
if ($order_id <= 0 || empty($new_status)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

// Validate status value
$valid_statuses = ['Ordered', 'Shipped', 'Out for Delivery', 'Delivered'];
if (!in_array($new_status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit();
}

// Get current status to validate progression
$check_sql = "SELECT order_status FROM orders WHERE id = ?";
$check_stmt = $conn->prepare($check_sql);

if (!$check_stmt) {
    // If prepare fails, it's likely the column doesn't exist
    // Return a clear error message instructing to run migration
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $conn->error . '. Please run the migration script at /add_order_status_column.php'
    ]);
    exit();
}

$check_stmt->bind_param("i", $order_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit();
}

$row = $result->fetch_assoc();
$current_status = $row['order_status'] ?? 'Ordered'; // Default if null
$check_stmt->close();

// Validate status progression (can only move forward)
// Handle case where current status might be invalid/empty by defaulting to 'Ordered'
if (!in_array($current_status, $valid_statuses)) {
    $current_status = 'Ordered';
}

$status_order = array_flip($valid_statuses);
if ($status_order[$new_status] <= $status_order[$current_status]) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cannot move to previous status']);
    exit();
}

// Update order status
$update_sql = "UPDATE orders SET order_status = ? WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);

if (!$update_stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Update failed: ' . $conn->error . '. Please run migration.'
    ]);
    exit();
}

$update_stmt->bind_param("si", $new_status, $order_id);

if ($update_stmt->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => 'Order status updated successfully',
        'new_status' => $new_status
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $update_stmt->error]);
}

$update_stmt->close();
$conn->close();
?>
