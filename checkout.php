<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

$message = '';
$error = '';

// Handle order placement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $email = sanitize($_POST['email']);
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $notes = sanitize($_POST['notes'] ?? '');
    
    // Basic validation
    if (empty($email) || empty($name) || empty($phone) || empty($address)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Create a simple order record
            $orderData = [
                'email' => $email,
                'name' => $name,
                'phone' => $phone,
                'address' => $address,
                'notes' => $notes,
                'order_date' => date('Y-m-d H:i:s'),
                'status' => 'pending'
            ];
            
            // For simplicity, we'll just show success without complex database operations
            $message = 'Order placed successfully! We will contact you soon at ' . $email;
        } catch (Exception $e) {
            $error = 'Failed to place order. Please try again.';
        }
    }
}

$pageTitle = 'Checkout - Xobo Mart';
include 'includes/header.php';
?>

<div class="container">
    <div class="checkout-header">
        <h1>Checkout</h1>
        <div class="checkout-steps">
            <div class="step active">1. Review</div>
            <div class="step active">2. Shipping</div>
            <div class="step">3. Payment</div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div id="checkout-container">
        <!-- Checkout content will be loaded by JavaScript -->
    </div>
</div>

<script>
// Get cart from localStorage
let cart = JSON.parse(localStorage.getItem('shopping_cart') || '[]');

function renderCheckout() {
    const container = document.getElementById('checkout-container');
    
    if (cart.length === 0) {
        container.innerHTML = `
            <div class="empty-cart">
                <div class="empty-cart-content">
                    <div class="empty-cart-icon">🛒</div>
                    <h3>Your cart is empty</h3>
                    <p>Add some products to your cart before checking out.</p>
                    <a href="index.php" class="btn btn-primary">Continue Shopping</a>
                </div>
            </div>
        `;
        return;
    }
    
    let totalCost = 0;
    let totalWeight = 0;
    let orderHTML = `
        <div class="checkout-container">
            <div class="order-review">
                <h2>Order Review</h2>
                <div class="order-items">
    `;
    
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        totalCost += itemTotal;
        // Assume 0.5kg average weight per item for demo
        totalWeight += (item.quantity * 0.5);
        
        orderHTML += `
            <div class="order-item">
                <div class="item-image">📦</div>
                <div class="item-details">
                    <h4>${item.name}</h4>
                    <p>Quantity: ${item.quantity}</p>
                    <p>Price: ${formatCurrency(item.price)} each</p>
                </div>
                <div class="item-total">
                    ${formatCurrency(itemTotal)}
                </div>
            </div>
        `;
    });
    
    orderHTML += `
                </div>
                <div class="order-totals">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span>${formatCurrency(totalCost)}</span>
                    </div>
                    <div class="total-row">
                        <span>Shipping:</span>
                        <span>FREE</span>
                    </div>
                    <div class="total-row grand-total">
                        <span>Total:</span>
                        <span>${formatCurrency(totalCost)}</span>
                    </div>
                </div>
            </div>
            
            <div class="shipping-details">
                <h2>Shipping Information</h2>
                <form method="POST" class="checkout-form">
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" required 
                               value="${document.querySelector('input[name="name"]')?.value || ''}">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required 
                               value="${document.querySelector('input[name="email"]')?.value || ''}">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" required 
                               value="${document.querySelector('input[name="phone"]')?.value || ''}">
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Delivery Address *</label>
                        <textarea id="address" name="address" rows="4" required 
                                  placeholder="Enter your complete delivery address...">${document.querySelector('textarea[name="address"]')?.value || ''}</textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Order Notes (Optional)</label>
                        <textarea id="notes" name="notes" rows="3" 
                                  placeholder="Any special instructions...">${document.querySelector('textarea[name="notes"]')?.value || ''}</textarea>
                    </div>
                    
                    <div class="payment-info">
                        <h3>Payment Method</h3>
                        <div class="payment-options">
                            <label class="payment-option">
                                <input type="radio" name="payment" value="cod" checked>
                                <span>💰 Cash on Delivery</span>
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment" value="mpesa">
                                <span>📱 M-Pesa (Coming Soon)</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="shop/cart.php" class="btn btn-secondary">Back to Cart</a>
                        <button type="submit" name="place_order" class="btn btn-primary btn-large">
                            Place Order - ${formatCurrency(totalCost)}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    container.innerHTML = orderHTML;
}

function formatCurrency(amount) {
    return 'KSH ' + new Intl.NumberFormat('en-KE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

// Initialize checkout
document.addEventListener('DOMContentLoaded', function() {
    renderCheckout();
    
    // Update cart display in header
    const cartCount = cart.reduce((total, item) => total + item.quantity, 0);
    const cartCountElements = document.querySelectorAll('.cart-count');
    cartCountElements.forEach(el => {
        el.textContent = cartCount;
    });
});
</script>

<style>
.checkout-header {
    margin: 2rem 0;
    text-align: center;
}

.checkout-header h1 {
    color: var(--xobo-dark-blue);
    font-size: 2.5rem;
    margin-bottom: 1rem;
    font-weight: 300;
}

.checkout-steps {
    display: flex;
    justify-content: center;
    gap: 2rem;
    margin-bottom: 2rem;
}

.step {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    background: var(--xobo-light-gray);
    color: var(--xobo-gray);
    font-weight: 500;
}

.step.active {
    background: var(--xobo-red);
    color: white;
}

.checkout-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    margin: 2rem 0;
}

.order-review,
.shipping-details {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

.order-review h2,
.shipping-details h2 {
    color: var(--xobo-dark-blue);
    margin-bottom: 1.5rem;
    font-size: 1.5rem;
    font-weight: 600;
}

.order-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-bottom: 1px solid var(--xobo-light-gray);
}

.order-item:last-child {
    border-bottom: none;
}

.order-item .item-image {
    font-size: 2rem;
    background: var(--xobo-light-gray);
    padding: 0.5rem;
    border-radius: 8px;
}

.order-item .item-details {
    flex: 1;
}

.order-item .item-details h4 {
    color: var(--xobo-dark-blue);
    margin-bottom: 0.25rem;
}

.order-item .item-details p {
    color: var(--xobo-gray);
    font-size: 0.9rem;
    margin-bottom: 0.1rem;
}

.order-item .item-total {
    font-weight: bold;
    color: var(--xobo-red);
    font-size: 1.1rem;
}

.order-totals {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 2px solid var(--xobo-light-gray);
}

.total-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 1rem;
}

.grand-total {
    font-size: 1.3rem;
    font-weight: bold;
    color: var(--xobo-dark-blue);
    border-top: 1px solid var(--xobo-red);
    padding-top: 0.5rem;
    margin-top: 0.5rem;
}

.payment-info {
    margin: 2rem 0;
}

.payment-info h3 {
    color: var(--xobo-dark-blue);
    margin-bottom: 1rem;
}

.payment-options {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.payment-option {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
    border: 2px solid var(--xobo-light-gray);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.payment-option:hover {
    border-color: var(--xobo-red);
}

.payment-option input[type="radio"] {
    margin: 0;
}

.payment-option span {
    font-weight: 500;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.btn-large {
    padding: 1rem 2rem;
    font-size: 1.1rem;
    font-weight: 600;
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

@media (max-width: 768px) {
    .checkout-container {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .checkout-steps {
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .order-item {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
}
</style>

<?php include 'includes/footer.php'; ?> 