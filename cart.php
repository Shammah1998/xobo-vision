<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

$pageTitle = 'Shopping Cart - XOBO MART';
include 'includes/header.php';
?>

<!-- XOBO-MART STYLE CART HEADER -->
<section class="cart-header">
    <h1>Shopping Cart</h1>
    <p class="cart-description">Review your items and proceed to checkout when ready</p>
</section>

<!-- XOBO-MART STYLE CART CONTAINER -->
<section id="cart-container">
    <!-- Cart content will be loaded by JavaScript -->
</section>

<script>
// XOBO-MART STYLE CART MANAGEMENT
let cart = JSON.parse(localStorage.getItem('shopping_cart') || '[]');

function renderCart() {
    const container = document.getElementById('cart-container');
    
    if (cart.length === 0) {
        container.innerHTML = `
            <div class="empty-cart">
                <div class="empty-cart-content">
                    <div class="empty-cart-icon">🛒</div>
                    <h3>Your cart is empty</h3>
                    <p>Browse our amazing products and add some to your cart!</p>
                    <a href="index.php" class="btn">Continue Shopping</a>
                </div>
            </div>
        `;
        return;
    }
    
    let totalCost = 0;
    let cartHTML = `
        <div class="cart-container">
            <div class="cart-items-section">
                <div class="cart-section-header">
                    <h2>Cart Items (${cart.length})</h2>
                    <button type="button" class="btn-clear-cart" onclick="clearCart()">
                        <i class="fas fa-trash"></i> Clear Cart
                    </button>
                </div>
                
                <div class="cart-items">
    `;
    
    cart.forEach((item, index) => {
        const itemTotal = item.price * item.quantity;
        totalCost += itemTotal;
        
        cartHTML += `
            <div class="cart-item" data-index="${index}">
                <div class="item-image">
                    <div class="product-image-placeholder">
                        ${getProductEmoji(item.name)}
                    </div>
                </div>
                
                <div class="item-details">
                    <h4 class="item-name">${item.name}</h4>
                    <p class="item-id">Product ID: ${item.id}</p>
                    <div class="item-rating">
                        <span>⭐⭐⭐⭐⭐</span>
                    </div>
                    <div class="item-price">
                        <span class="price-label">Unit Price:</span>
                        <span class="price-value">${formatCurrency(item.price)}</span>
                    </div>
                </div>
                
                <div class="item-quantity">
                    <label class="qty-label">Quantity</label>
                    <div class="qty-controls">
                        <button type="button" class="qty-btn qty-decrement" onclick="updateQuantity(${index}, -1)">-</button>
                        <input type="number" value="${item.quantity}" min="1" max="99" 
                               class="qty-input" onchange="setQuantity(${index}, this.value)">
                        <button type="button" class="qty-btn qty-increment" onclick="updateQuantity(${index}, 1)">+</button>
                    </div>
                </div>
                
                <div class="item-total">
                    <span class="total-label">Total</span>
                    <span class="total-value">${formatCurrency(itemTotal)}</span>
                </div>
                
                <div class="item-actions">
                    <button type="button" class="remove-btn" onclick="removeItem(${index})" title="Remove item">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    cartHTML += `
                </div>
            </div>
            
            <div class="cart-summary-section">
                <div class="summary-card">
                    <div class="guarantee-section">
                        <div class="guarantee-badge">
                            <i class="fas fa-shield-alt"></i>
                            <div class="guarantee-text">
                                <strong>SECURE CHECKOUT</strong>
                                <small>Safe & Protected</small>
                            </div>
                        </div>
                        <div class="guarantee-badge">
                            <i class="fas fa-undo"></i>
                            <div class="guarantee-text">
                                <strong>7 DAYS RETURN</strong>
                                <small>Money Back Guarantee</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="summary-details">
                        <h3>Order Summary</h3>
                        <div class="summary-row">
                            <span>Items (${cart.reduce((total, item) => total + item.quantity, 0)}):</span>
                            <span>${formatCurrency(totalCost)}</span>
                        </div>
                        <div class="summary-row">
                            <span>Delivery:</span>
                            <span class="free-delivery">FREE</span>
                        </div>
                        <div class="summary-divider"></div>
                        <div class="summary-row total-row">
                            <span>Total Amount:</span>
                            <span class="total-amount">${formatCurrency(totalCost)}</span>
                        </div>
                    </div>
                    
                    <div class="checkout-actions">
                        <a href="checkout.php" class="btn btn-checkout">
                            <i class="fas fa-lock"></i> Proceed to Checkout
                        </a>
                        <a href="index.php" class="btn btn-continue">Continue Shopping</a>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.innerHTML = cartHTML;
}

function getProductEmoji(productName) {
    if (productName.toLowerCase().includes('laptop')) return '💻';
    if (productName.toLowerCase().includes('desktop') || productName.toLowerCase().includes('computer')) return '🖥️';
    if (productName.toLowerCase().includes('mouse')) return '🖱️';
    if (productName.toLowerCase().includes('earpods') || productName.toLowerCase().includes('earphone')) return '🎧';
    if (productName.toLowerCase().includes('sneakers') || productName.toLowerCase().includes('nike')) return '👟';
    if (productName.toLowerCase().includes('shoes')) return '👞';
    if (productName.toLowerCase().includes('bag')) return '👜';
    if (productName.toLowerCase().includes('watch')) return '⌚';
    if (productName.toLowerCase().includes('phone') || productName.toLowerCase().includes('samsung')) return '📱';
    if (productName.toLowerCase().includes('sunglasses')) return '🕶️';
    return '📦';
}

function updateQuantity(index, change) {
    if (cart[index]) {
        cart[index].quantity += change;
        if (cart[index].quantity <= 0) {
            cart.splice(index, 1);
        }
        saveCart();
        renderCart();
        updateCartDisplay();
        showNotification('Cart updated', 'success');
    }
}

function setQuantity(index, value) {
    const qty = parseInt(value);
    if (cart[index] && qty > 0) {
        cart[index].quantity = qty;
        saveCart();
        renderCart();
        updateCartDisplay();
        showNotification('Quantity updated', 'success');
    }
}

function removeItem(index) {
    if (confirm('Remove this item from cart?')) {
        const itemName = cart[index].name;
        cart.splice(index, 1);
        saveCart();
        renderCart();
        updateCartDisplay();
        showNotification(`${itemName} removed from cart`, 'success');
    }
}

function clearCart() {
    if (confirm('Are you sure you want to clear your cart?')) {
        cart = [];
        saveCart();
        renderCart();
        updateCartDisplay();
        showNotification('Cart cleared', 'success');
    }
}

function saveCart() {
    localStorage.setItem('shopping_cart', JSON.stringify(cart));
}

function updateCartDisplay() {
    const cartCount = cart.reduce((total, item) => total + item.quantity, 0);
    const cartCountElement = document.getElementById('cart-count');
    if (cartCountElement) {
        cartCountElement.textContent = cartCount;
        cartCountElement.style.display = cartCount > 0 ? 'inline-block' : 'none';
    }
}

function formatCurrency(amount) {
    return 'KSh ' + new Intl.NumberFormat('en-KE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        background: ${type === 'success' ? '#27ae60' : '#e53935'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 4px;
        z-index: 1000;
        animation: slideIn 0.3s ease;
        font-family: 'Segoe UI', sans-serif;
        font-size: 14px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Initialize cart display
document.addEventListener('DOMContentLoaded', function() {
    renderCart();
    updateCartDisplay();
});
</script>

<style>
/* XOBO-MART STYLE CART STYLING */
.cart-header {
    text-align: center;
    margin: 2rem 0;
    padding: 2rem;
    background: var(--xobo-white);
    border-radius: 8px;
    box-shadow: 0 2px 5px var(--xobo-shadow);
}

.cart-header h1 {
    color: var(--xobo-primary);
    font-size: 2rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.cart-description {
    color: var(--xobo-gray);
    font-size: 1rem;
    margin: 0;
}

.empty-cart {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 50vh;
    background: var(--xobo-white);
    border-radius: 8px;
    box-shadow: 0 2px 5px var(--xobo-shadow);
}

.empty-cart-content {
    text-align: center;
    padding: 3rem;
}

.empty-cart-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
    color: var(--xobo-gray);
}

.empty-cart-content h3 {
    color: var(--xobo-primary);
    margin-bottom: 1rem;
    font-size: 1.5rem;
    font-weight: 600;
}

.empty-cart-content p {
    color: var(--xobo-gray);
    margin-bottom: 2rem;
}

.cart-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin: 2rem 0;
}

.cart-items-section {
    background: var(--xobo-white);
    border-radius: 8px;
    box-shadow: 0 2px 5px var(--xobo-shadow);
    overflow: hidden;
}

.cart-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--xobo-border);
    background: var(--xobo-light-gray);
}

.cart-section-header h2 {
    color: var(--xobo-primary);
    font-size: 1.2rem;
    font-weight: 600;
    margin: 0;
}

.btn-clear-cart {
    background: none;
    border: 1px solid var(--xobo-accent);
    color: var(--xobo-accent);
    padding: 0.5rem 1rem;
    border-radius: 4px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-clear-cart:hover {
    background: var(--xobo-accent);
    color: white;
}

.cart-items {
    padding: 1rem;
}

.cart-item {
    display: grid;
    grid-template-columns: 80px 2fr 120px 120px 50px;
    gap: 1rem;
    align-items: start;
    padding: 1.5rem;
    border: 1px solid var(--xobo-border);
    border-radius: 8px;
    margin-bottom: 1rem;
    transition: all 0.3s;
    background: var(--xobo-white);
}

.cart-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.item-image {
    display: flex;
    align-items: center;
    justify-content: center;
}

.product-image-placeholder {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    background: var(--xobo-light-gray);
    border-radius: 8px;
    color: var(--xobo-gray);
}

.item-details {
    min-width: 0;
}

.item-name {
    color: var(--xobo-primary);
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    line-height: 1.3;
}

.item-id {
    color: var(--xobo-gray);
    font-size: 0.8rem;
    margin-bottom: 0.5rem;
}

.item-rating {
    font-size: 0.8rem;
    margin-bottom: 0.5rem;
}

.item-price {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.price-label {
    color: var(--xobo-gray);
    font-size: 0.8rem;
}

.price-value {
    color: var(--xobo-primary);
    font-weight: 600;
    font-size: 0.9rem;
}

.item-quantity {
    text-align: center;
}

.qty-label {
    display: block;
    color: var(--xobo-gray);
    font-size: 0.8rem;
    margin-bottom: 0.5rem;
}

.qty-controls {
    display: flex;
    align-items: center;
    border: 1px solid var(--xobo-border);
    border-radius: 4px;
    overflow: hidden;
}

.qty-btn {
    background: var(--xobo-light-gray);
    border: none;
    padding: 0.5rem;
    cursor: pointer;
    font-weight: 600;
    color: var(--xobo-primary);
    transition: background 0.3s;
    min-width: 35px;
}

.qty-btn:hover {
    background: var(--xobo-primary);
    color: white;
}

.qty-input {
    border: none;
    text-align: center;
    width: 50px;
    padding: 0.5rem 0.2rem;
    font-size: 0.9rem;
    outline: none;
}

.item-total {
    text-align: center;
}

.total-label {
    display: block;
    color: var(--xobo-gray);
    font-size: 0.8rem;
    margin-bottom: 0.5rem;
}

.total-value {
    color: var(--xobo-primary);
    font-weight: 700;
    font-size: 1rem;
}

.item-actions {
    display: flex;
    justify-content: center;
}

.remove-btn {
    background: none;
    border: none;
    color: var(--xobo-accent);
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 4px;
    transition: all 0.3s;
}

.remove-btn:hover {
    background: #fef2f2;
    transform: scale(1.1);
}

.cart-summary-section {
    background: var(--xobo-white);
    border-radius: 8px;
    box-shadow: 0 2px 5px var(--xobo-shadow);
    height: fit-content;
    position: sticky;
    top: 100px;
}

.summary-card {
    padding: 1.5rem;
}

.guarantee-section {
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--xobo-border);
}

.guarantee-badge {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.guarantee-badge:last-child {
    margin-bottom: 0;
}

.guarantee-badge i {
    color: var(--xobo-primary);
    font-size: 1.2rem;
    width: 20px;
}

.guarantee-text strong {
    color: var(--xobo-primary);
    font-size: 0.8rem;
    display: block;
    font-weight: 600;
}

.guarantee-text small {
    color: var(--xobo-gray);
    font-size: 0.7rem;
}

.summary-details h3 {
    color: var(--xobo-primary);
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
}

.summary-row span:first-child {
    color: var(--xobo-gray);
}

.free-delivery {
    color: var(--xobo-success);
    font-weight: 600;
    font-size: 0.8rem;
}

.summary-divider {
    height: 1px;
    background: var(--xobo-border);
    margin: 1rem 0;
}

.total-row {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
}

.total-row span {
    color: var(--xobo-primary);
}

.total-amount {
    font-size: 1.2rem;
    font-weight: 700;
}

.checkout-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.btn-checkout {
    background: var(--xobo-primary);
    color: white;
    padding: 1rem;
    border-radius: 4px;
    text-decoration: none;
    text-align: center;
    font-weight: 600;
    transition: all 0.3s;
    border: none;
    font-size: 1rem;
}

.btn-checkout:hover {
    background: var(--xobo-primary-hover);
    transform: translateY(-1px);
}

.btn-continue {
    background: var(--xobo-white);
    color: var(--xobo-primary);
    border: 2px solid var(--xobo-primary);
    padding: 0.75rem;
    border-radius: 4px;
    text-decoration: none;
    text-align: center;
    font-weight: 500;
    transition: all 0.3s;
    font-size: 0.9rem;
}

.btn-continue:hover {
    background: var(--xobo-primary);
    color: white;
}

/* Responsive Design */
@media (max-width: 992px) {
    .cart-container {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .cart-summary-section {
        position: static;
    }
}

@media (max-width: 768px) {
    .cart-item {
        grid-template-columns: 1fr;
        gap: 1rem;
        text-align: center;
    }
    
    .cart-section-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .item-details {
        text-align: center;
    }
    
    .qty-controls {
        justify-content: center;
        width: fit-content;
        margin: 0 auto;
    }
}

@media (max-width: 480px) {
    .cart-header {
        padding: 1rem;
    }
    
    .cart-header h1 {
        font-size: 1.5rem;
    }
    
    .summary-card {
        padding: 1rem;
    }
}
</style>

<?php include 'includes/footer.php'; ?> 