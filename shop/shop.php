<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

requireRole(['user']);

$companyId = $_SESSION['company_id'];
$message = '';

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $productId = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    
    if ($quantity > 0) {
        // Initialize cart in session if not exists
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // Add or update product in cart
        if (isset($_SESSION['cart'][$productId])) {
            $_SESSION['cart'][$productId] += $quantity;
        } else {
            $_SESSION['cart'][$productId] = $quantity;
        }
        
        $message = 'Product added to cart successfully!';
    }
}

// Get company products
$stmt = $pdo->prepare("SELECT * FROM products WHERE company_id = ? ORDER BY name");
$stmt->execute([$companyId]);
$products = $stmt->fetchAll();

// Get company name
$companyName = getCompanyName($pdo, $companyId);

$pageTitle = 'Shop - ' . $companyName;
include '../includes/header.php';
?>

<!-- Hero Section -->
<div class="hero-section">
    <div class="container">
        <h1>Discover Amazing Products</h1>
        <p>Shop the latest electronics, fashion, home essentials and more from <?php echo htmlspecialchars($companyName); ?></p>
    </div>
</div>

<?php if ($message): ?>
    <div class="container">
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    </div>
<?php endif; ?>

<div class="container">
    <div class="shop-header">
        <h2>Our Products</h2>
        <div class="cart-summary">
            <a href="cart.php" class="btn btn-secondary">
                🛒 View Cart 
                <?php if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])): ?>
                    <span class="cart-count">(<?php echo array_sum($_SESSION['cart']); ?>)</span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <?php if (empty($products)): ?>
        <div class="no-data">
            <h3>No products available</h3>
            <p>This company hasn't added any products yet. Please check back later.</p>
        </div>
    <?php else: ?>
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
            <div class="product-card">
                <div class="product-image">
                    📦
                </div>
                
                <div class="product-info">
                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                    <p class="product-sku">SKU: <?php echo htmlspecialchars($product['sku']); ?></p>
                    <p class="product-weight">Weight: <?php echo number_format($product['weight_kg'], 2); ?> kg</p>
                    
                    <div class="product-rating">
                        <div class="stars">
                            ★★★★★
                        </div>
                    </div>
                    
                    <p class="product-price"><?php echo formatCurrency($product['rate_ksh']); ?></p>
                    
                    <form method="POST" class="add-to-cart-form">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <div class="quantity-input">
                            <label for="quantity_<?php echo $product['id']; ?>">Quantity:</label>
                            <input type="number" id="quantity_<?php echo $product['id']; ?>" 
                                   name="quantity" value="1" min="1" max="99" required>
                        </div>
                        <button type="submit" name="add_to_cart" class="btn btn-primary add-to-cart-btn">
                            Add to Cart
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?> 