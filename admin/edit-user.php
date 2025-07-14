<?php
include 'includes/admin_header.php';
require_once '../config/db.php';

function fetchAllUsers($pdo, $search = '') {
    $sql = "SELECT u.*, c.name as company_name FROM users u LEFT JOIN companies c ON u.company_id = c.id WHERE u.role IN ('user', 'admin_user')";
    $params = [];
    if ($search) {
        $sql .= ' AND (u.email LIKE ? OR c.name LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $sql .= ' ORDER BY c.name, u.email';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle update if POST and id is set
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user_id'])) {
    $userId = (int)$_POST['edit_user_id'];
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $password = $_POST['password'];
    $name = isset($_POST['name']) ? trim($_POST['name']) : null;
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : null;
    // Only super_admin or admin can assign admin_user or user roles
    if (in_array($role, ['admin_user', 'user']) && !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
        $error = 'You do not have permission to assign this role.';
    } else {
        try {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address.');
            }
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                throw new Exception('Email already in use by another user.');
            }
            if ($password) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET email = ?, name = ?, phone = ?, role = ?, password = ? WHERE id = ?');
                $stmt->execute([$email, $name, $phone, $role, $hashed, $userId]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET email = ?, name = ?, phone = ?, role = ? WHERE id = ?');
                $stmt->execute([$email, $name, $phone, $role, $userId]);
            }
            $message = 'User updated successfully!';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// If id is set, show single user edit form
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($userId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        echo '<div class="admin-card"><div class="alert alert-error">User not found.</div></div>';
        include 'includes/admin_footer.php';
        exit;
    }
    ?>
    <div class="admin-card" style="margin: 2rem 0 0 1.5rem; max-width: 700px; width: 100%; box-sizing: border-box;">
        <h2 style="color: var(--xobo-primary); margin-bottom: 1.5rem;">Edit User</h2>
        <?php if ($message): ?>
            <div class="alert alert-success" id="success-alert"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" style="display: flex; flex-direction: column; gap: 1.2rem; max-width: 600px;">
            <input type="hidden" name="edit_user_id" value="<?php echo $userId; ?>">
            <div class="form-group">
                <label for="email" style="font-weight: 600; color: var(--xobo-primary);">Email</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div class="form-group">
                <label for="name" style="font-weight: 600; color: var(--xobo-primary);">Full Name</label>
                <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div class="form-group">
                <label for="phone" style="font-weight: 600; color: var(--xobo-primary);">Phone Number</label>
                <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div class="form-group">
                <label for="role" style="font-weight: 600; color: var(--xobo-primary);">Role</label>
                <select name="role" id="role" required style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="super_admin" <?php if ($user['role'] === 'super_admin') echo 'selected'; ?>>Super Admin</option>
                    <option value="admin" <?php if ($user['role'] === 'admin') echo 'selected'; ?>>Admin</option>
                    <option value="admin_user" <?php if ($user['role'] === 'admin_user') echo 'selected'; ?>>Admin-User</option>
                    <option value="user" <?php if ($user['role'] === 'user') echo 'selected'; ?>>User</option>
                </select>
            </div>
            <div class="form-group">
                <label for="password" style="font-weight: 600; color: var(--xobo-primary);">Password <span style="font-weight:400; color:#888;">(leave blank to keep unchanged)</span></label>
                <input type="password" name="password" id="password" placeholder="New password (optional)" style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <a href="edit-user.php" class="btn btn-secondary">Back</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
    <?php
    include 'includes/admin_footer.php';
    exit;
}

// Otherwise, show user management dashboard
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$users = fetchAllUsers($pdo, $search);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $userId = (int)$_POST['delete_user_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $message = "User deleted successfully. All orders remain in the database.";
    } catch (PDOException $e) {
        $error = "Error deleting user: " . $e->getMessage();
    }
}
?>
<div class="admin-card" style="margin: 2rem 0 0 0; width: 100%; box-sizing: border-box;">
    <h2 style="color: var(--xobo-primary); margin-bottom: 1.5rem;">User Management</h2>
    <?php if ($message): ?>
        <div class="alert alert-success" id="success-alert"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="GET" style="margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by email or company..." style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px; min-width: 250px;">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search): ?>
            <a href="edit-user" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
    <table class="data-table" style="width:100%;">
        <thead>
            <tr style="background:#f0f0f0;">
                <th style="padding:0.5rem; text-align:left;">User ID</th>
                <th style="padding:0.5rem; text-align:left;">Email</th>
                <th style="padding:0.5rem; text-align:left;">Company</th>
                <th style="padding:0.5rem; text-align:left;">Role</th>
                <th style="padding:0.5rem; text-align:left;">Created</th>
                <th style="padding:0.5rem; text-align:center;">Edit</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td style="padding:0.5rem;">#<?php echo htmlspecialchars($user['id']); ?></td>
                <td style="padding:0.5rem;"><?php echo htmlspecialchars($user['email']); ?></td>
                <td style="padding:0.5rem;"><?php echo htmlspecialchars($user['company_name'] ?? 'N/A'); ?></td>
                <td style="padding:0.5rem; text-transform:capitalize;"><?php echo htmlspecialchars($user['role']); ?></td>
                <td style="padding:0.5rem;"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                <td style="padding:0.5rem; text-align:center;">
                    <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm" title="Edit User" style="padding: 0.4rem 0.7rem; min-width: 32px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-pen"></i>
                    </a>
                    <form method="POST" style="display:inline-block; margin:0;" onsubmit="return confirm('Delete this user? This will not delete their orders.');">
                        <input type="hidden" name="delete_user_id" value="<?php echo $user['id']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm" title="Delete User" style="padding: 0.4rem 0.7rem; min-width: 32px; display: inline-flex; align-items: center; justify-content: center;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var successAlert = document.getElementById('success-alert');
    if (successAlert) {
        setTimeout(function() {
            successAlert.style.display = 'none';
        }, 3000);
    }
});
</script>
<?php include 'includes/admin_footer.php'; ?> 