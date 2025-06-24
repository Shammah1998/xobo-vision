<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

requireRole(['user']);

$companyId = $_SESSION['company_id'];
$userId = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle cart updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_cart'])) {
        foreach ($_POST['quantities'] as $productId => $quantity) {
            $productId = (int)$productId;
            $quantity = (int)$quantity;
            
            if ($quantity <= 0) {
                unset($_SESSION['cart'][$productId]);
            } else {
                $_SESSION['cart'][$productId] = $quantity;
            }
        }
        $message = 'Cart updated successfully!';
    } elseif (isset($_POST['remove_item'])) {
        $productId = (int)$_POST['product_id'];
        unset($_SESSION['cart'][$productId]);
        $message = 'Item removed from cart!';
    } elseif (isset($_POST['delete_selected'])) {
        if (isset($_POST['selected_items']) && !empty($_POST['selected_items'])) {
            $deletedCount = 0;
            foreach ($_POST['selected_items'] as $productId) {
                $productId = (int)$productId;
                if (isset($_SESSION['cart'][$productId])) {
                    unset($_SESSION['cart'][$productId]);
                    $deletedCount++;
                }
            }
            if ($deletedCount > 0) {
                $message = "Successfully removed {$deletedCount} item(s) from your cart!";
            }
        } else {
            $error = "You haven't selected any item";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'add_multiple' && isset($_POST['products'])) {
        // Handle bulk adding of products from index.php
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        $addedCount = 0;
        foreach ($_POST['products'] as $productId) {
            $productId = (int)$productId;
            if ($productId > 0) {
                // Verify product belongs to user's company
                $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND company_id = ?");
                $stmt->execute([$productId, $companyId]);
                if ($stmt->fetch()) {
                    $_SESSION['cart'][$productId] = ($_SESSION['cart'][$productId] ?? 0) + 1;
                    $addedCount++;
                }
            }
        }
        
        if ($addedCount > 0) {
            $message = "Successfully added {$addedCount} product(s) to your cart!";
        } else {
            $error = "No valid products were added to cart.";
        }
    }
}

// Get company information
$stmt = $pdo->prepare("SELECT name FROM companies WHERE id = ? AND status = 'approved'");
$stmt->execute([$companyId]);
$company = $stmt->fetch();
$companyName = $company ? $company['name'] : 'XOBO MART';

// Get cart items with product details
$cartItems = [];
$totalWeight = 0;
$totalCost = 0;
$totalItems = 0;

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $productIds = array_keys($_SESSION['cart']);
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND company_id = ?");
    $stmt->execute(array_merge($productIds, [$companyId]));
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($products as $product) {
        $quantity = $_SESSION['cart'][$product['id']];
        $lineTotal = $quantity * $product['rate_ksh'];
        $lineWeight = $quantity * $product['weight_kg'];
        
        $cartItems[] = [
            'product' => $product,
            'quantity' => $quantity,
            'line_total' => $lineTotal,
            'line_weight' => $lineWeight
        ];
        
        $totalCost += $lineTotal;
        $totalWeight += $lineWeight;
        $totalItems += $quantity;
    }
}

$pageTitle = 'Shopping Cart - ' . $companyName;
include '../includes/header.php';
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

.cart-header {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 10px var(--xobo-shadow);
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.cart-header h2 {
    color: var(--xobo-primary);
    font-size: 1.8rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.cart-totals {
    background: var(--xobo-light-gray);
    border-top: 2px solid var(--xobo-border);
    padding: 1.5rem;
}

.totals-content {
    max-width: 400px;
    margin-left: auto;
}

.total-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
}

.total-row.grand-total {
    border-top: 2px solid var(--xobo-border);
    padding-top: 0.75rem;
    margin-top: 0.75rem;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--xobo-primary);
}

.empty-cart {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px var(--xobo-shadow);
    color: var(--xobo-gray);
}

.empty-cart i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
    color: var(--xobo-primary);
}

.empty-cart h3 {
    color: var(--xobo-primary);
    margin-bottom: 1rem;
    font-size: 1.5rem;
}

.empty-cart p {
    margin-bottom: 2rem;
}

.cart-section {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px var(--xobo-shadow);
    margin-bottom: 2rem;
    overflow: hidden;
}

.cart-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--xobo-border);
    background: #f8f9fa;
    flex-wrap: wrap;
    gap: 1rem;
}

.cart-controls-left {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.select-all-container {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
    cursor: pointer;
    color: var(--xobo-primary);
}

.cart-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.cart-table-container {
    overflow-x: auto;
}

.cart-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
    min-width: 1000px;
    font-size: 0.9rem;
}

