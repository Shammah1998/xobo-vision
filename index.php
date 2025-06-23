<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

// Restrict access: Only company users can access this page
if (isLoggedIn()) {
    // If user is admin, redirect to admin dashboard
    if (isAdmin($pdo)) {
        header('Location: /xobo-vision/admin/dashboard.php');
        exit;
    }
    
    // If user doesn't have a company_id, they shouldn't be here
    if (empty($_SESSION['company_id'])) {
        header('Location: /xobo-vision/auth/logout.php');
        exit;
    }
    
    // Check if user's company is approved
    $stmt = $pdo->prepare("SELECT status FROM companies WHERE id = ?");
    $stmt->execute([$_SESSION['company_id']]);
    $company = $stmt->fetch();
    
    if (!$company || $company['status'] !== 'approved') {
        session_destroy();
        header('Location: /xobo-vision/auth/login.php?error=' . urlencode('Your company is not approved or has been deactivated.'));
        exit;
    }
}

// Get products from user's company if logged in
$featuredProducts = [];
if (isLoggedIn() && !empty($_SESSION['company_id'])) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as company_name 
        FROM products p 
        JOIN companies c ON p.company_id = c.id 
        WHERE c.id = ? AND c.status = 'approved'
        ORDER BY p.created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$_SESSION['company_id']]);
    $featuredProducts = $stmt->fetchAll();
}

// Get product categories
$categories = [
    'Electronics' => '💻',
    'Fashion' => '👕',
    'Accessories' => '⌚',
    'General' => '🛍️'
];

// Set page title based on login status
if (isLoggedIn() && !empty($_SESSION['company_id'])) {
    $stmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
    $stmt->execute([$_SESSION['company_id']]);
    $companyName = $stmt->fetchColumn();
    $pageTitle = $companyName ? $companyName . ' - XOBO MART' : 'XOBO MART - Online Shopping';
} else {
    $pageTitle = 'XOBO MART - Online Shopping';
}
include 'includes/header.php';
?>

<!-- XOBO-MART STYLE HERO SECTION -->
<section class="hero-banner">
    <div class="hero-content">
        <h1>Discover Amazing Products</h1>
        <p>Shop the latest electronics, fashion, home essentials and more</p>
    </div>
</section>

<!-- XOBO-MART STYLE PRODUCT GRID -->
<section class="products-section">
    <div class="container">
        <?php if (!isLoggedIn()): ?>
            <div class="section-header" style="text-align: center; padding: 4rem 0;">
                <h2 style="color: var(--xobo-primary); margin-bottom: 1rem;">Welcome to XOBO MART</h2>
                <p style="color: var(--xobo-gray); font-size: 1.1rem; margin-bottom: 2rem;">Please log in to access your company's products and start shopping.</p>
                <a href="/xobo-vision/auth/login.php" class="btn" style="background: var(--xobo-primary); color: white; padding: 1rem 2rem; text-decoration: none; border-radius: 6px; font-weight: 600;">
                    <i class="fas fa-sign-in-alt"></i> Login to Continue
                </a>
            </div>
        <?php elseif (empty($featuredProducts)): ?>
            <div class="section-header" style="text-align: center; padding: 4rem 0;">
                <h2 style="color: var(--xobo-primary); margin-bottom: 1rem;">No Products Available Yet</h2>
                <p style="color: var(--xobo-gray); font-size: 1.1rem;">Your company's product catalog is being set up. Please check back soon!</p>
            </div>
        <?php else: ?>
            <div class="section-header" style="text-align: center; margin-bottom: 2rem;">
                <h2 style="color: var(--xobo-primary); margin-bottom: 0.5rem;">Our Products</h2>
                <p style="color: var(--xobo-gray);">Discover amazing products from your company catalog</p>
            </div>
        <div class="products-grid">
            <?php foreach ($featuredProducts as $product): ?>
            <div class="product-card" data-product-id="<?php echo $product['id']; ?>">
                <div class="product-image">
                    <div class="product-image-placeholder">
                        <?php 
                        // Product emojis based on category/name - Xobo-Mart Style
                        if (stripos($product['name'], 'laptop') !== false) echo '💻';
                        elseif (stripos($product['name'], 'desktop') !== false || stripos($product['name'], 'computer') !== false) echo '🖥️';
                        elseif (stripos($product['name'], 'mouse') !== false) echo '🖱️';
                        elseif (stripos($product['name'], 'earpods') !== false || stripos($product['name'], 'earphone') !== false) echo '🎧';
                        elseif (stripos($product['name'], 'sneakers') !== false || stripos($product['name'], 'nike') !== false) echo '👟';
                        elseif (stripos($product['name'], 'shoes') !== false) echo '👞';
                        elseif (stripos($product['name'], 'bag') !== false) echo '👜';
                        elseif (stripos($product['name'], 'watch') !== false) echo '⌚';
                        elseif (stripos($product['name'], 'phone') !== false || stripos($product['name'], 'samsung') !== false) echo '📱';
                        elseif (stripos($product['name'], 'sunglasses') !== false) echo '🕶️';
                        else echo '📦';
                        ?>
                    </div>
                </div>
                
                <div class="product-info">
                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                    <div class="product-company">
                        <span><?php echo htmlspecialchars($product['company_name']); ?></span>
                    </div>
                    <div class="product-rating">
                        <span>⭐⭐⭐⭐⭐</span>
                    </div>
                    <div class="product-price">
                        <?php echo formatCurrency($product['rate_ksh']); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </div>
