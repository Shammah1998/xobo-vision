<?php
require_once '../config/config.php';
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isAdmin($pdo)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Handle search
$search = trim($_GET['search'] ?? '');
$where = '';
$params = [];
if ($search !== '') {
    $where = "WHERE p.id = ? OR p.name LIKE ? OR p.sku LIKE ?";
    $params[] = $search;
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Fetch all products with company name
$sql = 'SELECT p.*, c.name as company_name FROM products p JOIN companies c ON p.company_id = c.id ' . $where . ' ORDER BY p.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$pageTitle = 'All Products';
include 'includes/admin_header.php';
?>
<div class="admin-card">
    <h2 style="color: var(--xobo-primary); margin-bottom: 1.5rem;">All Products</h2>
    <form method="get" style="margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: center;">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by Product ID, Name, or SKU" style="padding: 0.6rem 1rem; border: 1px solid #ccc; border-radius: 4px; min-width: 220px;">
        <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.2rem;">Search</button>
        <?php if ($search !== ''): ?>
            <a href="all-products.php" class="btn btn-secondary" style="padding: 0.6rem 1.2rem;">Clear</a>
        <?php endif; ?>
    </form>
    <table class="data-table" style="width:100%;">
        <thead>
            <tr style="background:#f0f0f0;">
                <th style="padding:0.5rem; text-align:left;">Product ID</th>
                <th style="padding:0.5rem; text-align:left;">Name</th>
                <th style="padding:0.5rem; text-align:left;">SKU</th>
                <th style="padding:0.5rem; text-align:right;">Weight (kg)</th>
                <th style="padding:0.5rem; text-align:right;">Price (KSH)</th>
                <th style="padding:0.5rem; text-align:left;">Company</th>
                <th style="padding:0.5rem; text-align:center;">Edit</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($products as $product): ?>
            <tr>
                <td style="padding:0.5rem;">#<?php echo htmlspecialchars($product['id']); ?></td>
                <td style="padding:0.5rem;"><?php echo htmlspecialchars($product['name']); ?></td>
                <td style="padding:0.5rem;"><?php echo htmlspecialchars($product['sku']); ?></td>
                <td style="padding:0.5rem; text-align:right;"><?php echo htmlspecialchars($product['weight_kg']); ?></td>
                <td style="padding:0.5rem; text-align:right;"><?php echo number_format($product['rate_ksh'], 2); ?></td>
                <td style="padding:0.5rem;"><?php echo htmlspecialchars($product['company_name']); ?></td>
                <td style="padding:0.5rem; text-align:center;">
                    <a href="edit-product.php?product_id=<?php echo $product['id']; ?>&company_id=<?php echo $product['company_id']; ?>" class="btn btn-primary btn-sm" title="Edit Product" style="padding: 0.4rem 0.7rem; min-width: 32px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-pen"></i>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include 'includes/admin_footer.php'; ?> 