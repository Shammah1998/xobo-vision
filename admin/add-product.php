<?php
require_once '../config/config.php';
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isAdmin($pdo)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
if ($companyId <= 0) {
    echo '<div class="admin-card"><div class="alert alert-error">Invalid company ID.</div></div>';
    include 'includes/admin_footer.php';
    exit;
}

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $products = $_POST['products'] ?? [];
    $addedCount = 0;
    if (!empty($products)) {
        $stmt = $pdo->prepare('INSERT INTO products (company_id, name, sku, weight_kg, rate_ksh) VALUES (?, ?, ?, ?, ?)');
        foreach ($products as $product) {
            $name = trim($product['name'] ?? '');
            $sku = trim($product['sku'] ?? '');
            $weight = (float)($product['weight_kg'] ?? 0);
            $price = (float)($product['rate_ksh'] ?? 0);
            if ($name && $sku && $weight > 0 && $price >= 0) {
                if ($stmt->execute([$companyId, $name, $sku, $weight, $price])) {
                    $addedCount++;
                }
            }
        }
        if ($addedCount > 0) {
            $message = "$addedCount product(s) added successfully.";
        } else {
            $error = 'No valid products to add.';
        }
    } else {
        // Fallback: single product form
        $name = trim($_POST['name'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $weight = (float)($_POST['weight_kg'] ?? 0);
        $price = (float)($_POST['rate_ksh'] ?? 0);
        if ($name && $sku && $weight > 0 && $price >= 0) {
            $stmt = $pdo->prepare('INSERT INTO products (company_id, name, sku, weight_kg, rate_ksh) VALUES (?, ?, ?, ?, ?)');
            if ($stmt->execute([$companyId, $name, $sku, $weight, $price])) {
                header("Location: company-products?company_id=$companyId");
                exit;
            } else {
                $error = 'Failed to add product.';
            }
        } else {
            $error = 'All fields are required and must be valid.';
        }
    }
}

$pageTitle = 'Add Product';
include 'includes/admin_header.php';
?>
<div class="admin-card" style="margin: 2rem 0 0 2rem; max-width: 700px; width: 100%; box-sizing: border-box; padding: 1.2rem 2rem 1.2rem 2rem;">
    <h2 style="color: var(--xobo-primary); margin-bottom: 1.2rem;">Add Product</h2>
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST" id="product-form" style="display: flex; flex-direction: column; gap: 1rem; max-width: 600px;">
        <div class="form-group">
            <label for="name" style="font-weight: 600; color: var(--xobo-primary);">Product Name</label>
            <input type="text" name="name" id="name" style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        <div class="form-group">
            <label for="sku" style="font-weight: 600; color: var(--xobo-primary);">SKU</label>
            <input type="text" name="sku" id="sku" style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        <div class="form-group">
            <label for="weight_kg" style="font-weight: 600; color: var(--xobo-primary);">Weight (kg)</label>
            <input type="number" name="weight_kg" id="weight_kg" step="0.01" min="0.01" style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        <div class="form-group">
            <label for="rate_ksh" style="font-weight: 600; color: var(--xobo-primary);">Price (KSH)</label>
            <input type="number" name="rate_ksh" id="rate_ksh" step="0.01" min="0" style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        <div style="display: flex; gap: 1rem; justify-content: flex-end;">
            <a href="company-products.php?company_id=<?php echo $companyId; ?>" class="btn btn-secondary">Back</a>
            <button type="submit" class="btn btn-primary">Add Product</button>
        </div>
        <!-- Excel Import Section -->
        <div style="background: #f8f9fa; border-radius: 8px; padding: 1.5rem 2rem; box-shadow: 0 1px 4px rgba(0,0,0,0.04); width: 100%; margin: 2rem 0 1.5rem 0; display: flex; flex-direction: column; gap: 1.1rem; align-items: flex-start;">
            <label for="excel-file" style="font-weight:600; color:var(--xobo-primary); margin-bottom: 0.5rem;">Import Products from Excel (.xlsx)</label>
            <input type="file" id="excel-file" accept=".xlsx,.xls" style="margin-bottom:0.5rem;">
            <div style="display: flex; gap: 1rem; align-items: center;">
                <button type="button" id="import-excel-btn" class="btn btn-primary" style="min-width: 160px;">Upload & Import</button>
                <button type="button" id="delete-catalog-btn" class="btn btn-danger" style="min-width: 160px;">Delete Catalog</button>
            </div>
            <div style="font-size:0.97em; color:#555; margin-top:0.7rem; background: #eef2f7; padding: 0.7rem 1rem; border-radius: 5px; width: 100%;">Excel columns: <strong>Name</strong> | <strong>SKU</strong> | <strong>Weight (kg)</strong> | <strong>Price (KSH)</strong></div>
            <div id="import-status" style="margin-top: 1rem; display: none;"></div>
        </div>
        <div id="products-container"></div>
    </form>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
let productCount = 0;

function addProductRow(name = '', sku = '', weight = '', price = '') {
    productCount++;
    if (!document.getElementById('products-table')) {
        document.getElementById('products-container').innerHTML = `
            <div style="margin-top: 1rem;">
                <table id="products-table" class="data-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>SKU</th>
                            <th>Weight (kg)</th>
                            <th>Price (KSH)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        `;
    }
    const tbody = document.querySelector('#products-table tbody');
    const rowHtml = `
        <tr id="product-row-${productCount}">
            <td><input type="text" name="products[${productCount}][name]" required value="${name}" style="width: 100%;"></td>
            <td><input type="text" name="products[${productCount}][sku]" required value="${sku}" style="width: 100%;"></td>
            <td><input type="number" name="products[${productCount}][weight_kg]" step="0.01" min="0.01" required value="${weight}" style="width: 100%;"></td>
            <td><input type="number" name="products[${productCount}][rate_ksh]" step="0.01" min="0" required value="${price}" style="width: 100%;"></td>
            <td style="text-align: center;"><button type="button" onclick="removeProduct(${productCount})" class="btn-sm btn-danger">Remove</button></td>
        </tr>
    `;
    tbody.insertAdjacentHTML('beforeend', rowHtml);
}

function removeProduct(productId) {
    const productRow = document.getElementById(`product-row-${productId}`);
    if (productRow) productRow.remove();
}

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
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            const jsonData = XLSX.utils.sheet_to_json(worksheet, {header: 1});
            if (jsonData.length < 1) {
                showStatus('Excel file appears to be empty.', 'error');
                return;
            }
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
                for (let i = 1; i < jsonData.length; i++) {
                    const row = jsonData[i];
                    if (row && row.length >= 4) {
                        if (row.length >= 5) {
                            nameCol = 1; skuCol = 2; weightCol = 3; priceCol = 4;
                        } else {
                            nameCol = 0; skuCol = 1; weightCol = 2; priceCol = 3;
                        }
                        break;
                    }
                }
            }
            // Process data rows
            const startRow = (jsonData[0] && jsonData[0].some(cell => cell && cell.toString().toLowerCase().includes('product'))) ? 1 : 0;
            for (let i = startRow; i < jsonData.length; i++) {
                const row = jsonData[i];
                if (!row || row.length === 0) continue;
                let productName, sku, weight, price;
                if (nameCol >= 0 && skuCol >= 0 && weightCol >= 0 && priceCol >= 0) {
                    productName = row[nameCol];
                    sku = row[skuCol];
                    weight = row[weightCol];
                    price = row[priceCol];
                } else {
                    const validCells = row.filter(cell => cell != null && cell !== '');
                    if (validCells.length >= 4) {
                        for (let j = 0; j < row.length - 3; j++) {
                            const candidate = {
                                name: row[j],
                                sku: row[j + 1],
                                weight: row[j + 2],
                                price: row[j + 3]
                            };
                            if (candidate.name && candidate.sku && !isNaN(parseFloat(candidate.weight)) && !isNaN(parseFloat(candidate.price))) {
                                productName = candidate.name;
                                sku = candidate.sku;
                                weight = candidate.weight;
                                price = candidate.price;
                                break;
                            }
                        }
                    }
                }
                if (productName && sku && weight != null && price != null) {
                    const nameStr = productName.toString().trim();
                    const skuStr = sku.toString().trim();
                    const weightNum = parseFloat(weight);
                    const priceNum = parseFloat(price);
                    if (nameStr && skuStr && !isNaN(weightNum) && !isNaN(priceNum) && weightNum > 0 && priceNum >= 0) { // <-- allow price to be zero
                        addProductRow(nameStr, skuStr, weightNum.toString(), priceNum.toString());
                        importedCount++;
                    } else {
                        skippedCount++;
                    }
                } else {
                    skippedCount++;
                }
            }
            if (importedCount > 0) {
                let message = `Successfully imported ${importedCount} products from Excel file!`;
                if (skippedCount > 0) {
                    message += ` (${skippedCount} rows skipped due to missing or invalid data)`;
                }
                showStatus(message, 'success');
                fileInput.value = '';
            } else {
                showStatus('No valid products found in Excel file. Please ensure your file has columns for Product Name, SKU, Weight, and Price.', 'error');
            }
        } catch (error) {
            showStatus('Error parsing Excel file. Please try again or check the file format.', 'error');
        }
    };
    reader.readAsArrayBuffer(file);
});

