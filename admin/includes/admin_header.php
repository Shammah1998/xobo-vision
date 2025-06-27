<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/db.php';

// Ensure user is admin
if (!isAdmin($pdo)) {
    header('Location: /xobo-vision/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Admin Panel - XOBO MART'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/xobo-vision/assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .admin-sidebar {
            width: 250px;
            background: var(--xobo-primary);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .admin-logo {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .admin-logo h2 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .admin-nav {
            padding: 1rem 0;
        }

        .nav-section {
            margin-bottom: 1rem;
        }

        .nav-section-title {
            padding: 0.5rem 1.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: rgba(255,255,255,0.7);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .nav-item {
            display: block;
            padding: 0.75rem 1.5rem;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .nav-item:hover,
        .nav-item.active {
            background: rgba(255,255,255,0.1);
            border-left-color: white;
            color: white;
        }

        .nav-item i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }

        /* Main Content */
        .admin-main {
            flex: 1;
            margin-left: 250px;
            background: #f5f5f5;
        }

        .admin-header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-header h1 {
            margin: 0;
            color: var(--xobo-primary);
            font-size: 1.5rem;
        }

        .admin-user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .admin-user span {
            color: var(--xobo-gray);
            font-size: 0.9rem;
        }

        .admin-content {
            padding: 2rem;
        }

        /* Cards */
        .admin-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card h3 {
            margin: 0 0 0.5rem 0;
            color: var(--xobo-gray);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 600;
            color: var(--xobo-primary);
            margin: 0;
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--xobo-primary);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        /* Buttons */
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8rem;
            margin: 0 0.25rem;
        }

        .btn-success {
            background: #28a745;
            color: white;
            border: none;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .admin-sidebar {
                width: 100%;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .admin-sidebar.show {
                transform: translateX(0);
            }

            .admin-main {
                margin-left: 0;
            }

            .admin-header {
                padding: 1rem;
            }

            .admin-content {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <nav class="admin-sidebar">
            <div class="admin-logo">
                <h2>XOBO ADMIN</h2>
            </div>
            
            <div class="admin-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Dashboard</div>
                    <a href="/xobo-vision/admin/dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i> Overview
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Company Management</div>
                    <a href="/xobo-vision/admin/companies.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'companies.php' ? 'active' : ''; ?>">
                        <i class="fas fa-building"></i> All Companies
                    </a>
                    <a href="/xobo-vision/admin/create-company.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'create-company.php' ? 'active' : ''; ?>">
                        <i class="fas fa-plus-circle"></i> Create Company
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">User Management</div>
                    <a href="/xobo-vision/admin/invite-user.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'invite-user.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-plus"></i> Invite User
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Admin Management</div>
                    <a href="/xobo-vision/admin/admin-users.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin-users.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-shield"></i> Admin Users
                    </a>
                    <a href="/xobo-vision/admin/invite-admin.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'invite-admin.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-plus"></i> Invite Admin
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Order Management</div>
                    <a href="/xobo-vision/admin/orders.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
                        <i class="fas fa-box"></i> All Orders
                    </a>
                </div>

                <div class="nav-section">
                    <a href="/xobo-vision/auth/logout.php" class="nav-item">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <h1><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Admin Dashboard'; ?></h1>
                <div class="admin-user">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['email']); ?></span>
                </div>
            </header>
            
            <div class="admin-content"> 