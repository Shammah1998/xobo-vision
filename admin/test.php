<?php
session_start();
echo "<h2>Admin Test Page</h2>";
echo "If you can see this, the admin directory is accessible.<br>";
echo "Current URL: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "Session role: " . ($_SESSION['role'] ?? 'Not set') . "<br>";
echo "<a href='../shop/orders.php'>Back to Orders</a>";
?> 