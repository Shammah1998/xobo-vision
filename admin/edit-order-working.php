<?php
require_once '../config/config.php';
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?error=Session expired, please log in again.');
    exit;
}
require_once '../includes/functions.php';
require_once '../config/db.php';

// Check access - allow admin_user and other admin roles
requireRole(['admin_user']);

// Check order ID
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$orderId) {
    header('Location: ../shop/orders.php?error=' . urlencode('Invalid order ID'));
    exit;
}

// Check if order exists and belongs to company (for admin_user only)
$companyId = $_SESSION['company_id'] ?? null;
try {
    if ($_SESSION['role'] === 'admin_user') {
        // admin_user can only edit orders from their company
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND company_id = ?');
        $stmt->execute([$orderId, $companyId]);
    } else {
        // admin and super_admin can edit orders from any company
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
    }
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        $errorMsg = ($_SESSION['role'] === 'admin_user') 
            ? 'Order not found or does not belong to your company'
            : 'Order not found';
        header('Location: ../shop/orders.php?error=' . urlencode($errorMsg));
        exit;
    }
} catch (PDOException $e) {
    error_log("Edit Order Error - Order fetch: " . $e->getMessage());
    header('Location: ../shop/orders.php?error=' . urlencode('Database error occurred'));
    exit;
}

