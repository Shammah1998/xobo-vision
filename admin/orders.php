<?php
require_once '../includes/functions.php';
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
    header('Location: orders');
    exit;
}

// Debug: log POST data
file_put_contents(__DIR__ . '/post_debug.log', print_r($_POST, true), FILE_APPEND);

// Handle delete all orders (super admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all_orders']) && hasRole('super_admin')) {
    // Debug: log if delete block is reached
    file_put_contents(__DIR__ . '/delete_debug.log', 'Delete block reached at ' . date('c') . PHP_EOL, FILE_APPEND);
    try {
        $pdo->exec('DELETE FROM order_items');
        $pdo->exec('DELETE FROM order_delivery_details');
        $pdo->exec('DELETE FROM delivery_details');
        $pdo->exec('DELETE FROM drivers');
        $pdo->exec('DELETE FROM order_vehicle_types');
        $pdo->exec('DELETE FROM orders');
        $message = 'All orders and related data have been deleted.';
    } catch (PDOException $e) {
        $message = 'Error deleting orders: ' . $e->getMessage();
    }
}

// Handle status update (admin_user only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status_order_id'], $_POST['order_status']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin_user') {
    $orderId = (int)$_POST['update_status_order_id'];
    $newStatus = $_POST['order_status'];
    if (in_array($newStatus, ['pending', 'confirmed'])) {
        $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$newStatus, $orderId]);
        $message = 'Order status updated.';
    }
}

include 'includes/admin_header.php';
require_once '../config/db.php';

// Handle search filters
$orderIdSearch = isset($_GET['order_id']) ? trim($_GET['order_id']) : '';
$fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

// Build query with filters
$where = [];
$params = [];
if ($orderIdSearch !== '') {
    $where[] = 'o.id = ?';
    $params[] = (int)$orderIdSearch;
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

// Fetch all orders with company and user info
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

// Fetch all order items for all orders
$orderIds = array_column($orders, 'id');
$orderItems = [];
$deliveryDetails = [];
$drivers = [];
$vehicleTypes = [];
$accessories = [];
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
    
    // Accessories
    $stmt = $pdo->prepare('SELECT order_id, main_product_id, accessory_name, accessory_sku, accessory_weight FROM order_accessories WHERE order_id IN (' . $placeholders . ')');
    $stmt->execute($orderIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $acc) {
        $accessories[$acc['order_id']][$acc['main_product_id']][] = $acc;
    }
}
?>

