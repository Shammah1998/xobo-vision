<?php
// Handle driver assignment BEFORE any output or includes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_driver_order_id'], $_POST['driver_name'])) {
    require_once '../config/db.php';
    $orderId = (int)$_POST['assign_driver_order_id'];
    $driverName = trim($_POST['driver_name']);
    if ($orderId > 0 && $driverName !== '') {
        $stmt = $pdo->prepare('SELECT id FROM drivers WHERE order_id = ?');
        $stmt->execute([$orderId]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare('UPDATE drivers SET driver_name = ?, assigned_at = NOW() WHERE order_id = ?');
            $stmt->execute([$driverName, $orderId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO drivers (order_id, driver_name) VALUES (?, ?)');
            $stmt->execute([$orderId, $driverName]);
        }
    }
    header('Location: orders.php');
    exit;
}

include 'includes/admin_header.php';
require_once '../config/db.php';

// Fetch all orders with company and user info
$stmt = $pdo->prepare('
    SELECT o.id, o.total_ksh, o.created_at, c.name AS company_name, u.email AS user_email, o.address
    FROM orders o
    JOIN companies c ON o.company_id = c.id
    JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
');
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all order items for all orders
$orderIds = array_column($orders, 'id');
$orderItems = [];
$deliveryDetails = [];
$drivers = [];
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
}
?>

<div class="admin-card">
    <h2 style="margin-bottom: 1rem; color: var(--xobo-primary);">All Orders</h2>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Company</th>
                    <th>User Email</th>
                    <th>Total (KSH)</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th style="text-align:center; width:40px;"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($orders && count($orders) > 0): ?>
                <?php foreach($orders as $order): ?>
                    <tr>
                        <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                        <td><?php echo htmlspecialchars($order['company_name']); ?></td>
                        <td><?php echo htmlspecialchars($order['user_email']); ?></td>
                        <td><?php echo number_format($order['total_ksh'], 2); ?></td>
                        <td>
                            <span class="status-badge status-approved">Completed</span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                        <td style="text-align:center; width:40px;">
                            <button type="button" class="details-toggle-btn" onclick="toggleOrderDetails(<?php echo $order['id']; ?>)" data-order-id="<?php echo $order['id']; ?>" style="background:none; border:none; cursor:pointer; font-size:1.1rem; color:var(--xobo-primary);">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </td>
                    </tr>
                    <tr class="order-details-row" id="order-details-<?php echo $order['id']; ?>" style="display:none; background:#f8f9fa;">
                        <td colspan="7">
                            <div style="padding:1.5rem;">
                                <h4 style="color:var(--xobo-primary); margin-bottom:0.75rem;">Order Items</h4>
                                <?php if (!empty($orderItems[$order['id']])): ?>
                                    <table style="width:100%; border-collapse:collapse; margin-bottom:1rem;">
                                        <thead>
                                            <tr style="background:#f0f0f0;">
                                                <th style="padding:0.5rem; text-align:left;">Product Name</th>
                                                <th style="padding:0.5rem; text-align:left;">SKU</th>
                                                <th style="padding:0.5rem; text-align:right;">Quantity</th>
                                                <th style="padding:0.5rem; text-align:right;">Line Total (KSH)</th>
                                                <th style="padding:0.5rem; text-align:left;">Delivery Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($orderItems[$order['id']] as $itemIndex => $item): ?>
                                                <tr>
                                                    <td style="padding:0.5rem;"> <?php echo htmlspecialchars($item['name']); ?> </td>
                                                    <td style="padding:0.5rem;"> <?php echo htmlspecialchars($item['sku']); ?> </td>
                                                    <td style="padding:0.5rem; text-align:right;"> <?php echo $item['quantity']; ?> </td>
                                                    <td style="padding:0.5rem; text-align:right;"> <?php echo number_format($item['line_total'], 2); ?> </td>
                                                    <td style="padding:0.5rem;">
                                                        <?php $d = $deliveryDetails[$order['id']][$item['product_id']] ?? null; ?>
                                                        <?php if ($d): ?>
                                                            <ul class="delivery-details-list flat-list" style="margin:0; padding-left:1.2em;">
                                                                <li>Destination: <?php echo htmlspecialchars($d['destination'] ?? '-'); ?></li>
                                                                <li>Company: <?php echo htmlspecialchars($d['company_name'] ?? '-'); ?></li>
                                                                <li>Address: <?php echo htmlspecialchars($d['company_address'] ?? '-'); ?></li>
                                                                <li>Recipient: <?php echo htmlspecialchars($d['recipient_name'] ?? '-'); ?></li>
                                                                <li>Phone: <?php echo htmlspecialchars($d['recipient_phone'] ?? '-'); ?></li>
                                                            </ul>
                                                        <?php else: ?>
                                                            <span style="color:var(--xobo-gray);">No delivery details</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <?php if ($itemIndex === 0): ?>
                                                        <td rowspan="<?php echo count($orderItems[$order['id']]); ?>" style="vertical-align: top; text-align: right; padding: 0.5rem 1.5rem 0.5rem 1.5rem; min-width: 180px;">
                                                            <?php if (!empty($drivers[$order['id']])): ?>
                                                                <span style="color: var(--xobo-primary); font-weight: 600; font-size: 1.05em; background: #f8f9fa; padding: 0.4em 1em; border-radius: 6px;">Driver: <?php echo htmlspecialchars($drivers[$order['id']]); ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <div style="color:var(--xobo-gray);">No items found for this order.</div>
                                <?php endif; ?>
                                <?php if (!empty($order['address'])): ?>
                                    <div style="margin-top:1rem;">
                                        <strong>Order Address:</strong> <?php echo htmlspecialchars($order['address']); ?>
                                    </div>
                                <?php endif; ?>
                                <div style="margin-top:1.5rem; display: flex; justify-content: flex-start; align-items: center; flex-wrap: wrap; gap: 1rem;">
                                    <form method="POST" style="display:flex; gap:1rem; align-items:center; margin:0;">
                                        <input type="hidden" name="assign_driver_order_id" value="<?php echo $order['id']; ?>">
                                        <label for="driver_name_<?php echo $order['id']; ?>" style="font-weight:600; color:var(--xobo-primary); margin:0;">Assign Driver:</label>
                                        <input type="text" id="driver_name_<?php echo $order['id']; ?>" name="driver_name" value="<?php echo htmlspecialchars($drivers[$order['id']] ?? ''); ?>" placeholder="Enter driver name" style="padding:0.5rem; border:1px solid #ccc; border-radius:4px; min-width:200px; margin:0;">
                                        <button type="submit" class="btn btn-primary btn-sm align-btns" style="width: 140px; height: 40px; text-align: center; display: flex; align-items: center; justify-content: center;">
                                            <?php echo isset($drivers[$order['id']]) ? 'Update' : 'Assign'; ?>
                                        </button>
                                        <?php if (isset($drivers[$order['id']])): ?>
                                            <!-- Remove the old driver label from below the buttons -->
                                        <?php endif; ?>
                                    </form>
                                    <a href="/xobo-vision/shop/order-receipt.php?order_id=<?php echo $order['id']; ?>" class="btn btn-primary btn-sm align-btns" style="width: 140px; height: 40px; text-align: center; display: flex; align-items: center; justify-content: center;">
                                        <span style="display: flex; align-items: center; justify-content: center; width: 100%;">
                                            <i class="fas fa-eye" style="margin-right: 0.4em;"></i>
                                            <span>View Receipt</span>
                                        </span>
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--xobo-gray);">No orders found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleOrderDetails(orderId) {
    const row = document.getElementById('order-details-' + orderId);
    const btn = document.querySelector('.details-toggle-btn[data-order-id="' + orderId + '"]');
    const icon = btn.querySelector('i');
    if (row.style.display === 'none' || row.style.display === '') {
        row.style.display = 'table-row';
        icon.style.transform = 'rotate(180deg)';
    } else {
        row.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
    }
}
</script>

<style>
.align-btns {
    width: 140px !important;
    height: 40px !important;
    text-align: center;
    display: flex !important;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    font-weight: 600;
    padding: 0 !important;
    line-height: 1 !important;
}
</style>

<?php include 'includes/admin_footer.php'; ?> 