// Fetch order items with proper joins
try {
    $stmt = $pdo->prepare('
        SELECT oi.*, p.name, p.sku, p.weight_kg, p.rate_ksh 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = ?
        ORDER BY p.name
    ');
    $stmt->execute([$orderId]);
    $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Edit Order Error - Items fetch: " . $e->getMessage());
    $orderItems = [];
}

// Fetch delivery details
try {
    $stmt = $pdo->prepare('SELECT * FROM order_delivery_details WHERE order_id = ?');
    $stmt->execute([$orderId]);
    $deliveryDetails = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $deliveryDetails[$d['product_id']] = $d;
    }
} catch (PDOException $e) {
    error_log("Edit Order Error - Delivery fetch: " . $e->getMessage());
    $deliveryDetails = [];
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? 'pending';
    $items = $_POST['items'] ?? [];
    $delivery = $_POST['delivery'] ?? [];
    
    // Validate status
    if (!in_array($status, ['pending', 'confirmed'])) {
        $error = 'Invalid status selected.';
    } else {
        try {
            // Start transaction for data consistency
            $pdo->beginTransaction();
            
            // Update order status
            $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ? AND company_id = ?');
            $updateResult = $stmt->execute([$status, $orderId, $companyId]);
            
            if (!$updateResult) {
                throw new Exception('Failed to update order status');
            }
            
            // Process item updates and recalculate total
            $newTotal = 0;
            $itemsProcessed = 0;
            
            foreach ($items as $productId => $qty) {
                $qty = max(0, (int)$qty); // Ensure non-negative quantity
                $productId = (int)$productId;
                
                if ($qty > 0) {
                    // Get current product price
                    $stmtP = $pdo->prepare('SELECT rate_ksh FROM products WHERE id = ? AND company_id = ?');
                    $stmtP->execute([$productId, $companyId]);
                    $rate = $stmtP->fetchColumn();
                    
                    if ($rate === false) {
                        throw new Exception("Product ID {$productId} not found or doesn't belong to your company");
                    }
                    
                    $lineTotal = $rate * $qty;
                    $newTotal += $lineTotal;
                    
                    // Update or insert order item
                    $stmtCheck = $pdo->prepare('SELECT order_id FROM order_items WHERE order_id = ? AND product_id = ?');
                    $stmtCheck->execute([$orderId, $productId]);
                    
                    if ($stmtCheck->fetch()) {
                        // Update existing item
                        $stmtU = $pdo->prepare('UPDATE order_items SET quantity = ?, line_total = ? WHERE order_id = ? AND product_id = ?');
                        $stmtU->execute([$qty, $lineTotal, $orderId, $productId]);
                    } else {
                        // Insert new item (shouldn't happen in edit, but handle gracefully)
                        $stmtI = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, line_total) VALUES (?, ?, ?, ?)');
                        $stmtI->execute([$orderId, $productId, $qty, $lineTotal]);
                    }
                    
                    $itemsProcessed++;
                } else {
                    // Remove item if quantity is 0
                    $stmtD = $pdo->prepare('DELETE FROM order_items WHERE order_id = ? AND product_id = ?');
                    $stmtD->execute([$orderId, $productId]);
                    
                    $stmtD2 = $pdo->prepare('DELETE FROM order_delivery_details WHERE order_id = ? AND product_id = ?');
                    $stmtD2->execute([$orderId, $productId]);
                }
            }
            
            // Ensure at least one item remains
            if ($itemsProcessed === 0) {
                throw new Exception('Order must contain at least one item');
            }
            
            // Update order total
            $stmt = $pdo->prepare('UPDATE orders SET total_ksh = ? WHERE id = ?');
            $stmt->execute([$newTotal, $orderId]);
            
            // Update delivery details
            foreach ($delivery as $productId => $fields) {
                $productId = (int)$productId;
                
                // Verify this product exists in the order
                $stmtCheck = $pdo->prepare('SELECT order_id FROM order_items WHERE order_id = ? AND product_id = ?');
                $stmtCheck->execute([$orderId, $productId]);
                
                if (!$stmtCheck->fetch()) {
                    continue; // Skip if product not in order
                }
                
                $fields = array_map('trim', $fields);
                
                // Check if delivery details already exist
                $stmtC = $pdo->prepare('SELECT id FROM order_delivery_details WHERE order_id = ? AND product_id = ?');
                $stmtC->execute([$orderId, $productId]);
                
                if ($stmtC->fetch()) {
                    // Update existing delivery details
                    $stmtU = $pdo->prepare('
                        UPDATE order_delivery_details 
                        SET pick_up = ?, drop_off = ?, additional_notes = ?, recipient_name = ?, recipient_phone = ? 
                        WHERE order_id = ? AND product_id = ?
                    ');
                    $stmtU->execute([
                        $fields['pick_up'] ?? '',
                        $fields['drop_off'] ?? '',
                        $fields['additional_notes'] ?? '',
                        $fields['recipient_name'] ?? '',
                        $fields['recipient_phone'] ?? '',
                        $orderId,
                        $productId
                    ]);
                } else {
                    // Insert new delivery details
                    $stmtI = $pdo->prepare('
                        INSERT INTO order_delivery_details 
                        (order_id, product_id, pick_up, drop_off, additional_notes, recipient_name, recipient_phone, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ');
                    $stmtI->execute([
                        $orderId,
                        $productId,
                        $fields['pick_up'] ?? '',
                        $fields['drop_off'] ?? '',
                        $fields['additional_notes'] ?? '',
                        $fields['recipient_name'] ?? '',
                        $fields['recipient_phone'] ?? ''
                    ]);
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            $message = 'Order #' . str_pad($orderId, 6, '0', STR_PAD_LEFT) . ' updated successfully!';
            
            // Log successful update
            error_log("Edit Order Success - Order {$orderId} updated by user {$_SESSION['user_id']}");
            
            // Redirect back to orders page with success message
            header('Location: ../shop/orders.php?success=' . urlencode($message));
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollback();
            $error = 'Error updating order: ' . $e->getMessage();
            error_log("Edit Order Error - Update failed: " . $e->getMessage());
        }
    }
}

// Handle success message from redirect
if (isset($_GET['success'])) {
    $message = $_GET['success'];
}

// Refresh order data after any updates
try {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND company_id = ?');
    $stmt->execute([$orderId, $companyId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare('
        SELECT oi.*, p.name, p.sku, p.weight_kg, p.rate_ksh 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = ?
        ORDER BY p.name
    ');
    $stmt->execute([$orderId]);
    $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare('SELECT * FROM order_delivery_details WHERE order_id = ?');
    $stmt->execute([$orderId]);
    $deliveryDetails = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $deliveryDetails[$d['product_id']] = $d;
    }
} catch (PDOException $e) {
    error_log("Edit Order Error - Data refresh: " . $e->getMessage());
}

$pageTitle = 'Edit Order #' . str_pad($orderId, 6, '0', STR_PAD_LEFT) . ' - XOBO';
include '../includes/header.php';
?>

<style>
.edit-order-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
}
.edit-header {
    background: linear-gradient(135deg, var(--xobo-primary) 0%, var(--xobo-primary-hover) 100%);
    color: white;
    padding: 2rem 0;
    text-align: center;
    margin-bottom: 2rem;
    border-radius: 8px;
}
.edit-header h1 {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
}
.edit-section {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px var(--xobo-shadow);
    margin-bottom: 2rem;
    padding: 2rem;
}
.form-group {
    margin-bottom: 1.5rem;
}
.form-group label {
    display: block;
    font-weight: 600;
    color: var(--xobo-primary);
    margin-bottom: 0.5rem;
}
.form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 1rem;
    box-sizing: border-box;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    border-color: var(--xobo-primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(22, 35, 77, 0.1);
}
.items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 2rem;
}
.items-table th, .items-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #eee;
}
.items-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: var(--xobo-primary);
}
.items-table input[readonly] {
    background: #f8f9fa;
    color: #6c757d;
}
.delivery-section {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 6px;
    margin-top: 1rem;
}
.delivery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}
.btn-group {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid #eee;
}
.btn {
    padding: 0.75rem 2rem;
    border: none;
    border-radius: 6px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.3s;
}
.btn-primary {
    background: var(--xobo-primary);
    color: white;
    border: 1px solid var(--xobo-primary);
}
.btn-primary:hover {
    background: var(--xobo-primary-hover);
    border-color: var(--xobo-primary-hover);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(37,99,235,0.15);
}
.btn-secondary {
    background: #6c757d;
    color: white;
    border: 1px solid #6c757d;
}
.btn-secondary:hover {
    background: #5a6268;
    border-color: #5a6268;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.alert {
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1.5rem;
}
.alert-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}
.alert-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}
</style>