<div class="admin-card">
    <h2 style="margin-bottom: 1rem; color: var(--xobo-primary);">All Orders</h2>
    <form method="GET" action="" style="display: flex; gap: 1rem; align-items: end; margin-bottom: 1.5rem; flex-wrap: wrap;">
        <input type="text" name="order_id" value="<?php echo htmlspecialchars($orderIdSearch); ?>" placeholder="Order ID" style="padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; min-width: 120px;">
        <input type="date" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>" style="padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; min-width: 160px;">
        <input type="date" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>" style="padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; min-width: 160px;">
        <button type="submit" class="btn btn-primary" name="search_orders" style="min-width: 100px; height: 40px;">Search</button>
        <?php if ($orderIdSearch || $fromDate || $toDate): ?>
            <a href="orders.php" class="btn btn-secondary" style="min-width: 100px; height: 40px; text-align: center; display: inline-flex; align-items: center; justify-content: center;">Clear</a>
        <?php endif; ?>
    </form>
    <?php if (!empty($message)): ?>
        <div class="alert alert-success" style="margin-bottom:1rem;"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
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
                    <?php
                    // Fetch order status for this order
                    $stmtStatus = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
                    $stmtStatus->execute([$order['id']]);
                    $orderStatusRow = $stmtStatus->fetch(PDO::FETCH_ASSOC);
                    $orderStatus = $orderStatusRow['status'] ?? 'pending';
                    ?>
                    <tr>
                        <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                        <td><?php echo htmlspecialchars($order['company_name']); ?></td>
                        <td><?php echo htmlspecialchars($order['user_email']); ?></td>
                        <td><?php echo number_format($order['total_ksh'], 2); ?></td>
                        <td>
                            <?php
                            $stmtStatus = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
                            $stmtStatus->execute([$order['id']]);
                            $orderStatusRow = $stmtStatus->fetch(PDO::FETCH_ASSOC);
                            $orderStatus = $orderStatusRow['status'] ?? 'pending';
                            $statusText = ucfirst($orderStatus);
                            if ($orderStatus === 'confirmed') {
                                echo '<span style="display:inline-block;padding:0.3em 1.2em;border-radius:10px;font-size:1em;font-weight:600;color:#fff;background:#172554;text-transform:capitalize;min-width:90px;text-align:center;letter-spacing:0.01em;">' . $statusText . '</span>';
                            } else {
                                echo '<span style="display:inline-block;padding:0.3em 1.2em;border-radius:10px;font-size:1em;font-weight:600;color:#fff;background:#dc3545;text-transform:capitalize;min-width:90px;text-align:center;letter-spacing:0.01em;">' . $statusText . '</span>';
                            }
                            ?>
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
                                                    <td style="padding:0.5rem; vertical-align: top;">
                                                        <span style="display: inline-flex; align-items: center;">
                                                            <?php echo htmlspecialchars($item['name']); ?>
                                                            <?php if (strtolower(trim($item['name'])) === 'vision plus accessories' && isset($accessories[$order['id']][$item['product_id']])): ?>
                                                                <button
                                                                    type="button"
                                                                    class="accessories-toggle-btn"
                                                                    onclick="toggleAccessoriesRow(<?php echo $order['id']; ?>, <?php echo $item['product_id']; ?>)"
                                                                    data-order-id="<?php echo $order['id']; ?>"
                                                                    data-product-id="<?php echo $item['product_id']; ?>"
                                                                    aria-expanded="false"
                                                                    aria-controls="accessories-row-<?php echo $order['id']; ?>-<?php echo $item['product_id']; ?>"
                                                                    style="
                                                                        margin-left: 18px;
                                                                        background: #16234d;
                                                                        color: #fff;
                                                                        border: none;
                                                                        border-radius: 16px;
                                                                        padding: 0.25em 1.2em;
                                                                        font-size: 0.95em;
                                                                        display: flex;
                                                                        align-items: center;
                                                                        gap: 0.4em;
                                                                        cursor: pointer;
                                                                        transition: background 0.2s;
                                                                        min-width: 70px;
                                                                        height: 32px;
                                                                    "
                                                                    onmouseover="this.style.background='#23336d';"
                                                                    onmouseout="this.style.background='#16234d';"
                                                                >
                                                                    <i class="fas fa-puzzle-piece"></i>
                                                                    <span class="accessories-btn-label">View</span>
                                                                    <i class="fas fa-chevron-down accessories-chevron" style="transition: transform 0.2s;"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </span>
                                                    </td>
                                                    <td style="padding:0.5rem; vertical-align: top;"> <?php echo htmlspecialchars($item['sku']); ?> </td>
                                                    <td style="padding:0.5rem; text-align:right; vertical-align: top;"> <?php echo $item['quantity']; ?> </td>
                                                    <td style="padding:0.5rem; text-align:right; vertical-align: top;"> <?php echo number_format($item['line_total'], 2); ?> </td>
                                                    <td style="padding:0.5rem; vertical-align: top;">
                                                        <?php $d = $deliveryDetails[$order['id']][$item['product_id']] ?? null; ?>
                                                        <?php if ($d): ?>
                                                            <ul class="delivery-details-list flat-list" style="margin:0; padding-left:1.2em;">
                                                                <li>Pick Up: <?php echo htmlspecialchars($d['pick_up'] ?? '-'); ?></li>
                                                                <li>Drop Off: <?php echo htmlspecialchars($d['drop_off'] ?? '-'); ?></li>
                                                                <li>Additional Notes: <?php echo htmlspecialchars($d['additional_notes'] ?? '-'); ?></li>
                                                                <li>Recipient: <?php echo htmlspecialchars($d['recipient_name'] ?? '-'); ?></li>
                                                                <li>Phone: <?php echo htmlspecialchars($d['recipient_phone'] ?? '-'); ?></li>
                                                            </ul>
                                                        <?php else: ?>
                                                            <span style="color:var(--xobo-gray);">No delivery details</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php if (isset($accessories[$order['id']][$item['product_id']])): ?>
                                                <tr class="accessories-details-row" id="accessories-row-<?php echo $order['id']; ?>-<?php echo $item['product_id']; ?>" style="display:none; background:#f6f8fa;">
                                                    <td colspan="5" style="padding: 1.2rem 2rem;">
                                                        <div class="accessory-list" style="margin-top:0;">
                                                            <div style="font-weight:600; color:var(--xobo-primary); margin-bottom:0.5rem; font-size:1.05rem;">
                                                                <i class="fas fa-puzzle-piece"></i> Included Accessories
                                                            </div>
                                                            <table style="width:100%; border-collapse:collapse; background:transparent;">
                                                                <thead>
                                                                    <tr style="background:transparent; color:#888; font-size:0.95em;">
                                                                        <th style="text-align:left; padding:4px 8px;">Name</th>
                                                                        <th style="text-align:left; padding:4px 8px;">SKU</th>
                                                                        <th style="text-align:left; padding:4px 8px;">Weight (kg)</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                <?php foreach ($accessories[$order['id']][$item['product_id']] as $acc): ?>
                                                                    <tr>
                                                                        <td style="padding:4px 8px;"><span class="product-name"><?php echo htmlspecialchars($acc['accessory_name']); ?></span></td>
                                                                        <td style="padding:4px 8px;"><span class="product-sku"><?php echo htmlspecialchars($acc['accessory_sku']); ?></span></td>
                                                                        <td style="padding:4px 8px;"><span class="product-weight"><?php echo htmlspecialchars($acc['accessory_weight']); ?></span></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <!-- Driver and Vehicle Type Row -->
                                            <tr>
                                                <td colspan="5" style="padding: 0.7rem 0.5rem 0.7rem 0.5rem; background: #f8f9fa; border-top: 1px solid #eee;">
                                                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 2.5rem; font-size: 1rem;">
                                                        <?php if (!empty($drivers[$order['id']])): ?>
                                                            <span style="color: var(--xobo-primary); font-weight: 600; font-size: 1em; background: #f8f9fa; padding: 0.3em 1em; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.5em;">
                                                                Driver: <?php echo htmlspecialchars($drivers[$order['id']]); ?>
                                                                <button class="delete-driver-btn" data-order-id="<?php echo $order['id']; ?>" title="Remove Driver" style="background: none; border: none; color: #dc3545; font-size: 1.1em; margin-left: 0.5em; cursor: pointer; display: inline-flex; align-items: center;">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($vehicleTypes[$order['id']])): ?>
                                                            <span style="color: var(--xobo-primary); font-weight: 600; font-size: 1em; background: #f8f9fa; padding: 0.3em 1em; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.5em;">
                                                                <i class="fas fa-truck"></i> Vehicle: <?php echo htmlspecialchars($vehicleTypes[$order['id']]); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
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
                                <?php
                                // Fetch order status for this order
                                $stmtStatus = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
                                $stmtStatus->execute([$order['id']]);
                                $orderStatusRow = $stmtStatus->fetch(PDO::FETCH_ASSOC);
                                $orderStatus = $orderStatusRow['status'] ?? 'pending';
                                ?>
                                <div style="margin-top:1.5rem; display: flex; justify-content: flex-start; align-items: center; flex-wrap: wrap; gap: 1rem;">
                                    <?php if (in_array($orderStatus, ['confirmed'])): ?>
                                    <form method="POST" class="assign-driver-form" data-order-id="<?php echo $order['id']; ?>" style="display:flex; gap:1rem; align-items:center; margin:0;">
                                        <input type="hidden" name="assign_driver_order_id" value="<?php echo $order['id']; ?>">
                                        <label for="driver_name_<?php echo $order['id']; ?>" style="font-weight:600; color:var(--xobo-primary); margin:0;">Assign Driver:</label>
                                        <input type="text" id="driver_name_<?php echo $order['id']; ?>" name="driver_name" value="" placeholder="Enter driver name" style="padding:0.5rem; border:1px solid #ccc; border-radius:4px; min-width:200px; margin:0;">
                                        <button type="submit" class="btn btn-primary btn-sm align-btns" style="width: 140px; height: 40px; text-align: center; display: flex; align-items: center; justify-content: center;">
                                            <?php echo isset($drivers[$order['id']]) ? 'Update' : 'Assign'; ?>
                                        </button>
                                        <?php if (isset($drivers[$order['id']])): ?>
                                            <!-- Remove the old driver label from below the buttons -->
                                        <?php endif; ?>
                                    </form>
                                    <?php else: ?>
                                        <div style="color: #888; font-style: italic;">Assign a driver after confirming the order.</div>
                                    <?php endif; ?>
                                    <a href="<?php echo BASE_URL; ?>/shop/order-receipt?order_id=<?php echo $order['id']; ?>" class="btn btn-primary btn-sm align-btns" style="width: 140px; height: 40px; text-align: center; display: flex; align-items: center; justify-content: center;">
                                        <span style="display: flex; align-items: center; justify-content: center; width: 100%;">
                                            <i class="fas fa-eye" style="margin-right: 0.4em;"></i>
                                            <span>View Receipt</span>
                                        </span>
                                    </a>
                                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin_user'): ?>
                                        <a href="edit-order.php?id=<?php echo $order['id']; ?>" class="btn btn-secondary btn-sm align-btns" style="width: 140px; height: 40px; text-align: center; display: flex; align-items: center; justify-content: center;">
                                            <span style="display: flex; align-items: center; justify-content: center; width: 100%;">
                                                <i class="fas fa-edit" style="margin-right: 0.4em;"></i>
                                                <span>Edit</span>
                                            </span>
                                        </a>
                                    <?php endif; ?>
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

