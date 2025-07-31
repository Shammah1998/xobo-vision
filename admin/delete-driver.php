<?php
// admin/delete-driver.php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['order_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?error=Session expired, please log in again.');
    exit;
}

require_once '../config/db.php';
require_once '../includes/functions.php';

// Only allow admins
if (!isAdmin($pdo)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$orderId = (int)$_POST['order_id'];
if ($orderId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
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

    // Check if driver exists for this order
    $stmt = $pdo->prepare('SELECT id FROM drivers WHERE order_id = ?');
    $stmt->execute([$orderId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'No driver assigned to this order']);
        exit;
    }

    // Delete the driver
    $stmt = $pdo->prepare('DELETE FROM drivers WHERE order_id = ?');
    $success = $stmt->execute([$orderId]);

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete driver']);
    }
} catch (PDOException $e) {
    error_log("Driver deletion error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
} 