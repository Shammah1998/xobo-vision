<?php
include 'includes/admin_header.php';
require_once '../config/db.php';

$pageTitle = 'Add Sub-Admin';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!in_array($role, ['super_admin', 'company_admin', 'admin'])) {
        $error = 'Invalid role.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already exists.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (email, password, role) VALUES (?, ?, ?)');
                $stmt->execute([$email, $hashed, $role]);
                $message = 'Sub-admin added successfully!';
            }
        } catch (Exception $e) {
            $error = 'Error adding sub-admin.';
        }
    }
}
?>
<div class="admin-card add-admin-card">
    <h2 style="margin-bottom: 1rem; color: var(--xobo-primary);">Add Sub-Admin</h2>
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST" action="">
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required class="form-control">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required class="form-control">
        </div>
        <div class="form-group">
            <label for="role">Role</label>
            <select id="role" name="role" class="form-control" required>
                <option value="super_admin">Super Admin</option>
                <option value="company_admin">Company Admin</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Add Sub-Admin</button>
    </form>
</div>
<style>
.add-admin-card {
    max-width: 600px;
    min-width: 400px;
    width: 100%;
    margin: 2rem 0 2rem 0;
    margin-left: 0;
    padding: 2rem 2.5rem 1.5rem 2.5rem;
    box-sizing: border-box;
    display: block;
}
.add-admin-card form {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
    min-height: unset;
}
.add-admin-card .form-group {
    margin-bottom: 0.5rem;
}
</style>
<?php include 'includes/admin_footer.php'; ?> 