<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is admin (first user or super_admin)
if (!isAdmin($pdo)) {
    header('Location: /xobo-vision/index.php');
    exit;
}

$message = '';
$error = '';

// Handle admin invitation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $name = sanitize($_POST['name']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validation
    if (empty($email) || empty($name) || empty($password) || empty($confirmPassword)) {
        $error = 'Please fill in all fields.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
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
                // Insert new admin user
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'super_admin')");
                $stmt->execute([$email, $hashedPassword]);
                
                $message = "Admin user '{$name}' has been successfully invited with email '{$email}'.";
                
                // Clear form
                $_POST = [];
            }
        } catch (PDOException $e) {
            $error = 'Failed to create admin user. Please try again.';
        }
    }
}

// Get all admin users
$stmt = $pdo->prepare("SELECT id, email, created_at FROM users WHERE role = 'super_admin' ORDER BY id ASC");
$stmt->execute();
$adminUsers = $stmt->fetchAll();

$pageTitle = 'Invite Admin';
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

<!-- Invite Admin Form -->
<div class="admin-card">
    <h2 style="margin-bottom: 1rem; color: var(--xobo-primary);">
        <i class="fas fa-user-plus"></i> Invite New Administrator
    </h2>
    <p style="color: var(--xobo-gray); margin-bottom: 2rem;">
        Create a new administrator account with full system access.
    </p>
    
    <form method="POST" action="" style="max-width: 500px;">
        <div class="form-group">
            <label for="name">Administrator Name</label>
            <input type="text" id="name" name="name" required 
                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                   style="width: 100%; padding: 12px; border: 1px solid var(--xobo-border); border-radius: 4px;">
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required 
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                   style="width: 100%; padding: 12px; border: 1px solid var(--xobo-border); border-radius: 4px;">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required
                   style="width: 100%; padding: 12px; border: 1px solid var(--xobo-border); border-radius: 4px;">
            <small style="color: var(--xobo-gray); font-size: 0.8rem;">Minimum 6 characters</small>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required
                   style="width: 100%; padding: 12px; border: 1px solid var(--xobo-border); border-radius: 4px;">
        </div>

        <button type="submit" class="btn" style="background: var(--xobo-primary); color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer;">
            <i class="fas fa-user-plus"></i> Create Administrator
        </button>
    </form>
</div>

<!-- Current Administrators -->
<div class="admin-card">
    <h2 style="margin-bottom: 1rem; color: var(--xobo-primary);">
        <i class="fas fa-user-shield"></i> Current Administrators
    </h2>
    
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Admin ID</th>
                    <th>Email Address</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adminUsers as $index => $admin): ?>
                <tr>
                    <td>
                        #<?php echo $admin['id']; ?>
                        <?php if ($index === 0): ?>
                            <span style="background: #28a745; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.7rem; margin-left: 5px;">
                                FOUNDER
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($admin['email']); ?></td>
                    <td>
                        <span class="status-badge status-approved">Active</span>
                    </td>
                    <td><?php echo date('M j, Y', strtotime($admin['created_at'])); ?></td>
                    <td>
                        <?php if ($admin['id'] == $_SESSION['user_id']): ?>
                            <span style="color: var(--xobo-gray); font-size: 0.8rem;">Current User</span>
                        <?php elseif ($index === 0): ?>
                            <span style="color: var(--xobo-gray); font-size: 0.8rem;">Founder Account</span>
                        <?php else: ?>
                            <button onclick="if(confirm('Are you sure you want to remove this administrator?')) { window.location.href='?remove_admin=<?php echo $admin['id']; ?>' }" 
                                    class="btn btn-danger btn-sm">
                                Remove
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Admin Guidelines -->
<div class="admin-card">
    <h3 style="color: var(--xobo-primary); margin-bottom: 1rem;">
        <i class="fas fa-info-circle"></i> Administrator Guidelines
    </h3>
    
    <div style="background: #f8f9fa; padding: 1rem; border-radius: 4px; border-left: 4px solid var(--xobo-primary);">
        <ul style="margin: 0; padding-left: 1.5rem; color: var(--xobo-gray);">
            <li>Administrators have full access to all system features</li>
            <li>They can manage companies, users, and other administrators</li>
            <li>The first user (Founder) cannot be removed from the system</li>
            <li>Use strong passwords and keep login credentials secure</li>
            <li>Only invite trusted individuals as administrators</li>
        </ul>
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