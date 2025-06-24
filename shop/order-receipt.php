<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

requireRole(['user']);

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$userId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'];

if (!$orderId) {
    header('Location: ../index.php');
    exit;
}

// Get order details
$stmt = $pdo->prepare("SELECT o.*, c.name as company_name, u.email as user_email 
                       FROM orders o 
                       JOIN companies c ON o.company_id = c.id 
                       JOIN users u ON o.user_id = u.id 
                       WHERE o.id = ? AND o.user_id = ? AND o.company_id = ?");
$stmt->execute([$orderId, $userId, $companyId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: ../index.php');
    exit;
}

// Get order items with product details
$stmt = $pdo->prepare("SELECT oi.*, p.name, p.sku, p.weight_kg, p.rate_ksh 
                       FROM order_items oi 
                       JOIN products p ON oi.product_id = p.id 
                       WHERE oi.order_id = ?");
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll();

// Get delivery details
$stmt = $pdo->prepare("SELECT * FROM order_delivery_details WHERE order_id = ?");
$stmt->execute([$orderId]);
$deliveryDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize delivery details by product_id
$deliveryByProduct = [];
foreach ($deliveryDetails as $detail) {
    $deliveryByProduct[$detail['product_id']] = $detail;
}

// Calculate totals
$totalItems = 0;
$totalWeight = 0;
foreach ($orderItems as $item) {
    $totalItems += $item['quantity'];
    $totalWeight += ($item['weight_kg'] * $item['quantity']);
}

$pageTitle = 'Receipt #' . $orderId;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* XOBO Vision Color Variables */
        :root {
            --xobo-primary: #16234d;
            --xobo-primary-hover: #1a2654;
            --xobo-light-gray: #f8f9fa;
            --xobo-gray: #666666;
            --xobo-border: #ddd;
            --xobo-shadow: rgba(0, 0, 0, 0.1);
            --xobo-accent: #e53935;
            --xobo-success: #27ae60;
            --xobo-warning: #f39c12;
            --text-primary: #333;
            --text-secondary: #666;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--xobo-light-gray);
            color: var(--text-primary);
            line-height: 1.5;
            font-size: 14px;
            margin: 0;
            padding-top: 45px; /* Space for fixed navbar */
        }

        /* Navbar Styles */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: white;
            border-bottom: 2px solid var(--xobo-border);
            padding: 0.6rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            box-shadow: 0 2px 4px var(--xobo-shadow);
            height: 55px;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--xobo-primary);
            text-decoration: none;
            letter-spacing: 0.05em;
        }

        .navbar-brand img {
            width: 5vw;
            height: auto;
            object-fit: contain;
            transition: transform 0.3s ease;
        }

        .navbar-brand img:hover {
            transform: scale(1.05);
        }

        .navbar-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .receipt-container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            box-shadow: 0 2px 10px var(--xobo-shadow);
            border-radius: 8px;
            overflow: hidden;
        }

        /* Header */
        .receipt-header {
            background: var(--xobo-primary);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid var(--xobo-border);
        }

        .header-left {
            display: flex;
            align-items: center;
            flex: 1;
        }

        .company-name {
            font-size: 1.3rem;
            font-weight: 700;
            letter-spacing: 0.1em;
        }

        .company-name img {
            width: 4vw;
            height: auto;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }

        .header-center {
            flex: 1;
            text-align: center;
        }

        .receipt-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0;
        }

        .header-right {
            flex: 1;
            text-align: right;
        }

        .receipt-date {
            font-size: 1rem;
            font-weight: 500;
            opacity: 0.9;
        }

        .order-info-line {
            background: var(--xobo-light-gray);
            padding: 0.75rem 2rem;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.9rem;
            border-bottom: 1px solid var(--xobo-border);
        }

        /* Table Section */
        .table-section {
            padding: 2rem;
            border-bottom: 1px solid var(--xobo-border);
        }

        .table-section:last-of-type {
            border-bottom: none;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--xobo-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid var(--xobo-border);
            border-radius: 6px;
            overflow: hidden;
        }

        .data-table th {
            background: var(--xobo-light-gray);
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            color: var(--xobo-primary);
            border-bottom: 1px solid var(--xobo-border);
            font-size: 0.9rem;
        }

        .data-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .data-table tbody tr:hover {
            background: #fafbfc;
        }

        /* Product Table Specific */
        .product-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .product-sku {
            font-family: monospace;
            background: var(--xobo-light-gray);
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .quantity, .weight, .price, .total {
            text-align: right;
            font-weight: 500;
        }

        .total {
            color: var(--xobo-primary);
            font-weight: 600;
        }

        /* Delivery Table Specific */
        .delivery-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 150px;
        }

        .delivery-value {
            color: var(--text-primary);
        }

        /* Totals Table Specific */
        .totals-table {
            width: 400px;
            margin-left: auto;
        }

        .totals-table td {
            padding: 0.5rem 0.75rem;
        }

        .totals-label {
            font-weight: 500;
            color: var(--text-secondary);
        }

        .totals-value {
            text-align: right;
            font-weight: 600;
            color: var(--text-primary);
        }

        .grand-total-row {
            background: var(--xobo-light-gray);
            border-top: 2px solid var(--xobo-border);
        }

        .grand-total-row .totals-label {
            font-weight: 700;
            color: var(--xobo-primary);
            font-size: 1.1rem;
        }

        .grand-total-row .totals-value {
            font-weight: 700;
            color: var(--xobo-primary);
            font-size: 1.1rem;
        }

        /* Navbar Button Styles */
        .navbar-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.2s;
            cursor: pointer;
        }

        .navbar-btn-primary {
            background: var(--xobo-primary);
            color: white;
        }

        .navbar-btn-primary:hover {
            background: var(--xobo-primary-hover);
        }

        .navbar-btn-secondary {
            background: var(--xobo-gray);
            color: white;
        }

        .navbar-btn-secondary:hover {
            background: #5a6268;
        }

        /* Print Styles */
        @media print {
            @page {
                margin: 0.3in 0.5in;
                size: A4;
            }
            
            /* Hide browser headers and footers */
            html {
                margin: 0 !important;
                padding: 0 !important;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body {
                background: white !important;
                font-size: 14px; /* Keep same as screen */
                padding-top: 0;
                margin: 0;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                line-height: 1.5; /* Keep same as screen */
            }
            
            /* Force hide browser headers/footers */
            body:before,
            body:after {
                display: none !important;
            }
            
            .navbar {
                display: none !important;
            }
            
            .receipt-container {
                box-shadow: none;
                margin: 0;
                max-width: 100%;
                border-radius: 8px;
                overflow: hidden;
                background: white;
                page-break-inside: avoid;
            }
            
            .receipt-header {
                background: var(--xobo-primary) !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                padding: 1.5rem 2rem !important; /* Keep same as screen */
                display: flex !important;
                flex-direction: row !important; /* Force horizontal layout */
                align-items: center !important;
                justify-content: space-between !important;
                border-bottom: 2px solid var(--xobo-border); /* Keep same as screen */
                width: 100%;
                box-sizing: border-box;
                gap: 0 !important; /* Override mobile gap */
                text-align: left !important; /* Override mobile text-align */
            }

            .header-left {
                display: flex !important;
                align-items: center !important;
                flex: 1 !important; /* Keep same as screen */
                justify-content: flex-start !important; /* Override mobile center */
            }

            .header-center {
                flex: 1 !important; /* Keep same as screen */
                text-align: center !important;
            }

            .header-right {
                flex: 1 !important; /* Keep same as screen */
                text-align: right !important;
            }

            .company-name {
                font-size: 1.3rem; /* Keep same as screen */
                font-weight: 700;
                letter-spacing: 0.1em;
            }

            .company-name img {
                width: 80px !important; /* Fixed size for print visibility */
                height: auto;
                object-fit: contain;
                filter: brightness(0) invert(1);
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .receipt-title {
                font-size: 1.8rem; /* Keep same as screen */
                font-weight: 600;
                margin: 0;
                color: white;
            }

            .receipt-date {
                font-size: 1rem; /* Keep same as screen */
                font-weight: 500;
                color: white;
                opacity: 0.9;
            }

            .order-info-line {
                background: var(--xobo-light-gray) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                padding: 0.75rem 2rem; /* Keep same as screen */
                text-align: center;
                color: var(--text-secondary);
                font-size: 0.9rem; /* Keep same as screen */
                border-bottom: 1px solid var(--xobo-border);
            }

            .table-section {
                padding: 2rem; /* Keep same as screen */
                border-bottom: 1px solid var(--xobo-border);
                background: white;
                page-break-inside: avoid;
            }

            .table-section:last-of-type {
                border-bottom: none;
            }

            .section-title {
                font-size: 1.2rem; /* Keep same as screen */
                font-weight: 600;
                color: var(--xobo-primary);
                margin-bottom: 1rem; /* Keep same as screen */
                display: flex;
                align-items: center;
                gap: 0.5rem; /* Keep same as screen */
            }

            .data-table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid var(--xobo-border);
                border-radius: 6px;
                overflow: hidden;
                background: white;
            }

            .data-table th {
                background: var(--xobo-light-gray) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                padding: 0.75rem; /* Keep same as screen */
                text-align: left;
                font-weight: 600;
                color: var(--xobo-primary) !important;
                border-bottom: 1px solid var(--xobo-border);
                font-size: 0.9rem; /* Keep same as screen */
            }
            
            .data-table td {
                padding: 0.75rem; /* Keep same as screen */
                border-bottom: 1px solid #eee;
                vertical-align: top;
            }

            .data-table tbody tr:last-child td {
                border-bottom: none;
            }

            .data-table tbody tr:hover {
                background: transparent;
            }

            .product-name {
                font-weight: 600;
                color: var(--text-primary);
            }

            .product-sku {
                font-family: monospace;
                background: var(--xobo-light-gray) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                padding: 0.2rem 0.4rem;
                border-radius: 3px;
                font-size: 0.8rem;
                color: var(--text-secondary);
            }

            .quantity, .weight, .price, .total {
                text-align: right;
                font-weight: 500;
            }

            .total {
                color: var(--xobo-primary) !important;
                font-weight: 600;
            }

            .delivery-label {
                font-weight: 600;
                color: var(--text-secondary);
                width: 150px;
            }

            .delivery-value {
                color: var(--text-primary);
            }

            .totals-table {
                width: 400px; /* Keep same as screen */
                margin-left: auto;
            }

            .totals-table td {
                padding: 0.5rem 0.75rem; /* Keep same as screen */
            }

            .totals-label {
                font-weight: 500;
                color: var(--text-secondary);
            }

            .totals-value {
                text-align: right;
                font-weight: 600;
                color: var(--text-primary);
            }

            .grand-total-row {
                background: var(--xobo-light-gray) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                border-top: 2px solid var(--xobo-border);
            }

            .grand-total-row .totals-label {
                font-weight: 700;
                color: var(--xobo-primary) !important;
                font-size: 1.1rem; /* Keep same as screen */
            }

            .grand-total-row .totals-value {
                font-weight: 700;
                color: var(--xobo-primary) !important;
                font-size: 1.1rem; /* Keep same as screen */
            }

            /* Ensure footer prints correctly */
            .footer {
                background: white !important;
                border-top: 1px solid var(--xobo-border);
                padding: 0.5rem 0;
                margin-top: 1rem;
                page-break-inside: avoid;
            }

            .footer-bottom {
                text-align: center;
                color: var(--xobo-gray) !important;
                font-size: 14px;
            }
        }

        /* PDF Generation Styles */
        .pdf-generating {
            pointer-events: none;
            opacity: 0.7;
        }

        /* Ensure receipt container is properly formatted for PDF */
        .receipt-container {
            position: relative;
            page-break-inside: avoid;
        }

        .receipt-container * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            body {
                padding-top: 40px;
            }
            
            .navbar {
                padding: 0.5rem 1rem;
                height: 50px;
            }
            
            .navbar-brand {
                font-size: 1.3rem;
            }
            
            .navbar-brand img {
                width: 8vw;
            }
            
            .navbar-actions {
                gap: 0.5rem;
            }
            
            .navbar-btn {
                padding: 0.3rem 0.7rem;
                font-size: 0.75rem;
            }
            
            .receipt-container {
                margin: 1rem;
            }
            
            .receipt-header {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .header-left,
            .header-center,
            .header-right {
                flex: none;
            }
            
            .header-left {
                justify-content: center;
            }
            
            .receipt-title {
                font-size: 1.5rem;
            }
            
            .receipt-date {
                font-size: 0.9rem;
            }
            
            .order-info-line {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }
            
            .table-section {
                padding: 1rem;
            }
            
            .data-table {
                font-size: 0.8rem;
            }
            
            .data-table th,
            .data-table td {
                padding: 0.5rem;
            }
            
            .totals-table {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            body {
                padding-top: 35px;
            }
            
            .navbar {
                padding: 0.4rem 0.8rem;
                height: 40px;
            }
            
            .navbar-brand {
                font-size: 1.1rem;
            }
            
            .navbar-brand img {
                width: 8vw;
            }
            
            .navbar-actions {
                gap: 0.3rem;
            }
            
            .navbar-btn {
                padding: 0.2rem 0.5rem;
                font-size: 0.7rem;
            }
            
            .navbar-btn i {
                font-size: 0.7rem;
            }
            
            .receipt-header {
                padding: 0.75rem;
            }
            
            .company-name {
                font-size: 1.1rem;
            }
            
            .company-name img {
                width: 6vw;
            }
            
            .receipt-title {
                font-size: 1.3rem;
            }
            
            .receipt-date {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="navbar-brand">
            <img src="../assets/images/xobo-logo.png" alt="XOBO MART">
        </div>
        <div class="navbar-actions">
            <button onclick="window.print()" class="navbar-btn navbar-btn-primary">
                <i class="fas fa-print"></i>
                Print
            </button>
            <button onclick="downloadPDF(event)" class="navbar-btn navbar-btn-primary">
                <i class="fas fa-download"></i>
                Download PDF
            </button>
            <a href="../index.php" class="navbar-btn navbar-btn-secondary">
                <i class="fas fa-home"></i>
                Back to Home
            </a>
        </div>
    </nav>

    <div class="receipt-container">
        <!-- Receipt Header -->
        <div class="receipt-header">
            <div class="header-left">
                <div class="company-name">
                    <img src="../assets/images/xobo-logo.png" alt="XOBO MART">
                </div>
            </div>
            <div class="header-center">
                <div class="receipt-title">Receipt</div>
            </div>
            <div class="header-right">
                <div class="receipt-date"><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></div>
            </div>
        </div>
        
        <!-- Order Info Line -->
        <div class="order-info-line">
            Order #<?php echo htmlspecialchars($orderId); ?> | <?php echo htmlspecialchars($order['company_name']); ?>
        </div>

        <!-- Product Information Table -->
        <div class="table-section">
            <div class="section-title">
                <i class="fas fa-box"></i>
                Product Information
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>SKU</th>
                        <th style="text-align: center;">Qty</th>
                        <th style="text-align: right;">Weight (kg)</th>
                        <th style="text-align: right;">Unit Price</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $item): ?>
                    <tr>
                        <td>
                            <div class="product-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        </td>
                        <td>
                            <span class="product-sku"><?php echo htmlspecialchars($item['sku']); ?></span>
                        </td>
                        <td class="quantity"><?php echo $item['quantity']; ?></td>
                        <td class="weight"><?php echo number_format($item['weight_kg'] * $item['quantity'], 2); ?></td>
                        <td class="price"><?php echo formatCurrency($item['rate_ksh']); ?></td>
                        <td class="total"><?php echo formatCurrency($item['line_total']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Delivery Details Table -->
        <?php if (!empty($deliveryDetails)): ?>
        <div class="table-section">
            <div class="section-title">
                <i class="fas fa-truck"></i>
                Delivery Details
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Destination</th>
                        <th>Company</th>
                        <th>Address</th>
                        <th>Recipient</th>
                        <th>Phone</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $item): ?>
                        <?php if (isset($deliveryByProduct[$item['product_id']])): ?>
                            <?php $delivery = $deliveryByProduct[$item['product_id']]; ?>
                            <tr>
                                <td class="product-name"><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($delivery['destination'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($delivery['company_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($delivery['company_address'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($delivery['recipient_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($delivery['recipient_phone'] ?? '-'); ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Order Totals Table -->
        <div class="table-section">
            <div class="section-title">
                <i class="fas fa-calculator"></i>
                Order Summary
            </div>
            <table class="data-table totals-table">
                <tbody>
                    <tr>
                        <td class="totals-label">Total Items:</td>
                        <td class="totals-value"><?php echo $totalItems; ?></td>
                    </tr>
                    <tr>
                        <td class="totals-label">Total Weight:</td>
                        <td class="totals-value"><?php echo number_format($totalWeight, 2); ?> kg</td>
                    </tr>
                    <tr>
                        <td class="totals-label">Subtotal:</td>
                        <td class="totals-value"><?php echo formatCurrency($order['total_ksh']); ?></td>
                    </tr>
                    <tr>
                        <td class="totals-label">Delivery:</td>
                        <td class="totals-value">As specified</td>
                    </tr>
                    <tr>
                        <td class="totals-label">Taxes:</td>
                        <td class="totals-value">Included</td>
                    </tr>
                    <tr class="grand-total-row">
                        <td class="totals-label">Grand Total:</td>
                        <td class="totals-value"><?php echo formatCurrency($order['total_ksh']); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>


    </div>

    <script>
        // Print functionality
        function printReceipt() {
            window.print();
        }

        // PDF Download functionality using browser's print-to-PDF
        function downloadPDF(event) {
            const receiptTitle = 'Receipt_<?php echo $orderId; ?>_<?php echo date('Y-m-d', strtotime($order['created_at'])); ?>';
            const button = event.target.closest('button');
            
            // Show user instruction and trigger print
            const originalButtonText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-file-pdf"></i> Opening Print Dialog...';
            button.disabled = true;
            
            // Set document title for PDF filename
            const originalTitle = document.title;
            document.title = receiptTitle;
            
            // Brief delay to show the button state change
            setTimeout(() => {
                // Trigger print dialog
                window.print();
                
                // Reset button and title after a short delay
                setTimeout(() => {
                    button.innerHTML = originalButtonText;
                    button.disabled = false;
                    document.title = originalTitle;
                }, 1000);
            }, 300);
        }

        // Hide browser headers and footers for printing
        window.addEventListener('beforeprint', function() {
            document.title = 'Receipt_<?php echo $orderId; ?>';
        });

        // Smooth reveal animation
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.receipt-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                container.style.transition = 'all 0.5s ease-out';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'p':
                        e.preventDefault();
                        window.print();
                        break;
                    case 's':
                        e.preventDefault();
                        downloadPDF();
                        break;
                }
            }
        });
    </script>

    <!-- XOBO-MART STYLE FOOTER -->
    <footer class="footer" style="background: white !important; padding: 1rem 0; margin-top: 3rem; border-top: 1px solid var(--xobo-border); width: 100%;">
        <div class="container" style="max-width: 800px; margin: 0 auto; padding: 0 2rem;">
            <div class="footer-bottom" style="text-align: center; color: var(--xobo-gray); font-size: 14px;">
                <p>&copy; 2025 XOBO MART. ALL RIGHTS RESERVED.</p>
            </div>
        </div>
    </footer>
</body>
</html> 