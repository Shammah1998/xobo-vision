<?php
require_once __DIR__ . '/config/config.php';
session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/auth/login');
    exit;
}

// Only allow admin_user and user roles to view this page (company context)
if (!in_array($_SESSION['role'], ['admin_user', 'user'])) {
    header('Location: ' . BASE_URL . '/index');
    exit;
}

$companyId = $_SESSION['company_id'];

// Fetch all users in the same company
$stmt = $pdo->prepare('SELECT id, email, name, phone, role, created_at FROM users WHERE company_id = ?');
$stmt->execute([$companyId]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'My Company Users';
include __DIR__ . '/includes/header.php';
?>
<div class="profile-section" style="max-width: 1100px; margin: 0 auto;">
    <div class="welcome-section" style="margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; gap: 1.2rem; margin-bottom: 1.2rem;">
            <i class="fas fa-users" style="font-size: 2.2rem; color: var(--xobo-primary);"></i>
            <div>
                <h2 style="margin: 0; color: var(--xobo-primary); font-size: 1.5rem; font-weight: 600;">Company Users</h2>
                <p style="margin: 0.25rem 0 0 0; color: var(--xobo-gray); font-size: 1rem;">View and manage users in your company.</p>
            </div>
        </div>
        <div class="profile-actions" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
            <input type="text" placeholder="Type to search" class="search-input" id="userSearchInput" style="min-width: 250px; max-width: 350px; flex: 1 1 300px;">
            <a href="invite-user" class="btn" style="min-width: 140px; text-align: center;">Invite Users</a>
        </div>
    </div>
    <div class="card" style="background: #fff; border-radius: 8px; box-shadow: 0 2px 10px var(--xobo-shadow); padding: 0; overflow-x: auto;">
        <table class="data-table" style="width: 100%; border-collapse: separate; border-spacing: 0; min-width: 700px;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 1.1rem 1rem; text-align: left;">Name</th>
                    <th style="padding: 1.1rem 1rem; text-align: left;">Phone</th>
                    <th style="padding: 1.1rem 1rem; text-align: left;">Email</th>
                    <th style="padding: 1.1rem 1rem; text-align: left;">Type</th>
                    <th style="padding: 1.1rem 1rem; text-align: center;">Edit</th>
                </tr>
            </thead>
            <tbody id="usersTableBody">
            <?php foreach (
                $users as $user): ?>
                <tr class="user-row">
                    <td style="padding: 1rem;">
                        <?php echo htmlspecialchars($user['name'] ?? ''); ?>
                    </td>
                    <td style="padding: 1rem;">
                        <?php echo (!empty($user['phone']) && !empty($user['email'])) ? htmlspecialchars($user['phone']) : '—'; ?>
                    </td>
                    <td style="padding: 1rem;">
                        <?php echo (!empty($user['phone']) && !empty($user['email'])) ? htmlspecialchars($user['email']) : '—'; ?>
                    </td>
                    <td style="padding: 1rem; text-transform:capitalize;">
                        <?php
                        // Only show 'Admin' for 'admin_user', otherwise 'Normal'
                        $roleDisplay = ($user['role'] === 'admin_user') ? 'Admin' : 'Normal';
                        echo htmlspecialchars($roleDisplay);
                        ?>
                    </td>
                    <td style="padding: 1rem; text-align: center;">
                        <?php
                        $canEdit = false;
                        if ($_SESSION['role'] === 'admin_user') {
                            $canEdit = true;
                        } elseif ($_SESSION['role'] === 'user' && $_SESSION['user_id'] == $user['id']) {
                            $canEdit = true;
                        }
                        if ($canEdit): ?>
                            <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm" title="Edit User" style="padding: 0.4rem 0.7rem; min-width: 32px; display: inline-flex; align-items: center; justify-content: center;">
                                <i class="fas fa-pen"></i>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var searchInput = document.getElementById('userSearchInput');
  var tableBody = document.getElementById('usersTableBody');
  if (searchInput && tableBody) {
    searchInput.addEventListener('input', function() {
      var filter = searchInput.value.toLowerCase();
      var rows = tableBody.querySelectorAll('.user-row');
      rows.forEach(function(row) {
        var cells = row.querySelectorAll('td');
        var name = cells[0] ? cells[0].textContent.toLowerCase() : '';
        var phone = cells[1] ? cells[1].textContent.toLowerCase() : '';
        if (name.includes(filter) || phone.includes(filter)) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
  }
});
</script> 