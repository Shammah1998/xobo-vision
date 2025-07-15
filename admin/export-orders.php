<?php
// admin/export-orders.php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Allow admins and admin_user
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?error=Session expired, please log in again.');
    exit;
}

// Allow admin, super_admin, and admin_user roles
requireRole(['admin_user']);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="orders_export_' . date('Ymd_His') . '.csv"');

$orderIdSearch = isset($_GET['order_id']) ? trim($_GET['order_id']) : '';
$fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

$where = [];
$params = [];

// If admin_user, filter by company
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin_user' && isset($_SESSION['company_id'])) {
    $where[] = 'o.company_id = ?';
    $params[] = $_SESSION['company_id'];
}

if ($orderIdSearch !== '') {
    $where[] = 'o.id LIKE ?';
    $params[] = "%$orderIdSearch%";
}
if ($fromDate && $toDate) {
    $where[] = 'DATE(o.created_at) BETWEEN ? AND ?';
    $params[] = $fromDate;
    $params[] = $toDate;
} elseif ($fromDate) {
    $where[] = 'DATE(o.created_at) >= ?';
    $params[] = $fromDate;
} elseif ($toDate) {
    $where[] = 'DATE(o.created_at) <= ?';
    $params[] = $toDate;
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare('
    SELECT o.id, o.total_ksh, o.created_at, c.name AS company_name, u.email AS user_email, o.address
    FROM orders o
    JOIN companies c ON o.company_id = c.id
    JOIN users u ON o.user_id = u.id
    ' . $whereClause . '
    ORDER BY o.created_at DESC
');
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all order items, delivery details, and drivers for these orders
$orderIds = array_column($orders, 'id');
$orderItems = [];
$deliveryDetails = [];
$drivers = [];
$vehicleTypes = [];
if ($orderIds) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    // Order items
    $stmt = $pdo->prepare('
        SELECT oi.order_id, p.name, p.sku, oi.quantity, oi.line_total, p.id as product_id
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id IN (' . $placeholders . ')
    ');
    $stmt->execute($orderIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $orderItems[$item['order_id']][] = $item;
    }
    // Delivery details
    $stmt = $pdo->prepare('SELECT * FROM order_delivery_details WHERE order_id IN (' . $placeholders . ')');
    $stmt->execute($orderIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $deliveryDetails[$d['order_id']][$d['product_id']] = $d;
    }
    // Drivers
    $stmt = $pdo->prepare('SELECT order_id, driver_name FROM drivers WHERE order_id IN (' . $placeholders . ')');
    $stmt->execute($orderIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $drivers[$d['order_id']] = $d['driver_name'];
    }
    // Vehicle types
    $stmt = $pdo->prepare('SELECT order_id, vehicle_type FROM order_vehicle_types WHERE order_id IN (' . $placeholders . ')');
    $stmt->execute($orderIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
        $vehicleTypes[$v['order_id']] = $v['vehicle_type'];
    }
}

// Output CSV header
$out = fopen('php://output', 'w');
fputcsv($out, [
    'Order ID', 'Company', 'User Email', 'Total (KSH)', 'Status', 'Date',
    'Product Name', 'SKU', 'Quantity', 'Line Total (KSH)',
    'Pick Up', 'Drop Off', 'Additional Notes', 'Recipient', 'Phone',
    'Driver', 'Vehicle Type'
]);

foreach ($orders as $order) {
    $status = 'Completed';
    if (!empty($orderItems[$order['id']])) {
        foreach ($orderItems[$order['id']] as $item) {
            $d = $deliveryDetails[$order['id']][$item['product_id']] ?? [];
            fputcsv($out, [
                $order['id'],
                $order['company_name'],
                $order['user_email'],
                $order['total_ksh'],
                $status,
                $order['created_at'],
                $item['name'],
                $item['sku'],
                $item['quantity'],
                $item['line_total'],
                $d['pick_up'] ?? '',
                $d['drop_off'] ?? '',
                $d['additional_notes'] ?? '',
                $d['recipient_name'] ?? '',
                $d['recipient_phone'] ?? '',
                $drivers[$order['id']] ?? '',
                $vehicleTypes[$order['id']] ?? ''
            ]);
        }
    } else {
        // No items for this order
        fputcsv($out, [
            $order['id'],
            $order['company_name'],
            $order['user_email'],
            $order['total_ksh'],
            $status,
            $order['created_at'],
            '', '', '', '', '', '', '', '', '',
            $drivers[$order['id']] ?? '',
            $vehicleTypes[$order['id']] ?? ''
        ]);
    }
}
fclose($out); 