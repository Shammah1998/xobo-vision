<?php
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?error=Session expired, please log in again.');
    exit;
}
require_once '../config/db.php';
require_once '../includes/functions.php';

requireRole(['company_admin']);

$companyId = $_SESSION['company_id'];

// Get company orders with customer details
$stmt = $pdo->prepare("
    SELECT o.*, u.email as customer_email, 
           GROUP_CONCAT(CONCAT(p.name, ' (', oi.quantity, 'x)') SEPARATOR ', ') as items
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE o.company_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute([$companyId]);
$orders = $stmt->fetchAll();

// Get company name
$companyName = getCompanyName($pdo, $companyId);

$pageTitle = 'Orders - ' . $companyName;
include '../includes/header.php';
?>

<h1>Order Management - <?php echo htmlspecialchars($companyName); ?></h1>

<div class="orders-section">
    <?php if (empty($orders)): ?>
        <p class="no-data">No orders received yet.</p>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Address</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?php echo $order['id']; ?></td>
                        <td><?php echo htmlspecialchars($order['customer_email']); ?></td>
                        <td><?php echo htmlspecialchars($order['items']); ?></td>
                        <td><?php echo formatCurrency($order['total_ksh']); ?></td>
                        <td><?php echo htmlspecialchars($order['address']); ?></td>
                        <td><?php echo date('M j, Y H:i', strtotime($order['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?> 