<?php
require_once '../config/config.php';
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?error=Session expired, please log in again.');
    exit;
}
require_once '../includes/functions.php';
require_once '../config/db.php';

requireRole(['user', 'admin_user']);

$userId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'];
$success = isset($_GET['success']) ? 'Order placed successfully!' : '';

if (empty($companyId)) {
    header('Location: ' . BASE_URL . '/auth/login?error=' . urlencode('You must be associated with a company to view orders.'));
    exit;
}

// Handle order confirmation by admin_user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin_user') {
        $confirmOrderId = (int)$_POST['confirm_order_id'];
        // Only allow confirming if order is pending and belongs to this company
        $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = ? AND company_id = ?');
        $stmt->execute([$confirmOrderId, $companyId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($order && $order['status'] === 'pending') {
            $stmt = $pdo->prepare('UPDATE orders SET status = "confirmed" WHERE id = ?');
            $stmt->execute([$confirmOrderId]);
            $success = 'Order #' . str_pad($confirmOrderId, 6, '0', STR_PAD_LEFT) . ' has been confirmed.';
            // Optionally: redirect to avoid resubmission
            header('Location: ' . $_SERVER['REQUEST_URI'] . '?success=' . urlencode($success));
            exit;
        } else {
            $success = 'Order could not be confirmed (already confirmed or not found).';
        }
    } else {
        $success = 'You do not have permission to confirm orders.';
    }
}

// Fetch orders for the user's company from the database
$stmt = $pdo->prepare("
    SELECT o.*, c.name AS company_name, u.email AS user_email
    FROM orders o
    JOIN companies c ON o.company_id = c.id
    JOIN users u ON o.user_id = u.id
    WHERE o.company_id = ?
    ORDER BY o.created_at DESC
");
$stmt->execute([$companyId]);
$db_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch drivers for these orders
$orderIds = array_column($db_orders, 'id');
$driversByOrder = [];
if (!empty($orderIds)) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $stmt_drivers = $pdo->prepare("SELECT order_id, driver_name FROM drivers WHERE order_id IN ($placeholders)");
    $stmt_drivers->execute($orderIds);
    foreach ($stmt_drivers->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $driversByOrder[$row['order_id']] = $row['driver_name'];
    }
}

