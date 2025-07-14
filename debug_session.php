<?php
session_start();
echo "<h2>Session Debug Information</h2>";
echo "<strong>Session ID:</strong> " . session_id() . "<br>";
echo "<strong>User ID:</strong> " . ($_SESSION['user_id'] ?? 'Not set') . "<br>";
echo "<strong>Email:</strong> " . ($_SESSION['email'] ?? 'Not set') . "<br>";
echo "<strong>Role:</strong> " . ($_SESSION['role'] ?? 'Not set') . "<br>";
echo "<strong>Company ID:</strong> " . ($_SESSION['company_id'] ?? 'Not set') . "<br>";
echo "<br><strong>All Session Data:</strong><br>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
?> 