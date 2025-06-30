<?php
require_once __DIR__ . '/../config/config.php';

// Utility functions

// Sanitize input data
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is the first user (super admin)
function isFirstUser($pdo, $userId) {
    $stmt = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
    $firstUser = $stmt->fetch();
    return $firstUser && $firstUser['id'] == $userId;
}

// Check if user is admin (first user or super_admin role)
function isAdmin($pdo = null) {
    if ($pdo === null) {
        global $pdo;
    }
    if (!isLoggedIn()) return false;
    
    // Check if user has super_admin or admin role
    if (isset($_SESSION['role']) && ($_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'admin')) {
        return true;
    }
    
    // Check if user is the first user in database
    if ($pdo && isFirstUser($pdo, $_SESSION['user_id'])) {
        return true;
    }
    
    return false;
}

// Check user role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Redirect based on role
function redirectByRole() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
    
    switch ($_SESSION['role']) {
        case 'super_admin':
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
            break;
        case 'company_admin':
            header('Location: ' . BASE_URL . '/company/products.php');
            break;
        case 'user':
            header('Location: ' . BASE_URL . '/shop/shop.php?cid=' . $_SESSION['company_id']);
            break;
        default:
            header('Location: ' . BASE_URL . '/auth/login.php');
    }
    exit;
}

// Require role access or admin privileges
function requireRole($allowedRoles) {
    global $pdo;
    if (!isLoggedIn()) {
        header('HTTP/1.0 403 Forbidden');
        die('Access denied');
    }
    
    // Allow if user is admin (first user or super_admin)
    if (isAdmin($pdo)) {
        return true;
    }
    
    // Allow if user has required role
    if (in_array($_SESSION['role'], $allowedRoles)) {
        return true;
    }
    
    header('HTTP/1.0 403 Forbidden');
    die('Access denied');
}

// Get company name by ID
function getCompanyName($pdo, $companyId) {
    $stmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
    $stmt->execute([$companyId]);
    $result = $stmt->fetch();
    return $result ? $result['name'] : 'Unknown Company';
}

// Format currency
function formatCurrency($amount) {
    return 'KSH ' . number_format($amount, 2);
}

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?> 