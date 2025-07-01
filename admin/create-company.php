<?php
require_once '../config/config.php';
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?error=Session expired, please log in again.');
    exit;
}
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is admin (first user or super_admin)
if (!isAdmin($pdo)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$message = '';
$error = '';

// Handle company creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName = sanitize($_POST['company_name']);
    $description = sanitize($_POST['description']);
    $status = $_POST['status'];
    $products = $_POST['products'] ?? [];
    
    // Validation
    if (empty($companyName)) {
        $error = 'Please enter a company name.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Check if company name already exists
            $stmt = $pdo->prepare("SELECT id FROM companies WHERE name = ?");
            $stmt->execute([$companyName]);
            if ($stmt->fetch()) {
                throw new Exception('Company name already exists.');
            }
            
            // Insert company
            $stmt = $pdo->prepare("INSERT INTO companies (name, status) VALUES (?, ?)");
            $stmt->execute([$companyName, $status]);
            $companyId = $pdo->lastInsertId();
            
            // Insert products if any
            $productCount = 0;
            if (!empty($products)) {
                $productStmt = $pdo->prepare("INSERT INTO products (company_id, name, sku, weight_kg, rate_ksh) VALUES (?, ?, ?, ?, ?)");
                
                foreach ($products as $product) {
                    if (!empty($product['name']) && !empty($product['sku']) && 
                        !empty($product['weight_kg']) && !empty($product['rate_ksh'])) {
                        
                        $productStmt->execute([
                            $companyId,
                            sanitize($product['name']),
                            sanitize($product['sku']),
                            (float)$product['weight_kg'],
                            (float)$product['rate_ksh']
                        ]);
                        $productCount++;
                    }
                }
            }
            
            $pdo->commit();
            
            if ($productCount > 0) {
                $message = "Company '{$companyName}' created successfully with {$productCount} products. You can now invite users to this company.";
            } else {
                $message = "Company '{$companyName}' created successfully. You can now invite users and add products to this company.";
            }
            
            // Clear form
            $_POST = [];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

$pageTitle = 'Create Company';
include 'includes/admin_header.php';
?>

<!-- Success/Error Messages -->
<?php if ($message): ?>
    <div class="admin-card">
        <div class="alert alert-success">
            <?php echo htmlspecialchars($message); ?>
            <div style="margin-top: 1rem;">
                <a href="/xobo-c/admin/invite-user.php" 
                   style="display: inline-block; padding: 8px 16px; background: var(--xobo-primary); color: white; text-decoration: none; border-radius: 4px; font-size: 0.9rem;">
                    <i class="fas fa-user-plus"></i> Invite Users Now
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-card">
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    </div>
<?php endif; ?>

<!-- Create Company Form -->
<div class="admin-card">
    <h2 style="margin-bottom: 1rem; color: var(--xobo-primary);">
        <i class="fas fa-building"></i> Create New Company
    </h2>
    <p style="color: var(--xobo-gray); margin-bottom: 2rem;">
        Create a new company profile. After creating the company, you can invite users and assign roles using the "Invite User" feature.
    </p>
    
    <form method="POST" action="" style="max-width: 600px;">
        <div class="form-group">
            <label for="company_name">Company Name *</label>
            <input type="text" id="company_name" name="company_name" required 
                   value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>"
                   style="width: 100%; padding: 12px; border: 1px solid var(--xobo-border); border-radius: 4px;">
            <small style="color: var(--xobo-gray); font-size: 0.8rem;">This must be unique across the system</small>
        </div>

        <div class="form-group">
            <label for="status">Initial Status *</label>
            <select id="status" name="status" required 
                    style="width: 100%; padding: 12px; border: 1px solid var(--xobo-border); border-radius: 4px;">
                <option value="approved" <?php echo ($_POST['status'] ?? 'approved') === 'approved' ? 'selected' : ''; ?>>
                    Approved (Ready to use)
                </option>
                <option value="pending" <?php echo ($_POST['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>
                    Pending (Needs approval)
                </option>
            </select>
            <small style="color: var(--xobo-gray); font-size: 0.8rem;">Approved companies can receive users immediately</small>
        </div>

        <div class="form-group">
            <label for="description">Description (Optional)</label>
            <textarea id="description" name="description" rows="3"
                      style="width: 100%; padding: 12px; border: 1px solid var(--xobo-border); border-radius: 4px; resize: vertical;"
                      placeholder="Brief description of the company..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>

        <!-- Product Catalog Section -->
        <div style="border-top: 1px solid var(--xobo-border); margin: 2rem 0; padding-top: 2rem;">
            <h3 style="color: var(--xobo-primary); margin-bottom: 1rem;">
                <i class="fas fa-boxes"></i> Product Catalog (Optional)
            </h3>
            <p style="color: var(--xobo-gray); margin-bottom: 1.5rem; font-size: 0.9rem;">
                Add products to get the company started. You can always add more products later.
            </p>

            <!-- Excel Import Section -->
            <div class="excel-import-section" style="background: #f8f9fa; border: 2px dashed var(--xobo-primary); border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem;">
                <h4 style="color: var(--xobo-primary); margin-bottom: 1rem;">
                    <i class="fas fa-file-excel"></i> Bulk Import from Excel
                </h4>
                <p style="color: var(--xobo-gray); font-size: 0.9rem; margin-bottom: 1rem;">
                    Upload any Excel file (.xlsx) with product data. Automatically detects columns for Product Name, SKU, Weight, and Price in any arrangement.
                </p>
                
                <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                    <input type="file" id="excel-file" accept=".xlsx,.xls" 
                           style="padding: 8px; border: 1px solid var(--xobo-border); border-radius: 4px;">
                    <button type="button" id="import-excel-btn" class="btn" 
                            style="background: var(--xobo-primary); color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-upload"></i> Import Excel
                    </button>
                    <button type="button" id="download-template-btn" class="btn" 
                            style="background: var(--xobo-gray); color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-download"></i> Download Examples
                    </button>
                </div>
                
                <div id="import-status" style="margin-top: 1rem; display: none;"></div>
            </div>

            <div style="text-align: center; margin-bottom: 1rem; color: var(--xobo-gray);">
                <strong>OR</strong>
            </div>

            <div id="products-container">
                <!-- Products table will be shown here when products are added -->
            </div>

            <button type="button" id="add-product-btn" class="btn" 
                    style="background: var(--xobo-light-gray); color: var(--xobo-primary); border: 2px dashed var(--xobo-primary); padding: 12px 24px; border-radius: 4px; cursor: pointer; margin-bottom: 1rem;">
                <i class="fas fa-plus"></i> Add Product Manually
            </button>
        </div>

        <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
            <a href="/xobo-c/admin/companies.php" 
               style="padding: 12px 24px; background: var(--xobo-gray); color: white; text-decoration: none; border-radius: 4px;">
                <i class="fas fa-arrow-left"></i> Back to Companies
            </a>
            <button type="submit" class="btn" style="background: var(--xobo-primary); color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer;">
                <i class="fas fa-building"></i> Create Company
            </button>
        </div>
    </form>
</div>

<!-- Company Creation Guidelines -->
<div class="admin-card">
    <h3 style="color: var(--xobo-primary); margin-bottom: 1rem;">
        <i class="fas fa-info-circle"></i> Company Creation Guidelines
    </h3>
    
    <div style="background: #f8f9fa; padding: 1rem; border-radius: 4px; border-left: 4px solid var(--xobo-primary);">
        <ul style="margin: 0; padding-left: 1.5rem; color: var(--xobo-gray);">
            <li><strong>Company Name:</strong> Must be unique and will be displayed to users</li>
            <li><strong>Status:</strong> 'Approved' allows users to be invited immediately, 'Pending' requires approval first</li>
            <li><strong>Product Catalog:</strong> Use CSV import for bulk upload or add products manually</li>
            <li><strong>Next Step:</strong> After creating the company, use "Invite User" to add team members</li>
            <li><strong>Data Isolation:</strong> Each company operates independently with isolated data</li>
        </ul>
    </div>
</div>

<style>
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--xobo-primary);
    font-weight: 500;
}

.alert {
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 1rem;
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

/* Products Table Styling */
.products-table-wrapper {
    max-height: 600px; /* Approximately 20 rows */
    overflow-y: auto;
    border: 1px solid var(--xobo-border);
    border-radius: 8px;
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

#products-table {
    border-collapse: collapse;
    width: 100%;
    background: white;
    margin: 0;
}

#products-table th {
    background: #f8f9fa;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--xobo-primary);
    border-bottom: 2px solid var(--xobo-border);
    font-size: 0.9rem;
    position: sticky;
    top: 0;
    z-index: 10;
}

#products-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

#products-table tbody tr:hover {
    background: #f8f9fa;
}

#products-table input {
    background: transparent;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}

