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

// Handle company actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['company_id'])) {
        $companyId = (int)$_POST['company_id'];
        $action = $_POST['action'];
        
        if (in_array($action, ['approved', 'rejected', 'pending'])) {
            try {
                $stmt = $pdo->prepare("UPDATE companies SET status = ? WHERE id = ?");
                $stmt->execute([$action, $companyId]);
                $message = "Company status updated to " . $action . " successfully.";
            } catch (PDOException $e) {
                $error = "Error updating company status.";
            }
        } elseif ($action === 'delete') {
            try {
                // Check if company has any orders (orders should be preserved for business records)
                $stmt = $pdo->prepare("SELECT COUNT(*) as order_count FROM orders WHERE company_id = ?");
                $stmt->execute([$companyId]);
                $orderCount = $stmt->fetch()['order_count'];
                
                if ($orderCount > 0) {
                    $error = "Cannot delete company. It has associated orders that must be preserved for business records.";
                } else {
                    // Get user count for confirmation message
                    $stmt = $pdo->prepare("SELECT COUNT(*) as user_count FROM users WHERE company_id = ?");
                    $stmt->execute([$companyId]);
                    $userCount = $stmt->fetch()['user_count'];
                    
                    // Delete company and all related data
                    $pdo->beginTransaction();
                    
                    // Delete users first
                    $stmt = $pdo->prepare("DELETE FROM users WHERE company_id = ?");
                    $stmt->execute([$companyId]);
                    
                    // Delete products
                    $stmt = $pdo->prepare("DELETE FROM products WHERE company_id = ?");
                    $stmt->execute([$companyId]);
                    
                    // Delete company
                    $stmt = $pdo->prepare("DELETE FROM companies WHERE id = ?");
                    $stmt->execute([$companyId]);
                    
                    $pdo->commit();
                    
                    if ($userCount > 0) {
                        $message = "Company deleted successfully along with {$userCount} associated user(s).";
                    } else {
                        $message = "Company deleted successfully.";
                    }
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Error deleting company: " . $e->getMessage();
            }
        }
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$searchTerm = $_GET['search'] ?? '';

// Build query
$whereClause = [];
$params = [];

if ($statusFilter !== 'all') {
    $whereClause[] = "c.status = ?";
    $params[] = $statusFilter;
}

if ($searchTerm) {
    $whereClause[] = "(c.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

$where = $whereClause ? 'WHERE ' . implode(' AND ', $whereClause) : '';

// Get companies with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT c.*, u.email as admin_email, u.id as admin_user_id,
           (SELECT COUNT(*) FROM users WHERE company_id = c.id) as user_count,
           (SELECT COUNT(*) FROM orders WHERE company_id = c.id) as order_count,
           (SELECT COUNT(*) FROM products WHERE company_id = c.id) as product_count
    FROM companies c 
    LEFT JOIN users u ON c.id = u.company_id AND u.role = 'company_admin'
    $where
    ORDER BY c.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$companies = $stmt->fetchAll();

// Get total count for pagination
$countStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT c.id) as total
    FROM companies c 
    LEFT JOIN users u ON c.id = u.company_id AND u.role = 'company_admin'
    $where
");
$countStmt->execute($params);
$totalCompanies = $countStmt->fetch()['total'];
$totalPages = ceil($totalCompanies / $perPage);

// Fetch users for all companies in the current page
$companyIds = array_column($companies, 'id');
$usersByCompany = [];
if ($companyIds) {
    $placeholders = implode(',', array_fill(0, count($companyIds), '?'));
    $stmt = $pdo->prepare('SELECT id, company_id, email, role, created_at FROM users WHERE company_id IN (' . $placeholders . ')');
    $stmt->execute($companyIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
        $usersByCompany[$user['company_id']][] = $user;
    }
}

$pageTitle = 'Company Management';
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

<!-- Filters and Search -->
<div class="admin-card">
    <h2 style="margin-bottom: 1rem; color: var(--xobo-primary);">
        <i class="fas fa-building"></i> Company Management
    </h2>
    
    <form method="GET" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
        <div>
            <label style="display: block; margin-bottom: 0.5rem; color: var(--xobo-primary); font-weight: 500;">
                Search
            </label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" 
                   placeholder="Company name or email..."
                   style="padding: 8px; border: 1px solid var(--xobo-border); border-radius: 4px; width: 200px;">
        </div>
        <button type="submit" style="background: var(--xobo-primary); color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">
            <i class="fas fa-search"></i> Filter
        </button>
        <?php if ($searchTerm): ?>
            <a href="companies.php" style="background: var(--xobo-gray); color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none;">
                <i class="fas fa-times"></i> Clear
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Companies Table -->
<div class="admin-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h3 style="margin: 0; color: var(--xobo-primary);">
            Companies (<?php echo $totalCompanies; ?> total)
        </h3>
    </div>
    
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Company Details</th>
                    <th>Admin</th>
                    <th>Statistics</th>
                    <th>Registered</th>
                    <th>Users</th>
                    <th style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($companies)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 2rem; color: var(--xobo-gray);">
                        No companies found matching your criteria.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($companies as $company): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600; color: var(--xobo-primary);">
                                <?php echo htmlspecialchars($company['name']); ?>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--xobo-gray);">
                                ID: #<?php echo $company['id']; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($company['admin_email']): ?>
                                <div><?php echo htmlspecialchars($company['admin_email']); ?></div>
                                <div style="font-size: 0.8rem; color: var(--xobo-gray);">
                                    User ID: #<?php echo $company['admin_user_id']; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--xobo-gray); font-style: italic;">No admin assigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-size: 0.8rem; color: var(--xobo-gray);">
                                <div>Users: <?php echo $company['user_count']; ?></div>
                                <div>Products: <?php echo $company['product_count']; ?></div>
                                <div>Orders: <?php echo $company['order_count']; ?></div>
                            </div>
                        </td>
                        <td>
                            <?php echo date('M j, Y', strtotime($company['created_at'])); ?>
                            <div style="font-size: 0.8rem; color: var(--xobo-gray);">
                                <?php echo date('H:i', strtotime($company['created_at'])); ?>
                            </div>
                        </td>
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
                        <td style="text-align: center; vertical-align: middle;">
                            <div style="display: flex; justify-content: center; align-items: center; min-height: 40px;">
                                <form method="POST" style="margin: 0;" onsubmit="return confirmDelete(<?php echo $company['user_count']; ?>, '<?php echo htmlspecialchars($company['name'], ENT_QUOTES); ?>')">
                                    <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                    <button type="submit" name="action" value="delete" class="delete-btn" title="Delete Company & Users">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <tr class="users-dropdown-row" id="users-dropdown-<?php echo $company['id']; ?>" style="display:none; background:#f8f9fa;">
                        <td colspan="7" style="padding:1.5rem 2rem;">
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
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 1rem;">
        <?php
        $currentUrl = $_SERVER['REQUEST_URI'];
        $urlParts = parse_url($currentUrl);
        parse_str($urlParts['query'] ?? '', $queryParams);
        
        for ($i = 1; $i <= $totalPages; $i++):
            $queryParams['page'] = $i;
            $url = '?' . http_build_query($queryParams);
        ?>
            <a href="<?php echo $url; ?>" 
               style="padding: 0.5rem 0.75rem; text-decoration: none; border-radius: 4px; <?php echo $i == $page ? 'background: var(--xobo-primary); color: white;' : 'background: #f8f9fa; color: var(--xobo-primary);'; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<style>
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

/* Delete button styling */
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

/* Lock icon styling */
.lock-icon {
    color: #6c757d;
    font-size: 0.9rem;
    opacity: 0.6;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
}
</style>

<script>
function confirmDelete(userCount, companyName) {
    let message = `Are you sure you want to delete "${companyName}"?\n\n`;
    
    if (userCount > 0) {
        message += `⚠️ WARNING: This will also delete ${userCount} user${userCount !== 1 ? 's' : ''} associated with this company.\n\n`;
        message += `This action will:\n`;
        message += `• Delete the company "${companyName}"\n`;
        message += `• Delete ${userCount} user${userCount !== 1 ? 's' : ''}\n`;
        message += `• Delete all products\n\n`;
        message += `This action CANNOT be undone!`;
    } else {
        message += `This will delete the company and all its products.\n\n`;
        message += `This action CANNOT be undone!`;
    }
    
    return confirm(message);
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