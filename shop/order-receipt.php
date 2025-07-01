<?php
require_once '../config/config.php';
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?error=Session expired, please log in again.');
    exit;
}
require_once '../config/db.php';
require_once '../includes/functions.php';

requireRole(['user', 'super_admin', 'company_admin']);

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$userId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'];
$role = $_SESSION['role'] ?? '';

// Get the referer URL
$referer = $_SERVER['HTTP_REFERER'] ?? '';

// Determine the button URL and text based on role and referer
$buttonUrl = '../index';  // Default URL for regular users
$buttonText = '<i class="fas fa-home"></i> Home';
$buttonClass = 'home-button';

if (isAdmin($pdo) || $role === 'company_admin') {
    $buttonUrl = '../admin/orders';
    $buttonText = '<i class="fas fa-arrow-left"></i> Back to Orders';
    $buttonClass = 'back-button';
} else if (strpos($referer, 'shop/orders') !== false) {
    // If user came from the user orders page
    $buttonUrl = 'orders';
    $buttonText = '<i class="fas fa-arrow-left"></i> Back to Orders';
    $buttonClass = 'back-button';
}

// Determine the home URL based on role and referrer
$homeUrl = '../index';  // Default URL for regular users
if (isAdmin($pdo) || $role === 'company_admin') {
    $homeUrl = '../admin/dashboard';
}

global $pdo;
if (!$orderId) {
    header('Location: ../index');
    exit;
}

