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
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (count($recentCompanies) >= 10): ?>
    <div style="text-align: center; margin-top: 1rem;">
        <a href="/xobo-vision/admin/companies.php" class="btn" style="background: var(--xobo-primary); color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 4px;">
            View All Companies
        </a>
    </div>
    <?php endif; ?>
</div>

<script>
function confirmDeleteDashboard(companyName) {
    return confirm('Are you sure you want to delete "' + companyName + '"? This will delete the company and all its users and products. This action CANNOT be undone!');
}
</script>

<?php include 'includes/admin_footer.php'; ?> 