$orders = [];
if (!empty($db_orders)) {
    $orderIds = array_column($db_orders, 'id');

    // Fetch all order items for these orders in a single query
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $stmt_items = $pdo->prepare("
        SELECT oi.order_id, oi.product_id, oi.quantity, p.name, p.rate_ksh
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id IN ($placeholders)
    ");
    $stmt_items->execute($orderIds);
    $db_order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all delivery details for these orders
    $stmt_delivery = $pdo->prepare("
        SELECT * FROM order_delivery_details WHERE order_id IN ($placeholders)
    ");
    $stmt_delivery->execute($orderIds);
    $db_delivery_details = $stmt_delivery->fetchAll(PDO::FETCH_ASSOC);

    // Group items and delivery details by order_id and product_id
    $itemsByOrderId = [];
    foreach ($db_order_items as $item) {
        $itemsByOrderId[$item['order_id']][] = $item;
    }
    $deliveryByOrderProduct = [];
    foreach ($db_delivery_details as $detail) {
        $deliveryByOrderProduct[$detail['order_id']][$detail['product_id']] = $detail;
    }

    // Build the final $orders array to be used in the view
    foreach ($db_orders as $db_order) {
        $order_id = $db_order['id'];
        $items_list = $itemsByOrderId[$order_id] ?? [];
        $delivery_details = $deliveryByOrderProduct[$order_id] ?? [];
        
        $items_str = implode('<br>', array_map(function($item) {
            return htmlspecialchars($item['name']) . ' (' . $item['quantity'] . 'x @' . number_format($item['rate_ksh'], 0) . ')';
        }, $items_list));

        // --- NEW DELIVERY ADDRESS LOGIC ---
        $delivery_html = '';
        if (!empty($items_list)) {
            // Collect all delivery details for this order
            $all_details = [];
            foreach ($items_list as $item) {
                $d = $delivery_details[$item['product_id']] ?? [];
                $all_details[] = [
                    'pick_up' => $d['pick_up'] ?? '-',
                    'drop_off' => $d['drop_off'] ?? '-',
                    'additional_notes' => $d['additional_notes'] ?? '-',
                    'recipient_name' => $d['recipient_name'] ?? '-',
                    'recipient_phone' => $d['recipient_phone'] ?? '-',
                ];
            }
            // Check if all delivery details are the same
            $first = $all_details[0];
            $all_same = true;
            foreach ($all_details as $det) {
                if ($det !== $first) {
                    $all_same = false;
                    break;
                }
            }
            if ($all_same) {
                // Show one delivery address block
                $delivery_html .= '<ul class="delivery-details-list flat-list">';
                $delivery_html .= '<li>Pick Up: ' . htmlspecialchars($first['pick_up']) . '</li>';
                $delivery_html .= '<li>Drop Off: ' . htmlspecialchars($first['drop_off']) . '</li>';
                $delivery_html .= '<li>Additional Notes: ' . htmlspecialchars($first['additional_notes']) . '</li>';
                $delivery_html .= '<li>Recipient: ' . htmlspecialchars($first['recipient_name']) . '</li>';
                $delivery_html .= '<li>Phone: ' . htmlspecialchars($first['recipient_phone']) . '</li>';
                $delivery_html .= '</ul>';
            } else {
                // Show per-item delivery address, labeled with product name
                foreach ($items_list as $item) {
                    $d = $delivery_details[$item['product_id']] ?? [];
                    $delivery_html .= '<div style="margin-bottom:0.5em"><strong>' . htmlspecialchars($item['name']) . ':</strong>';
                    $delivery_html .= '<ul class="delivery-details-list flat-list">';
                    $delivery_html .= '<li>Pick Up: ' . htmlspecialchars($d['pick_up'] ?? '-') . '</li>';
                    $delivery_html .= '<li>Drop Off: ' . htmlspecialchars($d['drop_off'] ?? '-') . '</li>';
                    $delivery_html .= '<li>Additional Notes: ' . htmlspecialchars($d['additional_notes'] ?? '-') . '</li>';
                    $delivery_html .= '<li>Recipient: ' . htmlspecialchars($d['recipient_name'] ?? '-') . '</li>';
                    $delivery_html .= '<li>Phone: ' . htmlspecialchars($d['recipient_phone'] ?? '-') . '</li>';
                    $delivery_html .= '</ul></div>';
                }
            }
        }
        // --- END NEW DELIVERY ADDRESS LOGIC ---

        $orders[] = [
            'id' => $order_id,
            'total_ksh' => $db_order['total_ksh'],
            'company_name' => htmlspecialchars($db_order['company_name']),
            'user_email' => htmlspecialchars($db_order['user_email']),
            'created_at' => $db_order['created_at'],
            'delivery_html' => $delivery_html,
            'items' => $items_str ?: 'No items found for this order.',
            'driver' => isset($driversByOrder[$order_id]) ? htmlspecialchars($driversByOrder[$order_id]) : 'Not assigned',
        ];
    }
}

$pageTitle = 'Company Orders - User Panel';
include '../includes/header.php';
?>

<div class="container">
    <div class="company-header">
        <h1>Company Orders</h1>
        <p>All orders placed by users in your company.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="catalog-section">
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin_user'): ?>
            <div class="btn-csv-container">
                <a href="../admin/export-orders.php" target="_blank" class="btn btn-primary">
                    <i class="fas fa-download"></i> Download CSV
                </a>
            </div>
        <?php endif; ?>
        <div id="table-scrollbar-top" class="table-scrollbar-top"></div>
        <div class="catalog-table-container" id="table-scrollbar-bottom">
            <?php if (empty($orders)): ?>
                <div class="empty-catalog">
                    <i class="fas fa-box-open"></i>
                    <h4>No orders found</h4>
                    <p>No orders have been placed by your company yet.</p>
                    <a href="<?php echo BASE_URL; ?>/index" class="btn btn-primary">
                        <i class="fas fa-shopping-cart"></i> Start Shopping
                    </a>
                </div>
            <?php else: ?>
                <table class="catalog-table">
                    <thead>
                        <tr>
                            <th class="order-id-col">Order ID</th>
                            <th class="date-col">Date</th>
                            <th class="email-col">Ordered By</th>
                            <th class="items-col">Items</th>
                            <th class="total-col">Total (KSH)</th>
                            <th class="address-col">Delivery Address</th>
                            <th class="status-col">Status</th>
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin_user'): ?>

                                <th class="actions-col">Actions</th>
                            <?php endif; ?>
                            <th class="driver-col">Driver</th>
                            <th class="receipt-col">Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (
                            $orders as $order): ?>
                            <tr>
                                <td class="order-id-col">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td class="date-col"><?php echo date('M j, Y H:i', strtotime($order['created_at'])); ?></td>
                                <td class="email-col"><?php echo $order['user_email']; ?></td>
                                <td class="items-col">
                                    <div class="items-list">
                                        <?php echo $order['items']; ?>
                                    </div>
                                </td>
                                <td class="total-col"><?php echo number_format($order['total_ksh'], 2); ?></td>
                                <td class="address-col">
                                    <div class="address-text">
                                        <?php echo $order['delivery_html']; ?>
                                    </div>
                                </td>
                                <td class="status-col">
                                    <?php
                                    $status = $db_orders[array_search($order['id'], array_column($db_orders, 'id'))]['status'];
                                    $statusText = ucfirst($status);
                                    $statusClass = $status === 'confirmed' ? 'status-confirmed' : 'status-pending';
                                    echo '<span class="status-text ' . $statusClass . '">' . $statusText . '</span>';
                                    ?>
                                </td>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin_user'): ?>
                                <td class="actions-col">
                                    <div class="actions-buttons">
                                    <?php
                                    // Role check passed
                                    if ($status === 'pending') {
                                        echo '<form method="POST" class="inline-form">';
                                        echo '<input type="hidden" name="confirm_order_id" value="' . (int)$order['id'] . '">';
                                        echo '<button type="submit" class="btn-confirm-order btn-uniform btn-sm" title="Confirm this order" onclick="return confirm(\'Confirm this order?\');">Confirm</button>';
                                        echo '</form>';
                                        echo '<a href="../admin/edit-order-working.php?id=' . (int)$order['id'] . '" class="btn-edit-order btn-uniform btn-sm" title="Edit order details">Edit</a>';
                                    }
                                    ?>
                                    </div>
                                </td>
                                <?php endif; ?>
                                <td class="driver-col">
                                    <?php echo $order['driver']; ?>
                                </td>
                                <td class="receipt-col">
                                    <a href="<?php echo BASE_URL; ?>/shop/order-receipt?order_id=<?php echo $order['id']; ?>" class="btn-receipt-long-narrow">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 2rem;
}
.company-header {
    background: linear-gradient(135deg, var(--xobo-primary) 0%, var(--xobo-primary-hover) 100%);
    color: white;
    padding: 2.5rem 0;
    text-align: center;
    margin-bottom: 2rem;
    border-radius: 8px;
}
.company-header h1 {
    font-size: 2.5rem;
    margin-bottom: 0.75rem;
    font-weight: 600;
}
.company-header p {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0;
}
.catalog-section {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px var(--xobo-shadow);
    margin-bottom: 2rem;
    overflow-x: auto;
    padding: 0;
}
.catalog-table-scroll-top {
    overflow-x: auto;
    direction: rtl;
    width: 100%;
    margin-bottom: -16px; /* visually hide bottom scrollbar if needed */
    padding-bottom: 0;
}
.catalog-table-container {
    overflow-x: auto;
    direction: ltr;
    width: 100%;
    padding: 0;
    margin: 0;
}
.catalog-table {
    width: 100%;
    min-width: 1400px;
    border-collapse: separate;
    border-spacing: 0;
    margin: 0.5rem 0;
    table-layout: auto;
}
.catalog-table th, .catalog-table td {
    padding: 1.25rem 1rem;
    text-align: left;
    border-bottom: 1px solid var(--xobo-border);
    vertical-align: top;
    line-height: 1.5;
    background: #fff;
    word-break: break-word;
}
.catalog-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: var(--xobo-primary);
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 10;
    border-bottom: 2px solid var(--xobo-primary);
}
.catalog-table tbody tr:hover {
    background: #f8f9fa;
}
.order-id-col { min-width: 100px; font-family: monospace; font-size: 0.9rem; }
.date-col { min-width: 150px; white-space: nowrap; }
.email-col { min-width: 200px; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.items-col { min-width: 300px; }
.items-list { line-height: 1.6; }
.total-col { min-width: 120px; font-weight: 600; text-align: right; }
.address-col { min-width: 400px; }
.receipt-col { min-width: 120px; text-align: center; padding-right: 2rem; }
.address-text { white-space: normal; line-height: 1.4; }

/* Mini delivery table improvements */
.mini-delivery-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.98em;
    margin-bottom: 0.5em;
    background: #f6f8fa;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(22,35,77,0.04);
}
.mini-delivery-table th, .mini-delivery-table td {
    border: 1px solid #e0e0e0;
    padding: 0.5em 0.7em;
    text-align: left;
    background: #fff;
    font-size: 0.97em;
}
.mini-delivery-table th {
    background: #e9eef6;
    color: var(--xobo-primary);
    font-weight: 600;
    text-align: left;
}
.mini-delivery-table tr:nth-child(even) td {
    background: #f6f8fa;
}
.mini-delivery-table td {
    max-width: 200px;
    white-space: pre-line;
    word-break: break-word;
}
@media (max-width: 1500px) {
    .container { max-width: 98vw; }
    .catalog-table { min-width: 1200px; }
}
@media (max-width: 1200px) {
    .container { max-width: 100vw; padding: 0 1rem; }
    .catalog-table { min-width: 900px; }
}
@media (max-width: 900px) {
    .catalog-table { min-width: 600px; }
}
@media (max-width: 768px) {
    .catalog-table { min-width: 500px; }
}

