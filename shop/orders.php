<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

requireRole(['user']);

$userId = $_SESSION['user_id'];
$success = isset($_GET['success']) ? 'Order placed successfully!' : '';

// Sample orders for demo (you can replace with actual database orders)
$orders = [
    [
        'id' => 1,
        'total_ksh' => 25500.00,
        'company_name' => 'Tech Store',
        'created_at' => '2024-12-01 10:30:00',
        'status' => 'delivered',
        'address' => 'Nairobi, Kenya\nWestlands Area\nBuilding 123, Floor 4',
        'items' => 'HP Laptop (1x @23000), HP Computer Mouse (1x @400)',
        'tracking_number' => 'XB24120001'
    ],
    [
        'id' => 2,
        'total_ksh' => 4900.00,
        'company_name' => 'Fashion Hub',
        'created_at' => '2024-11-28 14:15:00',
        'status' => 'shipped',
        'address' => 'Mombasa, Kenya\nNyali Area\nApartment 567',
        'items' => 'Tom Ford Fashion Bag (1x @3400), Sport & fashion sneakers (1x @1500)',
        'tracking_number' => 'XB24112801'
    ],
    [
        'id' => 3,
        'total_ksh' => 1375.00,
        'company_name' => 'Watch Center',
        'created_at' => '2024-11-25 09:45:00',
        'status' => 'processing',
        'address' => 'Kisumu, Kenya\nMilimani Estate\nHouse 89',
        'items' => 'Michael Kors men\'s watch (1x @1375)',
        'tracking_number' => 'XB24112502'
    ],
    [
        'id' => 4,
        'total_ksh' => 800.00,
        'company_name' => 'Audio World',
        'created_at' => '2024-11-20 16:20:00',
        'status' => 'pending',
        'address' => 'Nakuru, Kenya\nMilele Estate\nHouse 234',
        'items' => 'Black Earpods (1x @800)',
        'tracking_number' => 'XB24112003'
    ]
];

$pageTitle = 'My Orders - XOBO MART';
include '../includes/header.php';
?>

<!-- XOBO-MART STYLE ORDERS HEADER -->
<section class="orders-header">
    <h1>My Orders</h1>
    <p class="orders-description">Track and manage your order history</p>
</section>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<!-- XOBO-MART STYLE ORDERS SECTION -->
<section class="orders-section">
    <?php if (empty($orders)): ?>
        <div class="no-orders">
            <div class="no-orders-content">
                <div class="no-orders-icon">📦</div>
                <h3>No orders yet</h3>
                <p>You haven't placed any orders yet. Start shopping to see your orders here!</p>
                <a href="../index.php" class="btn">Start Shopping</a>
            </div>
        </div>
    <?php else: ?>
        <!-- Order Stats -->
        <div class="order-stats">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo count($orders); ?></span>
                    <span class="stat-label">Total Orders</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-info">
                    <span class="stat-number">KSh <?php echo number_format(array_sum(array_column($orders, 'total_ksh')), 0); ?></span>
                    <span class="stat-label">Total Spent</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🚚</div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo count(array_filter($orders, function($o) { return $o['status'] === 'delivered'; })); ?></span>
                    <span class="stat-label">Delivered</span>
                </div>
            </div>
        </div>

        <!-- Orders List -->
        <div class="orders-list">
            <?php foreach ($orders as $order): ?>
            <div class="order-card">
                <div class="order-header">
                    <div class="order-main-info">
                        <div class="order-id-section">
                            <h3>Order #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></h3>
                            <span class="tracking-number">
                                <i class="fas fa-truck"></i>
                                Tracking: <?php echo $order['tracking_number']; ?>
                            </span>
                        </div>
                        <div class="order-date">
                            <i class="fas fa-calendar"></i>
                            <?php echo date('M j, Y at H:i', strtotime($order['created_at'])); ?>
                        </div>
                    </div>
                    <div class="order-status-section">
                        <div class="order-total">
                            KSh <?php echo number_format($order['total_ksh'], 2); ?>
                        </div>
                        <span class="status-badge status-<?php echo $order['status']; ?>">
                            <?php 
                            $statusIcons = [
                                'pending' => '⏳',
                                'processing' => '⚙️',
                                'shipped' => '🚚',
                                'delivered' => '✅'
                            ];
                            echo $statusIcons[$order['status']] . ' ' . ucfirst($order['status']);
                            ?>
                        </span>
                    </div>
                </div>
                
                <div class="order-body">
                    <div class="order-details-grid">
                        <div class="order-company">
                            <label>Seller</label>
                            <span><?php echo htmlspecialchars($order['company_name']); ?></span>
                        </div>
                        
                        <div class="order-items">
                            <label>Items</label>
                            <span><?php echo htmlspecialchars($order['items']); ?></span>
                        </div>
                        
                        <div class="order-address">
                            <label>Delivery Address</label>
                            <div class="address-text">
                                <?php echo nl2br(htmlspecialchars($order['address'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="order-actions">
                        <?php if ($order['status'] !== 'delivered'): ?>
                            <button class="btn-track">
                                <i class="fas fa-map-marker-alt"></i>
                                Track Order
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($order['status'] === 'delivered'): ?>
                            <button class="btn-review">
                                <i class="fas fa-star"></i>
                                Write Review
                            </button>
                        <?php endif; ?>
                        
                        <button class="btn-details">
                            <i class="fas fa-eye"></i>
                            View Details
                        </button>
                        
                        <?php if (in_array($order['status'], ['pending', 'processing'])): ?>
                            <button class="btn-cancel">
                                <i class="fas fa-times"></i>
                                Cancel Order
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script>
// Order management functionality
document.addEventListener('DOMContentLoaded', function() {
    // Track order functionality
    document.querySelectorAll('.btn-track').forEach(btn => {
        btn.addEventListener('click', function() {
            // In a real application, this would open a tracking modal or page
            alert('Tracking functionality will be implemented here');
        });
    });
    
    // Review functionality
    document.querySelectorAll('.btn-review').forEach(btn => {
        btn.addEventListener('click', function() {
            // In a real application, this would open a review modal
            alert('Review functionality will be implemented here');
        });
    });
    
    // View details functionality
    document.querySelectorAll('.btn-details').forEach(btn => {
        btn.addEventListener('click', function() {
            // In a real application, this would open order details modal
            alert('Order details functionality will be implemented here');
        });
    });
    
    // Cancel order functionality
    document.querySelectorAll('.btn-cancel').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Are you sure you want to cancel this order?')) {
                alert('Order cancellation functionality will be implemented here');
            }
        });
    });
});
</script>

