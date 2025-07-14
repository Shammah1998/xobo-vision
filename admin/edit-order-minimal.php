<?php
// MINIMAL EDIT ORDER TEST
echo "<h1>EDIT ORDER - MINIMAL TEST</h1>";
echo "If you see this, the file is accessible!<br>";
echo "Order ID from URL: " . ($_GET['id'] ?? 'No ID provided') . "<br>";
echo "Current URL: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "Request Method: " . $_SERVER['REQUEST_METHOD'] . "<br>";
echo "Server Name: " . $_SERVER['SERVER_NAME'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";

session_start();
echo "<br><strong>Session Info:</strong><br>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "<br>";
echo "Role: " . ($_SESSION['role'] ?? 'Not set') . "<br>";
echo "Company ID: " . ($_SESSION['company_id'] ?? 'Not set') . "<br>";

echo "<br><a href='../shop/orders.php'>Back to Orders</a>";
?> 