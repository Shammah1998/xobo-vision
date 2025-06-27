<?php
// admin/delete-driver.php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['order_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

session_start();
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

$stmt = $pdo->prepare('DELETE FROM drivers WHERE order_id = ?');
$success = $stmt->execute([$orderId]);

echo json_encode(['success' => $success]); 