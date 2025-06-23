<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

requireRole(['user']);

$companyId = $_SESSION['company_id'];
$message = '';
$error = '';

// Handle cart updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_cart'])) {
        foreach ($_POST['quantities'] as $productId => $quantity) {
            $productId = (int)$productId;
            $quantity = (int)$quantity;
            
            if ($quantity <= 0) {
                unset($_SESSION['cart'][$productId]);
            } else {
                $_SESSION['cart'][$productId] = $quantity;
            }
        }
        $message = 'Cart updated successfully!';
    } elseif (isset($_POST['remove_item'])) {
        $productId = (int)$_POST['product_id'];
        unset($_SESSION['cart'][$productId]);
        $message = 'Item removed from cart!';
    } elseif (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
        $message = 'Cart cleared!';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'add_multiple' && isset($_POST['products'])) {
        // Handle bulk adding of products from company homepage
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        $addedCount = 0;
        foreach ($_POST['products'] as $productId) {
            $productId = (int)$productId;
            if ($productId > 0) {
                // Verify product belongs to user's company
                $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND company_id = ?");
                $stmt->execute([$productId, $companyId]);
                if ($stmt->fetch()) {
                    $_SESSION['cart'][$productId] = ($_SESSION['cart'][$productId] ?? 0) + 1;
                    $addedCount++;
                }
            }
        }
        
        if ($addedCount > 0) {
            $message = "Successfully added {$addedCount} product(s) to your cart!";
        } else {
            $error = "No valid products were added to cart.";
        }
    }
}

// Get cart items with product details
$cartItems = [];
$totalWeight = 0;
$totalCost = 0;

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
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
}

$pageTitle = 'Shopping Cart';
include '../includes/header.php';
?>

<?php if ($message): ?>
    <div class="container">
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="container">
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    </div>
<?php endif; ?>

<div class="container">
    <div class="cart-header">
        <h1>Shopping Cart</h1>
        <div class="cart-store">
            <p><strong>XOBO MART</strong></p>
        </div>
    </div>

    <?php if (empty($cartItems)): ?>
        <div class="empty-cart">
            <div class="empty-cart-content">
                <div class="empty-cart-icon">🛒</div>
                <h3>Your cart is empty</h3>
                <p>Browse our amazing products and add some to your cart!</p>
                <a href="shop.php?cid=<?php echo $companyId; ?>" class="btn btn-primary">Continue Shopping</a>
            </div>
        </div>
    <?php else: ?>
        <div class="cart-container">
            <div class="cart-items-section">
                <div class="cart-actions-top">
                    <label class="select-all">
                        <input type="checkbox" id="selectAll"> Select All
                    </label>
                    <button type="button" class="delete-selected btn btn-danger btn-sm" onclick="clearCart()">Delete</button>
                </div>

                <form method="POST" class="cart-form">
                    <?php foreach ($cartItems as $item): ?>
                    <div class="cart-item">
                        <div class="item-select">
                            <input type="checkbox" name="selected_items[]" value="<?php echo $item['product']['id']; ?>">
                        </div>
                        
                        <div class="item-image">
                            📦
                        </div>
                        
                        <div class="item-details">
                            <h4><?php echo htmlspecialchars($item['product']['name']); ?></h4>
                            <p><?php echo htmlspecialchars($item['product']['sku']); ?></p>
                            <div class="item-rating">
                                <span class="stars">★★★★★</span>
                            </div>
                        </div>
                        
                        <div class="item-price">
                            <?php echo formatCurrency($item['product']['rate_ksh']); ?>
                        </div>
                        
                        <div class="item-quantity">
                            <div class="qty-controls">
                                <button type="button" class="qty-btn qty-decrement" onclick="updateQuantity(<?php echo $item['product']['id']; ?>, -1)">-</button>
                                <input type="number" name="quantities[<?php echo $item['product']['id']; ?>]" 
                                       value="<?php echo $item['quantity']; ?>" min="1" max="99" 
                                       class="qty-input" id="qty_<?php echo $item['product']['id']; ?>">
                                <button type="button" class="qty-btn qty-increment" onclick="updateQuantity(<?php echo $item['product']['id']; ?>, 1)">+</button>
                            </div>
                        </div>
                        
                        <div class="item-total">
                            <?php echo formatCurrency($item['line_total']); ?>
                        </div>
                        
                        <div class="item-actions">
                            <button type="submit" name="remove_item" value="1" class="remove-btn"
                                    onclick="document.getElementsByName('product_id')[0].value = <?php echo $item['product']['id']; ?>">
                                ❌
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <input type="hidden" name="product_id" value="">
                    
                    <div class="cart-actions-bottom">
                        <button type="submit" name="update_cart" class="btn btn-secondary">Update Cart</button>
                        <button type="submit" name="clear_cart" class="btn btn-danger" 
                                onclick="return confirm('Are you sure you want to clear your cart?')">Clear Cart</button>
                    </div>
                </form>
            </div>
            
            <div class="cart-summary-section">
                <div class="summary-card">
                    <div class="guarantee">
                        <div class="guarantee-icon">🔄</div>
                        <div class="guarantee-text">
                            <strong>7 DAYS</strong><br>
                            <small>Money Back Guarantee</small>
                        </div>
                    </div>
                    
                    <div class="summary-details">
                        <h3>Order Summary</h3>
                        <div class="summary-row">
                            <span>Total Items:</span>
                            <span><?php echo array_sum($_SESSION['cart']); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Total Weight:</span>
                            <span><?php echo number_format($totalWeight, 2); ?> kg</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total</span>
                            <span><?php echo formatCurrency($totalCost); ?></span>
                        </div>
                    </div>
                    
                    <div class="checkout-actions">
                        <a href="shop.php?cid=<?php echo $companyId; ?>" class="btn btn-secondary">Continue Shopping</a>
                        <a href="checkout.php" class="btn btn-primary">Proceed to Checkout</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function updateQuantity(productId, change) {
    const input = document.getElementById('qty_' + productId);
    let currentValue = parseInt(input.value) || 1;
    let newValue = currentValue + change;
    
    if (newValue < 1) newValue = 1;
    if (newValue > 99) newValue = 99;
    
    input.value = newValue;
}

