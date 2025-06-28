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

// Handle company approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $companyId = (int)$_POST['company_id'];
    $action = $_POST['action'];
    
    if (in_array($action, ['approved', 'rejected'])) {
        try {
            $stmt = $pdo->prepare("UPDATE companies SET status = ? WHERE id = ?");
            $stmt->execute([$action, $companyId]);
            $message = "Company " . $action . " successfully.";
        } catch (PDOException $e) {
            $message = "Error updating company status.";
        }
    }
}

// Get dashboard stats
$stmt = $pdo->query("SELECT COUNT(*) as total_companies FROM companies");
$totalCompanies = $stmt->fetch()['total_companies'];

$stmt = $pdo->query("SELECT COUNT(*) as pending_companies FROM companies WHERE status = 'pending'");
$pendingCompanies = $stmt->fetch()['pending_companies'];

$stmt = $pdo->query("SELECT COUNT(*) as approved_companies FROM companies WHERE status = 'approved'");
$approvedCompanies = $stmt->fetch()['approved_companies'];

$stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE role != 'super_admin'");
$totalUsers = $stmt->fetch()['total_users'];

// Get recent companies
$stmt = $pdo->prepare("
    SELECT c.*, u.email as admin_email 
    FROM companies c 
    LEFT JOIN users u ON c.id = u.company_id AND u.role = 'company_admin'
    ORDER BY c.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recentCompanies = $stmt->fetchAll();

// Fetch users for all companies in recentCompanies
$companyIds = array_column($recentCompanies, 'id');
$usersByCompany = [];
if ($companyIds) {
    $placeholders = implode(',', array_fill(0, count($companyIds), '?'));
    $stmt = $pdo->prepare('SELECT id, company_id, email, role, created_at FROM users WHERE company_id IN (' . $placeholders . ')');
    $stmt->execute($companyIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
        $usersByCompany[$user['company_id']][] = $user;
    }
}

$pageTitle = 'Dashboard Overview';
include 'includes/admin_header.php';
?>

<?php if ($message): ?>
    <div class="admin-card">
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    </div>
<?php endif; ?>

<!-- Dashboard Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <h3>Total Companies</h3>
        <p class="stat-number"><?php echo $totalCompanies; ?></p>
    </div>
    <div class="stat-card">
        <h3>Pending Approval</h3>
        <p class="stat-number"><?php echo $pendingCompanies; ?></p>
    </div>
    <div class="stat-card">
        <h3>Approved Companies</h3>
        <p class="stat-number"><?php echo $approvedCompanies; ?></p>
    </div>
    <div class="stat-card">
        <h3>Total Users</h3>
        <p class="stat-number"><?php echo $totalUsers; ?></p>
    </div>
</div>

<!-- Recent Companies -->
<div class="admin-card">
    <h2 style="margin-bottom: 1rem; color: var(--xobo-primary);">Recent Company Registrations</h2>
    
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Company Name</th>
                    <th>Admin Email</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Users</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentCompanies)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 2rem; color: var(--xobo-gray);">
                        No companies registered yet.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($recentCompanies as $company): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($company['name']); ?></td>
                        <td><?php echo htmlspecialchars($company['admin_email'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $company['status']; ?>">
                                <?php echo ucfirst($company['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($company['created_at'])); ?></td>
                        <td style="text-align:center; width: 70px;">
                            <div style="display: flex; gap: 0.5rem; align-items: center; justify-content: center;">
                                <button type="button" class="btn btn-primary btn-sm view-users-btn" onclick="toggleUsersDropdown(<?php echo $company['id']; ?>)" data-company-id="<?php echo $company['id']; ?>" id="view-users-btn-<?php echo $company['id']; ?>" style="min-width: 60px; display: flex; align-items: center; justify-content: center; gap: 0.4em;">
                                    View
                                    <i class="fas fa-chevron-down" id="chevron-icon-<?php echo $company['id']; ?>" style="transition: transform 0.2s;"></i>
                                </button>
                                <a href="company-products.php?company_id=<?php echo $company['id']; ?>" class="btn btn-secondary btn-sm" style="min-width: 60px; display: flex; align-items: center; justify-content: center; gap: 0.4em;">
                                    Products
                                    <i class="fas fa-box-open"></i>
                                </a>
                            </div>
                        </td>
                        <td style="padding: 0; height: 100%; width: 80px; text-align: center;">
                            <div style="display: flex; justify-content: center; align-items: center; height: 100%; min-height: 40px; width: 100%;">
                                <form method="POST" style="display:inline-block; margin:0;" onsubmit="return confirmDeleteDashboard('<?php echo htmlspecialchars($company['name'], ENT_QUOTES); ?>')">
                                    <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                    <button type="submit" name="action" value="delete" class="delete-btn" title="Delete Company & Users" style="background: #dc3545; color: white; border: none; padding: 0; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; width: 32px; height: 32px; margin: 0 auto;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php if ($company['status'] === 'pending'): ?>
                                    <form method="POST" style="display: inline; margin-left: 8px;">
                                        <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                        <button type="submit" name="action" value="approved" class="btn btn-success btn-sm">
                                            Approve
                                        </button>
                                        <button type="submit" name="action" value="rejected" class="btn btn-danger btn-sm">
                                            Reject
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr class="users-dropdown-row" id="users-dropdown-<?php echo $company['id']; ?>" style="display:none; background:#f8f9fa;">
                        <td colspan="6" style="padding:1.5rem 2rem;">
                            <h4 style="color:var(--xobo-primary); margin-bottom:0.75rem;">Users for <?php echo htmlspecialchars($company['name']); ?></h4>
                            <?php if (!empty($usersByCompany[$company['id']])): ?>
                                <table style="width:100%; border-collapse:collapse; margin-bottom:1rem;">
                                    <thead>
                                        <tr style="background:#f0f0f0;">
                                            <th style="padding:0.5rem; text-align:left;">User ID</th>
                                            <th style="padding:0.5rem; text-align:left;">Email</th>
                                            <th style="padding:0.5rem; text-align:left;">Role</th>
                                            <th style="padding:0.5rem; text-align:left;">Created</th>
                                            <th style="padding:0.5rem; text-align:center;">Edit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($usersByCompany[$company['id']] as $user): ?>
                                            <tr>
                                                <td style="padding:0.5rem;">#<?php echo htmlspecialchars($user['id']); ?></td>
                                                <td style="padding:0.5rem;"><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td style="padding:0.5rem; text-transform:capitalize;"><?php echo htmlspecialchars($user['role']); ?></td>
                                                <td style="padding:0.5rem;"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td style="padding:0.5rem; text-align:center;">
                                                    <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm" title="Edit User" style="padding: 0.4rem 0.7rem; min-width: 32px; display: inline-flex; align-items: center; justify-content: center;">
                                                        <i class="fas fa-pen"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div style="color:var(--xobo-gray);">No users found for this company.</div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (count($recentCompanies) >= 10): ?>
    <div style="text-align: center; margin-top: 1rem;">
        <a href="<?php echo BASE_URL; ?>/admin/companies.php" class="btn" style="background: var(--xobo-primary); color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 4px;">
            View All Companies
        </a>
    </div>
    <?php endif; ?>
</div>

<script>
function confirmDeleteDashboard(companyName) {
    return confirm('Are you sure you want to delete "' + companyName + '"? This will delete the company and all its users and products. This action CANNOT be undone!');
}

function toggleUsersDropdown(companyId) {
    const row = document.getElementById('users-dropdown-' + companyId);
    const icon = document.getElementById('chevron-icon-' + companyId);
    if (row.style.display === 'none' || row.style.display === '') {
        row.style.display = 'table-row';
        if (icon) icon.style.transform = 'rotate(180deg)';
    } else {
        row.style.display = 'none';
        if (icon) icon.style.transform = 'rotate(0deg)';
    }
}
</script>

<?php include 'includes/admin_footer.php'; ?> 