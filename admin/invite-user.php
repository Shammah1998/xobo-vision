<?php
require_once '../config/config.php';
session_start();
require_once '../includes/functions.php';
require_once '../config/db.php';

// Check if user is admin (first user or super_admin)
if (!isAdmin($pdo)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$message = '';
$error = '';

// Get all approved companies for the dropdown
$stmt = $pdo->prepare("SELECT id, name FROM companies WHERE status = 'approved' ORDER BY name");
$stmt->execute();
$companies = $stmt->fetchAll();

// Handle user invitation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyId = (int)$_POST['company_id'];
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $userName = sanitize($_POST['user_name']);
    
    // Validation
    if (empty($companyId) || empty($email) || empty($password) || empty($userName)) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered.';
            } else {
                // Get company name for confirmation
                $stmt = $pdo->prepare("SELECT name FROM companies WHERE id = ? AND status = 'approved'");
                $stmt->execute([$companyId]);
                $company = $stmt->fetch();
                
                if (!$company) {
                    $error = 'Selected company not found or not approved.';
                } else {
                    // Insert new user (always as regular user)
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (company_id, email, password, role) VALUES (?, ?, ?, 'user')");
                    $stmt->execute([$companyId, $email, $hashedPassword]);
                    
                    $message = "User '{$userName}' has been successfully invited to '{$company['name']}'.";
                    
                    // Clear form
                    $_POST = [];
                }
            }
        } catch (PDOException $e) {
            $error = 'Failed to create user. Please try again.';
        }
    }
}

// Get recent users for display
$stmt = $pdo->prepare("
    SELECT u.id, u.email, u.created_at, c.name as company_name
    FROM users u 
    JOIN companies c ON u.company_id = c.id 
    WHERE u.role != 'super_admin'
    ORDER BY u.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recentUsers = $stmt->fetchAll();

$pageTitle = 'Invite User';
include 'includes/admin_header.php';
?>

<!-- Success/Error Messages -->
<?php if ($message): ?>
    <div class="admin-card">
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-card">
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    </div>
<?php endif; ?>

<!-- Invite User Form -->
<div class="admin-card">
    <h2 style="margin-bottom: 1rem; color: var(--xobo-primary);">
        <i class="fas fa-user-plus"></i> Invite User to Company
    </h2>
    <p style="color: var(--xobo-gray); margin-bottom: 2rem;">
        Invite a new user to a company. All users can browse products, make purchases, and view their order history.
    </p>
    
    <?php if (empty($companies)): ?>
        <div style="background: #fff3cd; color: #856404; padding: 1rem; border-radius: 4px; margin-bottom: 2rem;">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>No approved companies found.</strong> 
            You need to <a href="<?php echo BASE_URL; ?>/admin/create-company.php" style="color: var(--xobo-primary);">create a company</a> first before inviting users.
        </div>
    <?php else: ?>
    
    <form method="POST" action="" style="max-width: 700px;">
        <div class="form-group">
            <label for="company_id">Assign to Company *</label>
            <select id="company_id" name="company_id" required 
                    style="width: 100%; padding: 12px; border: 1px solid var(--xobo-border); border-radius: 4px;">
                <option value="">Select a company...</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?php echo $company['id']; ?>" 
                            <?php echo ($_POST['company_id'] ?? '') == $company['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($company['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="user_name">User Name *</label>
            <input type="text" id="user_name" name="user_name" required 
                   value="<?php echo htmlspecialchars($_POST['user_name'] ?? ''); ?>"
                   style="width: 100%; padding: 12px; border: 1px solid var(--xobo-border); border-radius: 4px;"
                   placeholder="Full name of the user">
        </div>

        <div class="form-group">
            <label for="email">Email Address *</label>
            <input type="email" id="email" name="email" required 
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                   style="width: 100%; padding: 12px; border: 1px solid var(--xobo-border); border-radius: 4px;">
        </div>

        <div class="form-group">
            <label for="password">Password *</label>
            <input type="password" id="password" name="password" required
                   style="width: 100%; padding: 12px; border: 1px solid var(--xobo-border); border-radius: 4px;">
            <small style="color: var(--xobo-gray); font-size: 0.8rem;">Minimum 6 characters</small>
        </div>

        <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
            <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" 
               style="padding: 12px 24px; background: var(--xobo-gray); color: white; text-decoration: none; border-radius: 4px;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <button type="submit" class="btn" style="background: var(--xobo-primary); color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer;">
                <i class="fas fa-user-plus"></i> Invite User
            </button>
        </div>
    </form>
    
    <?php endif; ?>
</div>

<!-- Recent Users -->
<?php if (!empty($recentUsers)): ?>
<div class="admin-card">
    <h2 style="margin-bottom: 1rem; color: var(--xobo-primary);">
        <i class="fas fa-users"></i> Recently Invited Users
    </h2>
    
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>User Details</th>
                    <th>Company</th>
                    <th>Invited</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentUsers as $user): ?>
                <tr>
                    <td>
                        <div style="font-weight: 600; color: var(--xobo-primary);">
                            <?php echo htmlspecialchars($user['email']); ?>
                        </div>
                        <div style="font-size: 0.9rem; color: var(--xobo-gray);">
                            ID: #<?php echo $user['id']; ?>
                        </div>
                    </td>
                    <td>
                        <span style="font-weight: 500;">
                            <?php echo htmlspecialchars($user['company_name']); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                        <div style="font-size: 0.8rem; color: var(--xobo-gray);">
                            <?php echo date('H:i', strtotime($user['created_at'])); ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- User Invitation Guidelines -->
<div class="admin-card">
    <h3 style="color: var(--xobo-primary); margin-bottom: 1rem;">
        <i class="fas fa-info-circle"></i> User Invitation Guidelines
    </h3>
    
    <div style="background: #f8f9fa; padding: 1rem; border-radius: 4px; border-left: 4px solid var(--xobo-primary);">
        <div style="margin-bottom: 1rem;">
            <strong style="color: var(--xobo-primary);">User Capabilities:</strong>
            <ul style="margin: 0.5rem 0; padding-left: 1.5rem; color: var(--xobo-gray);">
                <li>Browse products within their assigned company</li>
                <li>Add products to cart and make purchases</li>
                <li>View their order history and track orders</li>
            </ul>
        </div>
        
        <div style="margin-bottom: 1rem;">
            <strong style="color: var(--xobo-primary);">Data Isolation:</strong>
            <ul style="margin: 0.5rem 0; padding-left: 1.5rem; color: var(--xobo-gray);">
                <li>Users can only see products and data belonging to their assigned company</li>
                <li>Complete separation between different companies' data</li>
                <li>Users cannot access or interfere with other companies' operations</li>
            </ul>
        </div>
        
        <div>
            <strong style="color: var(--xobo-primary);">Best Practices:</strong>
            <ul style="margin: 0.5rem 0; padding-left: 1.5rem; color: var(--xobo-gray);">
                <li>Use strong passwords and share them securely with the invited users</li>
                <li>Only invite users to companies that are approved and active</li>
                <li>Ensure users understand they're joining a specific company's shopping portal</li>
            </ul>
        </div>
    </div>
</div>

<style>
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--xobo-primary);
    font-weight: 500;
}

.alert {
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 1rem;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style>

<?php include 'includes/admin_footer.php'; ?> 