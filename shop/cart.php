<?php
require_once '../config/config.php';
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?error=Session expired, please log in again.');
    exit;
}
require_once '../config/db.php';
require_once '../includes/functions.php';

requireRole(['user']);

$companyId = $_SESSION['company_id'];
$userId = $_SESSION['user_id'];
$message = '';
$error = '';

// Add this at the top, after session_start():
if (isset($_POST['ajax']) && $_POST['ajax'] === 'update_quantity') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    if ($productId > 0 && $quantity > 0) {
        $_SESSION['cart'][$productId] = $quantity;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// Handle cart updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_item'])) {
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
                    // Also remove delivery details for this product
                    $stmt = $pdo->prepare("DELETE FROM delivery_details WHERE user_id = ? AND product_id = ? AND session_id = ?");
                    $stmt->execute([$userId, $productId, session_id()]);
                    $deletedCount++;
                }
            }
            if ($deletedCount > 0) {
                $message = "Successfully removed {$deletedCount} item(s) from your cart!";
            }
        } else {
            $error = "You haven't selected any item";
        }
    } elseif (isset($_POST['save_delivery_details'])) {
        // Handle saving delivery details
        $productId = (int)$_POST['product_id'];
        $destination = sanitize($_POST['destination'] ?? '');
        $companyName = sanitize($_POST['company_name'] ?? '');
        $companyAddress = sanitize($_POST['company_address'] ?? '');
        $recipientName = sanitize($_POST['recipient_name'] ?? '');
        $recipientPhone = sanitize($_POST['recipient_phone'] ?? '');
        
        // Require both destination and company name
        if (!empty($destination) && !empty($companyName)) {
            $stmt = $pdo->prepare("INSERT INTO delivery_details (user_id, product_id, session_id, destination, company_name, company_address, recipient_name, recipient_phone) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE 
                                   destination = VALUES(destination), 
                                   company_name = VALUES(company_name), 
                                   company_address = VALUES(company_address), 
                                   recipient_name = VALUES(recipient_name), 
                                   recipient_phone = VALUES(recipient_phone),
                                   updated_at = CURRENT_TIMESTAMP");
            $stmt->execute([$userId, $productId, session_id(), $destination, $companyName, $companyAddress, $recipientName, $recipientPhone]);
            $message = "Delivery details saved successfully!";
        } else if (empty($destination) && empty($companyName) && empty($companyAddress) && empty($recipientName) && empty($recipientPhone)) {
            // Delete if all fields are empty
            $stmt = $pdo->prepare("DELETE FROM delivery_details WHERE user_id = ? AND product_id = ? AND session_id = ?");
            $stmt->execute([$userId, $productId, session_id()]);
            $message = "Delivery details cleared!";
        } else {
            $error = "Please fill in both Destination and Company Name.";
        }
    } elseif (isset($_POST['delete_delivery_details'])) {
        // Handle deleting delivery details
        $productId = (int)$_POST['product_id'];
        $stmt = $pdo->prepare("DELETE FROM delivery_details WHERE user_id = ? AND product_id = ? AND session_id = ?");
        $stmt->execute([$userId, $productId, session_id()]);
        $message = "Delivery details deleted successfully!";
    } elseif (isset($_POST['confirm_order'])) {
        // Handle order confirmation - save everything to database
        if (empty($_SESSION['cart'])) {
            $error = "Your cart is empty!";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Calculate total cost
                $productIds = array_keys($_SESSION['cart']);
                $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
                
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND company_id = ?");
                $stmt->execute(array_merge($productIds, [$companyId]));
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $totalCost = 0;
                $orderItems = [];
                
                foreach ($products as $product) {
                    $quantity = $_SESSION['cart'][$product['id']];
                    $lineTotal = $quantity * $product['rate_ksh'];
                    $totalCost += $lineTotal;
                    
                    $orderItems[] = [
                        'product_id' => $product['id'],
                        'quantity' => $quantity,
                        'line_total' => $lineTotal
                    ];
                }
                
                // Insert main order
                $stmt = $pdo->prepare("INSERT INTO orders (user_id, company_id, total_ksh, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$userId, $companyId, $totalCost]);
                $orderId = $pdo->lastInsertId();
                
                // Insert vehicle type for this order
                $vehicleType = $_POST['vehicle_type'] ?? null;
                if ($vehicleType && $orderId) {
                    $stmt = $pdo->prepare("INSERT INTO order_vehicle_types (order_id, vehicle_type) VALUES (?, ?)");
                    $stmt->execute([$orderId, $vehicleType]);
                }
                
                // Insert order items
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, line_total) VALUES (?, ?, ?, ?)");
                foreach ($orderItems as $item) {
                    $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['line_total']]);
                }
                
                // Get delivery details and save them to order_delivery_details
                $stmt = $pdo->prepare("SELECT * FROM delivery_details WHERE user_id = ? AND product_id IN ($placeholders) AND session_id = ?");
                $stmt->execute(array_merge([$userId], $productIds, [session_id()]));
                $deliveryDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Insert delivery details for the order
                $stmt = $pdo->prepare("INSERT INTO order_delivery_details (order_id, product_id, destination, company_name, company_address, recipient_name, recipient_phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($deliveryDetails as $detail) {
                    $stmt->execute([
                        $orderId,
                        $detail['product_id'],
                        $detail['destination'],
                        $detail['company_name'],
                        $detail['company_address'],
                        $detail['recipient_name'],
                        $detail['recipient_phone']
                    ]);
                }
                
                // Clean up cart and delivery details
                $_SESSION['cart'] = [];
                $stmt = $pdo->prepare("DELETE FROM delivery_details WHERE user_id = ? AND session_id = ?");
                $stmt->execute([$userId, session_id()]);
                
                $pdo->commit();
                
                // Redirect to receipt page
                header("Location: order-receipt?order_id=" . $orderId);
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to process order. Please try again. Error: " . $e->getMessage();
            }
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

