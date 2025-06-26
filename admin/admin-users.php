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

// Fetch all super_admin users using PDO
$stmt = $pdo->prepare("SELECT id, email, created_at FROM users WHERE role = 'super_admin' ORDER BY created_at DESC");
$stmt->execute();
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
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Admin ID</th>
                    <th>Email Address</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th style="width: 80px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($admins && count($admins) > 0): ?>
                <?php foreach($admins as $row): ?>
                    <tr>
                        <td>#<?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><span class="status-badge status-approved">Active</span></td>
                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                        <td style="padding: 0; height: 100%; width: 80px; text-align: center;">
                            <div style="display: flex; justify-content: center; align-items: center; height: 100%; min-height: 40px; width: 100%;">
                                <?php if ($row['id'] == $_SESSION['user_id']): ?>
                                    <span style="color: var(--xobo-gray); font-size: 0.8rem; display: inline-block;">Current User</span>
                                <?php elseif ($row['id'] == 1): ?>
                                    <span style="color: var(--xobo-gray); font-size: 0.8rem; display: inline-block;">Founder</span>
                                <?php else: ?>
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
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align: center; padding: 2rem; color: var(--xobo-gray);">No admin users found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

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