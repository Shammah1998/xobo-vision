<?php
// admin/company-products.php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isAdmin($pdo)) {
    header('Location: /xobo-c/index');
    exit;
}

$companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
if ($companyId <= 0) {
    echo '<div class="admin-card"><div class="alert alert-error">Invalid company ID.</div></div>';
    include 'includes/admin_footer.php';
    exit;
}

// Fetch company info
$stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
$stmt->execute([$companyId]);
$company = $stmt->fetch();
if (!$company) {
    echo '<div class="admin-card"><div class="alert alert-error">Company not found.</div></div>';
    include 'includes/admin_footer.php';
    exit;
}

// Handle product deletion
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product_id'])) {
    $productId = (int)$_POST['delete_product_id'];
    $stmt = $pdo->prepare('DELETE FROM products WHERE id = ? AND company_id = ?');
    if ($stmt->execute([$productId, $companyId])) {
        $message = 'Product deleted successfully.';
    } else {
        $error = 'Failed to delete product.';
    }
}

// Fetch products for this company
$stmt = $pdo->prepare('SELECT * FROM products WHERE company_id = ? ORDER BY id DESC');
$stmt->execute([$companyId]);
$products = $stmt->fetchAll();

$pageTitle = 'Product Catalog - ' . htmlspecialchars($company['name']);
include 'includes/admin_header.php';
?>
<div class="admin-card">
    <h2 style="color: var(--xobo-primary); margin-bottom: 1.5rem;">Product Catalog for <?php echo htmlspecialchars($company['name']); ?></h2>
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <a href="add-product?company_id=<?php echo $companyId; ?>" class="btn btn-primary" style="margin-bottom: 1.2rem;">
        <i class="fas fa-plus"></i> Add Product
    </a>
    <table class="data-table" style="width:100%;">
        <thead>
            <tr style="background:#f0f0f0;">
                <th style="padding:0.5rem; text-align:left;">Product ID</th>
                <th style="padding:0.5rem; text-align:left;">Name</th>
                <th style="padding:0.5rem; text-align:left;">SKU</th>
                <th style="padding:0.5rem; text-align:right;">Weight (kg)</th>
                <th style="padding:0.5rem; text-align:right;">Price (KSH)</th>
                <th style="padding:0.5rem; text-align:center;">Actions</th>
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
                <td style="padding:0.5rem; text-align:center;">
                    <a href="edit-product?product_id=<?php echo $product['id']; ?>&company_id=<?php echo $companyId; ?>" class="btn btn-primary btn-sm" title="Edit Product" style="padding: 0.4rem 0.7rem; min-width: 32px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-pen"></i>
                    </a>
                    <form method="POST" style="display:inline-block; margin:0;" onsubmit="return confirm('Delete this product?');">
                        <input type="hidden" name="delete_product_id" value="<?php echo $product['id']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm" title="Delete Product" style="padding: 0.4rem 0.7rem; min-width: 32px; display: inline-flex; align-items: center; justify-content: center;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include 'includes/admin_footer.php'; ?> 