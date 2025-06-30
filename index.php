<?php
require_once 'config/config.php';
session_start();
require_once 'includes/functions.php';
require_once 'config/db.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/auth/login');
    exit;
}

// Ensure user has a company
if (empty($_SESSION['company_id'])) {
    header('Location: ' . BASE_URL . '/auth/login');
    exit;
}

// Get company information
$stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ? AND status = 'approved'");
$stmt->execute([$_SESSION['company_id']]);
$company = $stmt->fetch();

if (!$company) {
    // Company not found or not approved
    $error = "Your company is not yet approved or has been deactivated. Please contact the administrator.";
    session_destroy();
    header('Location: ' . BASE_URL . '/auth/login?error=' . urlencode($error));
    exit;
}

// Get all company products for catalog
$stmt = $pdo->prepare("SELECT * FROM products WHERE company_id = ? ORDER BY name ASC");
$stmt->execute([$_SESSION['company_id']]);
$allProducts = $stmt->fetchAll();



// Get company statistics (for company admins)
$companyStats = null;
if ($_SESSION['role'] === 'company_admin') {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_products FROM products WHERE company_id = ?");
    $stmt->execute([$_SESSION['company_id']]);
    $totalProducts = $stmt->fetch()['total_products'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE company_id = ?");
    $stmt->execute([$_SESSION['company_id']]);
    $totalOrders = $stmt->fetch()['total_orders'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_users FROM users WHERE company_id = ?");
    $stmt->execute([$_SESSION['company_id']]);
    $totalUsers = $stmt->fetch()['total_users'];
    
    $stmt = $pdo->prepare("SELECT SUM(total_ksh) as total_revenue FROM orders WHERE company_id = ?");
    $stmt->execute([$_SESSION['company_id']]);
    $totalRevenue = $stmt->fetch()['total_revenue'] ?? 0;
    
    $companyStats = [
        'products' => $totalProducts,
        'orders' => $totalOrders,
        'users' => $totalUsers,
        'revenue' => $totalRevenue
    ];
}

$pageTitle = $company['name'] . ' - XOBO MART';
include 'includes/header.php';
?>

<style>
.company-header {
    background: linear-gradient(135deg, var(--xobo-primary) 0%, var(--xobo-primary-hover) 100%);
    color: white;
    padding: 2rem 0;
    text-align: center;
    margin-bottom: 2rem;
}

.company-header h1 {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.company-header p {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0;
}

.welcome-section {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 10px var(--xobo-shadow);
    margin-bottom: 2rem;
}

.welcome-user {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.welcome-user i {
    font-size: 2rem;
    color: var(--xobo-primary);
}

.user-info h2 {
    margin: 0;
    color: var(--xobo-primary);
    font-size: 1.3rem;
}

.user-info p {
    margin: 0.25rem 0 0 0;
    color: var(--xobo-gray);
}

.quick-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--xobo-primary);
    color: #fff !important;
    padding: 0.6rem 1.4rem;
    border-radius: 6px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    box-shadow: 0 2px 6px rgba(22,35,77,0.08);
    border: none;
    min-width: 120px;
    min-height: 44px;
    transition: background 0.2s, box-shadow 0.2s, transform 0.1s;
    cursor: pointer;
    justify-content: center;
}

.action-btn:hover {
    background: var(--xobo-primary-hover);
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(22,35,77,0.15);
    transform: translateY(-1px) scale(1.03);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 10px var(--xobo-shadow);
    text-align: center;
}

.stat-card h3 {
    margin: 0 0 0.5rem 0;
    color: var(--xobo-gray);
    font-size: 0.9rem;
    font-weight: 500;
}

.stat-card .stat-number {
    font-size: 2rem;
    font-weight: 600;
    color: var(--xobo-primary);
    margin: 0;
}

/* Product Catalog Styles */
.catalog-section {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px var(--xobo-shadow);
    margin-bottom: 2rem;
    overflow: hidden;
}

.catalog-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 1.5rem;
    border-bottom: 1px solid var(--xobo-border);
    background: #f8f9fa;
    flex-wrap: wrap;
    gap: 1rem;
}

.catalog-header h3 {
    margin: 0;
    color: var(--xobo-primary);
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Search Functionality Styles */
.catalog-search {
    flex: 1;
    max-width: 400px;
    min-width: 300px;
}

.search-container {
    position: relative;
    display: flex;
    align-items: center;
    margin-bottom: 0.5rem;
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 2px solid var(--xobo-border);
    border-radius: 6px;
    font-size: 0.9rem;
    outline: none;
    transition: all 0.3s ease;
    background: white;
}

.search-input:focus {
    border-color: var(--xobo-primary);
    box-shadow: 0 0 0 3px rgba(22, 35, 77, 0.1);
}

.search-icon {
    position: absolute;
    left: 0.75rem;
    color: var(--xobo-gray);
    font-size: 0.9rem;
    z-index: 2;
}

.clear-search {
    position: absolute;
    right: 0.5rem;
    background: none;
    border: none;
    color: var(--xobo-gray);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 3px;
    transition: all 0.3s ease;
    z-index: 2;
}

.clear-search:hover {
    background: var(--xobo-light-gray);
    color: var(--xobo-primary);
}

.search-results-count {
    font-size: 0.8rem;
    color: var(--xobo-gray);
    font-style: italic;
}

/* No Search Results Styles */
.no-search-results {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.95);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.no-results-content {
    text-align: center;
    padding: 2rem;
    color: var(--xobo-gray);
}

.no-results-content i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
    color: var(--xobo-primary);
}

.no-results-content h4 {
    color: var(--xobo-primary);
    margin-bottom: 0.5rem;
    font-size: 1.2rem;
}

.no-results-content p {
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
}

.catalog-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.catalog-table-container {
    max-height: 500px;
    overflow-y: auto;
    position: relative;
}

.catalog-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.catalog-table th {
    background: #f8f9fa;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--xobo-primary);
    border-bottom: 2px solid var(--xobo-border);
    position: sticky;
    top: 0;
    z-index: 10;
}

.catalog-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

.catalog-table tbody tr:hover {
    background: #f8f9fa;
}

.catalog-table tbody tr:has(.product-checkbox:checked) {
    background: #e3f2fd;
}

.product-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.product-name {
    color: var(--xobo-primary);
}

.product-sku {
    color: var(--xobo-gray);
    font-family: monospace;
}

.product-weight {
    color: var(--xobo-gray);
}

.product-price {
    color: var(--xobo-primary);
    font-weight: 600;
}

.empty-catalog {
    text-align: center;
    padding: 3rem;
    color: var(--xobo-gray);
}

.empty-catalog i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-catalog h4 {
    margin: 1rem 0 0.5rem 0;
    color: var(--xobo-primary);
}

.empty-catalog p {
    margin-bottom: 1.5rem;
}

/* Button Styles */
.btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.3s;
}