</section>

<script>
// XOBO-MART STYLE CART FUNCTIONALITY
let cart = JSON.parse(localStorage.getItem('shopping_cart') || '[]');

function addToCart(productId, productName, price) {
    // Check if item already exists in cart
    const existingItem = cart.find(item => item.id === productId);
    
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({
            id: productId,
            name: productName,
            price: price,
            quantity: 1
        });
    }
    
    // Save to localStorage
    localStorage.setItem('shopping_cart', JSON.stringify(cart));
    
    // Update cart display
    updateCartDisplay();
    
    // Show success notification - Xobo-Mart Style
    showNotification(`${productName} added to cart!`, 'success');
}

function updateCartDisplay() {
    const cartCount = cart.reduce((total, item) => total + item.quantity, 0);
    const cartCountElement = document.getElementById('cart-count');
    if (cartCountElement) {
        cartCountElement.textContent = cartCount;
        cartCountElement.style.display = cartCount > 0 ? 'inline-block' : 'none';
    }
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

// Initialize on page load - Xobo-Mart Style
document.addEventListener('DOMContentLoaded', function() {
    updateCartDisplay();
    
    // Add click handlers to product cards for future product detail pages
    document.querySelectorAll('.product-card').forEach(card => {
        card.addEventListener('click', function(e) {
            // Only if click wasn't on a button
            if (!e.target.closest('button')) {
                // Future: redirect to product detail page
                // window.location.href = `product.php?id=${this.dataset.productId}`;
                console.log('Product clicked:', this.dataset.productId);
            }
        });
    });
});
</script>

<style>
/* Product Image Placeholder */
.product-image-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 60px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    color: #6c757d;
    border-radius: 8px;
}

/* Product Cards Enhanced */
.product-card {
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
}

.product-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.product-card:hover .product-image-placeholder {
    transform: scale(1.05);
}

.product-image {
    border-radius: 12px 12px 0 0;
    overflow: hidden;
}

.product-info {
    padding: 1rem;
}

.product-info h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--xobo-primary);
    line-height: 1.3;
}

.product-company {
    margin-bottom: 0.5rem;
}

.product-company span {
    font-size: 0.8rem;
    color: var(--xobo-gray);
    background: var(--xobo-light-gray);
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    font-weight: 500;
}

.product-rating {
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.product-price {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--xobo-primary);
}

/* Clean grid layout */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 24px;
    margin: 40px 0;
}

/* Animations */
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}

/* Responsive */
@media (max-width: 768px) {
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 16px;
    }
}

@media (max-width: 480px) {
    .products-grid {
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
}
</style>

<?php include 'includes/footer.php'; ?> 