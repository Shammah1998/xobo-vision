<?php
header('Content-Type: application/json');
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

// Only allow admins
if (!isAdmin($pdo)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['assign_driver_order_id'], $_POST['driver_name'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$orderId = (int)$_POST['assign_driver_order_id'];
$driverName = trim($_POST['driver_name']);

if ($orderId <= 0 || $driverName === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

try {
    // First check if the order exists
    $stmt = $pdo->prepare('SELECT id FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }

    // Check if driver already exists for this order
    $stmt = $pdo->prepare('SELECT id FROM drivers WHERE order_id = ?');
    $stmt->execute([$orderId]);
    
    if ($stmt->fetch()) {
        // Update existing driver
        $stmt = $pdo->prepare('UPDATE drivers SET driver_name = ?, assigned_at = NOW() WHERE order_id = ?');
        $result = $stmt->execute([$driverName, $orderId]);
    } else {
        // Insert new driver
        $stmt = $pdo->prepare('INSERT INTO drivers (order_id, driver_name, assigned_at) VALUES (?, ?, NOW())');
        $result = $stmt->execute([$orderId, $driverName]);
    }

    if ($result) {
        echo json_encode(['success' => true, 'driver_name' => htmlspecialchars($driverName)]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database operation failed']);
    }
} catch (PDOException $e) {
    error_log("Driver assignment error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
} 