// If admin, fetch by order_id only. If not, restrict by user_id and company_id.
if (isAdmin($pdo) || $role === 'company_admin') {
    $stmt = $pdo->prepare("SELECT o.*, c.name as company_name, u.email as user_email 
                           FROM orders o 
                           JOIN companies c ON o.company_id = c.id 
                           JOIN users u ON o.user_id = u.id 
                           WHERE o.id = ?");
    $stmt->execute([$orderId]);
} else {
    $stmt = $pdo->prepare("SELECT o.*, c.name as company_name, u.email as user_email 
                           FROM orders o 
                           JOIN companies c ON o.company_id = c.id 
                           JOIN users u ON o.user_id = u.id 
                           WHERE o.id = ? AND o.user_id = ? AND o.company_id = ?");
    $stmt->execute([$orderId, $userId, $companyId]);
}
$order = $stmt->fetch();

if (!$order) {
    header('Location: ../index');
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

$tabTitle = (isAdmin($pdo) || $role === 'company_admin') ? 'Admin Panel' : 'User Panel';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $tabTitle; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="icon" type="image/png" href="../assets/images/XDL-ICON.png">
    <style>
        :root {
            --xobo-primary: #16234d;
            --xobo-primary-hover: #1a2654;
            --xobo-light-gray: #f8f9fa;
            --xobo-gray: #666666;
            --xobo-border: #ddd;
            --xobo-shadow: rgba(0, 0, 0, 0.1);
            --text-primary: #333;
            --text-secondary: #666;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--xobo-light-gray);
            color: var(--text-primary);
            line-height: 1.5;
            font-size: 14px;
            padding-top: 60px;
        }
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: white;
            border-bottom: 2px solid var(--xobo-border);
            padding: 0.6rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 1000;
            box-shadow: 0 2px 4px var(--xobo-shadow);
            height: 55px;
        }
        .navbar-brand { display: flex; align-items: center; }
        .navbar-brand img { height: 90px; width: auto; object-fit: contain; }
        .navbar-actions { display: flex; gap: 1rem; align-items: center; }
        .navbar-btn-primary {
            background: var(--xobo-primary);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 14px;
            transition: background-color 0.2s ease;
        }
        .navbar-btn-primary:hover { background: var(--xobo-primary-hover); }
        .receipt-container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            box-shadow: 0 2px 10px var(--xobo-shadow);
            border-radius: 8px;
            overflow: hidden;
        }
        .receipt-header {
            background: var(--xobo-primary);
            color: white;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }
        .receipt-title {
            font-size: 1.3rem;
            font-weight: 600;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
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
        .table-section { padding: 2rem; border-bottom: 1px solid var(--xobo-border); }
        .table-section:last-of-type { border-bottom: none; }
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--xobo-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
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
        }
        .data-table td { padding: 0.75rem; border-bottom: 1px solid #eee; }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .product-name { font-weight: 600; }
        .product-sku {
            font-family: monospace;
            background: var(--xobo-light-gray);
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
        }
        .totals-table { width: 400px; margin-left: auto; }
        .signature-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--xobo-primary);
            margin-bottom: 1.5rem;
        }
        .footer {
            background: white;
            padding: 1.5rem 0;
            text-align: center;
            font-size: 0.9rem;
            color: var(--xobo-gray);
            border-top: 1px solid var(--xobo-border);
        }
        @media print {
            body, html {
                width: 210mm;
                height: 297mm;
                margin: 0;
                padding: 0;
            }
            .receipt-container {
                width: 100%;
                max-width: 800px;
                margin: 0 auto;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .table-section, .order-info-line, .receipt-header {
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="<?php echo $homeUrl; ?>" class="navbar-brand">
            <img src="../assets/images/xobo-logo.png" alt="XOBO" class="logo">
        </a>
        <div class="navbar-actions">
            <a href="<?php echo $buttonUrl; ?>" class="navbar-btn-primary" style="margin-right: 1rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;">
                <?php echo $buttonText; ?>
            </a>
            <button onclick="downloadPDF(event)" class="navbar-btn-primary">
                <i class="fas fa-download"></i> Download PDF
            </button>
        </div>
    </nav>

    <div class="receipt-container">
        <div class="receipt-header">
            <img src="../assets/images/xobo-logo-white.png" alt="XOBO" style="height:75px; width:auto; object-fit:contain;">
            <div class="receipt-title">Receipt</div>
            <div class="receipt-date"><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></div>
        </div>
        <div class="order-info-line">
            Order #<?php echo htmlspecialchars($orderId); ?> | <?php echo htmlspecialchars($order['company_name']); ?>
        </div>
        <div class="table-section">
            <div class="section-title"><i class="fas fa-box"></i> Product Information</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>SKU</th>
                        <th style="text-align: center;">Qty</th>
                        <th style="text-align: right;">Weight (kg)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $item): ?>
                    <tr>
                        <td><div class="product-name"><?php echo htmlspecialchars($item['name']); ?></div></td>
                        <td><span class="product-sku"><?php echo htmlspecialchars($item['sku']); ?></span></td>
                        <td style="text-align:center;"><?php echo $item['quantity']; ?></td>
                        <td style="text-align:right;"><?php echo number_format($item['weight_kg'] * $item['quantity'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($deliveryDetails)): ?>
        <div class="table-section">
            <div class="section-title"><i class="fas fa-truck"></i> Delivery Details</div>
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
                        <?php if (isset($deliveryByProduct[$item['product_id']])): 
                            $delivery = $deliveryByProduct[$item['product_id']]; ?>
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
        <div class="table-section">
            <div class="section-title"><i class="fas fa-calculator"></i> Order Summary</div>
            <table class="data-table totals-table">
                <tbody>
                    <tr>
                        <td>Total Items:</td>
                        <td style="text-align:right;"><?php echo $totalItems; ?></td>
                    </tr>
                    <tr>
                        <td>Total Weight:</td>
                        <td style="text-align:right;"><?php echo number_format($totalWeight, 2); ?> kg</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-section signature-section">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 2rem;">
                <!-- Receiver Side -->
                <div style="flex: 1; display: flex; flex-direction: column;">
                    <div class="signature-title">Receiver</div>
                    <div style="margin-bottom: 2rem; width: 100%;">
                        <label style="font-size: 0.9rem; color: #666;">Name:</label>
                        <div style="border-bottom: 1.5px solid #bbb; height: 2.2em; margin-top: 0.5rem;"></div>
                    </div>
                    <div style="margin-bottom: 2rem; width: 100%;">
                        <label style="font-size: 0.9rem; color: #666;">Date:</label>
                        <div style="border-bottom: 1.5px solid #bbb; height: 2.2em; margin-top: 0.5rem;"></div>
                    </div>
                    <div style="margin-bottom: 2rem; width: 100%;">
                        <label style="font-size: 0.9rem; color: #666;">Signature:</label>
                        <div style="border-bottom: 1.5px solid #bbb; height: 2.2em; margin-top: 0.5rem;"></div>
                    </div>
                </div>

                <!-- Vertical Divider -->
                <div style="width: 1px; background-color: #e0e0e0; align-self: stretch;"></div>

                <!-- Driver Side -->
                <div style="flex: 1; display: flex; flex-direction: column;">
                    <div class="signature-title">Driver</div>
                    <div style="margin-bottom: 2rem; width: 100%;">
                        <label style="font-size: 0.9rem; color: #666;">Name:</label>
                        <div style="border-bottom: 1.5px solid #bbb; height: 2.2em; margin-top: 0.5rem;"></div>
                    </div>
                    <div style="margin-bottom: 2rem; width: 100%;">
                        <label style="font-size: 0.9rem; color: #666;">Date:</label>
                        <div style="border-bottom: 1.5px solid #bbb; height: 2.2em; margin-top: 0.5rem;"></div>
                    </div>
                    <div style="margin-bottom: 2rem; width: 100%;">
                        <label style="font-size: 0.9rem; color: #666;">Signature:</label>
                        <div style="border-bottom: 1.5px solid #bbb; height: 2.2em; margin-top: 0.5rem;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer class="footer hide-for-print">
        <p>&copy; <?php echo date('Y'); ?> XOBO. ALL RIGHTS RESERVED.</p>
    </footer>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadPDF(event) {
            event.preventDefault();
            const element = document.querySelector('.receipt-container');
            const button = event.currentTarget;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
            button.disabled = true;

            // Always scale to fit A4 page (1122px at 96dpi)
            const a4HeightPx = 1122;
            const originalTransform = element.style.transform;
            const originalTransformOrigin = element.style.transformOrigin;
            const scale = a4HeightPx / element.offsetHeight;
            element.style.transform = `scale(${scale})`;
            element.style.transformOrigin = 'top center';

            const opt = {
                margin:       0,
                filename:     'Receipt_<?php echo $orderId; ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save().then(() => {
                // Restore original transform after PDF generation
                element.style.transform = originalTransform;
                element.style.transformOrigin = originalTransformOrigin;
                button.innerHTML = '<i class="fas fa-download"></i> Download PDF';
                button.disabled = false;
            });
        }
    </script>
</body>
</html> 