.cart-table th {
    background: #f8f9fa;
    padding: 0.75rem 0.5rem;
    text-align: left;
    font-weight: 600;
    color: var(--xobo-primary);
    border-bottom: 2px solid var(--xobo-border);
    font-size: 0.85rem;
}

.cart-table td {
    padding: 0.75rem 0.5rem;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
    font-size: 0.9rem;
}

.cart-table tbody tr:hover {
    background: #f8f9fa;
}

.cart-table tbody tr:has(.cart-checkbox:checked) {
    background: #e3f2fd;
}

.item-number {
    font-weight: 600;
    color: var(--xobo-gray);
    text-align: center;
    width: 40px;
    font-size: 0.85rem;
}

.product-name {
    color: var(--xobo-primary);
    font-weight: 600;
}

.product-sku {
    color: var(--xobo-gray);
    font-family: monospace;
    background: var(--xobo-light-gray);
    padding: 0.2rem 0.4rem;
    border-radius: 3px;
    font-size: 0.75rem;
}

.product-weight {
    color: var(--xobo-gray);
    text-align: center;
}

.unit-price, .line-total {
    text-align: right;
    font-weight: 600;
    color: var(--xobo-primary);
}

.line-total {
    color: var(--xobo-primary);
    font-size: 1rem;
    font-weight: 600;
}

.quantity-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--xobo-border);
    border-radius: 4px;
    background: white;
    overflow: hidden;
    width: fit-content;
    margin: 0 auto;
}

.qty-btn {
    background: var(--xobo-light-gray);
    border: none;
    padding: 0.4rem 0.6rem;
    cursor: pointer;
    font-size: 0.9rem;
    color: var(--xobo-primary);
    transition: background 0.3s;
    font-weight: bold;
}

.qty-btn:hover {
    background: #e9ecef;
    color: var(--xobo-primary-hover);
}

.qty-input {
    border: none;
    text-align: center;
    width: 50px;
    padding: 0.4rem 0.2rem;
    font-size: 0.9rem;
    font-weight: 500;
    outline: none;
    background: white;
}

.qty-input:focus {
    background: var(--xobo-light-gray);
}



.cart-form-actions {
    padding: 1.5rem;
    background: var(--xobo-light-gray);
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    flex-wrap: wrap;
}



.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    font-weight: 600;
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

.btn-danger {
    background: var(--xobo-accent);
    color: white;
    border-radius: 3px;
}

.btn-danger:hover {
    background: #dc2626;
}

.btn-outline {
    background: transparent;
    color: var(--xobo-gray);
    border: 1px solid var(--xobo-border);
}