#products-table input:focus {
    background: white;
    border-color: var(--xobo-primary);
    box-shadow: 0 0 0 2px rgba(var(--xobo-primary-rgb), 0.1);
    outline: none;
}

/* Scrollbar styling */
.products-table-wrapper::-webkit-scrollbar {
    width: 8px;
}

.products-table-wrapper::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.products-table-wrapper::-webkit-scrollbar-thumb {
    background: var(--xobo-primary);
    border-radius: 4px;
}

.products-table-wrapper::-webkit-scrollbar-thumb:hover {
    background: #0056b3;
}

/* Responsive table */
@media (max-width: 768px) {
    .products-table-wrapper {
        max-height: 400px; /* Smaller height on mobile */
        overflow-x: auto;
    }
    
    #products-table {
        min-width: 600px;
    }
    
    #products-table th,
    #products-table td {
        padding: 0.5rem 0.25rem;
        font-size: 0.8rem;
    }
    
    #products-table input {
        padding: 6px;
        font-size: 0.8rem;
    }
    
    /* Hide scroll hint on mobile */
    .scroll-hint {
        display: none;
    }
}
</style>

<script>
let productCount = 0;

document.getElementById('add-product-btn').addEventListener('click', function() {
    addProductRow();
});

function removeProduct(productId) {
    const productRow = document.getElementById(`product-row-${productId}`);
    if (productRow) {
        productRow.remove();
        
        // Update product count
        updateProductCount();
        
        // If no products left, hide the table
        const tbody = document.querySelector('#products-table tbody');
        if (tbody && tbody.children.length === 0) {
            document.getElementById('products-container').innerHTML = '';
        }
    }
}

