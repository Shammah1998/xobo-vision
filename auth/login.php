<?php
require_once '../config/config.php';
session_start();
require_once '../includes/functions.php';
require_once '../config/db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin($pdo)) {
        header('Location: ' . BASE_URL . '/admin/dashboard');
    } else {
        header('Location: ' . BASE_URL . '/index');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT u.*, c.status as company_status FROM users u 
                                  LEFT JOIN companies c ON u.company_id = c.id 
                                  WHERE u.email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Check if company is approved ONLY for regular users
                if ($user['role'] === 'user' && $user['company_status'] !== 'approved') {
                    $error = 'Your company is not yet approved. Please wait for admin approval.';
                } else {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['company_id'] = $user['company_id'];
                    // Only super_admin and admin can access admin panel
                    if (in_array($user['role'], ['super_admin', 'admin'])) {
                        header('Location: ' . BASE_URL . '/admin/dashboard');
                    } else {
                        header('Location: ' . BASE_URL . '/index');
                    }
                    exit;
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error = 'Login failed. Please try again.';
        }
    }
}

$pageTitle = 'Login - User Panel';
include '../includes/header.php';
?>

<style>
/* Center the login page vertically */
.main-content {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: calc(100vh - 160px); /* Full height minus header and footer */
    padding: 2rem 0;
}

.login-container {
    max-width: 600px;
    margin: 0 auto;
    padding: 2rem;
    background: var(--xobo-white);
    border-radius: 8px;
    box-shadow: 0 2px 10px var(--xobo-shadow);
}

.login-header {
    text-align: center;
    margin-bottom: 2rem;
}

.login-header h1 {
    color: var(--xobo-primary);
    font-size: 1.8rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.login-header p {
    color: var(--xobo-gray);
    font-size: 0.95rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--xobo-primary);
    font-weight: 500;
}

.form-group input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid var(--xobo-border);
    border-radius: 4px;
    font-size: 1rem;
    outline: none;
    transition: border-color 0.3s;
}

.form-group input:focus {
    border-color: var(--xobo-primary);
    box-shadow: 0 0 0 2px rgba(22, 35, 77, 0.1);
}

.btn-login {
    width: 100%;
    padding: 12px 16px;
    background: var(--xobo-primary);
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.3s;
}

.btn-login:hover {
    background: var(--xobo-primary-hover);
}

.alert {
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
}

.alert-error {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}



@media (max-width: 768px) {
    .main-content {
        min-height: calc(100vh - 120px); /* Adjust for smaller header on mobile */
        padding: 1rem 0;
    }
    
    .login-container {
        margin: 0 1rem;
        padding: 1.5rem;
    }
}
</style>

<div class="login-container">
    <div class="login-header">
        <h1>Sign In</h1>
        <p>Enter your details to access your account</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required 
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>

        <button type="submit" class="btn-login">Sign In</button>
    </form>
</div>