function clearCart() {
    if (confirm('Are you sure you want to clear your cart?')) {
        const form = document.querySelector('.cart-form');
        const clearInput = document.createElement('input');
        clearInput.type = 'hidden';
        clearInput.name = 'clear_cart';
        clearInput.value = '1';
        form.appendChild(clearInput);
        form.submit();
    }
}

// Select all functionality
document.getElementById('selectAll')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('input[name="selected_items[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});
</script>

<style>
.cart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 2rem 0;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--xobo-light-gray);
}

.cart-header h1 {
    color: var(--xobo-dark-blue);
    font-size: 2rem;
    font-weight: 300;
}

.cart-store p {
    color: var(--xobo-gray);
    font-size: 1.1rem;
}

.empty-cart {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 50vh;
}

.empty-cart-content {
    text-align: center;
    padding: 3rem;
}

.empty-cart-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-cart-content h3 {
    color: var(--xobo-dark-blue);
    margin-bottom: 1rem;
    font-size: 1.5rem;
}

.cart-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin: 2rem 0;
}

.cart-actions-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: white;
    border-radius: 8px;
    margin-bottom: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.select-all {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
}

.cart-item {
    display: grid;
    grid-template-columns: 50px 80px 2fr 100px 120px 100px 50px;
    gap: 1rem;
    align-items: center;
    background: white;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.item-image {
    font-size: 2rem;
    text-align: center;
    background: var(--xobo-light-gray);
    padding: 1rem;
    border-radius: 8px;
}

.item-details h4 {
    margin-bottom: 0.25rem;
    color: var(--xobo-dark-blue);
}

.item-details p {
    color: var(--xobo-gray);
    font-size: 0.9rem;
}

.item-rating .stars {
    color: #f39c12;
    font-size: 0.8rem;
}

.item-price {
    font-weight: bold;
    color: var(--xobo-red);
}

.item-total {
    font-weight: bold;
    color: var(--xobo-dark-blue);
}

.remove-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.2rem;
    padding: 0.25rem;
    border-radius: 4px;
    transition: background-color 0.3s;
}

.remove-btn:hover {
    background: #f8f9fa;
}

.cart-actions-bottom {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.guarantee {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.guarantee-icon {
    font-size: 2rem;
    color: var(--xobo-red);
}

.guarantee-text strong {
    color: var(--xobo-dark-blue);
}

@media (max-width: 768px) {
    .cart-container {
        grid-template-columns: 1fr;
    }
    
    .cart-item {
        grid-template-columns: 1fr;
        gap: 0.5rem;
        text-align: center;
    }
    
    .cart-actions-top {
        flex-direction: column;
        gap: 1rem;
    }
}
</style>

<?php include '../includes/footer.php'; ?> 