<?php
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?error=Session expired, please log in again.');
    exit;
}
require_once '../config/db.php';
require_once '../includes/functions.php';

requireRole(['company_admin']);

$companyId = $_SESSION['company_id'];
$message = '';
$error = '';

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_product'])) {
        $name = sanitize($_POST['name']);
        $sku = sanitize($_POST['sku']);
        $weight = (float)$_POST['weight_kg'];
        $rate = (float)$_POST['rate_ksh'];
        
        if (empty($name) || empty($sku) || $weight <= 0 || $rate < 0) {
            $error = 'Please fill all fields with valid values.';
        } else {
            try {
                // Check if SKU already exists for this company
                $stmt = $pdo->prepare("SELECT id FROM products WHERE company_id = ? AND sku = ?");
                $stmt->execute([$companyId, $sku]);
                if ($stmt->fetch()) {
                    $error = 'SKU already exists for your company.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO products (company_id, name, sku, weight_kg, rate_ksh) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$companyId, $name, $sku, $weight, $rate]);
                    $message = 'Product added successfully.';
                }
            } catch (PDOException $e) {
                $error = 'Failed to add product.';
            }
        }
    } elseif (isset($_POST['delete_product'])) {
        $productId = (int)$_POST['product_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND company_id = ?");
            $stmt->execute([$productId, $companyId]);
            $message = 'Product deleted successfully.';
        } catch (PDOException $e) {
            $error = 'Failed to delete product.';
        }
    }
}

// Get company products
$stmt = $pdo->prepare("SELECT * FROM products WHERE company_id = ? ORDER BY created_at DESC");
$stmt->execute([$companyId]);
$products = $stmt->fetchAll();

// Get company name
$companyName = getCompanyName($pdo, $companyId);

$pageTitle = 'Products - ' . $companyName;
include '../includes/header.php';
?>

<h1>Product Management - <?php echo htmlspecialchars($companyName); ?></h1>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="products-section">
    <div class="section-header">
        <h2>Add New Product</h2>
    </div>
    
    <form method="POST" class="product-form">
        <div class="form-row">
            <div class="form-group">
                <label for="name">Product Name:</label>
                <input type="text" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="sku">SKU:</label>
                <input type="text" id="sku" name="sku" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="weight_kg">Weight (kg):</label>
                <input type="number" id="weight_kg" name="weight_kg" step="0.01" min="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="rate_ksh">Rate (KSH):</label>
                <input type="number" id="rate_ksh" name="rate_ksh" step="0.01" min="0" required>
            </div>
        </div>
        
        <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
    </form>
</div>

<div class="products-list">
    <h2>Current Products</h2>
    
    <?php if (empty($products)): ?>
        <p class="no-data">No products added yet.</p>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>SKU</th>
                        <th>Weight (kg)</th>
                        <th>Rate (KSH)</th>
                        <th>Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['sku']); ?></td>
                        <td><?php echo number_format($product['weight_kg'], 2); ?></td>
                        <td><?php echo formatCurrency($product['rate_ksh']); ?></td>
                        <td><?php echo date('M j, Y', strtotime($product['created_at'])); ?></td>
                        <td>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this product?')">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <button type="submit" name="delete_product" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?> 