// Excel Import functionality
document.getElementById('download-template-btn').addEventListener('click', function() {
    // Create a new workbook
    const wb = XLSX.utils.book_new();
    
    // Create multiple format examples
    const wsData1 = [
        ['Product Name', 'SKU', 'Weight (Kg)', 'Price (KSH)'],
        ['Sample TV 32 Inch Smart', 'SAM32TV', 5, 280.00],
        ['Sample Sound Bar 2.1ch', 'SAMSB21', 3, 280.00],
        ['Sample Power Bank 10000mAh', 'SAMPB10', 1, 250.00]
    ];
    
    const wsData2 = [
        ['Serial No', 'Product Name', 'SKU', 'Weight (Kg)', 'Delivery Rate (KSH)'],
        [1, 'Sample TV 32 Inch Smart', 'SAM32TV', 5, 280.00],
        [2, 'Sample Sound Bar 2.1ch', 'SAMSB21', 3, 280.00],
        [3, 'Sample Power Bank 10000mAh', 'SAMPB10', 1, 250.00]
    ];
    
    const wsData3 = [
        ['Item', 'Code', 'Weight Kg', 'Cost KSH'],
        ['Sample TV 32 Inch Smart', 'SAM32TV', 5, 280.00],
        ['Sample Sound Bar 2.1ch', 'SAMSB21', 3, 280.00],
        ['Sample Power Bank 10000mAh', 'SAMPB10', 1, 250.00]
    ];
    
    // Create worksheets
    const ws1 = XLSX.utils.aoa_to_sheet(wsData1);
    const ws2 = XLSX.utils.aoa_to_sheet(wsData2);
    const ws3 = XLSX.utils.aoa_to_sheet(wsData3);
    
    // Add worksheets to workbook
    XLSX.utils.book_append_sheet(wb, ws1, 'Format 1 - Basic');
    XLSX.utils.book_append_sheet(wb, ws2, 'Format 2 - With Serial');
    XLSX.utils.book_append_sheet(wb, ws3, 'Format 3 - Alternative');
    
    // Save the file
    XLSX.writeFile(wb, 'product_templates.xlsx');
});