<div class="edit-order-container">
    <div class="edit-header">
        <h1>Edit Order #<?php echo str_pad($orderId, 6, '0', STR_PAD_LEFT); ?></h1>
        <p>Order Management for Admin Users</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
                 <div class="edit-section">
             <h3><i class="fas fa-info-circle"></i> Order Information</h3>
             <div class="form-group">
                 <label for="status">Order Status</label>
                 <select name="status" id="status">
                     <option value="pending" <?php if ($order['status'] === 'pending') echo 'selected'; ?>>Pending</option>
                     <option value="confirmed" <?php if ($order['status'] === 'confirmed') echo 'selected'; ?>>Confirmed</option>
                 </select>
             </div>
             <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 6px;">
                 <div><strong>Order ID:</strong> #<?php echo str_pad($orderId, 6, '0', STR_PAD_LEFT); ?></div>
                 <div><strong>Current Status:</strong> <?php echo ucfirst($order['status']); ?></div>
                 <div><strong>Order Total:</strong> KSH <?php echo number_format($order['total_ksh'], 2); ?></div>
                 <div><strong>Created:</strong> <?php echo date('M j, Y H:i', strtotime($order['created_at'])); ?></div>
                 <div><strong>Company ID:</strong> <?php echo $order['company_id']; ?></div>
                 <div><strong>Items Count:</strong> <?php echo count($orderItems); ?></div>
             </div>
         </div>

                 <div class="edit-section">
             <h3><i class="fas fa-boxes"></i> Order Items</h3>
             <?php if (empty($orderItems)): ?>
                 <div class="alert alert-error">
                     <i class="fas fa-exclamation-triangle"></i> No items found for this order. This may indicate a data issue.
                 </div>
             <?php else: ?>
                 <table class="items-table">
                     <thead>
                         <tr>
                             <th>Product</th>
                             <th>SKU</th>
                             <th>Quantity</th>
                             <th>Price (KSH)</th>
                             <th>Weight (kg)</th>
                             <th>Line Total</th>
                         </tr>
                     </thead>
                     <tbody>
                         <?php 
                         $calculatedTotal = 0;
                         foreach ($orderItems as $item): 
                             $lineTotal = $item['quantity'] * $item['rate_ksh'];
                             $calculatedTotal += $lineTotal;
                         ?>
                             <tr>
                                 <td><?php echo htmlspecialchars($item['name']); ?></td>
                                 <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                 <td>
                                     <input type="number" name="items[<?php echo $item['product_id']; ?>]" 
                                            value="<?php echo (int)$item['quantity']; ?>" min="0" style="width:80px;">
                                 </td>
                                 <td>
                                     <input type="text" value="<?php echo number_format($item['rate_ksh'], 2); ?>" 
                                            readonly style="width:100px;">
                                 </td>
                                 <td>
                                     <input type="text" value="<?php echo number_format($item['weight_kg'], 2); ?>" 
                                            readonly style="width:80px;">
                                 </td>
                                 <td style="font-weight: 600;">
                                     KSH <?php echo number_format($lineTotal, 2); ?>
                                 </td>
                             </tr>
                         <?php endforeach; ?>
                         <tr style="background: #e9ecef; font-weight: 600;">
                             <td colspan="5" style="text-align: right;"><strong>Calculated Total:</strong></td>
                             <td><strong>KSH <?php echo number_format($calculatedTotal, 2); ?></strong></td>
                         </tr>
                         <?php if (abs($calculatedTotal - $order['total_ksh']) > 0.01): ?>
                             <tr style="background: #fff3cd; color: #856404;">
                                 <td colspan="6" style="text-align: center;">
                                     <i class="fas fa-exclamation-triangle"></i> 
                                     Note: Calculated total (<?php echo number_format($calculatedTotal, 2); ?>) differs from stored total (<?php echo number_format($order['total_ksh'], 2); ?>)
                                 </td>
                             </tr>
                         <?php endif; ?>
                     </tbody>
                 </table>
             <?php endif; ?>
         </div>

        <div class="edit-section">
            <h3><i class="fas fa-truck"></i> Delivery Details</h3>
            <?php foreach ($orderItems as $item): 
                $d = $deliveryDetails[$item['product_id']] ?? []; ?>
                <div class="delivery-section">
                    <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                    <div class="delivery-grid">
                        <div class="form-group">
                            <label>Pick Up Location</label>
                            <input type="text" name="delivery[<?php echo $item['product_id']; ?>][pick_up]" 
                                   value="<?php echo htmlspecialchars($d['pick_up'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Drop Off Location</label>
                            <input type="text" name="delivery[<?php echo $item['product_id']; ?>][drop_off]" 
                                   value="<?php echo htmlspecialchars($d['drop_off'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Recipient Name</label>
                            <input type="text" name="delivery[<?php echo $item['product_id']; ?>][recipient_name]" 
                                   value="<?php echo htmlspecialchars($d['recipient_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Recipient Phone</label>
                            <input type="text" name="delivery[<?php echo $item['product_id']; ?>][recipient_phone]" 
                                   value="<?php echo htmlspecialchars($d['recipient_phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Additional Notes</label>
                            <textarea name="delivery[<?php echo $item['product_id']; ?>][additional_notes]" 
                                      rows="2"><?php echo htmlspecialchars($d['additional_notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="btn-group">
            <a href="../shop/orders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?> 