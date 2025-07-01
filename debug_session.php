<?php
session_start();
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Debug - User Panel</title>
    <link rel="icon" type="image/png" href="assets/images/XDL-ICON.png">
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 2rem; background: #f5f5f5; }
        .debug-container { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .status { padding: 1rem; margin: 1rem 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #cce5ff; color: #004085; border: 1px solid #b8daff; }
        pre { background: #f8f9fa; padding: 1rem; border-radius: 4px; overflow-x: auto; }
        .test-login { margin-top: 2rem; }
        .test-login a { 
            display: inline-block; 
            padding: 0.75rem 1.5rem; 
            background: #16234d; 
            color: white; 
            text-decoration: none; 
            border-radius: 4px; 
            margin-right: 1rem;
        }
        .test-login a:hover { background: #1a2654; }
    </style>
</head>
<body>
    <div class="debug-container">
        <h1>🔍 Session Debug Information</h1>
        
        <div class="status <?php echo isLoggedIn() ? 'success' : 'error'; ?>">
            <strong>Login Status:</strong> 
            <?php echo isLoggedIn() ? '✅ User is logged in' : '❌ User is NOT logged in'; ?>
        </div>

        <?php if (isLoggedIn()): ?>
            <div class="status success">
                <strong>User Session Data:</strong>
                <pre><?php 
                    $sessionData = [
                        'user_id' => $_SESSION['user_id'] ?? 'NOT SET',
                        'email' => $_SESSION['email'] ?? 'NOT SET', 
                        'role' => $_SESSION['role'] ?? 'NOT SET',
                        'company_id' => $_SESSION['company_id'] ?? 'NOT SET'
                    ];
                    print_r($sessionData);
                ?></pre>
            </div>
        <?php else: ?>
            <div class="status info">
                <strong>Session Status:</strong> No user session found. You need to log in to see the dropdown.
            </div>
        <?php endif; ?>

        <div class="status info">
            <strong>All Session Variables:</strong>
            <pre><?php 
                if (empty($_SESSION)) {
                    echo "No session variables set.";
                } else {
                    print_r($_SESSION);
                }
            ?></pre>
        </div>

        <div class="status info">
            <strong>Session ID:</strong> <?php echo session_id(); ?>
        </div>

        <div class="test-login">
            <h3>Quick Actions:</h3>
            <a href="auth/login">🔑 Go to Login</a>
            <a href="index">🏠 Back to Home</a>
            <?php if (isLoggedIn()): ?>
                <a href="auth/logout">🚪 Logout</a>
            <?php endif; ?>
        </div>

        <div style="margin-top: 2rem; padding: 1rem; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
            <strong>💡 Dropdown Troubleshooting:</strong>
            <ul>
                <li>The profile dropdown only appears when you're logged in</li>
                <li>Check browser console (F12) for JavaScript errors and debug messages</li>
                <li>If logged in but dropdown doesn't work, check console for element detection issues</li>
                <li>Make sure JavaScript is enabled in your browser</li>
            </ul>
        </div>
    </div>

    <script>
        // Additional client-side debugging
        console.log('=== SESSION DEBUG PAGE ===');
        console.log('Page loaded at:', new Date());
        
        // Test if dropdown elements exist on main page
        if (window.location.pathname.includes('debug_session.php')) {
            console.log('This is the debug page - check main page for dropdown elements');
        }
    </script>
</body>
</html> 