// Get cart items with product details and delivery details
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
    
    // Get delivery details for all products in cart
    $deliveryDetails = [];
    if (!empty($productIds)) {
        $stmt = $pdo->prepare("SELECT * FROM delivery_details WHERE user_id = ? AND product_id IN ($placeholders) AND session_id = ?");
        $stmt->execute(array_merge([$userId], $productIds, [session_id()]));
        $deliveryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($deliveryData as $detail) {
            $deliveryDetails[$detail['product_id']] = $detail;
        }
    }
    
    foreach ($products as $product) {
        $quantity = $_SESSION['cart'][$product['id']];
        $lineTotal = $quantity * $product['rate_ksh'];
        $lineWeight = $quantity * $product['weight_kg'];
        
        $cartItems[] = [
            'product' => $product,
            'quantity' => $quantity,
            'line_total' => $lineTotal,
            'line_weight' => $lineWeight,
            'delivery_details' => $deliveryDetails[$product['id']] ?? null
        ];
        
        $totalCost += $lineTotal;
        $totalWeight += $lineWeight;
        $totalItems += $quantity;
    }
}

// Check if any delivery details exist for validation
$hasDeliveryDetails = false;
foreach ($cartItems as $item) {
    if ($item['delivery_details']) {
        $hasDeliveryDetails = true;
        break;
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
    min-width: 1100px;
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

/* Delivery Details Styles */
.delivery-details-toggle {
    text-align: center;
}

.details-toggle-btn {
    background: none;
    border: none;
    color: var(--xobo-primary);
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 3px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    margin: 0 auto;
    font-size: 0.9rem;
}

.details-toggle-btn:hover {
    background: var(--xobo-light-gray);
    color: var(--xobo-primary-hover);
}

.details-toggle-btn.expanded i {
    transform: rotate(180deg);
}

.status-indicator {
    font-size: 0.8rem;
    margin-left: 0.25rem;
}

.status-indicator.filled {
    color: var(--xobo-success);
}

.status-indicator.empty {
    color: var(--xobo-gray);
}

.delivery-details-row {
    background: #f8f9fa;
    border-top: 2px solid var(--xobo-primary);
}

.delivery-details-form {
    padding: 1.5rem;
    border-radius: 6px;
    background: white;
    margin: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.delivery-details-form h4 {
    color: var(--xobo-primary);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1rem;
}

.delivery-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full-width {
    grid-column: span 2;
}

.form-group label {
    font-weight: 600;
    color: var(--xobo-primary);
    margin-bottom: 0.25rem;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.form-group input,
.form-group textarea {
    padding: 0.5rem;
    border: 1px solid var(--xobo-border);
    border-radius: 3px;
    font-size: 0.9rem;
    outline: none;
    transition: border-color 0.3s;
}

.form-group input:focus,
.form-group textarea:focus {
    border-color: var(--xobo-primary);
    box-shadow: 0 0 0 2px rgba(22, 35, 77, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 60px;
}

.delivery-form-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    padding-top: 1rem;
    border-top: 1px solid var(--xobo-border);
}

.delivery-form-actions .btn {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
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
    
    .delivery-form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-group.full-width {
        grid-column: span 1;
    }
    
    .delivery-form-actions {
        flex-direction: column;
    }
    
    .delivery-form-actions .btn {
        width: 100%;
        text-align: center;
    }
}

.vehicle-type-dropdown {
    margin-left: 0;
    padding-left: 0;
    min-width: 220px;
}
.vehicle-type-select {
    padding: 0.5rem 1.5rem 0.5rem 0.75rem;
    border-radius: 4px;
    border: 1px solid var(--xobo-border);
    font-size: 1rem;
    background: #fff;
    font-family: inherit;
    font-weight: 400;
    min-width: 120px;
    color: var(--xobo-primary);
    background-image: none;
    height: 40px;
    box-sizing: border-box;
}
.cart-form-actions {
    background: var(--xobo-light-gray);
    border-top: 1px solid var(--xobo-border);
    margin-top: 0;
    border-radius: 0 0 8px 8px;
    box-shadow: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    padding: 1.5rem 2rem 1.5rem 2rem;
}
.cart-form-actions > div {
    display: flex;
    align-items: center;
    gap: 1rem;
}
@media (max-width: 900px) {
    .cart-form-actions {
        flex-direction: column;
        align-items: stretch;
        gap: 1.5rem;
        padding: 1.5rem 1rem;
    }
    .cart-form-actions > div {
        justify-content: flex-start;
    }
    .vehicle-type-dropdown {
        margin-bottom: 1rem;
    }
}
.required-star {
    color: #dc2626;
    font-size: 1rem;
    margin-left: 2px;
    vertical-align: middle;
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
            <a href="../index" class="btn btn-primary">
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
                                <th width="80">Details</th>
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
                                <td class="delivery-details-toggle">
                                    <button type="button" class="details-toggle-btn" 
                                            onclick="toggleDeliveryDetails(<?php echo $item['product']['id']; ?>)"
                                            data-product-id="<?php echo $item['product']['id']; ?>">
                                        <i class="fas fa-chevron-down"></i>
                                        <?php if ($item['delivery_details']): ?>
                                            <span class="status-indicator filled" title="Delivery details filled">●</span>
                                        <?php else: ?>
                                            <span class="status-indicator empty" title="No delivery details">○</span>
                                        <?php endif; ?>
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Expandable Delivery Details Row -->
                            <tr class="delivery-details-row" id="delivery-row-<?php echo $item['product']['id']; ?>" style="display: none;">
                                <td colspan="9">
                                    <div class="delivery-details-form">
                                        <h4><i class="fas fa-truck"></i> Delivery Details for <?php echo htmlspecialchars($item['product']['name']); ?></h4>
                                        <form method="POST" class="delivery-form">
                                            <input type="hidden" name="product_id" value="<?php echo $item['product']['id']; ?>">
                                            
                                            <div class="delivery-form-grid">
                                                <div class="form-group">
                                                    <label for="destination_<?php echo $item['product']['id']; ?>">
                                                        <i class="fas fa-map-marker-alt"></i> Destination <span class="required-star">*</span>
                                                    </label>
                                                    <input type="text" id="destination_<?php echo $item['product']['id']; ?>" 
                                                           name="destination" placeholder="Where is this item going?"
                                                           value="<?php echo htmlspecialchars($item['delivery_details']['destination'] ?? ''); ?>" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="company_name_<?php echo $item['product']['id']; ?>">
                                                        <i class="fas fa-building"></i> Company Name <span class="required-star">*</span>
                                                    </label>
                                                    <input type="text" id="company_name_<?php echo $item['product']['id']; ?>" 
                                                           name="company_name" placeholder="Receiving company name"
                                                           value="<?php echo htmlspecialchars($item['delivery_details']['company_name'] ?? ''); ?>" required>
                                                </div>
                                                
                                                <div class="form-group full-width">
                                                    <label for="company_address_<?php echo $item['product']['id']; ?>">
                                                        <i class="fas fa-map"></i> Company Address
                                                    </label>
                                                    <textarea id="company_address_<?php echo $item['product']['id']; ?>" 
                                                              name="company_address" rows="2" 
                                                              placeholder="Full delivery address"><?php echo htmlspecialchars($item['delivery_details']['company_address'] ?? ''); ?></textarea>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="recipient_name_<?php echo $item['product']['id']; ?>">
                                                        <i class="fas fa-user"></i> Recipient Name
                                                    </label>
                                                    <input type="text" id="recipient_name_<?php echo $item['product']['id']; ?>" 
                                                           name="recipient_name" placeholder="Person receiving the item"
                                                           value="<?php echo htmlspecialchars($item['delivery_details']['recipient_name'] ?? ''); ?>">
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="recipient_phone_<?php echo $item['product']['id']; ?>">
                                                        <i class="fas fa-phone"></i> Recipient Phone
                                                    </label>
                                                    <input type="tel" id="recipient_phone_<?php echo $item['product']['id']; ?>" 
                                                           name="recipient_phone" placeholder="Contact number"
                                                           value="<?php echo htmlspecialchars($item['delivery_details']['recipient_phone'] ?? ''); ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="delivery-form-actions">
                                                <button type="submit" name="save_delivery_details" class="btn btn-primary">
                                                    <i class="fas fa-save"></i> Save Details
                                                </button>
                                                <button type="button" class="btn btn-danger" 
                                                        onclick="deleteDeliveryDetails(<?php echo $item['product']['id']; ?>)">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </form>
                                    </div>
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
                    
                    <div class="cart-form-actions" style="display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; padding: 1.5rem 2rem 1.5rem 2rem; background: var(--xobo-light-gray); border-top: 1px solid var(--xobo-border); border-radius: 0 0 8px 8px;">
                        <div class="vehicle-type-dropdown" style="display: flex; align-items: center; gap: 0.75rem; min-width: 220px;">
                            <label for="vehicle_type" style="font-weight: 600; color: var(--xobo-primary); margin-bottom: 0;">Vehicle Type:</label>
                            <select id="vehicle_type" name="vehicle_type" class="vehicle-type-select">
                                <option value="">-select-</option>
                                <option value="Motor-Bike">Motor-Bike</option>
                                <option value="Mini-Van">Mini-Van</option>
                                <option value="Van">Van</option>
                                <option value="Pick-Up">Pick-Up</option>
                                <option value="3 Tonne">3 Tonne</option>
                                <option value="5 Tonne">5 Tonne</option>
                                <option value="10 Tonne">10 Tonne</option>
                                <option value="14 Tonne">14 Tonne</option>
                            </select>
                        </div>
                        <div style="display: flex; gap: 1rem;">
                            <a href="../index" class="btn btn-secondary">
                                <i class="fas fa-home"></i> Home
                            </a>
                            <button type="submit" name="confirm_order" id="confirm-order-btn" class="btn btn-primary">
                                <i class="fas fa-check"></i> Confirm Order
                            </button>
                        </div>
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
    
    // AJAX update session
    fetch('cart', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `ajax=update_quantity&product_id=${productId}&quantity=${newValue}`
    });
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
    
    // Handle confirm order button
    const confirmOrderBtn = document.getElementById('confirm-order-btn');
    if (confirmOrderBtn) {
        confirmOrderBtn.addEventListener('click', function(e) {
            if (this.disabled) {
                e.preventDefault();
                alert('Please add delivery details for at least one product before confirming your order.');
                return;
            }
            
            if (!confirm('Are you sure you want to confirm this order? This will create your order and generate a receipt.')) {
                e.preventDefault();
                return;
            }
        });
    }
    
    // Add event listeners to delivery form inputs for real-time validation
    const deliveryInputs = document.querySelectorAll('.delivery-form input, .delivery-form textarea');
    deliveryInputs.forEach(input => {
        input.addEventListener('input', checkDeliveryDetailsAndUpdateButton);
        input.addEventListener('blur', checkDeliveryDetailsAndUpdateButton);
    });
    
    // Add event listeners to delivery form save buttons to update status indicators
    const deliveryForms = document.querySelectorAll('.delivery-form');
    deliveryForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Let the form submit normally, but mark that we need to update the indicator
            const productId = form.querySelector('input[name="product_id"]').value;
            
            // Check if any field has content
            const inputs = form.querySelectorAll('input[type="text"], input[type="tel"], textarea');
            let hasContent = false;
            inputs.forEach(input => {
                if (input.value.trim() !== '') {
                    hasContent = true;
                }
            });
            
            if (hasContent) {
                // Update status indicator immediately for better UX
                setTimeout(() => {
                    const statusIndicator = document.querySelector(`[data-product-id="${productId}"] .status-indicator`);
                    if (statusIndicator) {
                        statusIndicator.className = 'status-indicator filled';
                        statusIndicator.title = 'Delivery details filled';
                        statusIndicator.textContent = '●';
                    }
                    checkDeliveryDetailsAndUpdateButton();
                }, 100);
            }
        });
    });
    
    // Initial check for delivery details
    // Set initial state based on PHP data
    const confirmBtn = document.getElementById('confirm-order-btn');
    const hasDeliveryDetailsFromPHP = <?php echo $hasDeliveryDetails ? 'true' : 'false'; ?>;
    
    if (confirmBtn) {
        if (hasDeliveryDetailsFromPHP) {
            confirmBtn.disabled = false;
            confirmBtn.title = '';
            confirmBtn.classList.remove('btn-disabled');
        } else {
            confirmBtn.disabled = true;
            confirmBtn.title = 'Please add delivery details for at least one product';
            confirmBtn.classList.add('btn-disabled');
        }
    }
    
    // Also run the JavaScript check
    checkDeliveryDetailsAndUpdateButton();
    
    // Initial state set successfully
});

