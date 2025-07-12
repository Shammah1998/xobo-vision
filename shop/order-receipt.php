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

// Get accessories for this order
$orderAccessories = [];
$stmt = $pdo->prepare("SELECT * FROM order_accessories WHERE order_id = ?");
$stmt->execute([$orderId]);
$accessories = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($accessories as $accessory) {
    $orderAccessories[$accessory['main_product_id']][] = $accessory;
}

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
        /* Enhanced PDF rendering styles */
        .pdf-optimized {
            font-family: 'Arial', 'Helvetica', sans-serif !important;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
        }
        
        /* Dynamic layout helpers */
        .compact-layout { 
            font-size: 12px; 
            line-height: 1.3; 
        }
        
        .compact-layout .data-table th,
        .compact-layout .data-table td { 
            padding: 0.5rem; 
        }
        
        .expanded-layout { 
            font-size: 14px; 
            line-height: 1.5; 
        }
        
        /* Signature section optimization */
        .signature-section {
            min-height: 120px;
            page-break-inside: avoid;
        }
        
        /* Accessories styling for PDF */
        .accessories-row {
            background-color: var(--xobo-light-gray) !important;
            font-size: 0.85rem;
        }
        
        @media print {
            body, html {
                width: 210mm;
                height: 297mm;
                margin: 0;
                padding: 0;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .receipt-container {
                width: 100%;
                max-width: 800px;
                margin: 0 auto;
                page-break-inside: avoid;
                break-inside: avoid;
                box-shadow: none;
            }
            .table-section, .order-info-line, .receipt-header {
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .navbar, .footer {
                display: none !important;
            }
            .signature-section {
                page-break-inside: avoid;
                min-height: 100px;
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
                    <?php if (strtolower(trim($item['name'])) === 'vision plus accessories' && !empty($orderAccessories[$item['product_id']])): ?>
                        <tr style="background:#f8f9fa;">
                            <td colspan="4" style="padding-left:2rem;">
                                <div style="font-size:0.9rem; color:#666; margin-bottom:0.5rem;">
                                    <i class="fas fa-puzzle-piece" style="margin-right:0.5rem;"></i>
                                    <strong>Included Accessories:</strong>
                                </div>
                                <table style="width:100%; margin-left:1rem; font-size:0.85rem;">
                                    <?php foreach ($orderAccessories[$item['product_id']] as $accessory): ?>
                                    <tr style="border:none; background:transparent;">
                                        <td style="padding:2px 0; border:none;">• <?php echo htmlspecialchars($accessory['accessory_name']); ?></td>
                                        <td style="padding:2px 0; border:none; color:#888;">SKU: <?php echo htmlspecialchars($accessory['accessory_sku']); ?></td>
                                        <td style="padding:2px 0; border:none; color:#888; text-align:right;"><?php echo number_format($accessory['accessory_weight'], 2); ?> kg</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </table>
                            </td>
                        </tr>
                    <?php endif; ?>
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
                        <th>Pick Up</th>
                        <th>Drop Off</th>
                        <th>Additional Notes</th>
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
                                <td><?php echo htmlspecialchars($delivery['pick_up'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($delivery['drop_off'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($delivery['additional_notes'] ?? '-'); ?></td>
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
    
    <!-- Professional PDF Generation Overlay -->
    <div id="pdf-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(22, 35, 77, 0.95); z-index: 9999; align-items: center; justify-content: center; flex-direction: column;">
        <div style="background: white; padding: 3rem; border-radius: 12px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); text-align: center; max-width: 400px;">
            <div style="width: 60px; height: 60px; margin: 0 auto 1.5rem; border: 4px solid #f3f3f3; border-top: 4px solid #16234d; border-radius: 50%; animation: spin 1s linear infinite;"></div>
            <h3 style="color: #16234d; margin-bottom: 1rem; font-size: 1.3rem;">Generating Professional PDF</h3>
            <p style="color: #666; margin-bottom: 1rem; line-height: 1.5;">Creating your state-of-the-art receipt document with optimized layout and professional formatting...</p>
            <div style="background: #f8f9fa; padding: 0.75rem; border-radius: 6px; font-size: 0.9rem; color: #666;">
                <div id="pdf-progress">Preparing document structure...</div>
            </div>
        </div>
    </div>
    
    <footer class="footer hide-for-print">
        <p>&copy; <?php echo date('Y'); ?> XOBO. ALL RIGHTS RESERVED.</p>
    </footer>
    
    <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        #pdf-overlay {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Responsive PDF button */
        @media (max-width: 768px) {
            .navbar-actions {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .navbar-btn-primary {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        // State-of-the-art PDF generation with dynamic layout optimization
        function downloadPDF(event) {
            event.preventDefault();
            const button = event.currentTarget;
            const originalButtonHTML = button.innerHTML;
            const overlay = document.getElementById('pdf-overlay');
            const progressDiv = document.getElementById('pdf-progress');
            
            // Show professional loading overlay
            overlay.style.display = 'flex';
            button.innerHTML = '<i class="fas fa-file-pdf"></i> Processing...';
            button.disabled = true;
            
            // Progressive status updates
            let progressStep = 0;
            const progressMessages = [
                'Preparing document structure...',
                'Loading XOBO branding assets...',
                'Optimizing layout for content...',
                'Processing product information...',
                'Adding delivery details...',
                'Generating signature section...',
                'Finalizing PDF document...'
            ];
            
            const progressInterval = setInterval(() => {
                if (progressStep < progressMessages.length - 1) {
                    progressDiv.textContent = progressMessages[progressStep++];
                }
            }, 800);
            
            // Create optimized PDF layout
            generateOptimizedPDF().then(() => {
                clearInterval(progressInterval);
                progressDiv.textContent = 'PDF generated successfully!';
                
                setTimeout(() => {
                    overlay.style.display = 'none';
                    button.innerHTML = originalButtonHTML;
                    button.disabled = false;
                }, 1000);
            }).catch((error) => {
                console.error('PDF generation failed:', error);
                clearInterval(progressInterval);
                progressDiv.textContent = 'Error occurred. Please try again.';
                
                setTimeout(() => {
                    overlay.style.display = 'none';
                    button.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Failed - Try Again';
                    button.disabled = false;
                    
                    // Reset button after 3 seconds
                    setTimeout(() => {
                        button.innerHTML = originalButtonHTML;
                    }, 3000);
                }, 2000);
            });
        }

        async function generateOptimizedPDF() {
            const { jsPDF } = window.jspdf;
            
            // Dynamic content analysis for layout optimization
            const orderItems = <?php echo json_encode($orderItems); ?>;
            const orderAccessories = <?php echo json_encode($orderAccessories); ?>;
            const deliveryDetails = <?php echo json_encode($deliveryByProduct); ?>;
            
            const contentAnalysis = {
                productCount: orderItems.length,
                hasAccessories: Object.keys(orderAccessories).length > 0,
                hasDelivery: Object.keys(deliveryDetails).length > 0,
                totalContentRows: orderItems.length + Object.values(orderAccessories).flat().length + Object.keys(deliveryDetails).length
            };
            
            // Determine optimal layout based on content
            const isCompactLayout = contentAnalysis.totalContentRows > 8;
            const fontScale = isCompactLayout ? 0.9 : 1.0;
            const spacingScale = isCompactLayout ? 0.8 : 1.0;
            
            // Create PDF with optimal settings
            const pdf = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4',
                compress: true
            });
            
            // PDF dimensions with dynamic margins
            const pageWidth = pdf.internal.pageSize.getWidth();
            const pageHeight = pdf.internal.pageSize.getHeight();
            const margin = isCompactLayout ? 12 : 15;
            const contentWidth = pageWidth - (margin * 2);
            
            let yPosition = margin;
            
            // Professional color scheme with better contrast
            const colors = {
                primary: '#16234d',
                secondary: '#555555',  // Darker for better readability
                accent: '#f8f9fa',
                border: '#dddddd',
                text: '#222222',       // Darker for better contrast
                lightText: '#666666'   // Darker than before but still subtle
            };
            
            // Layout configuration object
            const layoutConfig = {
                isCompact: isCompactLayout,
                fontScale: fontScale,
                spacingScale: spacingScale,
                margin: margin,
                contentAnalysis: contentAnalysis
            };
            
            // Add header with optimized layout
            yPosition = await addPDFHeader(pdf, pageWidth, colors, yPosition, layoutConfig);
            
            // Add order info line
            yPosition = addOrderInfo(pdf, pageWidth, colors, yPosition, margin, layoutConfig);
            
            // Add product information section
            yPosition = await addProductSection(pdf, pageWidth, pageHeight, colors, yPosition, margin, contentWidth, layoutConfig);
            
            // Add delivery details if present
            if (contentAnalysis.hasDelivery) {
                yPosition = await addDeliverySection(pdf, pageWidth, pageHeight, colors, yPosition, margin, contentWidth, layoutConfig);
            }
            
            // Add order summary
            yPosition = addOrderSummary(pdf, pageWidth, colors, yPosition, margin, contentWidth, layoutConfig);
            
            // Add signature section
            addSignatureSection(pdf, pageWidth, pageHeight, colors, yPosition, margin, contentWidth, layoutConfig);
            
            // Add professional footer
            addPDFFooter(pdf, pageWidth, pageHeight, colors, margin, layoutConfig);
            
            // Save with dynamic filename
            const orderNumber = '<?php echo $orderId; ?>';
            const companyName = '<?php echo preg_replace("/[^a-zA-Z0-9\-_]/", "", $order["company_name"]); ?>';
            const date = new Date().toISOString().split('T')[0];
            const filename = `XOBO_Receipt_${orderNumber}_${companyName}_${date}.pdf`;
            
            pdf.save(filename);
        }
        
        async function addPDFHeader(pdf, pageWidth, colors, yPos, layoutConfig = {}) {
            // Professional header background
            pdf.setFillColor(colors.primary);
            pdf.rect(0, 0, pageWidth, 35, 'F');
            
            // Define consistent vertical alignment position
            const textBaselineY = 20;
            const logoHeight = 20; // Slightly reduced for better proportion
            const logoY = textBaselineY - (logoHeight / 2); // Center the logo with the text
            
            // Load and embed XOBO logo
            try {
                const logoBase64 = await loadImageAsBase64('../assets/images/xobo-logo-white.png');
                pdf.addImage(logoBase64, 'PNG', 15, logoY, 40, logoHeight); // x, y, width, height - aligned with text
            } catch (error) {
                console.log('Logo loading failed, using text fallback');
                // Fallback to text logo - aligned with same baseline
                pdf.setFont('helvetica', 'bold');
                pdf.setFontSize(20);
                pdf.setTextColor(255, 255, 255);
                pdf.text('XOBO', 15, textBaselineY);
            }
            
            // Receipt title (centered) 
            pdf.setFont('helvetica', 'normal');
            pdf.setFontSize(12);
            pdf.setTextColor(255, 255, 255);
            const title = 'DELIVERY RECEIPT';
            const titleWidth = pdf.getStringUnitWidth(title) * 12 / pdf.internal.scaleFactor;
            pdf.text(title, (pageWidth - titleWidth) / 2, textBaselineY);
            
            // Date (right aligned)
            pdf.setFontSize(12);
            const receiptDate = '<?php echo date("M d, Y H:i", strtotime($order["created_at"])); ?>';
            const dateWidth = pdf.getStringUnitWidth(receiptDate) * 12 / pdf.internal.scaleFactor;
            pdf.text(receiptDate, pageWidth - 15 - dateWidth, textBaselineY);
            
            return 45; // Return new Y position
        }
        
        function addOrderInfo(pdf, pageWidth, colors, yPos, margin, layoutConfig = {}) {
            // Order info background
            pdf.setFillColor(colors.accent);
            pdf.rect(0, yPos - 5, pageWidth, 12, 'F');
            
            // Order info text
            pdf.setFont('helvetica', 'normal');
            pdf.setFontSize(11);
            pdf.setTextColor(colors.secondary);
            
            const orderInfo = `Order #<?php echo htmlspecialchars($orderId); ?> | <?php echo htmlspecialchars($order["company_name"]); ?>`;
            const textWidth = pdf.getStringUnitWidth(orderInfo) * 11 / pdf.internal.scaleFactor;
            pdf.text(orderInfo, (pageWidth - textWidth) / 2, yPos + 2);
            
            return yPos + 20;
        }
        
        async function addProductSection(pdf, pageWidth, pageHeight, colors, yPos, margin, contentWidth, layoutConfig = {}) {
            // Section title
            pdf.setFont('helvetica', 'bold');
            pdf.setFontSize(14);
            pdf.setTextColor(colors.primary);
            pdf.text('Product Information', margin, yPos);
            yPos += 12;
            
            // Table headers
            const colWidths = [contentWidth * 0.4, contentWidth * 0.25, contentWidth * 0.15, contentWidth * 0.2];
            const headers = ['Product Name', 'SKU', 'Qty', 'Weight (kg)'];
            
            // Header background
            pdf.setFillColor(colors.accent);
            pdf.rect(margin, yPos - 3, contentWidth, 10, 'F');
            
            // Header text
            pdf.setFont('helvetica', 'bold');
            pdf.setFontSize(10);
            pdf.setTextColor(colors.primary);
            
            let xPos = margin;
            headers.forEach((header, index) => {
                pdf.text(header, xPos + 2, yPos + 3);
                xPos += colWidths[index];
            });
            
            yPos += 12;
            
            // Product rows
            pdf.setFont('helvetica', 'normal');
            pdf.setFontSize(9);
            pdf.setTextColor(colors.text);
            
            const orderItems = <?php echo json_encode($orderItems); ?>;
            const orderAccessories = <?php echo json_encode($orderAccessories); ?>;
            
            for (const item of orderItems) {
                // Check if we need a new page
                if (yPos > pageHeight - 60) {
                    pdf.addPage();
                    yPos = margin + 20;
                }
                
                // Product row
                xPos = margin;
                
                // Product name (with word wrap if needed)
                const productName = item.name;
                const maxWidth = colWidths[0] - 4;
                const wrappedName = pdf.splitTextToSize(productName, maxWidth);
                pdf.text(wrappedName, xPos + 2, yPos + 3);
                
                // SKU
                xPos += colWidths[0];
                pdf.setFont('helvetica', 'normal');
                pdf.setFillColor(240, 240, 240);
                pdf.roundedRect(xPos + 1, yPos - 1, colWidths[1] - 2, 6, 1, 1, 'F');
                pdf.setFont('courier', 'normal');
                pdf.setFontSize(8);
                pdf.text(item.sku, xPos + 3, yPos + 3);
                
                // Quantity (centered)
                pdf.setFont('helvetica', 'normal');
                pdf.setFontSize(9);
                xPos += colWidths[1];
                const qtyText = item.quantity.toString();
                const qtyWidth = pdf.getStringUnitWidth(qtyText) * 9 / pdf.internal.scaleFactor;
                pdf.text(qtyText, xPos + (colWidths[2] - qtyWidth) / 2, yPos + 3);
                
                // Weight (right aligned)
                xPos += colWidths[2];
                const weight = (item.weight_kg * item.quantity).toFixed(2);
                const weightText = weight + ' kg';
                const weightWidth = pdf.getStringUnitWidth(weightText) * 9 / pdf.internal.scaleFactor;
                pdf.text(weightText, xPos + colWidths[3] - weightWidth - 2, yPos + 3);
                
                let rowHeight = Math.max(8, wrappedName.length * 4);
                yPos += rowHeight;
                
                // Add accessories if this is Vision Plus Accessories
                if (item.name.toLowerCase().trim() === 'vision plus accessories' && orderAccessories[item.product_id]) {
                    // Accessories background
                    pdf.setFillColor(248, 249, 250);
                    pdf.rect(margin, yPos, contentWidth, 3, 'F');
                    yPos += 5;
                    
                    // Accessories header
                    pdf.setFont('helvetica', 'bold');
                    pdf.setFontSize(9);
                    pdf.setTextColor(colors.text);
                    pdf.text('Included Accessories:', margin + 10, yPos + 3);
                    yPos += 10;
                    
                    // Accessories list with improved spacing and layout
                    pdf.setFont('helvetica', 'normal');
                    pdf.setFontSize(9); // Slightly larger font for better readability
                    
                    orderAccessories[item.product_id].forEach((accessory, index) => {
                        // Check if we need a new page before adding each accessory
                        if (yPos > pageHeight - 40) {
                            pdf.addPage();
                            yPos = margin + 20;
                        }
                        
                        // Accessory name with bullet point
                        pdf.setTextColor(colors.text);
                        const bulletText = `• ${accessory.accessory_name}`;
                        pdf.text(bulletText, margin + 15, yPos + 4);
                        
                        // Calculate dynamic positioning for SKU and weight to avoid overlap
                        const nameWidth = pdf.getStringUnitWidth(bulletText) * 9 / pdf.internal.scaleFactor;
                        const skuStartX = Math.max(margin + 15 + nameWidth + 10, margin + 90); // Minimum distance or flexible spacing
                        
                        // SKU with better contrast and positioning
                        pdf.setTextColor(colors.secondary);
                        const skuText = `SKU: ${accessory.accessory_sku}`;
                        pdf.text(skuText, skuStartX, yPos + 4);
                        
                        // Weight (right aligned with sufficient margin)
                        pdf.setTextColor(colors.secondary);
                        const weightText = `${parseFloat(accessory.accessory_weight).toFixed(2)} kg`;
                        const weightWidth = pdf.getStringUnitWidth(weightText) * 9 / pdf.internal.scaleFactor;
                        pdf.text(weightText, margin + contentWidth - weightWidth - 5, yPos + 4);
                        
                        // Increased line spacing for better readability
                        yPos += 9;
                    });
                    
                    // Add extra spacing after accessories section
                    yPos += 8;
                }
                
                // Row border
                pdf.setDrawColor(colors.border);
                pdf.line(margin, yPos, margin + contentWidth, yPos);
                yPos += 3;
            }
            
            return yPos + 10;
        }
        
        async function addDeliverySection(pdf, pageWidth, pageHeight, colors, yPos, margin, contentWidth, layoutConfig = {}) {
            // Check if we need a new page
            if (yPos > pageHeight - 100) {
                pdf.addPage();
                yPos = margin + 20;
            }
            
            // Section title
            pdf.setFont('helvetica', 'bold');
            pdf.setFontSize(14);
            pdf.setTextColor(colors.primary);
            pdf.text('Delivery Details', margin, yPos);
            yPos += 12;
            
            // Check if delivery details exist
            const hasDeliveryData = Object.keys(<?php echo json_encode($deliveryByProduct); ?>).length > 0;
            
            if (hasDeliveryData) {
                // Delivery table headers
                const deliveryHeaders = ['Product', 'Pick Up', 'Drop Off', 'Additional Notes', 'Recipient', 'Phone'];
                const deliveryColWidths = [
                    contentWidth * 0.2, contentWidth * 0.15, contentWidth * 0.2, 
                    contentWidth * 0.2, contentWidth * 0.15, contentWidth * 0.1
                ];
                
                // Header background
                pdf.setFillColor(colors.accent);
                pdf.rect(margin, yPos - 3, contentWidth, 10, 'F');
                
                // Header text
                pdf.setFont('helvetica', 'bold');
                pdf.setFontSize(9);
                pdf.setTextColor(colors.primary);
                
                let xPos = margin;
                deliveryHeaders.forEach((header, index) => {
                    pdf.text(header, xPos + 1, yPos + 3);
                    xPos += deliveryColWidths[index];
                });
                
                yPos += 12;
                
                // Delivery data
                pdf.setFont('helvetica', 'normal');
                pdf.setFontSize(8.5);
                pdf.setTextColor(colors.text);
                
                const deliveryDetails = <?php echo json_encode($deliveryByProduct); ?>;
                const orderItems = <?php echo json_encode($orderItems); ?>;
                
                orderItems.forEach(item => {
                    if (deliveryDetails[item.product_id]) {
                        const delivery = deliveryDetails[item.product_id];
                        
                        if (yPos > pageHeight - 30) {
                            pdf.addPage();
                            yPos = margin + 20;
                        }
                        
                        xPos = margin;
                        const deliveryData = [
                            item.name,
                            delivery.pick_up || '-',
                            delivery.drop_off || '-',
                            delivery.additional_notes || '-',
                            delivery.recipient_name || '-',
                            delivery.recipient_phone || '-'
                        ];
                        
                        deliveryData.forEach((data, index) => {
                            const wrappedText = pdf.splitTextToSize(data, deliveryColWidths[index] - 2);
                            pdf.text(wrappedText, xPos + 1, yPos + 3);
                            xPos += deliveryColWidths[index];
                        });
                        
                        yPos += 10;
                        pdf.setDrawColor(colors.border);
                        pdf.line(margin, yPos, margin + contentWidth, yPos);
                        yPos += 3;
                    }
                });
            }
            
            return yPos + 10;
        }
        
        function addOrderSummary(pdf, pageWidth, colors, yPos, margin, contentWidth, layoutConfig = {}) {
            // Section title
            pdf.setFont('helvetica', 'bold');
            pdf.setFontSize(14);
            pdf.setTextColor(colors.primary);
            pdf.text('Order Summary', margin, yPos);
            yPos += 12;
            
            // Summary box
            const summaryWidth = 80;
            const summaryX = pageWidth - margin - summaryWidth;
            
            pdf.setFillColor(colors.accent);
            pdf.rect(summaryX, yPos - 5, summaryWidth, 25, 'F');
            pdf.setDrawColor(colors.border);
            pdf.rect(summaryX, yPos - 5, summaryWidth, 25, 'S');
            
            // Summary content
            pdf.setFont('helvetica', 'normal');
            pdf.setFontSize(10);
            pdf.setTextColor(colors.text);
            
            const totalItems = <?php echo $totalItems; ?>;
            const totalWeight = <?php echo number_format($totalWeight, 2); ?>;
            
            pdf.text(`Total Items: ${totalItems}`, summaryX + 5, yPos + 3);
            pdf.text(`Total Weight: ${totalWeight} kg`, summaryX + 5, yPos + 10);
            
            return yPos + 35;
        }
        
        function addSignatureSection(pdf, pageWidth, pageHeight, colors, yPos, margin, contentWidth, layoutConfig = {}) {
            // Check if we need a new page for signatures
            if (yPos > pageHeight - 80) {
                pdf.addPage();
                yPos = margin + 20;
            }
            
            // Section title
            pdf.setFont('helvetica', 'bold');
            pdf.setFontSize(14);
            pdf.setTextColor(colors.primary);
            pdf.text('Signatures', margin, yPos);
            yPos += 15;
            
            // Signature boxes
            const boxWidth = (contentWidth - 10) / 2;
            const boxHeight = 50;
            
            // Receiver box
            pdf.setDrawColor(colors.border);
            pdf.rect(margin, yPos, boxWidth, boxHeight, 'S');
            
            pdf.setFont('helvetica', 'bold');
            pdf.setFontSize(12);
            pdf.setTextColor(colors.primary);
            pdf.text('Receiver', margin + 5, yPos + 8);
            
            pdf.setFont('helvetica', 'normal');
            pdf.setFontSize(9);
            pdf.setTextColor(colors.text);
            
            // Receiver fields
            pdf.text('Name:', margin + 5, yPos + 18);
            pdf.line(margin + 20, yPos + 19, margin + boxWidth - 5, yPos + 19);
            
            pdf.text('Date:', margin + 5, yPos + 30);
            pdf.line(margin + 20, yPos + 31, margin + boxWidth - 5, yPos + 31);
            
            pdf.text('Signature:', margin + 5, yPos + 42);
            pdf.line(margin + 30, yPos + 43, margin + boxWidth - 5, yPos + 43);
            
            // Driver box
            const driverX = margin + boxWidth + 10;
            pdf.rect(driverX, yPos, boxWidth, boxHeight, 'S');
            
            pdf.setFont('helvetica', 'bold');
            pdf.setFontSize(12);
            pdf.setTextColor(colors.primary);
            pdf.text('Driver', driverX + 5, yPos + 8);
            
            pdf.setFont('helvetica', 'normal');
            pdf.setFontSize(9);
            pdf.setTextColor(colors.text);
            
            // Driver fields
            pdf.text('Name:', driverX + 5, yPos + 18);
            pdf.line(driverX + 20, yPos + 19, driverX + boxWidth - 5, yPos + 19);
            
            pdf.text('Date:', driverX + 5, yPos + 30);
            pdf.line(driverX + 20, yPos + 31, driverX + boxWidth - 5, yPos + 31);
            
            pdf.text('Signature:', driverX + 5, yPos + 42);
            pdf.line(driverX + 30, yPos + 43, driverX + boxWidth - 5, yPos + 43);
        }
        
        function addPDFFooter(pdf, pageWidth, pageHeight, colors, margin, layoutConfig = {}) {
            // Footer line
            pdf.setDrawColor(colors.border);
            pdf.line(margin, pageHeight - 20, pageWidth - margin, pageHeight - 20);
            
            // Footer text
            pdf.setFont('helvetica', 'normal');
            pdf.setFontSize(8);
            pdf.setTextColor(colors.lightText);
            
            const footerText = `© ${new Date().getFullYear()} XOBO. ALL RIGHTS RESERVED.`;
            const footerWidth = pdf.getStringUnitWidth(footerText) * 8 / pdf.internal.scaleFactor;
            pdf.text(footerText, (pageWidth - footerWidth) / 2, pageHeight - 10);
            
            // Page number
            const pageCount = pdf.internal.getNumberOfPages();
            for (let i = 1; i <= pageCount; i++) {
                pdf.setPage(i);
                pdf.setFont('helvetica', 'normal');
                pdf.setFontSize(8);
                pdf.setTextColor(colors.lightText);
                pdf.text(`Page ${i} of ${pageCount}`, pageWidth - margin - 20, pageHeight - 10);
            }
        }
        
        // Helper function to load images as base64
        function loadImageAsBase64(src) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    canvas.width = this.naturalWidth;
                    canvas.height = this.naturalHeight;
                    ctx.drawImage(this, 0, 0);
                    const dataURL = canvas.toDataURL('image/png');
                    resolve(dataURL);
                };
                img.onerror = function() {
                    reject(new Error('Failed to load image: ' + src));
                };
                img.src = src;
            });
        }

        // Enhanced keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                downloadPDF(e);
            }
        });
        
        // Add CSS helper for delivery detection
        document.addEventListener('DOMContentLoaded', function() {
            // Helper function to check if delivery section exists and is visible
            window.hasDeliveryDetails = function() {
                const deliverySection = document.querySelector('.table-section .section-title');
                if (!deliverySection) return false;
                
                const sections = Array.from(document.querySelectorAll('.section-title'));
                return sections.some(section => section.textContent.includes('Delivery Details'));
            };
        });
    </script>
</body>
</html> 