function toggleAccessoriesRow(orderId, productId) {
    const row = document.getElementById('accessories-row-' + orderId + '-' + productId);
    const toggleBtn = document.querySelector('.accessories-toggle-btn[data-order-id="' + orderId + '"][data-product-id="' + productId + '"]');
    const chevron = toggleBtn.querySelector('.accessories-chevron');
    const expanded = row.style.display === 'table-row';
    row.style.display = expanded ? 'none' : 'table-row';
    chevron.style.transform = expanded ? 'rotate(0deg)' : 'rotate(180deg)';
    toggleBtn.setAttribute('aria-expanded', !expanded);
}

document.addEventListener('DOMContentLoaded', function() {
    // Use event delegation for delete-driver-btn
    document.body.addEventListener('click', function(e) {
        if (e.target.closest('.delete-driver-btn')) {
            e.preventDefault();
            var btn = e.target.closest('.delete-driver-btn');
            if (!confirm('Remove the assigned driver for this order?')) return;
            var orderId = btn.getAttribute('data-order-id');
            var driverSpan = btn.closest('span');
            var driverInput = document.getElementById('driver_name_' + orderId);
            fetch('delete-driver', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'order_id=' + encodeURIComponent(orderId)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    driverSpan.remove();
                    if (driverInput) driverInput.value = '';
                } else {
                    alert('Failed to remove driver.');
                }
            })
            .catch(() => alert('Failed to remove driver.'));
        }
    });

    document.querySelectorAll('.assign-driver-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var orderId = form.getAttribute('data-order-id');
            var input = form.querySelector('input[name="driver_name"]');
            var driverName = input.value.trim();
            if (!driverName) {
                input.focus();
                return;
            }
            var btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            fetch('assign-driver', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'assign_driver_order_id=' + encodeURIComponent(orderId) + '&driver_name=' + encodeURIComponent(driverName)
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.textContent = data.success ? 'Update' : 'Assign';
                if (data.success) {
                    // Update the driver label in the table
                    var detailsRow = form.closest('td');
                    var driverRow = detailsRow.parentElement.parentElement.querySelector('tr:last-child td > div');
                    if (driverRow) {
                        // Remove any existing driver span
                        driverRow.querySelectorAll('span').forEach(function(span) {
                            if (span.textContent.includes('Driver:')) span.remove();
                        });
                        // Add new driver span
                        var span = document.createElement('span');
                        span.style = 'color: var(--xobo-primary); font-weight: 600; font-size: 1em; background: #f8f9fa; padding: 0.3em 1em; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.5em;';
                        span.innerHTML = 'Driver: ' + data.driver_name +
                            ' <button class="delete-driver-btn" data-order-id="' + orderId + '" title="Remove Driver" style="background: none; border: none; color: #dc3545; font-size: 1.1em; margin-left: 0.5em; cursor: pointer; display: inline-flex; align-items: center;"><i class="fas fa-times"></i></button>';
                        driverRow.prepend(span);
                        input.value = '';
                    }
                } else {
                    alert(data.error || 'Failed to assign driver.');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.textContent = 'Assign';
                alert('Failed to assign driver.');
            });
        });
    });

    document.getElementById('download-csv-btn').addEventListener('click', function() {
        const params = new URLSearchParams(window.location.search);
        window.location.href = 'export-orders?' + params.toString();
    });
});
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
button[name="delete_all_orders"]:hover,
button[name="delete_all_orders"]:focus,
button[name="delete_all_orders"]:active {
    background-color: #dc3545 !important;
    color: #fff !important;
    border-color: #dc3545 !important;
    box-shadow: none !important;
    outline: none !important;
    filter: none !important;
    transition: none !important;
    cursor: pointer;
}
.status-badge {
    display: inline-block;
    padding: 0.3em 0.9em;
    border-radius: 12px;
    font-size: 0.95em;
    font-weight: 600;
    color: #fff;
    background: #172554; /* Match the View button background */
    text-transform: capitalize;
    margin-right: 0.3em;
    border: none;
}
.status-pending {
    background: #172554;
    color: #fff;
}
.status-confirmed {
    background: #172554;
    color: #fff;
}
.accessories-toggle-btn {
    background: #16234d;
    color: #fff;
    border: none;
    border-radius: 16px;
    padding: 0.25em 0.9em;
    font-size: 0.95em;
    display: flex;
    align-items: center;
    gap: 0.4em;
    cursor: pointer;
    transition: background 0.2s;
}
.accessories-toggle-btn:hover {
    background: #23336d;
}
.accessories-chevron {
    transition: transform 0.2s;
}
</style>

<?php include 'includes/admin_footer.php'; ?> 