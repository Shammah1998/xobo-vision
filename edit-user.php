<?php
require_once __DIR__ . '/config/config.php';
session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/auth/login');
    exit;
}

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$currentUserId = $_SESSION['user_id'];
$currentRole = $_SESSION['role'];
$companyId = $_SESSION['company_id'];

// Access control
if ($currentRole === 'admin_user') {
    // Can edit any user in their company
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND company_id = ?');
    $stmt->execute([$userId, $companyId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        header('Location: profile.php');
        exit;
    }
} elseif ($currentRole === 'user') {
    // Can only edit themselves
    if ($userId !== $currentUserId) {
        header('Location: profile.php');
        exit;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND company_id = ?');
    $stmt->execute([$userId, $companyId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        header('Location: profile.php');
        exit;
    }
} else {
    // Not allowed
    header('Location: profile.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');

    // Password change fields
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email)) {
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check for duplicate email (exclude current user)
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            $error = 'A user with this email already exists.';
        } else {
            // Handle password change if fields are filled and user is editing their own profile
            if ($currentRole === 'user' && $userId === $currentUserId && ($currentPassword || $newPassword || $confirmPassword)) {
                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    $error = 'All password fields are required to change your password.';
                } elseif ($newPassword !== $confirmPassword) {
                    $error = 'New password and confirmation do not match.';
                } elseif (strlen($newPassword) < 6) {
                    $error = 'New password must be at least 6 characters.';
                } else {
                    // Verify current password
                    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
                    $stmt->execute([$userId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$row || !password_verify($currentPassword, $row['password'])) {
                        $error = 'Current password is incorrect.';
                    } else {
                        // Update password
                        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                        $stmt->execute([$hashed, $userId]);
                        $message = 'Password updated successfully.';
                    }
                }
            }
            // Only update user details if no error so far
            if (!$error) {
                $stmt = $pdo->prepare('UPDATE users SET name = ?, phone = ?, email = ? WHERE id = ?');
                $stmt->execute([$name, $phone, $email, $userId]);
                $message = $message ? $message . ' User details updated successfully.' : 'User details updated successfully.';
                // Refresh user data
                $user['name'] = $name;
                $user['phone'] = $phone;
                $user['email'] = $email;
                // Redirect after 2s
                echo '<script>setTimeout(function(){ window.location.href = "profile.php"; }, 2000);</script>';
            }
        }
    }
}

$pageTitle = 'Edit User';
include __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width: 500px; margin: 3rem auto 0 auto;">
    <div class="card" style="background: #fff; border-radius: 8px; box-shadow: 0 2px 10px var(--xobo-shadow); padding: 2.5rem 2rem;">
        <div style="display: flex; align-items: center; gap: 1.2rem; margin-bottom: 1.5rem;">
            <i class="fas fa-user-edit" style="font-size: 2.2rem; color: var(--xobo-primary);"></i>
            <div>
                <h2 style="margin: 0; color: var(--xobo-primary); font-size: 1.5rem; font-weight: 600;">Edit User</h2>
                <p style="margin: 0.25rem 0 0 0; color: var(--xobo-gray); font-size: 1rem;">Update user details below.</p>
            </div>
        </div>
        <?php if ($message): ?>
            <div class="alert alert-success" style="margin-bottom: 1rem;"> <?php echo htmlspecialchars($message); ?> </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 1rem;"> <?php echo htmlspecialchars($error); ?> </div>
        <?php endif; ?>
        <form method="POST" style="display: flex; flex-direction: column; gap: 1.2rem;">
            <div class="form-group">
                <label for="name" style="font-weight: 600; color: var(--xobo-primary);">Full Name</label>
                <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div class="form-group">
                <label for="phone" style="font-weight: 600; color: var(--xobo-primary);">Phone Number</label>
                <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div class="form-group">
                <label for="email" style="font-weight: 600; color: var(--xobo-primary);">Email Address</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <?php if ($currentRole === 'user' && $userId === $currentUserId): ?>
            <div class="form-group">
                <label for="current_password" style="font-weight: 600; color: var(--xobo-primary);">Current Password</label>
                <input type="password" name="current_password" id="current_password" autocomplete="current-password" style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div class="form-group">
                <label for="new_password" style="font-weight: 600; color: var(--xobo-primary);">New Password</label>
                <input type="password" name="new_password" id="new_password" autocomplete="new-password" style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div class="form-group">
                <label for="confirm_password" style="font-weight: 600; color: var(--xobo-primary);">Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirm_password" autocomplete="new-password" style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <?php endif; ?>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <a href="profile.php" class="btn" style="background: var(--xobo-gray); color: white; text-decoration: none; border-radius: 4px; padding: 12px 24px;">Cancel</a>
                <button type="submit" class="btn" style="background: var(--xobo-primary); color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer;">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?> 