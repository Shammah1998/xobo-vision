<?php
include 'includes/admin_header.php';
require_once '../config/db.php';

function fetchAllAdmins($pdo, $search = '') {
    $sql = "SELECT id, email, created_at FROM users WHERE role = 'super_admin' AND id != 1";
    $params = [];
    if ($search) {
        $sql .= " AND email LIKE ?";
        $params[] = "%$search%";
    }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$message = '';
$error = '';

// Handle update if POST and id is set
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_admin_id'])) {
    $adminId = (int)$_POST['edit_admin_id'];
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $password = $_POST['password'];
    try {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address.');
        }
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $adminId]);
        if ($stmt->fetch()) {
            throw new Exception('Email already in use by another user.');
        }
        if ($password) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET email = ?, role = ?, password = ? WHERE id = ?');
            $stmt->execute([$email, $role, $hashed, $adminId]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET email = ?, role = ? WHERE id = ?');
            $stmt->execute([$email, $role, $adminId]);
        }
        $message = 'Admin updated successfully!';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$adminId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($adminId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND role = "super_admin" AND id != 1');
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin) {
        echo '<div class="admin-card"><div class="alert alert-error">Admin not found.</div></div>';
        include 'includes/admin_footer.php';
        exit;
    }
    ?>
    <div class="admin-card" style="margin: 2rem 0 0 1.5rem; max-width: 700px; width: 100%; box-sizing: border-box;">
        <h2 style="color: var(--xobo-primary); margin-bottom: 1.5rem;">Edit Admin</h2>
        <?php if ($message): ?>
            <div class="alert alert-success" id="success-alert"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" style="display: flex; flex-direction: column; gap: 1.2rem; max-width: 600px;">
            <input type="hidden" name="edit_admin_id" value="<?php echo $adminId; ?>">
            <div class="form-group">
                <label for="email" style="font-weight: 600; color: var(--xobo-primary);">Email</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div class="form-group">
                <label for="role" style="font-weight: 600; color: var(--xobo-primary);">Role</label>
                <select name="role" id="role" required style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="super_admin" selected>Super Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label for="password" style="font-weight: 600; color: var(--xobo-primary);">Password <span style="font-weight:400; color:#888;">(leave blank to keep unchanged)</span></label>
                <input type="password" name="password" id="password" placeholder="New password (optional)" style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <a href="edit-admin.php" class="btn btn-secondary">Back</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
    <?php
    include 'includes/admin_footer.php';
    exit;
}

// Otherwise, show admin management dashboard
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$admins = fetchAllAdmins($pdo, $search);
?>
<div class="admin-card" style="margin: 2rem 0 0 0; width: 100%; box-sizing: border-box;">
    <h2 style="color: var(--xobo-primary); margin-bottom: 1.5rem;">Admin Management</h2>
    <?php if ($message): ?>
        <div class="alert alert-success" id="success-alert"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="GET" style="margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by email..." style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px; min-width: 250px;">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search): ?>
            <a href="edit-admin.php" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
    <table class="data-table" style="width:100%;">
        <thead>
            <tr style="background:#f0f0f0;">
                <th style="padding:0.5rem; text-align:left;">Admin ID</th>
                <th style="padding:0.5rem; text-align:left;">Email</th>
                <th style="padding:0.5rem; text-align:left;">Created</th>
                <th style="padding:0.5rem; text-align:center;">Edit</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($admins as $admin): ?>
            <tr>
                <td style="padding:0.5rem;">#<?php echo htmlspecialchars($admin['id']); ?></td>
                <td style="padding:0.5rem;"><?php echo htmlspecialchars($admin['email']); ?></td>
                <td style="padding:0.5rem;"><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                <td style="padding:0.5rem; text-align:center;">
                    <a href="edit-admin.php?id=<?php echo $admin['id']; ?>" class="btn btn-primary btn-sm" title="Edit Admin" style="padding: 0.4rem 0.7rem; min-width: 32px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-pen"></i>
                    </a>
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