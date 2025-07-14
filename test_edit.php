<?php
session_start();
echo "<h2>Edit Order Test</h2>";
echo "<strong>Your current role:</strong> " . ($_SESSION['role'] ?? 'Not set') . "<br>";
echo "<strong>Your company ID:</strong> " . ($_SESSION['company_id'] ?? 'Not set') . "<br>";

// Test the URL that's being generated
$sampleOrderId = 1; // Use an actual order ID if you know one
$editUrl = "admin/edit-order.php?id=" . $sampleOrderId;
echo "<br><strong>Edit URL being generated:</strong> <a href='{$editUrl}'>{$editUrl}</a><br>";

// Check if the file exists
if (file_exists('admin/edit-order.php')) {
    echo "<strong>File exists:</strong> ✅ admin/edit-order.php found<br>";
} else {
    echo "<strong>File exists:</strong> ❌ admin/edit-order.php NOT found<br>";
}

echo "<br><a href='shop/orders.php'>Back to Orders</a>";
?> 