<style>
/* XOBO-MART STYLE ORDERS STYLING */
.orders-header {
    text-align: center;
    margin: 2rem 0;
    padding: 2rem;
    background: var(--xobo-white);
    border-radius: 8px;
    box-shadow: 0 2px 5px var(--xobo-shadow);
}

.orders-header h1 {
    color: var(--xobo-primary);
    font-size: 2rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.orders-description {
    color: var(--xobo-gray);
    font-size: 1rem;
    margin: 0;
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin: 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.no-orders {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 50vh;
    background: var(--xobo-white);
    border-radius: 8px;
    box-shadow: 0 2px 5px var(--xobo-shadow);
}

.no-orders-content {
    text-align: center;
    padding: 3rem;
}

.no-orders-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
    color: var(--xobo-gray);
}

.no-orders-content h3 {
    color: var(--xobo-primary);
    margin-bottom: 1rem;
    font-size: 1.5rem;
    font-weight: 600;
}

.no-orders-content p {
    color: var(--xobo-gray);
    margin-bottom: 2rem;
}

.order-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin: 2rem 0;
}

.stat-card {
    background: var(--xobo-white);
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 5px var(--xobo-shadow);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-icon {
    font-size: 2rem;
    background: var(--xobo-light-gray);
    padding: 1rem;
    border-radius: 50%;
}

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-number {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--xobo-primary);
    line-height: 1.2;
}

.stat-label {
    font-size: 0.8rem;
    color: var(--xobo-gray);
}

.orders-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin: 2rem 0;
}

.order-card {
    background: var(--xobo-white);
    border-radius: 8px;
    box-shadow: 0 2px 5px var(--xobo-shadow);
    overflow: hidden;
    transition: all 0.3s;
}

.order-card:hover {
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    background: var(--xobo-light-gray);
    border-bottom: 1px solid var(--xobo-border);
}

.order-main-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.order-id-section h3 {
    color: var(--xobo-primary);
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 0.25rem 0;
}

.tracking-number {
    font-size: 0.8rem;
    color: var(--xobo-gray);
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.order-date {
    font-size: 0.8rem;
    color: var(--xobo-gray);
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.order-status-section {
    text-align: right;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-end;
}

.order-total {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--xobo-primary);
}

.status-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-processing {
    background: #cce5ff;
    color: #004085;
}

.status-shipped {
    background: #e1ecf4;
    color: #0c5460;
}

.status-delivered {
    background: #d4edda;
    color: #155724;
}

.order-body {
    padding: 1.5rem;
}

.order-details-grid {
    display: grid;
    grid-template-columns: 1fr 2fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.order-details-grid > div {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.order-details-grid label {
    font-size: 0.8rem;
    color: var(--xobo-gray);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.order-details-grid span {
    color: var(--xobo-primary);
    font-weight: 500;
    font-size: 0.9rem;
}

.address-text {
    color: var(--xobo-primary);
    font-weight: 500;
    font-size: 0.9rem;
    line-height: 1.4;
}

.order-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    border-top: 1px solid var(--xobo-border);
    padding-top: 1rem;
}

.order-actions button {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.btn-track {
    background: var(--xobo-primary);
    color: white;
}

.btn-track:hover {
    background: var(--xobo-primary-hover);
}

.btn-review {
    background: #f39c12;
    color: white;
}

.btn-review:hover {
    background: #e67e22;
}

.btn-details {
    background: var(--xobo-gray);
    color: white;
}

.btn-details:hover {
    background: #555;
}

.btn-cancel {
    background: var(--xobo-accent);
    color: white;
}

.btn-cancel:hover {
    background: #c0392b;
}

/* Responsive Design */
@media (max-width: 992px) {
    .order-details-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .order-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .order-status-section {
        align-items: center;
    }
}

@media (max-width: 768px) {
    .orders-header {
        padding: 1rem;
    }
    
    .orders-header h1 {
        font-size: 1.5rem;
    }
    
    .order-stats {
        grid-template-columns: 1fr;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .order-card {
        margin: 0 -1rem;
        border-radius: 0;
    }
    
    .order-actions {
        flex-direction: column;
    }
    
    .order-actions button {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .order-header {
        padding: 1rem;
    }
    
    .order-body {
        padding: 1rem;
    }
}
</style>

<?php include '../includes/footer.php'; ?> 