// Toggle delivery details section
function toggleDeliveryDetails(productId) {
    const row = document.getElementById('delivery-row-' + productId);
    const button = document.querySelector(`[data-product-id="${productId}"]`);
    const icon = button.querySelector('i');

    // --- Sync the hidden quantity field with the main cart quantity input ---
    const mainQtyInput = document.getElementById('qty_' + productId);
    const deliveryForm = row.querySelector('form.delivery-form');
    if (mainQtyInput && deliveryForm) {
        const hiddenQtyInput = deliveryForm.querySelector('input[name="quantity"]');
        if (hiddenQtyInput) {
            hiddenQtyInput.value = mainQtyInput.value;
        }
    }
    // ----------------------------------------------------------------------

    if (row.style.display === 'none' || row.style.display === '') {
        row.style.display = 'table-row';
        button.classList.add('expanded');
        icon.style.transform = 'rotate(180deg)';
    } else {
        row.style.display = 'none';
        button.classList.remove('expanded');
        icon.style.transform = 'rotate(0deg)';
    }
}

// Delete delivery details
function deleteDeliveryDetails(productId) {
    if (confirm('Are you sure you want to delete the delivery details for this product?')) {
        // First clear the form fields for immediate visual feedback
        const deliveryRow = document.getElementById('delivery-row-' + productId);
        if (deliveryRow) {
            const inputs = deliveryRow.querySelectorAll('input, textarea');
            inputs.forEach(input => {
                input.value = '';
            });
            
            // Update the status indicator to empty
            const statusIndicator = document.querySelector(`[data-product-id="${productId}"] .status-indicator`);
            if (statusIndicator) {
                statusIndicator.className = 'status-indicator empty';
                statusIndicator.title = 'No delivery details';
                statusIndicator.textContent = '○';
            }
            
            // Close the delivery details section
            toggleDeliveryDetails(productId);
            
            // Update the confirm order button state
            checkDeliveryDetailsAndUpdateButton();
        }
        
        // Create a form to submit the delete request
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'delete_delivery_details';
        actionInput.value = '1';
        
        const productIdInput = document.createElement('input');
        productIdInput.type = 'hidden';
        productIdInput.name = 'product_id';
        productIdInput.value = productId;
        
        form.appendChild(actionInput);
        form.appendChild(productIdInput);
        document.body.appendChild(form);
        
        // Submit the form to save changes to database
        form.submit();
    }
}