document.getElementById('import-excel-btn').addEventListener('click', function() {
    const fileInput = document.getElementById('excel-file');
    const file = fileInput.files[0];
    
    if (!file) {
        showStatus('Please select an Excel file first.', 'error');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            showStatus('Processing Excel file...', 'info');
            
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, {type: 'array'});
            
            // Get first worksheet
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            
            // Convert to array of arrays
            const jsonData = XLSX.utils.sheet_to_json(worksheet, {header: 1});
            
            if (jsonData.length < 1) {
                showStatus('Excel file appears to be empty.', 'error');
                return;
            }
            
            // Clear existing products
            document.getElementById('products-container').innerHTML = '';
            productCount = 0;
            
            let importedCount = 0;
            let skippedCount = 0;
            
            // Auto-detect columns by examining headers and data
            let nameCol = -1, skuCol = -1, weightCol = -1, priceCol = -1;
            
            // Try to detect column positions from header row
            if (jsonData.length > 0) {
                const headerRow = jsonData[0];
                for (let i = 0; i < headerRow.length; i++) {
                    const header = (headerRow[i] || '').toString().toLowerCase();
                    
                    if ((header.includes('product') && header.includes('name')) || header.includes('product name')) {
                        nameCol = i;
                    } else if (header.includes('sku')) {
                        skuCol = i;
                    } else if (header.includes('weight') || header.includes('kg')) {
                        weightCol = i;
                    } else if (header.includes('price') || header.includes('rate') || header.includes('cost') || header.includes('ksh')) {
                        priceCol = i;
                    }
                }
            }
            
            // If auto-detection failed, try common patterns
            if (nameCol === -1 || skuCol === -1 || weightCol === -1 || priceCol === -1) {
                // Check if we have at least 4 columns with data
                for (let i = 1; i < jsonData.length; i++) {
                    const row = jsonData[i];
                    if (row && row.length >= 4) {
                        // Try different column arrangements
                        if (row.length >= 5) {
                            // Format: Serial, Product Name, SKU, Weight, Price
                            nameCol = 1; skuCol = 2; weightCol = 3; priceCol = 4;
                        } else {
                            // Format: Product Name, SKU, Weight, Price
                            nameCol = 0; skuCol = 1; weightCol = 2; priceCol = 3;
                        }
                        break;
                    }
                }
            }
            
            // Process data rows
            const startRow = (jsonData[0] && jsonData[0].some(cell => 
                cell && cell.toString().toLowerCase().includes('product'))) ? 1 : 0;
            
            for (let i = startRow; i < jsonData.length; i++) {
                const row = jsonData[i];
                
                if (!row || row.length === 0) continue;
                
                // Extract data using detected columns or try to parse flexibly
                let productName, sku, weight, price;
                
                if (nameCol >= 0 && skuCol >= 0 && weightCol >= 0 && priceCol >= 0) {
                    // Use detected column positions
                    productName = row[nameCol];
                    sku = row[skuCol];
                    weight = row[weightCol];
                    price = row[priceCol];
                } else {
                    // Flexible parsing - try to find valid data in any arrangement
                    const validCells = row.filter(cell => cell != null && cell !== '');
                    
                    if (validCells.length >= 4) {
                        // Try to identify columns by content pattern
                        for (let j = 0; j < row.length - 3; j++) {
                            const candidate = {
                                name: row[j],
                                sku: row[j + 1],
                                weight: row[j + 2],
                                price: row[j + 3]
                            };
                            
                            // Validate if this looks like valid product data
                            if (candidate.name && candidate.sku && 
                                !isNaN(parseFloat(candidate.weight)) && 
                                !isNaN(parseFloat(candidate.price))) {
                                productName = candidate.name;
                                sku = candidate.sku;
                                weight = candidate.weight;
                                price = candidate.price;
                                break;
                            }
                        }
                    }
                }
                
                // Validate and import the product
                if (productName && sku && weight != null && price != null) {
                    const nameStr = productName.toString().trim();
                    const skuStr = sku.toString().trim();
                    const weightNum = parseFloat(weight);
                    const priceNum = parseFloat(price);
                    
                    if (nameStr && skuStr && !isNaN(weightNum) && !isNaN(priceNum) && 
                        weightNum > 0 && priceNum > 0) {
                        addProductFromData(nameStr, skuStr, weightNum.toString(), priceNum.toString());
                        importedCount++;
                    } else {
                        skippedCount++;
                    }
                } else {
                    skippedCount++;
                }
            }
            
            // Show results
            if (importedCount > 0) {
                let message = `Successfully imported ${importedCount} products from Excel file!`;
                if (skippedCount > 0) {
                    message += ` (${skippedCount} rows skipped due to missing or invalid data)`;
                }
                showStatus(message, 'success');
                fileInput.value = ''; // Clear file input
            } else {
                showStatus('No valid products found in Excel file. Please ensure your file has columns for Product Name, SKU, Weight, and Price.', 'error');
            }
            
        } catch (error) {
            console.error('Excel Import Error:', error);
            showStatus('Error parsing Excel file. Please try again or check the file format.', 'error');
        }
    };
    
    reader.readAsArrayBuffer(file);
});

