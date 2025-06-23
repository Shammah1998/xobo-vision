<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

// Get search query
$searchQuery = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

// Sample products for demo (you can replace with actual database products)
$products = [
    [
        'id' => 1,
        'name' => 'HP Laptop',
        'sku' => 'HP-001',
        'rate_ksh' => 23000.00,
        'company_name' => 'Tech Store',
        'category' => 'Electronics'
    ],
    [
        'id' => 2,
        'name' => 'Sport & fashion sneakers',
        'sku' => 'SNK-001',
        'rate_ksh' => 1500.00,
        'company_name' => 'Fashion Hub',
        'category' => 'Fashion'
    ],
    [
        'id' => 3,
        'name' => 'Tom Ford Fashion Bag',
        'sku' => 'BAG-001',
        'rate_ksh' => 3400.00,
        'company_name' => 'Luxury Store',
        'category' => 'Fashion'
    ],
    [
        'id' => 4,
        'name' => "Men's official black shoes",
        'sku' => 'SHO-001',
        'rate_ksh' => 2500.00,
        'company_name' => 'Shoe Palace',
        'category' => 'Fashion'
    ],
    [
        'id' => 5,
        'name' => 'Desktop Computer',
        'sku' => 'DT-001',
        'rate_ksh' => 14000.00,
        'company_name' => 'Computer World',
        'category' => 'Electronics'
    ],
    [
        'id' => 6,
        'name' => "Official men's Ray-Ban watch",
        'sku' => 'WTC-001',
        'rate_ksh' => 2900.00,
        'company_name' => 'Watch Center',
        'category' => 'Accessories'
    ],
    [
        'id' => 7,
        'name' => 'White new nike sneakers',
        'sku' => 'SNK-002',
        'rate_ksh' => 1500.00,
        'company_name' => 'Sports Store',
        'category' => 'Fashion'
    ],
    [
        'id' => 8,
        'name' => "Michael Kors men's watch",
        'sku' => 'WTC-002',
        'rate_ksh' => 1375.00,
        'company_name' => 'Watch Center',
        'category' => 'Accessories'
    ],
    [
        'id' => 9,
        'name' => 'HP Computer Mouse',
        'sku' => 'MSE-001',
        'rate_ksh' => 400.00,
        'company_name' => 'Tech Store',
        'category' => 'Electronics'
    ],
    [
        'id' => 10,
        'name' => 'Black Earpods',
        'sku' => 'EAR-001',
        'rate_ksh' => 800.00,
        'company_name' => 'Audio World',
        'category' => 'Electronics'
    ],
    [
        'id' => 11,
        'name' => 'Samsung Galaxy Phone',
        'sku' => 'PHN-001',
        'rate_ksh' => 35000.00,
        'company_name' => 'Mobile World',
        'category' => 'Electronics'
    ],
    [
        'id' => 12,
        'name' => 'Designer Sunglasses',
        'sku' => 'SUN-001',
        'rate_ksh' => 2200.00,
        'company_name' => 'Fashion Hub',
        'category' => 'Accessories'
    ]
];

// Filter products based on search and category
$filteredProducts = $products;

if (!empty($searchQuery)) {
    $filteredProducts = array_filter($filteredProducts, function($product) use ($searchQuery) {
        return stripos($product['name'], $searchQuery) !== false || 
               stripos($product['sku'], $searchQuery) !== false ||
               stripos($product['company_name'], $searchQuery) !== false;
    });
}

if (!empty($category)) {
    $filteredProducts = array_filter($filteredProducts, function($product) use ($category) {
        return $product['category'] === $category;
    });
}

// Get categories for filter
$categories = [
    'Electronics' => '💻',
    'Fashion' => '👕',
    'Accessories' => '⌚'
];

$pageTitle = !empty($searchQuery) ? "Search Results for '$searchQuery' - XOBO MART" : 'All Products - XOBO MART';
include 'includes/header.php';
?>

<!-- XOBO-MART STYLE CATALOG HEADER -->
<section class="catalog-header">
    <h1>
        <?php if (!empty($searchQuery)): ?>
            Search Results for "<?php echo htmlspecialchars($searchQuery); ?>"
        <?php elseif (!empty($category)): ?>
            <?php echo htmlspecialchars($category); ?> Products
        <?php else: ?>
            All Products
        <?php endif; ?>
    </h1>
    <p class="results-count"><?php echo count($filteredProducts); ?> products found</p>
</section>

