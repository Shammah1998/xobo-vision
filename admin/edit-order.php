<?php
// DEBUG: Log the access attempt
error_log("EDIT ORDER DEBUG: Access attempt at " . date('Y-m-d H:i:s'));
error_log("EDIT ORDER DEBUG: Request URI: " . $_SERVER['REQUEST_URI']);
error_log("EDIT ORDER DEBUG: GET parameters: " . print_r($_GET, true));

require_once '../config/db.php';
require_once '../includes/functions.php';
session_start();

// DEBUG: Log session info
error_log("EDIT ORDER DEBUG: Session data: " . print_r($_SESSION, true));

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin_user') {
    error_log("EDIT ORDER DEBUG: Access denied - User ID: " . ($_SESSION['user_id'] ?? 'not set') . ", Role: " . ($_SESSION['role'] ?? 'not set'));
    die('<div style="max-width:600px;margin:2rem auto;padding:2rem;background:#fff3f3;border:1px solid #e53935;color:#e53935;border-radius:8px;font-size:1.2em;text-align:center;">Access denied: Only admin_user can access this page.</div>');
}

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
error_log("EDIT ORDER DEBUG: Order ID: " . $orderId);
if (!$orderId) {
    error_log("EDIT ORDER DEBUG: Invalid order ID");
    die('<div style="max-width:600px;margin:2rem auto;padding:2rem;background:#fff3f3;border:1px solid #e53935;color:#e53935;border-radius:8px;font-size:1.2em;text-align:center;">Invalid order ID.</div>');
}

