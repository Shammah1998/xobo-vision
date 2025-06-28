<?php
require_once '../config/config.php';
session_start();
require_once '../includes/functions.php';
require_once '../config/db.php';

requireRole(['user']);

$userId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'];
$success = isset($_GET['success']) ? 'Order placed successfully!' : '';

if (empty($companyId)) {
    header('Location: ' . BASE_URL . '/auth/login.php?error=' . urlencode('You must be associated with a company to view orders.'));
    exit;
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

        // Build delivery details HTML as a flat unordered list (one bullet per field, no bold labels)
        $delivery_html = '';
        if (!empty($items_list)) {
            foreach ($items_list as $item) {
                $d = $delivery_details[$item['product_id']] ?? [];
                $delivery_html .= '<ul class="delivery-details-list flat-list">';
                $delivery_html .= '<li>Destination: ' . htmlspecialchars($d['destination'] ?? '-') . '</li>';
                $delivery_html .= '<li>Company: ' . htmlspecialchars($d['company_name'] ?? '-') . '</li>';
                $delivery_html .= '<li>Address: ' . htmlspecialchars($d['company_address'] ?? '-') . '</li>';
                $delivery_html .= '<li>Recipient: ' . htmlspecialchars($d['recipient_name'] ?? '-') . '</li>';
                $delivery_html .= '<li>Phone: ' . htmlspecialchars($d['recipient_phone'] ?? '-') . '</li>';
                $delivery_html .= '</ul>';
            }
        }

        $orders[] = [
            'id' => $order_id,
            'total_ksh' => $db_order['total_ksh'],
            'company_name' => htmlspecialchars($db_order['company_name']),
            'user_email' => htmlspecialchars($db_order['user_email']),
            'created_at' => $db_order['created_at'],
            'delivery_html' => $delivery_html,
            'items' => $items_str ?: 'No items found for this order.'
        ];
    }
}

$pageTitle = 'Company Orders - XOBO MART';
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
        <div id="table-scrollbar-top" class="table-scrollbar-top"></div>
        <div class="catalog-table-container" id="table-scrollbar-bottom">
            <?php if (empty($orders)): ?>
                <div class="empty-catalog">
                    <i class="fas fa-box-open"></i>
                    <h4>No orders found</h4>
                    <p>No orders have been placed by your company yet.</p>
                    <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-primary">
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
                            <th class="receipt-col">Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
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
                                <td class="receipt-col">
                                    <a href="<?php echo BASE_URL; ?>/shop/order-receipt.php?order_id=<?php echo $order['id']; ?>" class="btn-receipt-long-narrow">
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
.btn-receipt-long-narrow {
    min-width: 70px;
    height: 36px;
    padding: 0.25rem 0.5rem;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--xobo-primary);
    color: #fff;
    border: none;
    transition: background 0.2s;
    text-decoration: none !important;
    gap: 0.5rem;
}
.btn-receipt-long-narrow:hover {
    background: var(--xobo-primary-hover);
    color: #fff;
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