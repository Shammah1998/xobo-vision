<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isAdmin($pdo)) {
    header('Location: /xobo-c/index.php');
    exit;
}

$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
if ($productId <= 0 || $companyId <= 0) {
    echo '<div class="admin-card"><div class="alert alert-error">Invalid product or company ID.</div></div>';
    include 'includes/admin_footer.php';
    exit;
}

// Fetch product
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND company_id = ?');
$stmt->execute([$productId, $companyId]);
$product = $stmt->fetch();
if (!$product) {
    echo '<div class="admin-card"><div class="alert alert-error">Product not found.</div></div>';
    include 'includes/admin_footer.php';
    exit;
}

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $weight = (float)($_POST['weight_kg'] ?? 0);
    $price = (float)($_POST['rate_ksh'] ?? 0);
    if ($name && $sku && $weight > 0 && $price > 0) {
        $stmt = $pdo->prepare('UPDATE products SET name = ?, sku = ?, weight_kg = ?, rate_ksh = ? WHERE id = ? AND company_id = ?');
        if ($stmt->execute([$name, $sku, $weight, $price, $productId, $companyId])) {
            header('Location: company-products.php?company_id=' . $companyId . '&msg=updated');
            exit;
        } else {
            $error = 'Failed to update product.';
        }
    } else {
        $error = 'All fields are required and must be valid.';
    }
}
$pageTitle = 'Edit Product';
include 'includes/admin_header.php';
?>
<div class="admin-card" style="margin: 2rem 0 0 2rem; max-width: 700px; width: 100%; box-sizing: border-box; padding: 1.2rem 2rem 1.2rem 2rem;">
    <h2 style="color: var(--xobo-primary); margin-bottom: 1.2rem;">Edit Product</h2>
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST" style="display: flex; flex-direction: column; gap: 1rem; max-width: 600px;">
        <div class="form-group">
            <label for="name" style="font-weight: 600; color: var(--xobo-primary);">Product Name</label>
            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($product['name']); ?>" required style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        <div class="form-group">
            <label for="sku" style="font-weight: 600; color: var(--xobo-primary);">SKU</label>
            <input type="text" name="sku" id="sku" value="<?php echo htmlspecialchars($product['sku']); ?>" required style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        <div class="form-group">
            <label for="weight_kg" style="font-weight: 600; color: var(--xobo-primary);">Weight (kg)</label>
            <input type="number" name="weight_kg" id="weight_kg" step="0.01" min="0.01" value="<?php echo htmlspecialchars($product['weight_kg']); ?>" required style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        <div class="form-group">
            <label for="rate_ksh" style="font-weight: 600; color: var(--xobo-primary);">Price (KSH)</label>
            <input type="number" name="rate_ksh" id="rate_ksh" step="0.01" min="0.01" value="<?php echo htmlspecialchars($product['rate_ksh']); ?>" required style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        <div style="display: flex; gap: 1rem; justify-content: flex-end;">
            <a href="company-products.php?company_id=<?php echo $companyId; ?>" class="btn btn-secondary">Back</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>
<?php include 'includes/admin_footer.php'; ?> 