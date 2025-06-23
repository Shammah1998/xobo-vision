<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin($pdo)) {
        header('Location: /xobo-vision/admin/dashboard.php');
    } else {
        header('Location: /xobo-vision/company-home.php');
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
                // Check if company is approved (except for super_admin)
                if ($user['role'] !== 'super_admin' && $user['company_status'] !== 'approved') {
                    $error = 'Your company is not yet approved. Please wait for admin approval.';
                } else {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['company_id'] = $user['company_id'];
                    
                    // Redirect based on admin status and user role
                    if (isAdmin($pdo)) {
                        header('Location: /xobo-vision/admin/dashboard.php');
                    } else {
                        // Redirect users to company-specific homepage
                        header('Location: /xobo-vision/company-home.php');
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

$pageTitle = 'Login - XOBO MART';
include '../includes/header.php';
?>

<style>
.login-container {
    max-width: 600px;
    margin: 4rem auto;
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

.login-links {
    text-align: center;
    margin-top: 1.5rem;
}

.login-links a {
    color: var(--xobo-primary);
    text-decoration: none;
    font-size: 0.9rem;
}

.login-links a:hover {
    text-decoration: underline;
}

.divider {
    margin: 1.5rem 0;
    text-align: center;
    position: relative;
    display: flex;
    align-items: center;
}

.divider::before,
.divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--xobo-border);
}

.divider span {
    padding: 0 1.5rem;
    color: var(--xobo-gray);
    font-size: 0.9rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

@media (max-width: 768px) {
    .login-container {
        margin: 2rem 1rem;
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

    <div class="divider">
        <span>or</span>
    </div>

    <div class="login-links">
        <p>Don't have an account? <a href="signup.php">Create Account</a></p>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 