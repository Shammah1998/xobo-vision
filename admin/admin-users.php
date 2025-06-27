<?php
include 'includes/admin_header.php';
require_once '../config/db.php';

// Handle delete admin action
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin_id'])) {
    $deleteId = (int)$_POST['delete_admin_id'];
    // Prevent deleting self or founder (ID 1)
    if ($deleteId == $_SESSION['user_id']) {
        $error = "You cannot delete your own admin account.";
    } elseif ($deleteId == 1) {
        $error = "The founder admin cannot be deleted.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'super_admin'");
            $stmt->execute([$deleteId]);
            $message = "Admin user deleted successfully.";
        } catch (PDOException $e) {
            $error = "Error deleting admin user.";
        }
    }
}

// Handle update if POST and edit_admin_id is set (AJAX or fallback)
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

// Search filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "SELECT id, email, created_at FROM users WHERE role = 'super_admin'";
$params = [];
if ($search) {
    $sql .= " AND email LIKE ?";
    $params[] = "%$search%";
}
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

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

<div class="admin-card">
    <h2 style="margin-bottom: 1rem; color: var(--xobo-primary);">Admin Users</h2>
    <form method="GET" style="margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by email..." style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px; min-width: 250px;">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search): ?>
            <a href="admin-users.php" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Admin ID</th>
                    <th>Email Address</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th style="width: 120px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody id="admin-table-body">
            <?php if ($admins && count($admins) > 0): ?>
                <?php foreach($admins as $row): ?>
                    <tr id="admin-row-<?php echo $row['id']; ?>">
                        <td>#<?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><span class="status-badge status-approved">Active</span></td>
                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                        <td style="padding: 0; height: 100%; width: 120px; text-align: center;">
                            <div style="display: flex; justify-content: center; align-items: center; height: 100%; min-height: 40px; width: 100%; gap: 0.5rem;">
                                <?php if ($row['id'] == $_SESSION['user_id']): ?>
                                    <span style="color: var(--xobo-gray); font-size: 0.8rem; display: inline-block;">Current User</span>
                                <?php elseif ($row['id'] == 1): ?>
                                    <span style="color: var(--xobo-gray); font-size: 0.8rem; display: inline-block;">Founder</span>
                                <?php else: ?>
                                    <a href="edit-admin.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm" title="Edit Admin" style="padding: 0.4rem 0.7rem; min-width: 32px; display: inline-flex; align-items: center; justify-content: center;"><i class="fas fa-pen"></i></a>
                                    <form method="POST" style="display:inline-block; margin:0;">
                                        <input type="hidden" name="delete_admin_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="delete-btn" title="Delete Admin User" style="margin: 0 auto; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr id="admin-edit-row-<?php echo $row['id']; ?>" style="display:none;"><td colspan="5"></td></tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align: center; padding: 2rem; color: var(--xobo-gray);">No admin users found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.edit-admin-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var adminId = this.getAttribute('data-id');
            var editRow = document.getElementById('admin-edit-row-' + adminId);
            // Hide any open edit rows
            document.querySelectorAll('tr[id^="admin-edit-row-"]').forEach(function(row) {
                row.style.display = 'none';
                row.querySelector('td').innerHTML = '';
            });
            // Fetch the edit form via AJAX
            fetch('ajax-edit-user.php?id=' + adminId + '&role=super_admin')
                .then(response => response.text())
                .then(html => {
                    editRow.querySelector('td').innerHTML = html;
                    editRow.style.display = '';
                });
        });
    });
});
</script>

<style>
.delete-btn {
    background: #dc3545;
    color: white;
    border: none;
    padding: 0;
    border-radius: 4px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    width: 32px;
    height: 32px;
    margin: 0 auto;
}
.delete-btn:hover {
    background: #c82333;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
}
.delete-btn:active {
    transform: translateY(0);
}
</style>

<?php include 'includes/admin_footer.php'; ?> 