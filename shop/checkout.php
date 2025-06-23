<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

requireRole(['user']);

$companyId = $_SESSION['company_id'];
$userId = $_SESSION['user_id'];

// Redirect if cart is empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$error = '';
$success = '';

// Handle order placement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $address = sanitize($_POST['address']);
    
    if (empty($address)) {
        $error = 'Please provide a delivery address.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Get cart items with product details
            $productIds = array_keys($_SESSION['cart']);
            $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
            
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND company_id = ?");
            $stmt->execute(array_merge($productIds, [$companyId]));
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalCost = 0;
            $orderItems = [];
            
            foreach ($products as $product) {
                $quantity = $_SESSION['cart'][$product['id']];
                $lineTotal = $quantity * $product['rate_ksh'];
                $totalCost += $lineTotal;
                
                $orderItems[] = [
                    'product_id' => $product['id'],
                    'quantity' => $quantity,
                    'line_total' => $lineTotal
                ];
            }
            
            // Insert order
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, company_id, total_ksh, address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $companyId, $totalCost, $address]);
            $orderId = $pdo->lastInsertId();
            
            // Insert order items
            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, line_total) VALUES (?, ?, ?, ?)");
            foreach ($orderItems as $item) {
                $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['line_total']]);
            }
            
            $pdo->commit();
            
            // Clear cart
            $_SESSION['cart'] = [];
            
            // Redirect to success page
            header('Location: orders.php?success=1');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to place order. Please try again.';
        }
    }
}

// Get cart items for display
$cartItems = [];
$totalWeight = 0;
$totalCost = 0;

$productIds = array_keys($_SESSION['cart']);
$placeholders = str_repeat('?,', count($productIds) - 1) . '?';

$stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND company_id = ?");
$stmt->execute(array_merge($productIds, [$companyId]));
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($products as $product) {
    $quantity = $_SESSION['cart'][$product['id']];
    $lineTotal = $quantity * $product['rate_ksh'];
    $lineWeight = $quantity * $product['weight_kg'];
    
    $cartItems[] = [
        'product' => $product,
        'quantity' => $quantity,
        'line_total' => $lineTotal,
        'line_weight' => $lineWeight
    ];
    
    $totalCost += $lineTotal;
    $totalWeight += $lineWeight;
}

$pageTitle = 'Checkout';
include '../includes/header.php';
?>

<h1>Checkout</h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="checkout-container">
    <div class="checkout-sections">
        <div class="order-review">
            <h2>Order Review</h2>
            
            <div class="table-container">
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Weight</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cartItems as $item): ?>
                        <tr>
                            <td>
                                <div class="product-info">
                                    <h4><?php echo htmlspecialchars($item['product']['name']); ?></h4>
                                    <p>SKU: <?php echo htmlspecialchars($item['product']['sku']); ?></p>
                                </div>
                            </td>
                            <td><?php echo formatCurrency($item['product']['rate_ksh']); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo number_format($item['line_weight'], 2); ?> kg</td>
                            <td><?php echo formatCurrency($item['line_total']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="order-totals">
                <div class="total-row">
                    <span>Total Items:</span>
                    <span><?php echo array_sum($_SESSION['cart']); ?></span>
                </div>
                <div class="total-row">
                    <span>Total Weight:</span>
                    <span><?php echo number_format($totalWeight, 2); ?> kg</span>
                </div>
                <div class="total-row grand-total">
                    <span>Grand Total:</span>
                    <span><?php echo formatCurrency($totalCost); ?></span>
                </div>
            </div>
        </div>
        
        <div class="delivery-details">
            <h2>Delivery Details</h2>
            
            <form method="POST" class="checkout-form">
                <div class="form-group">
                    <label for="address">Delivery Address:</label>
                    <textarea id="address" name="address" rows="4" required 
                              placeholder="Enter your complete delivery address..."><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-actions">
                    <a href="cart.php" class="btn btn-secondary">Back to Cart</a>
                    <button type="submit" name="place_order" class="btn btn-primary">
                        Place Order - <?php echo formatCurrency($totalCost); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 