// Restrict to orders belonging to the admin's company
$companyId = $_SESSION['company_id'] ?? null;
error_log("EDIT ORDER DEBUG: Company ID: " . $companyId);
$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND company_id = ?');
$stmt->execute([$orderId, $companyId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
error_log("EDIT ORDER DEBUG: Order found: " . ($order ? 'YES' : 'NO'));
if (!$order) {
    error_log("EDIT ORDER DEBUG: Order not found or doesn't belong to company");
    die('<div style="max-width:600px;margin:2rem auto;padding:2rem;background:#fff3f3;border:1px solid #e53935;color:#e53935;border-radius:8px;font-size:1.2em;text-align:center;">Order not found or does not belong to your company.</div>');
}

error_log("EDIT ORDER DEBUG: Successfully passed all checks, proceeding to edit form");
// Fetch order items
$stmt = $pdo->prepare('SELECT oi.*, p.name, p.sku, p.weight_kg, p.rate_ksh FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?');
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch delivery details
$stmt = $pdo->prepare('SELECT * FROM order_delivery_details WHERE order_id = ?');
$stmt->execute([$orderId]);
$deliveryDetails = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
    $deliveryDetails[$d['product_id']] = $d;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = trim($_POST['address'] ?? '');
    $status = $_POST['status'] ?? 'pending';
    $items = $_POST['items'] ?? [];
    $delivery = $_POST['delivery'] ?? [];
    if (empty($address)) {
        $error = 'Address is required.';
    } elseif (!in_array($status, ['pending', 'confirmed'])) {
        $error = 'Invalid status.';
    } else {
        // Update order
        $stmt = $pdo->prepare('UPDATE orders SET address = ?, status = ? WHERE id = ?');
        $stmt->execute([$address, $status, $orderId]);
        // Update items and recalculate total
        $total = 0;
        foreach ($items as $productId => $qty) {
            $qty = (int)$qty;
            if ($qty > 0) {
                // Get price
                $stmtP = $pdo->prepare('SELECT rate_ksh FROM products WHERE id = ?');
                $stmtP->execute([$productId]);
                $rate = $stmtP->fetchColumn();
                $lineTotal = $rate * $qty;
                $total += $lineTotal;
                $stmtU = $pdo->prepare('UPDATE order_items SET quantity = ?, line_total = ? WHERE order_id = ? AND product_id = ?');
                $stmtU->execute([$qty, $lineTotal, $orderId, $productId]);
            } else {
                // Remove item
                $stmtD = $pdo->prepare('DELETE FROM order_items WHERE order_id = ? AND product_id = ?');
                $stmtD->execute([$orderId, $productId]);
                $stmtD2 = $pdo->prepare('DELETE FROM order_delivery_details WHERE order_id = ? AND product_id = ?');
                $stmtD2->execute([$orderId, $productId]);
            }
        }
        // Update order total
        $stmt = $pdo->prepare('UPDATE orders SET total_ksh = ? WHERE id = ?');
        $stmt->execute([$total, $orderId]);
        // Update delivery details
        foreach ($delivery as $productId => $fields) {
            $fields = array_map('trim', $fields);
            $stmtC = $pdo->prepare('SELECT id FROM order_delivery_details WHERE order_id = ? AND product_id = ?');
            $stmtC->execute([$orderId, $productId]);
            if ($stmtC->fetch()) {
                $stmtU = $pdo->prepare('UPDATE order_delivery_details SET pick_up = ?, drop_off = ?, additional_notes = ?, recipient_name = ?, recipient_phone = ? WHERE order_id = ? AND product_id = ?');
                $stmtU->execute([
                    $fields['pick_up'] ?? '',
                    $fields['drop_off'] ?? '',
                    $fields['additional_notes'] ?? '',
                    $fields['recipient_name'] ?? '',
                    $fields['recipient_phone'] ?? '',
                    $orderId,
                    $productId
                ]);
            }
        }
        $message = 'Order updated successfully.';
        echo '<script>setTimeout(function(){ window.location.href = "orders.php"; }, 1500);</script>';
    }
}

include 'includes/admin_header.php';
?>
<div class="admin-card" style="max-width: 900px; margin: 2rem auto;">
    <h2 style="color: var(--xobo-primary); margin-bottom: 1.2rem;">Edit Order #<?php echo htmlspecialchars($orderId); ?></h2>
    <?php if ($message): ?>
        <div class="alert alert-success" style="margin-bottom: 1rem;"> <?php echo htmlspecialchars($message); ?> </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom: 1rem;"> <?php echo htmlspecialchars($error); ?> </div>
    <?php endif; ?>
    <form method="POST" style="display: flex; flex-direction: column; gap: 1.2rem;">
        <div class="form-group">
            <label for="status" style="font-weight: 600; color: var(--xobo-primary);">Order Status</label>
            <select name="status" id="status" style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
                <option value="pending" <?php if ($order['status'] === 'pending') echo 'selected'; ?>>Pending</option>
                <option value="confirmed" <?php if ($order['status'] === 'confirmed') echo 'selected'; ?>>Confirmed</option>
            </select>
        </div>
        <div class="form-group">
            <label for="address" style="font-weight: 600; color: var(--xobo-primary);">Order Address</label>
            <textarea name="address" id="address" rows="3" required style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px; width: 100%;"><?php echo htmlspecialchars($order['address'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label style="font-weight: 600; color: var(--xobo-primary);">Order Items</label>
            <table class="data-table" style="width:100%;">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Quantity</th>
                        <th>Price (KSH)</th>
                        <th>Weight (kg)</th>
                        <th>Remove</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orderItems as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo htmlspecialchars($item['sku']); ?></td>
                        <td><input type="number" name="items[<?php echo $item['product_id']; ?>]" value="<?php echo (int)$item['quantity']; ?>" min="0" style="width:60px;"></td>
                        <td><input type="text" value="<?php echo number_format($item['rate_ksh'], 2); ?>" readonly style="width:80px; background:#f8f9fa; border:none; color:#888;"></td>
                        <td><input type="text" value="<?php echo number_format($item['weight_kg'], 2); ?>" readonly style="width:80px; background:#f8f9fa; border:none; color:#888;"></td>
                        <td><input type="checkbox" name="items[<?php echo $item['product_id']; ?>]" value="0"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-group">
            <label style="font-weight: 600; color: var(--xobo-primary);">Delivery Details</label>
            <table class="data-table" style="width:100%;">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Pick Up</th>
                        <th>Drop Off</th>
                        <th>Notes</th>
                        <th>Recipient</th>
                        <th>Phone</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orderItems as $item): $d = $deliveryDetails[$item['product_id']] ?? []; ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><input type="text" name="delivery[<?php echo $item['product_id']; ?>][pick_up]" value="<?php echo htmlspecialchars($d['pick_up'] ?? ''); ?>" style="width:120px;"></td>
                        <td><input type="text" name="delivery[<?php echo $item['product_id']; ?>][drop_off]" value="<?php echo htmlspecialchars($d['drop_off'] ?? ''); ?>" style="width:120px;"></td>
                        <td><input type="text" name="delivery[<?php echo $item['product_id']; ?>][additional_notes]" value="<?php echo htmlspecialchars($d['additional_notes'] ?? ''); ?>" style="width:120px;"></td>
                        <td><input type="text" name="delivery[<?php echo $item['product_id']; ?>][recipient_name]" value="<?php echo htmlspecialchars($d['recipient_name'] ?? ''); ?>" style="width:120px;"></td>
                        <td><input type="text" name="delivery[<?php echo $item['product_id']; ?>][recipient_phone]" value="<?php echo htmlspecialchars($d['recipient_phone'] ?? ''); ?>" style="width:120px;"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
            <a href="orders.php" class="btn btn-secondary" style="padding: 12px 24px;">Cancel</a>
            <button type="submit" class="btn btn-primary" style="padding: 12px 24px;">Save Changes</button>
        </div>
    </form>
</div>
<?php include 'includes/admin_footer.php'; ?> 