// Check if any delivery details exist and update confirm button
function checkDeliveryDetailsAndUpdateButton(onlyCheck) {
    const confirmBtn = document.getElementById('confirm-order-btn');
    let hasDetails = false;
    const filledIndicators = document.querySelectorAll('.status-indicator.filled');
    if (filledIndicators.length > 0) {
        hasDetails = true;
    } else {
        const deliveryForms = document.querySelectorAll('.delivery-form');
        deliveryForms.forEach(form => {
            const inputs = form.querySelectorAll('input[type="text"], input[type="tel"], textarea');
            inputs.forEach(input => {
                if (input.value.trim() !== '') {
                    hasDetails = true;
                }
            });
        });
    }
    if (!onlyCheck) {
        // Instead of updating button here, call the new combined check
        checkVehicleTypeAndUpdateButton();
    }
    return hasDetails;
}

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

// Font Awesome icons in select (for modern browsers)
document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('vehicle_type');
    if (select) {
        for (let i = 0; i < select.options.length; i++) {
            const opt = select.options[i];
            if (opt.dataset.icon) {
                opt.textContent = String.fromCharCode(parseInt(opt.innerHTML.match(/&#x([0-9a-fA-F]+);/)[1], 16)) + ' ' + opt.textContent.replace(/^[^ ]+ /, '');
            }
        }
    }
});

// --- VEHICLE TYPE CHECK ---
function checkVehicleTypeAndUpdateButton() {
    const vehicleTypeSelect = document.getElementById('vehicle_type');
    const confirmBtn = document.getElementById('confirm-order-btn');
    // Also check delivery details
    const hasDeliveryDetails = checkDeliveryDetailsAndUpdateButton(true); // pass true to only check, not update
    let vehicleTypeValid = false;
    if (vehicleTypeSelect) {
        vehicleTypeValid = !!vehicleTypeSelect.value && vehicleTypeSelect.value.trim() !== '';
    }
    if (confirmBtn) {
        if (hasDeliveryDetails && vehicleTypeValid) {
            confirmBtn.disabled = false;
            confirmBtn.title = '';
            confirmBtn.classList.remove('btn-disabled');
        } else if (!hasDeliveryDetails) {
            confirmBtn.disabled = true;
            confirmBtn.title = 'Please add delivery details for at least one product';
            confirmBtn.classList.add('btn-disabled');
        } else if (!vehicleTypeValid) {
            confirmBtn.disabled = true;
            confirmBtn.title = 'Please select a vehicle type';
            confirmBtn.classList.add('btn-disabled');
        }
    }
    return hasDeliveryDetails && vehicleTypeValid;
}

// Add vehicle type change event
const vehicleTypeSelect = document.getElementById('vehicle_type');
if (vehicleTypeSelect) {
    vehicleTypeSelect.addEventListener('change', checkVehicleTypeAndUpdateButton);
}
// Initial combined check
checkVehicleTypeAndUpdateButton();
</script>

<?php include '../includes/footer.php'; ?> 