document.getElementById('delete-catalog-btn').addEventListener('click', function() {
    document.getElementById('products-container').innerHTML = '';
    productCount = 0;
    document.getElementById('excel-file').value = '';
    showStatus('Product catalog cleared.', 'info');
});

function showStatus(message, type) {
    const statusDiv = document.getElementById('import-status');
    statusDiv.style.display = 'block';
    let bgColor, textColor, borderColor;
    if (type === 'success') {
        bgColor = '#d4edda'; textColor = '#155724'; borderColor = '#c3e6cb';
    } else if (type === 'info') {
        bgColor = '#d1ecf1'; textColor = '#0c5460'; borderColor = '#bee5eb';
    } else {
        bgColor = '#f8d7da'; textColor = '#721c24'; borderColor = '#f5c6cb';
    }
    statusDiv.innerHTML = `<div style="padding: 12px; border-radius: 4px; background: ${bgColor}; color: ${textColor}; border: 1px solid ${borderColor};">${message}</div>`;
    if (type === 'success') {
        setTimeout(() => { statusDiv.style.display = 'none'; }, 5000);
    }
}

document.getElementById('product-form').addEventListener('submit', function(e) {
    const productsTable = document.getElementById('products-table');
    if (productsTable && productsTable.querySelectorAll('tbody tr').length > 0) {
        // Catalog present, allow submit even if top fields are empty
        return true;
    } else {
        // No catalog, require top fields
        const name = document.getElementById('name').value.trim();
        const sku = document.getElementById('sku').value.trim();
        const weight = document.getElementById('weight_kg').value.trim();
        const price = document.getElementById('rate_ksh').value.trim();
        if (!name || !sku || !weight || !price) {
            alert('Please fill out all product fields or import a catalog.');
            e.preventDefault();
            return false;
        }
    }
});
</script>
<?php include 'includes/admin_footer.php'; ?> 