<!-- XOBO-MART STYLE FILTERS -->
<section class="catalog-filters">
    <div class="filter-section">
        <h3>Categories</h3>
        <div class="category-filters">
            <a href="catalog.php<?php echo !empty($searchQuery) ? '?search=' . urlencode($searchQuery) : ''; ?>" 
               class="filter-btn <?php echo empty($category) ? 'active' : ''; ?>">
                All Products
            </a>
            <?php foreach ($categories as $cat => $icon): ?>
                <a href="catalog.php?category=<?php echo urlencode($cat); ?><?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" 
                   class="filter-btn <?php echo $category === $cat ? 'active' : ''; ?>">
                    <?php echo $icon; ?> <?php echo $cat; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- XOBO-MART STYLE PRODUCT GRID -->
<section class="products-section">
    <?php if (empty($filteredProducts)): ?>
        <div class="no-products">
            <div class="no-products-content">
                <div class="no-products-icon">🔍</div>
                <h3>No products found</h3>
                <?php if (!empty($searchQuery)): ?>
                    <p>Try searching with different keywords or browse all products.</p>
                    <a href="catalog.php" class="btn">View All Products</a>
                <?php else: ?>
                    <p>We're adding amazing products. Check back soon!</p>
                    <a href="index.php" class="btn">Back to Home</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="products-grid">
            <?php foreach ($filteredProducts as $product): ?>
            <div class="product-card" data-product-id="<?php echo $product['id']; ?>">
                <div class="product-image">
                    <div class="product-image-placeholder">
                        <?php 
                        // Product emojis based on name - Xobo-Mart Style
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
                    <p class="product-company">by <?php echo htmlspecialchars($product['company_name']); ?></p>
                    <div class="product-rating">
                        <span>⭐⭐⭐⭐⭐</span>
                    </div>
                    <div class="product-price">
                        KSh <?php echo number_format($product['rate_ksh'], 2); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script>
// XOBO-MART STYLE CART FUNCTIONALITY
let cart = JSON.parse(localStorage.getItem('shopping_cart') || '[]');

function addToCart(productId, productName, price) {
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
    
    localStorage.setItem('shopping_cart', JSON.stringify(cart));
    updateCartDisplay();
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

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateCartDisplay();
    
    // Add click handlers to product cards
    document.querySelectorAll('.product-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (!e.target.closest('button')) {
                console.log('Product clicked:', this.dataset.productId);
            }
        });
    });
});
</script>

<style>
/* XOBO-MART CATALOG STYLES */
.catalog-header {
    text-align: center;
    margin: 2rem 0;
    padding: 2rem;
    background: var(--xobo-white);
    border-radius: 8px;
    box-shadow: 0 2px 5px var(--xobo-shadow);
}

.catalog-header h1 {
    color: var(--xobo-primary);
    font-size: 2rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.results-count {
    color: var(--xobo-gray);
    font-size: 1rem;
}

.catalog-filters {
    margin: 2rem 0;
    padding: 1.5rem;
    background: var(--xobo-white);
    border-radius: 8px;
    box-shadow: 0 2px 5px var(--xobo-shadow);
}

.filter-section h3 {
    color: var(--xobo-primary);
    margin-bottom: 1rem;
    font-size: 1.1rem;
    font-weight: 600;
}

.category-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.filter-btn {
    padding: 0.5rem 1rem;
    border: 1px solid var(--xobo-border);
    border-radius: 4px;
    text-decoration: none;
    color: var(--xobo-gray);
    font-weight: 500;
    font-size: 0.9rem;
    transition: all 0.3s;
    background: var(--xobo-white);
}

.filter-btn:hover,
.filter-btn.active {
    border-color: var(--xobo-primary);
    background: var(--xobo-primary);
    color: white;
}

.no-products {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 40vh;
    background: var(--xobo-white);
    border-radius: 8px;
    box-shadow: 0 2px 5px var(--xobo-shadow);
}

.no-products-content {
    text-align: center;
    padding: 3rem;
}

.no-products-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.no-products-content h3 {
    color: var(--xobo-primary);
    margin-bottom: 1rem;
    font-size: 1.5rem;
    font-weight: 600;
}

.no-products-content p {
    color: var(--xobo-gray);
    margin-bottom: 2rem;
}

.product-company {
    font-size: 0.8rem;
    color: var(--xobo-gray);
    margin-bottom: 0.3rem;
    font-style: italic;
}

/* Responsive Design */
@media (max-width: 768px) {
    .catalog-header h1 {
        font-size: 1.5rem;
    }
    
    .category-filters {
        justify-content: center;
    }
    
    .filter-btn {
        font-size: 0.8rem;
        padding: 0.4rem 0.8rem;
    }
}

@media (max-width: 480px) {
    .catalog-header {
        padding: 1rem;
    }
    
    .catalog-filters {
        padding: 1rem;
    }
}
</style>

<?php include 'includes/footer.php'; ?> 