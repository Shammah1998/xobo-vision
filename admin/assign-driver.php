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

$stmt = $pdo->prepare('SELECT id FROM drivers WHERE order_id = ?');
$stmt->execute([$orderId]);
if ($stmt->fetch()) {
    $stmt = $pdo->prepare('UPDATE drivers SET driver_name = ?, assigned_at = NOW() WHERE order_id = ?');
    $stmt->execute([$driverName, $orderId]);
} else {
    $stmt = $pdo->prepare('INSERT INTO drivers (order_id, driver_name) VALUES (?, ?)');
    $stmt->execute([$orderId, $driverName]);
}

echo json_encode(['success' => true, 'driver_name' => htmlspecialchars($driverName)]); 