.btn-outline:hover {
    background: var(--xobo-gray);
    color: white;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 4px;
    margin-bottom: 1rem;
    font-weight: 500;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.cart-table-container::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.cart-table-container::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.cart-table-container::-webkit-scrollbar-thumb {
    background: var(--xobo-primary);
    border-radius: 3px;
}

.cart-table-container::-webkit-scrollbar-thumb:hover {
    background: var(--xobo-primary-hover);
}

@media (max-width: 768px) {
    .cart-header {
        text-align: center;
    }
    
    .cart-controls {
        flex-direction: column;
        text-align: center;
    }
    
    .totals-content {
        max-width: 100%;
        margin: 0;
    }
    
    .cart-table {
        min-width: 800px;
    }
    
    .cart-table th,
    .cart-table td {
        padding: 0.5rem 0.3rem;
        font-size: 0.8rem;
    }
    
    .cart-form-actions {
        flex-direction: column;
    }
    
    .company-header h1 {
        font-size: 2rem;
    }
}

@media (max-width: 480px) {
    .company-header {
        padding: 1.5rem 0;
    }
    
    .company-header h1 {
        font-size: 1.8rem;
    }
    
    .cart-header {
        padding: 1rem;
    }
    
    .cart-table {
        min-width: 700px;
    }
}
</style>

<!-- Company Header -->
<div class="company-header">
    <div class="container">
        <h1><?php echo htmlspecialchars($companyName); ?></h1>
        <p>Review your cart and proceed to checkout</p>
    </div>
</div>

<div class="container">
    <!-- Success/Error Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Cart Header -->
    <div class="cart-header">
        <h2><i class="fas fa-shopping-cart"></i> Shopping Cart</h2>
    </div>

    <?php if (empty($cartItems)): ?>
        <div class="empty-cart">
            <i class="fas fa-shopping-cart"></i>
            <h3>Your cart is empty</h3>
            <p>Browse your company's catalog and add products to your cart!</p>
            <a href="../index.php" class="btn btn-primary">
                <i class="fas fa-box-open"></i> Browse Catalog
            </a>
        </div>
    <?php else: ?>
        
        <!-- Cart Section -->
        <div class="cart-section">
            <!-- Cart Controls -->
            <div class="cart-controls">
                <div class="cart-controls-left">
                    <label class="select-all-container">
                        <input type="checkbox" id="select-all" class="cart-checkbox"> Select All
                    </label>
                </div>
                <div class="cart-controls-right">
                    <button type="button" id="delete-selected-btn" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>

            <!-- Cart Table -->
            <div class="cart-table-container">
                <form method="POST" id="cart-form">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th width="40">
                                    <input type="checkbox" id="select-all-header" class="cart-checkbox">
                                </th>
                                <th width="40">#</th>
                                <th>Product Name</th>
                                <th width="100">SKU</th>
                                <th width="80">Weight (kg)</th>
                                <th width="100">Unit Price</th>
                                <th width="100">Quantity</th>
                                <th width="100">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $index => $item): ?>
                            <tr class="cart-item-row">
                                <td>
                                    <input type="checkbox" name="selected_items[]" 
                                           value="<?php echo $item['product']['id']; ?>" class="item-checkbox cart-checkbox">
                                </td>
                                <td class="item-number"><?php echo $index + 1; ?></td>
                                <td class="product-name">
                                    <?php echo htmlspecialchars($item['product']['name']); ?>
                                </td>
                                <td>
                                    <span class="product-sku"><?php echo htmlspecialchars($item['product']['sku']); ?></span>
                                </td>
                                <td class="product-weight">
                                    <?php echo number_format($item['product']['weight_kg'], 2); ?>
                                </td>
                                <td class="unit-price">
                                    <?php echo formatCurrency($item['product']['rate_ksh']); ?>
                                </td>
                                <td class="quantity-cell">
                                    <div class="quantity-controls">
                                        <button type="button" class="qty-btn qty-minus" 
                                                onclick="updateQuantity(<?php echo $item['product']['id']; ?>, -1)">-</button>
                                        <input type="number" name="quantities[<?php echo $item['product']['id']; ?>]" 
                                               value="<?php echo $item['quantity']; ?>" 
                                               min="1" max="999" class="qty-input"
                                               id="qty_<?php echo $item['product']['id']; ?>">
                                        <button type="button" class="qty-btn qty-plus" 
                                                onclick="updateQuantity(<?php echo $item['product']['id']; ?>, 1)">+</button>
                                    </div>
                                </td>
                                <td class="line-total">
                                    <?php echo formatCurrency($item['line_total']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Cart Totals -->
                    <div class="cart-totals">
                        <div class="totals-content">
                            <div class="total-row">
                                <span>Total Items:</span>
                                <span id="total-items"><?php echo $totalItems; ?> items</span>
                            </div>
                            <div class="total-row">
                                <span>Total Weight:</span>
                                <span id="total-weight"><?php echo number_format($totalWeight, 2); ?> kg</span>
                            </div>
                            <div class="total-row grand-total">
                                <span>Total Amount:</span>
                                <span id="total-amount"><?php echo formatCurrency($totalCost); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="cart-form-actions">
                        <a href="../index.php" class="btn btn-secondary">
                            <i class="fas fa-home"></i> Home
                        </a>
                        <button type="submit" name="update_cart" class="btn btn-primary">
                            <i class="fas fa-check"></i> Confirm
                        </button>
                    </div>
                </form>
            </div>
        </div>


    <?php endif; ?>
</div>

<script>
// Quantity update function with automatic price calculation
function updateQuantity(productId, change) {
    const input = document.getElementById('qty_' + productId);
    let currentValue = parseInt(input.value) || 1;
    let newValue = currentValue + change;
    
    if (newValue < 1) newValue = 1;
    if (newValue > 999) newValue = 999;
    
    input.value = newValue;
    
    // Update prices automatically
    updateLineTotalAndCart(productId, newValue);
}

// Function to update line total and cart totals
function updateLineTotalAndCart(productId, quantity) {
    // Get the unit price from the row
    const row = document.querySelector(`input[name="quantities[${productId}]"]`).closest('tr');
    const unitPriceText = row.querySelector('.unit-price').textContent;
    const unitPrice = parseFloat(unitPriceText.replace(/[^\d.]/g, ''));
    
    // Calculate new line total
    const lineTotal = unitPrice * quantity;
    
    // Update the line total display
    const lineTotalCell = row.querySelector('.line-total');
    lineTotalCell.textContent = formatCurrency(lineTotal);
    
    // Update cart totals in header
    updateCartTotals();
}

// Function to calculate and update cart totals
function updateCartTotals() {
    let totalItems = 0;
    let totalAmount = 0;
    let totalWeight = 0;
    
    // Calculate totals from all quantity inputs
    document.querySelectorAll('input[name^="quantities["]').forEach(input => {
        const quantity = parseInt(input.value) || 0;
        const row = input.closest('tr');
        const unitPriceText = row.querySelector('.unit-price').textContent;
        const unitPrice = parseFloat(unitPriceText.replace(/[^\d.]/g, ''));
        
        // Get weight from the product weight cell
        const weightText = row.querySelector('.product-weight').textContent;
        const productWeight = parseFloat(weightText) || 0;
        
        totalItems += quantity;
        totalAmount += (unitPrice * quantity);
        totalWeight += (productWeight * quantity);
    });
    
    // Update total elements at bottom
    const totalItemsElement = document.getElementById('total-items');
    const totalWeightElement = document.getElementById('total-weight');
    const totalAmountElement = document.getElementById('total-amount');
    
    if (totalItemsElement) {
        totalItemsElement.textContent = `${totalItems} items`;
    }
    if (totalWeightElement) {
        totalWeightElement.textContent = `${totalWeight.toFixed(2)} kg`;
    }
    if (totalAmountElement) {
        totalAmountElement.textContent = formatCurrency(totalAmount);
    }
}

// Format currency helper function
function formatCurrency(amount) {
    return 'KSH ' + new Intl.NumberFormat('en-KE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

// Select all and delete functionality
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckboxes = document.querySelectorAll('#select-all, #select-all-header');
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    const deleteBtn = document.getElementById('delete-selected-btn');
    const cartForm = document.getElementById('cart-form');
    
    // Handle select all
    selectAllCheckboxes.forEach(selectAll => {
        selectAll.addEventListener('change', function() {
            const isChecked = this.checked;
            itemCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            // Sync both select all checkboxes
            selectAllCheckboxes.forEach(cb => cb.checked = isChecked);
        });
    });
    
    // Handle individual checkboxes
    itemCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const checkedCount = document.querySelectorAll('.item-checkbox:checked').length;
            const totalCount = itemCheckboxes.length;
            
            selectAllCheckboxes.forEach(selectAll => {
                selectAll.checked = checkedCount === totalCount;
                selectAll.indeterminate = checkedCount > 0 && checkedCount < totalCount;
            });
        });
    });
    
    // Handle delete selected items
    deleteBtn.addEventListener('click', function() {
        const selectedItems = document.querySelectorAll('.item-checkbox:checked');
        
        if (selectedItems.length === 0) {
            alert("You haven't selected any item");
            return;
        }
        
        const itemCount = selectedItems.length;
        const confirmMessage = itemCount === 1 ? 
            'Are you sure you want to delete this item?' : 
            `Are you sure you want to delete these ${itemCount} items?`;
        
        if (confirm(confirmMessage)) {
            // Create hidden input for delete action
            const deleteInput = document.createElement('input');
            deleteInput.type = 'hidden';
            deleteInput.name = 'delete_selected';
            deleteInput.value = '1';
            cartForm.appendChild(deleteInput);
            
            // Submit the form
            cartForm.submit();
        }
    });
    
    // Add event listeners for manual quantity input changes
    document.querySelectorAll('input[name^="quantities["]').forEach(input => {
        input.addEventListener('input', function() {
            const productId = this.name.match(/\[(\d+)\]/)[1];
            const quantity = parseInt(this.value) || 1;
            
            // Validate quantity
            if (quantity < 1) {
                this.value = 1;
                updateLineTotalAndCart(productId, 1);
            } else if (quantity > 999) {
                this.value = 999;
                updateLineTotalAndCart(productId, 999);
            } else {
                updateLineTotalAndCart(productId, quantity);
            }
        });
        
        input.addEventListener('blur', function() {
            // Ensure minimum value on blur
            if (this.value === '' || parseInt(this.value) < 1) {
                this.value = 1;
                const productId = this.name.match(/\[(\d+)\]/)[1];
                updateLineTotalAndCart(productId, 1);
            }
        });
    });
});



// Add animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../includes/footer.php'; ?> 