/* Delivery details as list */
.delivery-details-list {
    list-style: disc inside;
    margin: 0;
    padding-left: 1.2em;
}
.delivery-details-list li {
    margin-bottom: 1em;
    background: #f6f8fa;
    border-radius: 6px;
    padding: 0.7em 1em;
    box-shadow: 0 1px 4px rgba(22,35,77,0.04);
}
.delivery-detail {
    display: inline-block;
    margin-bottom: 0.1em;
    font-size: 0.97em;
}
.delivery-details-list.flat-list {
    list-style: disc inside;
    margin: 0 0 1em 0;
    padding-left: 1.2em;
    background: none;
    border-radius: 0;
    box-shadow: none;
}
.delivery-details-list.flat-list li {
    margin-bottom: 0.2em;
    background: none;
    padding: 0;
    font-size: 1em;
}
/* Hide the bottom scrollbar for the container */
.catalog-table-container::-webkit-scrollbar {
    display: none;
}
.catalog-table-container {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.table-scrollbar-top {
    overflow-x: auto;
    overflow-y: hidden;
    height: 16px;
    width: 100%;
    margin-bottom: 0;
}
.table-scrollbar-top::-webkit-scrollbar {
    height: 16px;
}
.table-scrollbar-top::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 8px;
}
.catalog-table-container {
    overflow-x: auto;
    padding: 0;
    margin: 0;
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.catalog-table-container::-webkit-scrollbar {
    display: none;
}
.btn-receipt {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1.2rem;
    background: #2563eb;
    color: #fff !important;
    text-decoration: none;
    border-radius: 6px;
    font-size: 1rem;
    font-weight: 600;
    transition: background 0.2s, box-shadow 0.2s;
    box-shadow: 0 2px 6px rgba(37,99,235,0.08);
    border: none;
}
.btn-receipt:hover {
    background: #1d4ed8;
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(37,99,235,0.15);
    transform: translateY(-1px) scale(1.03);
}
.btn-uniform, .btn-receipt-long-narrow {
    min-width: 70px;
    height: 36px;
    padding: 0.25rem 0.9rem;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    transition: background 0.2s, color 0.2s, box-shadow 0.2s;
    white-space: nowrap;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}
.actions-buttons .btn-confirm-order {
    background: var(--xobo-primary);
    color: #fff;
    border: 1px solid var(--xobo-primary);
}
.actions-buttons .btn-confirm-order:hover {
    background: var(--xobo-primary-hover);
    color: #fff;
    box-shadow: 0 2px 6px rgba(37,99,235,0.12);
}
.actions-buttons .btn-edit-order {
    background: var(--xobo-primary) !important;
    color: #fff !important;
    border: 1px solid var(--xobo-primary) !important;
}
.actions-buttons .btn-edit-order:hover {
    background: var(--xobo-primary-hover) !important;
    color: #fff !important;
    box-shadow: 0 2px 6px rgba(37,99,235,0.12);
}
.btn-receipt-long-narrow {
    background: var(--xobo-primary);
    color: #fff;
    border: none;
    text-decoration: none !important;
    gap: 0.5rem;
}
.btn-receipt-long-narrow:hover {
    background: var(--xobo-primary-hover);
    color: #fff;
}
.status-text {
    margin-right: 0.5em;
    font-size: 0.95em;
    font-weight: 600;
    letter-spacing: 0.01em;
}
.status-col {
    min-width: 110px;
    white-space: nowrap;
    text-align: left;
}
.actions-col {
    min-width: 140px;
    white-space: nowrap;
    text-align: left;
}
.status-text.status-pending {
    color: #fff;
    background: #e53935;
    border-radius: 6px;
    padding: 0.25rem 0.9rem;
    font-size: 1rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    height: 36px;
    min-width: 70px;
    justify-content: center;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}
.status-text.status-confirmed {
    color: #fff;
    background: var(--xobo-primary);
    border-radius: 6px;
    padding: 0.25rem 0.9rem;
    font-size: 1rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    height: 36px;
    min-width: 70px;
    justify-content: center;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}
.actions-buttons {
    display: flex;
    align-items: center;
    gap: 0.5em;
}
.actions-buttons .inline-form {
    display: inline;
    margin: 0;
    padding: 0;
}
.actions-buttons .btn-confirm-order {
    background: #198754;
    color: #fff;
    border: 1px solid #198754;
    font-size: 0.92em;
    padding: 0.25em 0.9em;
    border-radius: 5px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    transition: background 0.2s, color 0.2s, box-shadow 0.2s;
    white-space: nowrap;
}
.actions-buttons .btn-confirm-order:hover {
    background: #157347;
    color: #fff;
    box-shadow: 0 2px 6px rgba(25,135,84,0.12);
}
.actions-buttons .btn-edit-order {
    background: #ffc107;
    color: #333;
    border: 1px solid #ffc107;
    font-size: 0.92em;
    padding: 0.25em 0.9em;
    border-radius: 5px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    transition: background 0.2s, color 0.2s, box-shadow 0.2s;
    white-space: nowrap;
}
.actions-buttons .btn-edit-order:hover {
    background: #e0a800;
    color: #222;
    box-shadow: 0 2px 6px rgba(255,193,7,0.12);
}
.driver-col {
    min-width: 130px;
    white-space: nowrap;
    text-align: left;
}
.catalog-section a.btn.btn-primary {
    background: var(--xobo-primary) !important;
    color: #fff !important;
    border-radius: 6px !important;
    padding: 0.7em 1.5em !important;
    font-weight: 600 !important;
    text-decoration: none !important;
    border: none !important;
    font-size: 1rem !important;
    transition: background 0.2s, color 0.2s, box-shadow 0.2s;
    box-shadow: 0 2px 6px rgba(37,99,235,0.08);
    display: inline-flex;
    align-items: center;
    gap: 0.5em;
}
.catalog-section a.btn.btn-primary:hover {
    background: var(--xobo-primary-hover) !important;
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(37,99,235,0.15);
    transform: translateY(-1px) scale(1.03);
}
.catalog-section .btn-csv-container {
    display: flex;
    justify-content: flex-end;
    margin-top: 1.2rem;
    margin-right: 2.2rem;
    margin-bottom: 1rem;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Create a fake inner div to match the table width
    var table = document.querySelector('.catalog-table');
    var topScrollbar = document.getElementById('table-scrollbar-top');
    var bottomContainer = document.getElementById('table-scrollbar-bottom');
    if (table && topScrollbar && bottomContainer) {
        var fakeDiv = document.createElement('div');
        fakeDiv.style.width = table.scrollWidth + 'px';
        fakeDiv.style.height = '1px';
        topScrollbar.appendChild(fakeDiv);

        // Sync scroll positions
        topScrollbar.onscroll = function() {
            bottomContainer.scrollLeft = topScrollbar.scrollLeft;
        };
        bottomContainer.onscroll = function() {
            topScrollbar.scrollLeft = bottomContainer.scrollLeft;
        };
    }
});
</script>

<?php include '../includes/footer.php'; ?> 