.btn-primary {
    background: var(--xobo-primary);
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background: var(--xobo-primary-hover);
    transform: translateY(-1px);
}

.btn-secondary {
    background: var(--xobo-gray);
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-1px);
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Scrollbar Styling */
.catalog-table-container::-webkit-scrollbar {
    width: 6px;
}

.catalog-table-container::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.catalog-table-container::-webkit-scrollbar-thumb {
    background: var(--xobo-primary);
    border-radius: 3px;
}

.catalog-table-container::-webkit-scrollbar-thumb:hover {
    background: var(--xobo-primary-hover);
}

@media (max-width: 768px) {
    .catalog-header {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
    
    .catalog-search {
        max-width: 100%;
        min-width: 100%;
    }
    
    .catalog-actions {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .catalog-table-container {
        overflow-x: auto;
        max-height: 400px;
    }
    
    .catalog-table {
        min-width: 600px;
    }
    
    .catalog-table th,
    .catalog-table td {
        padding: 0.5rem;
        font-size: 0.9rem;
    }
    
    .company-header h1 {
        font-size: 1.8rem;
    }
    
    .quick-actions {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Company Header -->
<div class="company-header">
    <div class="container">
        <h1><?php echo htmlspecialchars($company['name']); ?></h1>
        <p>Welcome to your company portal</p>
    </div>
</div>

<div class="container">
    <!-- Welcome Section -->
    <div class="welcome-section">
        <div class="welcome-user">
            <i class="fas fa-user-circle"></i>
            <div class="user-info">
                <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['email']); ?></h2>
                <p><?php echo $_SESSION['role'] === 'company_admin' ? 'Company Administrator' : 'Team Member'; ?> • <?php echo htmlspecialchars($company['name']); ?></p>
            </div>
        </div>
        
        <div class="quick-actions">
            <a href="<?php echo BASE_URL; ?>/shop/orders" class="action-btn">
                <i class="fas fa-list-alt"></i> My Orders
            </a>
            <?php if ($_SESSION['role'] === 'company_admin'): ?>
                <a href="<?php echo BASE_URL; ?>/company/products" class="action-btn">
                    <i class="fas fa-boxes"></i> Manage Products
                </a>
                <a href="<?php echo BASE_URL; ?>/company/orders" class="action-btn">
                    <i class="fas fa-chart-line"></i> Company Orders
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Company Statistics (for admins only) -->
    <?php if ($companyStats): ?>
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Products</h3>
            <p class="stat-number"><?php echo $companyStats['products']; ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Orders</h3>
            <p class="stat-number"><?php echo $companyStats['orders']; ?></p>
        </div>
        <div class="stat-card">
            <h3>Team Members</h3>
            <p class="stat-number"><?php echo $companyStats['users']; ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Revenue</h3>
            <p class="stat-number"><?php echo formatCurrency($companyStats['revenue']); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Product Catalog -->
    <div class="catalog-section">
        <div class="catalog-header">
            <h3><i class="fas fa-box-open"></i> Product Catalog</h3>
            <div class="catalog-search">
                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="product-search" placeholder="Search by product name or SKU..." class="search-input">
                    <button type="button" id="clear-search" class="clear-search" style="display: none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="search-results-count">
                    <span id="search-results">All products</span>
                </div>
            </div>
            <div class="catalog-actions">
                <button type="button" id="select-all-btn" class="btn btn-secondary">
                    <i class="fas fa-check-square"></i> Select All
                </button>
                <button type="button" id="add-to-cart-btn" class="btn btn-primary" disabled>
                    <i class="fas fa-shopping-cart"></i> Add to Cart (<span id="selected-count">0</span>)
                </button>
            </div>
        </div>

        <?php if (empty($allProducts)): ?>
            <div class="empty-catalog">
                <i class="fas fa-box-open"></i>
                <h4>No products available yet</h4>
                <p>Your company's product catalog is empty.</p>
                <?php if ($_SESSION['role'] === 'company_admin'): ?>
                    <a href="<?php echo BASE_URL; ?>/company/products" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Products
                    </a>
                <?php else: ?>
                    <p style="color: var(--xobo-gray);">Contact your administrator to add products.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="catalog-table-container">
                <table class="catalog-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">
                                <input type="checkbox" id="select-all-checkbox" title="Select all products">
                            </th>
                            <th>Product Name</th>
                            <th>SKU</th>
                            <th>Weight (kg)</th>
                            <th>Price (KSH)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allProducts as $product): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" 
                                           class="product-checkbox" 
                                           data-product-id="<?php echo $product['id']; ?>"
                                           data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                           data-product-price="<?php echo $product['rate_ksh']; ?>">
                                </td>
                                <td class="product-name">
                                    <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                </td>
                                <td class="product-sku"><?php echo htmlspecialchars($product['sku']); ?></td>
                                <td class="product-weight"><?php echo number_format($product['weight_kg'], 2); ?> kg</td>
                                <td class="product-price">
                                    <strong><?php echo formatCurrency($product['rate_ksh']); ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="no-search-results" class="no-search-results" style="display: none;">
                    <div class="no-results-content">
                        <i class="fas fa-search"></i>
                        <h4>No products found</h4>
                        <p>Try searching with different keywords or check your spelling.</p>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('product-search').value=''; filterProducts('');">
                            <i class="fas fa-refresh"></i> Show All Products
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const productCheckboxes = document.querySelectorAll('.product-checkbox');
    const selectAllBtn = document.getElementById('select-all-btn');
    const addToCartBtn = document.getElementById('add-to-cart-btn');
    const selectedCountSpan = document.getElementById('selected-count');
    
    // Search functionality elements
    const searchInput = document.getElementById('product-search');
    const clearSearchBtn = document.getElementById('clear-search');
    const searchResultsSpan = document.getElementById('search-results');
    const catalogTableRows = document.querySelectorAll('.catalog-table tbody tr');
    
    // Search and filter functionality
    function filterProducts(searchTerm) {
        let visibleCount = 0;
        const term = searchTerm.toLowerCase().trim();
        const noResultsDiv = document.getElementById('no-search-results');
        
        catalogTableRows.forEach(row => {
            const productName = row.querySelector('.product-name').textContent.toLowerCase();
            const productSku = row.querySelector('.product-sku').textContent.toLowerCase();
            
            const isVisible = term === '' || 
                             productName.includes(term) || 
                             productSku.includes(term);
            
            row.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        });
        
        // Show/hide no results message
        if (noResultsDiv) {
            noResultsDiv.style.display = (term !== '' && visibleCount === 0) ? 'flex' : 'none';
        }
        
        // Update search results count
        if (term === '') {
            searchResultsSpan.textContent = 'All products';
        } else {
            searchResultsSpan.textContent = `${visibleCount} product${visibleCount !== 1 ? 's' : ''} found`;
        }
        
        // Show/hide clear button
        clearSearchBtn.style.display = term === '' ? 'none' : 'block';
        
        // Update selection after filtering
        updateSelection();
    }

    // Update selected count and button state
    function updateSelection() {
        // Only count visible checkboxes
        const visibleCheckboxes = Array.from(productCheckboxes).filter(checkbox => {
            return checkbox.closest('tr').style.display !== 'none';
        });
        
        const checkedBoxes = visibleCheckboxes.filter(checkbox => checkbox.checked);
        const count = checkedBoxes.length;
        
        selectedCountSpan.textContent = count;
        addToCartBtn.disabled = count === 0;
        
        // Update select all checkbox state based on visible rows only
        if (count === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        } else if (count === visibleCheckboxes.length) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else {
            selectAllCheckbox.indeterminate = true;
        }
        
        // Update select all button text
        selectAllBtn.innerHTML = count === visibleCheckboxes.length && visibleCheckboxes.length > 0 ? 
            '<i class="fas fa-square"></i> Deselect All' : 
            '<i class="fas fa-check-square"></i> Select All';
    }
    
    // Handle individual checkbox changes
    productCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelection);
    });
    
    // Handle select all checkbox (only for visible products)
    selectAllCheckbox.addEventListener('change', function() {
        const isChecked = this.checked;
        const visibleCheckboxes = Array.from(productCheckboxes).filter(checkbox => {
            return checkbox.closest('tr').style.display !== 'none';
        });
        
        visibleCheckboxes.forEach(checkbox => {
            checkbox.checked = isChecked;
        });
        updateSelection();
    });
    
    // Handle select all button (only for visible products)
    selectAllBtn.addEventListener('click', function() {
        const visibleCheckboxes = Array.from(productCheckboxes).filter(checkbox => {
            return checkbox.closest('tr').style.display !== 'none';
        });
        const checkedVisibleCount = visibleCheckboxes.filter(checkbox => checkbox.checked).length;
        const shouldCheck = checkedVisibleCount !== visibleCheckboxes.length;
        
        visibleCheckboxes.forEach(checkbox => {
            checkbox.checked = shouldCheck;
        });
        updateSelection();
    });
    
    // Search functionality event listeners
    searchInput.addEventListener('input', function() {
        filterProducts(this.value);
    });
    
    clearSearchBtn.addEventListener('click', function() {
        searchInput.value = '';
        filterProducts('');
        searchInput.focus();
    });
    
    // Enter key support for search
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            this.value = '';
            filterProducts('');
        }
    });
    
    // Handle add to cart button
    addToCartBtn.addEventListener('click', function() {
        const checkedBoxes = document.querySelectorAll('.product-checkbox:checked');
        
        if (checkedBoxes.length === 0) {
            alert('Please select at least one product to add to cart.');
            return;
        }
        
        // Collect selected products
        const selectedProducts = Array.from(checkedBoxes).map(checkbox => ({
            id: checkbox.dataset.productId,
            name: checkbox.dataset.productName,
            price: parseFloat(checkbox.dataset.productPrice)
        }));
        
        // Show confirmation
        const productNames = selectedProducts.map(p => p.name).join('\n• ');
        const totalPrice = selectedProducts.reduce((sum, p) => sum + p.price, 0);
        
        const message = `Add ${selectedProducts.length} product(s) to cart?\n\n• ${productNames}\n\nTotal: KSH ${totalPrice.toLocaleString()}`;
        
        if (confirm(message)) {
            // Create form and submit to cart
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'shop/cart';
            
            selectedProducts.forEach(product => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'products[]';
                input.value = product.id;
                form.appendChild(input);
            });
            
            // Add action input
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'add_multiple';
            form.appendChild(actionInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    });
    
    // Initialize
    updateSelection();
    
    // Make filterProducts available globally for no-results button
    window.filterProducts = filterProducts;
});
</script>

<?php include 'includes/footer.php'; ?> 