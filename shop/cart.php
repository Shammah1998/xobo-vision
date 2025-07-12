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
        // Also remove accessories for this product
        if (isset($_SESSION['cart_accessories'][$productId])) {
            unset($_SESSION['cart_accessories'][$productId]);
        }
        $message = 'Item removed from cart!';
    } elseif (isset($_POST['delete_selected'])) {
        if (isset($_POST['selected_items']) && !empty($_POST['selected_items'])) {
            $deletedCount = 0;
            foreach ($_POST['selected_items'] as $productId) {
                $productId = (int)$productId;
                if (isset($_SESSION['cart'][$productId])) {
                    unset($_SESSION['cart'][$productId]);
                    // Also remove accessories for this product
                    if (isset($_SESSION['cart_accessories'][$productId])) {
                        unset($_SESSION['cart_accessories'][$productId]);
                    }
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
        $pickUp = sanitize($_POST['destination'] ?? '');
        $dropOff = sanitize($_POST['company_name'] ?? '');
        $additionalNotes = sanitize($_POST['company_address'] ?? '');
        $recipientName = sanitize($_POST['recipient_name'] ?? '');
        $recipientPhone = sanitize($_POST['recipient_phone'] ?? '');
        
        // Require both destination and company name
        if (!empty($pickUp) && !empty($dropOff)) {
            $stmt = $pdo->prepare("INSERT INTO delivery_details (user_id, product_id, session_id, pick_up, drop_off, additional_notes, recipient_name, recipient_phone) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE 
                                   pick_up = VALUES(pick_up), 
                                   drop_off = VALUES(drop_off), 
                                   additional_notes = VALUES(additional_notes), 
                                   recipient_name = VALUES(recipient_name), 
                                   recipient_phone = VALUES(recipient_phone),
                                   updated_at = CURRENT_TIMESTAMP");
            $stmt->execute([$userId, $productId, session_id(), $pickUp, $dropOff, $additionalNotes, $recipientName, $recipientPhone]);
            $message = "Delivery details saved successfully!";
        } else if (empty($pickUp) && empty($dropOff) && empty($additionalNotes) && empty($recipientName) && empty($recipientPhone)) {
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
    } elseif (isset($_POST['fill_all'])) {
        // Handle Fill All Delivery Details for all cart items
        $pickUp = sanitize($_POST['destination'] ?? '');
        $dropOff = sanitize($_POST['company_name'] ?? '');
        $additionalNotes = sanitize($_POST['company_address'] ?? '');
        $recipientName = sanitize($_POST['recipient_name'] ?? '');
        $recipientPhone = sanitize($_POST['recipient_phone'] ?? '');
        
        if (!empty($pickUp) && !empty($dropOff)) {
            if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
                    $productIds = array_keys($_SESSION['cart']);
                $stmt = $pdo->prepare("INSERT INTO delivery_details (user_id, product_id, session_id, pick_up, drop_off, additional_notes, recipient_name, recipient_phone, created_at, updated_at)
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()) 
                                           ON DUPLICATE KEY UPDATE 
                                       pick_up = VALUES(pick_up),
                                       drop_off = VALUES(drop_off),
                                       additional_notes = VALUES(additional_notes),
                                           recipient_name = VALUES(recipient_name), 
                                           recipient_phone = VALUES(recipient_phone),
                                           updated_at = NOW()");
                    foreach ($productIds as $productId) {
                    $stmt->execute([
                                $userId, 
                                (int)$productId, 
                                session_id(), 
                        $pickUp,
                        $dropOff,
                        $additionalNotes,
                                $recipientName, 
                                $recipientPhone
                            ]);
                }
                // After updating, reload the page to show updated details
                header('Location: ' . $_SERVER['REQUEST_URI']);
                            exit;
            } else {
                $error = "Your cart is empty. Cannot apply delivery details.";
            }
        } else {
            $error = "Please fill in both Destination and Company Name to apply to all items.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'add_multiple' && isset($_POST['products'])) {
        // Handle bulk adding of products from index.php
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        if (!isset($_SESSION['cart_accessories'])) {
            $_SESSION['cart_accessories'] = [];
        }
        $addedCount = 0;
        foreach ($_POST['products'] as $productId) {
            $productId = (int)$productId;
            if ($productId > 0) {
                // Verify product belongs to user's company
                $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND company_id = ?");
                $stmt->execute([$productId, $companyId]);
                if ($stmt->fetch()) {
                    // Only add if not already in cart, otherwise keep existing quantity
                    if (!isset($_SESSION['cart'][$productId])) {
                        $_SESSION['cart'][$productId] = 1;
                    }
                    $addedCount++;
                    // If accessories are posted, store them
                    if (isset($_POST['accessories'])) {
                        $accessories = json_decode($_POST['accessories'], true);
                        if (is_array($accessories)) {
                            $_SESSION['cart_accessories'][$productId] = $accessories;
                        }
                    }
                }
            }
        }
        if ($addedCount > 0) {
            $message = "Successfully added {$addedCount} product(s) to your cart!";
        } else {
            $error = "No valid products were added to cart.";
        }
        // Redirect to cart page to avoid resubmission
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } elseif (isset($_POST['confirm_order'])) {
        // Confirm order: create order, save items, delivery details, clear cart, redirect
        if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
            $error = 'Your cart is empty.';
        } else {
            $pdo->beginTransaction();
            try {
                // Calculate total
                $totalKsh = 0;
                foreach ($_SESSION['cart'] as $productId => $qty) {
                    $stmt = $pdo->prepare('SELECT rate_ksh FROM products WHERE id = ? AND company_id = ?');
                    $stmt->execute([$productId, $companyId]);
                    $product = $stmt->fetch();
                    if ($product) {
                        $totalKsh += $product['rate_ksh'] * $qty;
                    }
                }
                // Insert order
                $stmt = $pdo->prepare('INSERT INTO orders (user_id, company_id, total_ksh, created_at) VALUES (?, ?, ?, NOW())');
                $stmt->execute([$userId, $companyId, $totalKsh]);
                $orderId = $pdo->lastInsertId();
                // Insert order items
                foreach ($_SESSION['cart'] as $productId => $qty) {
                    $stmt = $pdo->prepare('SELECT rate_ksh FROM products WHERE id = ? AND company_id = ?');
                    $stmt->execute([$productId, $companyId]);
                    $product = $stmt->fetch();
                    $lineTotal = $product ? $product['rate_ksh'] * $qty : 0;
                    $stmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, line_total) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$orderId, $productId, $qty, $lineTotal]);
                }
                // Insert delivery details for each product
                $stmt = $pdo->prepare('SELECT * FROM delivery_details WHERE user_id = ? AND session_id = ?');
                $stmt->execute([$userId, session_id()]);
                $deliveryRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($deliveryRows as $row) {
                    $stmt2 = $pdo->prepare('INSERT INTO order_delivery_details (order_id, product_id, pick_up, drop_off, additional_notes, recipient_name, recipient_phone, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
                    $stmt2->execute([
                        $orderId,
                        $row['product_id'],
                        $row['pick_up'],
                        $row['drop_off'],
                        $row['additional_notes'],
                        $row['recipient_name'],
                        $row['recipient_phone']
                    ]);
                }
                
                // Insert accessories for each product that has them
                if (!empty($_SESSION['cart_accessories'])) {
                    foreach ($_SESSION['cart_accessories'] as $mainProductId => $accessories) {
                        foreach ($accessories as $accessory) {
                            $stmt3 = $pdo->prepare('INSERT INTO order_accessories (order_id, main_product_id, accessory_product_id, accessory_name, accessory_sku, accessory_weight, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
                            $stmt3->execute([
                                $orderId,
                                (int)$mainProductId,
                                isset($accessory['id']) ? (int)$accessory['id'] : null,
                                $accessory['name'] ?? '',
                                $accessory['sku'] ?? '',
                                (float)($accessory['weight'] ?? 0)
                            ]);
                        }
                    }
                }
                
                // Clear cart and delivery details
                unset($_SESSION['cart']);
                unset($_SESSION['cart_accessories']);
                $stmt = $pdo->prepare('DELETE FROM delivery_details WHERE user_id = ? AND session_id = ?');
                $stmt->execute([$userId, session_id()]);
                $pdo->commit();
                // Redirect to order receipt
                header('Location: order-receipt.php?order_id=' . $orderId);
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Order could not be completed. Please try again.';
            }
        }
    }
}

// Get company information
$stmt = $pdo->prepare("SELECT name FROM companies WHERE id = ? AND status = 'approved'");
$stmt->execute([$companyId]);
$company = $stmt->fetch();
$companyName = $company ? $company['name'] : '';

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

// Filter out accessory-only products from cartItems
$accessoryProductIds = [];
if (!empty($_SESSION['cart_accessories'])) {
    foreach ($_SESSION['cart_accessories'] as $mainId => $accessories) {
        foreach ($accessories as $acc) {
            if (!empty($acc['id'])) {
                $accessoryProductIds[] = (int)$acc['id'];
            }
        }
    }
}
// Only filter out products that are accessories AND not main products in cart
$cartItems = array_filter($cartItems, function($item) use ($accessoryProductIds) {
    $productId = (int)$item['product']['id'];
    // Keep the product if it's in the main cart (even if it's also an accessory)
    // Only filter out if it's ONLY an accessory and not a main cart item
    return !in_array($productId, $accessoryProductIds, true) || isset($_SESSION['cart'][$productId]);
});

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
        gap: 1rem;
    }
    
    .cart-controls-right {
        flex-direction: column;
        gap: 0.5rem;
        width: 100%;
    }
    
    .cart-controls-right .btn {
        width: 100%;
        justify-content: center;
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

/* Fill All Delivery Details Modal Styles */
.fill-all-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(2px);
}

.fill-all-modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 0;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow-y: auto;
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.fill-all-modal-header {
    background: linear-gradient(135deg, var(--xobo-primary) 0%, var(--xobo-primary-hover) 100%);
    color: white;
    padding: 1.5rem 2rem;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.fill-all-modal-header h3 {
    margin: 0;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-close {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: background-color 0.3s;
}

.modal-close:hover {
    background-color: rgba(255, 255, 255, 0.2);
}

.fill-all-modal-body {
    padding: 2rem;
}

.fill-all-description {
    background: #f8f9fa;
    border-left: 4px solid var(--xobo-primary);
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    border-radius: 0 4px 4px 0;
}

.fill-all-description p {
    margin: 0;
    color: var(--xobo-gray);
    font-size: 0.9rem;
    line-height: 1.4;
}

.fill-all-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.fill-all-form-group {
    display: flex;
    flex-direction: column;
}

.fill-all-form-group.full-width {
    grid-column: span 2;
}

.fill-all-form-group label {
    font-weight: 600;
    color: var(--xobo-primary);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.fill-all-form-group input,
.fill-all-form-group textarea {
    padding: 0.75rem;
    border: 2px solid #e1e5e9;
    border-radius: 6px;
    font-size: 0.9rem;
    outline: none;
    transition: all 0.3s;
    background: white;
}

.fill-all-form-group input:focus,
.fill-all-form-group textarea:focus {
    border-color: var(--xobo-primary);
    box-shadow: 0 0 0 3px rgba(22, 35, 77, 0.1);
    transform: translateY(-1px);
}

.fill-all-form-group textarea {
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}

.fill-all-modal-footer {
    background: #f8f9fa;
    padding: 1.5rem 2rem;
    border-radius: 0 0 8px 8px;
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    border-top: 1px solid #e1e5e9;
}

.btn-fill-all {
    background: var(--xobo-success);
    color: white;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s;
}

.btn-fill-all:hover {
    background: #0d5520;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(21, 128, 61, 0.3);
}

.btn-fill-all:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-cancel {
    background: transparent;
    color: var(--xobo-gray);
    border: 2px solid #e1e5e9;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-cancel:hover {
    background: var(--xobo-gray);
    color: white;
    border-color: var(--xobo-gray);
}

@media (max-width: 768px) {
    .fill-all-modal-content {
        width: 95%;
        margin: 10% auto;
        max-height: 85vh;
    }
    
    .fill-all-form-grid {
        grid-template-columns: 1fr;
    }
    
    .fill-all-form-group.full-width {
        grid-column: span 1;
    }
    
    .fill-all-modal-header {
        padding: 1rem 1.5rem;
    }
    
    .fill-all-modal-body {
        padding: 1.5rem;
    }
    
    .fill-all-modal-footer {
        padding: 1rem 1.5rem;
        flex-direction: column;
    }
    
    .fill-all-modal-footer .btn {
        width: 100%;
        justify-content: center;
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
                <div class="cart-controls-right" style="display: flex; align-items: center; gap: 1rem;">
                    <button type="button" id="fill-all-dropdown-btn" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-magic"></i> Fill All Delivery Details
                        <span id="fill-all-arrow" style="display: inline-block; transition: transform 0.2s; margin-left: 0.25rem;"><i class="fas fa-chevron-down"></i></span>
                    </button>
                    <button type="button" id="delete-selected-btn" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>

            <!-- Fill All Delivery Details Dropdown (block, above cart table) -->
            <div id="fill-all-dropdown" class="delivery-details-row" style="display: none; width: 100%; margin-bottom: 2rem;">
                <div class="delivery-details-form">
                    <h4><i class="fas fa-truck"></i> Fill All Delivery Details</h4>
                    <form method="POST" id="fill-all-delivery-form">
                        <input type="hidden" name="fill_all" value="1">
                        <div class="delivery-form-grid">
                            <div class="form-group">
                                <label for="fill_all_destination">
                                    <i class="fas fa-map-marker-alt"></i> Pick Up <span class="required-star">*</span>
                                </label>
                                <input type="text" id="fill_all_destination" name="destination" placeholder="Where is the pick up?" required>
                            </div>
                            <div class="form-group">
                                <label for="fill_all_company_name">
                                    <i class="fas fa-building"></i> Drop Off <span class="required-star">*</span>
                                </label>
                                <input type="text" id="fill_all_company_name" name="company_name" placeholder="Where is the drop off?" required>
                            </div>
                            <div class="form-group full-width">
                                <label for="fill_all_company_address">
                                    <i class="fas fa-map"></i> Additional Notes
                                </label>
                                <textarea id="fill_all_company_address" name="company_address" rows="2" placeholder="Any additional notes"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="fill_all_recipient_name">
                                    <i class="fas fa-user"></i> Recipient Name
                                </label>
                                <input type="text" id="fill_all_recipient_name" name="recipient_name" placeholder="Person receiving the items">
                            </div>
                            <div class="form-group">
                                <label for="fill_all_recipient_phone">
                                    <i class="fas fa-phone"></i> Recipient Phone
                                </label>
                                <input type="tel" id="fill_all_recipient_phone" name="recipient_phone" placeholder="Contact number">
                            </div>
                        </div>
                        <div class="delivery-form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Apply to All Items
                            </button>
                            <button type="button" class="btn btn-danger" id="cancel-fill-all-dropdown">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
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
                                    <?php if (strtolower(trim($item['product']['name'])) === 'vision plus accessories' && !empty($_SESSION['cart_accessories'][$item['product']['id']])): ?>
                                        <button type="button" class="details-toggle-btn accessories-toggle-btn" 
                                                onclick="toggleAccessoriesRow(<?php echo $item['product']['id']; ?>)"
                                                data-product-id="<?php echo $item['product']['id']; ?>"
                                                style="margin-left:12px; vertical-align:middle;">
                                            <i class="fas fa-chevron-down"></i> View
                                        </button>
                                    <?php endif; ?>
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
                            <?php if (strtolower(trim($item['product']['name'])) === 'vision plus accessories' && !empty($_SESSION['cart_accessories'][$item['product']['id']])): ?>
                            <tr class="accessories-details-row" id="accessories-row-<?php echo $item['product']['id']; ?>" style="display:none; background:#f6f8fa;">
                                <td colspan="9" style="padding: 1.2rem 2rem;">
                                    <div class="accessory-list" style="margin-top:0;">
                                        <div style="font-weight:600; color:var(--xobo-primary); margin-bottom:0.5rem; font-size:1.05rem;">
                                            <i class="fas fa-puzzle-piece"></i> Included Accessories
                                        </div>
                                        <table style="width:100%; border-collapse:collapse; background:transparent;">
                                            <thead>
                                                <tr style="background:transparent; color:#888; font-size:0.95em;">
                                                    <th style="text-align:left; padding:4px 8px;">Name</th>
                                                    <th style="text-align:left; padding:4px 8px;">SKU</th>
                                                    <th style="text-align:left; padding:4px 8px;">Weight (kg)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($_SESSION['cart_accessories'][$item['product']['id']] as $acc): ?>
                                                <tr>
                                                    <td style="padding:4px 8px;"><span class="product-name"><?php echo htmlspecialchars($acc['name']); ?></span></td>
                                                    <td style="padding:4px 8px;"><span class="product-sku"><?php echo htmlspecialchars($acc['sku']); ?></span></td>
                                                    <td style="padding:4px 8px;"><span class="product-weight"><?php echo htmlspecialchars($acc['weight']); ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                            
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
                                                        <i class="fas fa-map-marker-alt"></i> Pick Up <span class="required-star">*</span>
                                                    </label>
                                                    <input type="text" id="destination_<?php echo $item['product']['id']; ?>" 
                                                           name="destination" placeholder="Where is the pick up?"
                                                           value="<?php echo htmlspecialchars($item['delivery_details']['pick_up'] ?? ''); ?>" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="company_name_<?php echo $item['product']['id']; ?>">
                                                        <i class="fas fa-building"></i> Drop Off <span class="required-star">*</span>
                                                    </label>
                                                    <input type="text" id="company_name_<?php echo $item['product']['id']; ?>" 
                                                           name="company_name" placeholder="Where is the drop off?"
                                                           value="<?php echo htmlspecialchars($item['delivery_details']['drop_off'] ?? ''); ?>" required>
                                                </div>
                                                
                                                <div class="form-group full-width">
                                                    <label for="company_address_<?php echo $item['product']['id']; ?>">
                                                        <i class="fas fa-map"></i> Additional Notes
                                                    </label>
                                                    <textarea id="company_address_<?php echo $item['product']['id']; ?>" 
                                                              name="company_address" rows="2" 
                                                              placeholder="Any additional notes"><?php echo htmlspecialchars($item['delivery_details']['additional_notes'] ?? ''); ?></textarea>
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

<?php include '../includes/footer.php'; ?> 

<script>
// Quantity update function with automatic price calculation
function updateQuantity(productId, change) {
    const input = document.getElementById('qty_' + productId);
    let currentValue = parseInt(input.value) || 1;
    let newValue = currentValue + change;
    if (newValue < 1) newValue = 1;
    if (newValue > 999) newValue = 999;
    input.value = newValue;
    // Update session via AJAX
    updateQuantitySession(productId, newValue);
}

// Update session quantity via AJAX
function updateQuantitySession(productId, quantity) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('ajax=update_quantity&product_id=' + encodeURIComponent(productId) + '&quantity=' + encodeURIComponent(quantity));
}

// Attach event listeners to all quantity inputs for manual changes
window.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.qty-input').forEach(function(input) {
        input.addEventListener('change', function() {
            let val = parseInt(input.value) || 1;
            if (val < 1) val = 1;
            if (val > 999) val = 999;
            input.value = val;
            const productId = input.id.replace('qty_', '');
            updateQuantitySession(productId, val);
        });
        input.addEventListener('blur', function() {
            let val = parseInt(input.value) || 1;
            if (val < 1) val = 1;
            if (val > 999) val = 999;
            input.value = val;
            const productId = input.id.replace('qty_', '');
            updateQuantitySession(productId, val);
        });
    });
});

// Toggle delivery details section for per-item dropdowns
function toggleDeliveryDetails(productId) {
    const row = document.getElementById('delivery-row-' + productId);
    const button = document.querySelector(`[data-product-id="${productId}"]`);
    const icon = button.querySelector('i');
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

// Toggle accessories details section for accessories dropdowns
function toggleAccessoriesRow(productId) {
    const row = document.getElementById('accessories-row-' + productId);
    const button = document.querySelector('.accessories-toggle-btn[data-product-id="' + productId + '"]');
    const icon = button.querySelector('i');
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

// Fill All Delivery Details Dropdown logic with arrow rotation
const fillAllBtn = document.getElementById('fill-all-dropdown-btn');
const fillAllDropdown = document.getElementById('fill-all-dropdown');
const cancelFillAllDropdown = document.getElementById('cancel-fill-all-dropdown');
const fillAllArrow = document.getElementById('fill-all-arrow');
if (fillAllBtn && fillAllDropdown && fillAllArrow) {
        fillAllBtn.addEventListener('click', function() {
        const isOpen = fillAllDropdown.style.display === 'block';
        fillAllDropdown.style.display = isOpen ? 'none' : 'block';
        fillAllArrow.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
        if (!isOpen) {
            fillAllDropdown.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }
if (cancelFillAllDropdown && fillAllDropdown && fillAllArrow) {
    cancelFillAllDropdown.addEventListener('click', function() {
        fillAllDropdown.style.display = 'none';
        fillAllArrow.style.transform = 'rotate(0deg)';
    });
}

// Select All and item checkbox logic
const selectAll = document.getElementById('select-all');
const selectAllHeader = document.getElementById('select-all-header');
const itemCheckboxes = document.querySelectorAll('.item-checkbox');

function updateSelectAllState() {
    const checkedCount = document.querySelectorAll('.item-checkbox:checked').length;
    const totalCount = itemCheckboxes.length;
    if (selectAll) {
        selectAll.checked = checkedCount === totalCount && totalCount > 0;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < totalCount;
    }
    if (selectAllHeader) {
        selectAllHeader.checked = checkedCount === totalCount && totalCount > 0;
        selectAllHeader.indeterminate = checkedCount > 0 && checkedCount < totalCount;
    }
}

if (selectAll) {
    selectAll.addEventListener('change', function() {
        itemCheckboxes.forEach(cb => { cb.checked = selectAll.checked; });
        if (selectAllHeader) selectAllHeader.checked = selectAll.checked;
    });
}
if (selectAllHeader) {
    selectAllHeader.addEventListener('change', function() {
        itemCheckboxes.forEach(cb => { cb.checked = selectAllHeader.checked; });
        if (selectAll) selectAll.checked = selectAllHeader.checked;
    });
}
itemCheckboxes.forEach(cb => {
    cb.addEventListener('change', updateSelectAllState);
});
// Initialize state on page load
updateSelectAllState();

// Restore original delete button JS
const deleteBtn = document.getElementById('delete-selected-btn');
const cartForm = document.getElementById('cart-form');
if (deleteBtn && cartForm) {
    deleteBtn.addEventListener('click', function() {
        const checked = document.querySelectorAll('.item-checkbox:checked');
        if (checked.length === 0) {
            alert("You haven't selected any item");
            return;
        }
        const confirmMsg = checked.length === 1 ? 'Are you sure you want to delete this item?' : `Are you sure you want to delete these ${checked.length} items?`;
        if (!confirm(confirmMsg)) return;
        // Add hidden input for delete_selected
        let hidden = cartForm.querySelector('input[name="delete_selected"]');
        if (!hidden) {
            hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'delete_selected';
            hidden.value = '1';
            cartForm.appendChild(hidden);
        }
        cartForm.submit();
    });
}
</script>