function createProductsTable() {
    return `
        <div style="margin-top: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <h4 style="color: var(--xobo-primary); margin: 0;">
                    <i class="fas fa-list"></i> Product List <span id="product-count" style="font-size: 0.8rem; color: var(--xobo-gray);">(0 products)</span>
                </h4>
                <div class="scroll-hint" style="font-size: 0.8rem; color: var(--xobo-gray);">
                    <i class="fas fa-info-circle"></i> Auto-detects columns • Scrollable when more than 20 products
                </div>
            </div>
            <div class="products-table-wrapper">
                <table id="products-table" class="data-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Product Name</th>
                            <th style="width: 15%;">SKU</th>
                            <th style="width: 15%;">Weight (kg)</th>
                            <th style="width: 15%;">Price (KSH)</th>
                            <th style="width: 10%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

function updateProductCount() {
    const tbody = document.querySelector('#products-table tbody');
    const count = tbody ? tbody.children.length : 0;
    const countElement = document.getElementById('product-count');
    if (countElement) {
        countElement.textContent = `(${count} product${count !== 1 ? 's' : ''})`;
    }
}

function addProductRow(name = '', sku = '', weight = '', price = '') {
    productCount++;
    
    // Create table if it doesn't exist
    if (!document.getElementById('products-table')) {
        document.getElementById('products-container').innerHTML = createProductsTable();
    }
    
    const tbody = document.querySelector('#products-table tbody');
    const rowHtml = `
        <tr id="product-row-${productCount}">
            <td>
                <input type="text" name="products[${productCount}][name]" required
                       value="${escapeHtml(name)}"
                       style="width: 100%; padding: 8px; border: 1px solid var(--xobo-border); border-radius: 4px; font-size: 0.9rem;"
                       placeholder="Product name">
            </td>
            <td>
                <input type="text" name="products[${productCount}][sku]" required
                       value="${escapeHtml(sku)}"
                       style="width: 100%; padding: 8px; border: 1px solid var(--xobo-border); border-radius: 4px; font-size: 0.9rem;"
                       placeholder="SKU">
            </td>
            <td>
                <input type="number" name="products[${productCount}][weight_kg]" step="0.01" min="0.01" required
                       value="${weight}"
                       style="width: 100%; padding: 8px; border: 1px solid var(--xobo-border); border-radius: 4px; font-size: 0.9rem;"
                       placeholder="0.00">
            </td>
            <td>
                <input type="number" name="products[${productCount}][rate_ksh]" step="0.01" min="0.01" required
                       value="${price}"
                       style="width: 100%; padding: 8px; border: 1px solid var(--xobo-border); border-radius: 4px; font-size: 0.9rem;"
                       placeholder="0.00">
            </td>
            <td style="text-align: center;">
                <button type="button" onclick="removeProduct(${productCount})" 
                        class="btn-sm btn-danger" style="padding: 0.4rem 0.6rem; font-size: 0.8rem;">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `;
    
    tbody.insertAdjacentHTML('beforeend', rowHtml);
    updateProductCount();
}

function addProductFromData(name, sku, weight, price) {
    addProductRow(name, sku, weight, price);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showStatus(message, type) {
    const statusDiv = document.getElementById('import-status');
    statusDiv.style.display = 'block';
    
    let bgColor, textColor, borderColor, icon;
    
    if (type === 'success') {
        bgColor = '#d4edda';
        textColor = '#155724';
        borderColor = '#c3e6cb';
        icon = 'check-circle';
    } else if (type === 'info') {
        bgColor = '#d1ecf1';
        textColor = '#0c5460';
        borderColor = '#bee5eb';
        icon = 'info-circle';
    } else {
        bgColor = '#f8d7da';
        textColor = '#721c24';
        borderColor = '#f5c6cb';
        icon = 'exclamation-triangle';
    }
    
    statusDiv.innerHTML = `
        <div style="padding: 12px; border-radius: 4px; background: ${bgColor}; color: ${textColor}; border: 1px solid ${borderColor};">
            <i class="fas fa-${icon}"></i> ${message}
        </div>
    `;
    
    // Auto-hide success messages after 5 seconds
    if (type === 'success') {
        setTimeout(() => {
            statusDiv.style.display = 'none';
        }, 5000);
    }
}

// Add first product by default - but not when page loads, only when manual add is clicked
document.addEventListener('DOMContentLoaded', function() {
    // Don't add default product anymore since we have CSV import
});
</script>

<?php include 'includes/admin_footer.php'; ?> 