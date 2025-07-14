<?php
require_once '../config/config.php';
session_start();
require_once '../includes/functions.php';
require_once '../config/db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName = sanitize($_POST['company_name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $name = isset($_POST['name']) ? sanitize($_POST['name']) : null;
    $phone = isset($_POST['phone']) ? sanitize($_POST['phone']) : null;
    
    // Validation
    if (empty($companyName) || empty($email) || empty($password) || empty($confirmPassword) || empty($name)) {
        $error = 'Please fill in all fields.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            // Check if company exists and is approved
            $stmt = $pdo->prepare("SELECT id FROM companies WHERE name = ? AND status = 'approved'");
            $stmt->execute([$companyName]);
            $company = $stmt->fetch();
            
            if (!$company) {
                $error = 'Company not found or not yet approved. Please contact your company admin.';
            } else {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Email already registered.';
                } else {
                    // Insert new user
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (company_id, email, name, phone, password, role) VALUES (?, ?, ?, ?, ?, 'user')");
                    $stmt->execute([$company['id'], $email, $name, $phone, $hashedPassword]);
                    
                    $success = 'Registration successful! You can now login and start shopping.';
                }
            }
        } catch (Exception $e) {
            $error = 'Registration failed. Please try again.';
        }
    }
}

// Get approved companies for dropdown
$stmt = $pdo->prepare("SELECT name FROM companies WHERE status = 'approved' ORDER BY name");
$stmt->execute();
$companies = $stmt->fetchAll();

$pageTitle = 'Join Company - User Panel';
include '../includes/header.php';
?>

<style>
.register-container {
    max-width: 600px;
    margin: 4rem auto;
    padding: 2rem;
    background: var(--xobo-white);
    border-radius: 8px;
    box-shadow: 0 2px 10px var(--xobo-shadow);
}

.register-header {
    text-align: center;
    margin-bottom: 2rem;
}

.register-header h1 {
    color: var(--xobo-primary);
    font-size: 1.8rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.register-header p {
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

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid var(--xobo-border);
    border-radius: 4px;
    font-size: 1rem;
    outline: none;
    transition: border-color 0.3s;
}

.form-group input:focus,
.form-group select:focus {
    border-color: var(--xobo-primary);
    box-shadow: 0 0 0 2px rgba(22, 35, 77, 0.1);
}

.btn-register {
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

.btn-register:hover {
    background: var(--xobo-primary-hover);
}

.alert {
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
}

.alert-success {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.alert-error {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.register-links {
    text-align: center;
    margin-top: 1.5rem;
}

.register-links a {
    color: var(--xobo-primary);
    text-decoration: none;
    font-size: 0.9rem;
}

.register-links a:hover {
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
    .register-container {
        margin: 2rem 1rem;
        padding: 1.5rem;
    }
}
</style>

<div class="register-container">
    <div class="register-header">
        <h1>Join Company</h1>
        <p>Register with an existing company</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="company_name">Select Company</label>
            <select id="company_name" name="company_name" required>
                <option value="">Choose a company...</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?php echo htmlspecialchars($company['name']); ?>"
                            <?php echo (isset($_POST['company_name']) && $_POST['company_name'] === $company['name']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($company['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required 
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="name">Full Name *</label>
            <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" placeholder="Your full name">
        </div>
        <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="Phone number (optional)">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>

        <button type="submit" class="btn-register">Join Company</button>
    </form>

    <div class="divider">
        <span>or</span>
    </div>

    <div class="register-links">
        <p>Already have an account? <a href="login">Sign In</a></p>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 