<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'XOBO MART - Online Shopping'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/xobo-vision/assets/css/style.css">
</head>
<body>
    <!-- XOBO-MART STYLE NAVIGATION -->
    <nav class="header">
        <div class="container">
            <div class="nav-brand">
                <a href="/xobo-vision/">
                    <img src="/xobo-vision/assets/images/xobo-logo.png" alt="XOBO MART" class="logo">
                </a>
            </div>
            
            <div class="search-container">
                <form action="/xobo-vision/catalog.php" method="get">
                    <input type="text" name="search" class="search-input" placeholder="Search products...">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            
            <div class="nav-right">
                <!-- Cart Menu -->
                <div class="cart-menu" id="cart-menu">
                    <a href="/xobo-vision/cart.php" class="cart-link">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count" id="cart-count">0</span>
                    </a>
                </div>
                
                <!-- User Menu -->
                <div class="user-menu" id="user-menu">
                    <?php if (isLoggedIn()): ?>
                        <div class="user-dropdown-toggle">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="user-dropdown-menu" id="user-dropdown-menu">
                            <div class="user-welcome">
                                <div class="welcome-text">Welcome back!</div>
                                <div class="user-email"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
                            </div>
                            <a class="dropdown-item logout-btn" href="/xobo-vision/auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Log Out
                            </a>
                        </div>
                    <?php else: ?>
                        <a href="/xobo-vision/auth/login.php" class="auth-link">
                            <i class="fas fa-user"></i> Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <div class="container">

<script>
// XOBO-MART STYLE JAVASCRIPT FUNCTIONALITY - WITH DEBUGGING

// Update cart count from localStorage
function updateCartCount() {
    const cart = JSON.parse(localStorage.getItem('shopping_cart') || '[]');
    const cartCount = cart.reduce((total, item) => total + item.quantity, 0);
    const cartCountElement = document.getElementById('cart-count');
    if (cartCountElement) {
        cartCountElement.textContent = cartCount;
        cartCountElement.style.display = cartCount > 0 ? 'inline-block' : 'none';
    }
}

// User dropdown functionality - Fixed toggle logic
let dropdownState = 'closed'; // Track state: 'closed', 'hover', 'clicked'

function toggleUserDropdown() {
    console.log('toggleUserDropdown called');
    const dropdown = document.getElementById('user-dropdown-menu');
    
    if (!dropdown) {
        console.error('Dropdown element not found!');
        return;
    }
    
    console.log('Dropdown element found:', dropdown);
    console.log('Current dropdown state:', dropdownState);
    
    // Check if dropdown is currently visible using the show class
    const isCurrentlyVisible = dropdown.classList.contains('show');
    console.log('Is currently visible:', isCurrentlyVisible);
    
    if (isCurrentlyVisible) {
        // Hide the dropdown
        dropdown.classList.remove('show');
        dropdownState = 'closed';
        console.log('Hiding dropdown - set to closed');
    } else {
        // Show the dropdown
        dropdown.classList.add('show');
        dropdownState = 'clicked';
        console.log('Showing dropdown - set to clicked');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded');
    
    // Initialize cart count
    updateCartCount();
    
    // Check if user menu elements exist
    const userMenu = document.getElementById('user-menu');
    const userDropdownMenu = document.getElementById('user-dropdown-menu');
    const toggleButton = document.querySelector('.user-dropdown-toggle');
    
    console.log('User menu element:', userMenu);
    console.log('Dropdown menu element:', userDropdownMenu);
    console.log('Toggle button element:', toggleButton);

    if (userMenu && userDropdownMenu && toggleButton) {
        console.log('All dropdown elements found, setting up event listeners');
        
        // Handle click events on the toggle button
        toggleButton.addEventListener('click', function(e) {
            console.log('Toggle button clicked');
            e.preventDefault();
            e.stopPropagation();
            toggleUserDropdown();
        });
        
        // Handle hover events (only when not in clicked state)
        userMenu.addEventListener('mouseenter', function() {
            if (dropdownState !== 'clicked') {
                console.log('Mouse entered user menu - showing via hover');
                userDropdownMenu.classList.add('show');
                dropdownState = 'hover';
            }
        });

        userMenu.addEventListener('mouseleave', function() {
            if (dropdownState === 'hover') {
                console.log('Mouse left user menu - hiding via hover');
                setTimeout(function() {
                    if (dropdownState === 'hover' && !userDropdownMenu.matches(':hover')) {
                        userDropdownMenu.classList.remove('show');
                        dropdownState = 'closed';
                    }
                }, 300);
            }
        });

        userDropdownMenu.addEventListener('mouseenter', function() {
            if (dropdownState === 'hover') {
                userDropdownMenu.classList.add('show');
            }
        });

        userDropdownMenu.addEventListener('mouseleave', function() {
            if (dropdownState === 'hover') {
                userDropdownMenu.classList.remove('show');
                dropdownState = 'closed';
            }
        });
        
    } else {
        console.log('Some dropdown elements are missing:');
        console.log('- User menu:', !!userMenu);
        console.log('- Dropdown menu:', !!userDropdownMenu);
        console.log('- Toggle button:', !!toggleButton);
    }
    
    // Update cart count whenever localStorage changes
    window.addEventListener('storage', function(e) {
        if (e.key === 'shopping_cart') {
            updateCartCount();
        }
    });
});

// Global function to update cart count (called from other pages)
window.updateCartDisplay = updateCartCount;

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const userMenu = document.getElementById('user-menu');
    const dropdown = document.getElementById('user-dropdown-menu');
    
    if (userMenu && dropdown && !userMenu.contains(event.target)) {
        console.log('Clicked outside user menu, closing dropdown');
        dropdown.classList.remove('show');
        dropdownState = 'closed';
    }
});

// Additional debugging - show session info
console.log('User logged in status: <?php echo isLoggedIn() ? "true" : "false"; ?>');
<?php if (isLoggedIn()): ?>
console.log('User email: <?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : "NOT SET"; ?>');
console.log('User ID: <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : "NOT SET"; ?>');
<?php endif; ?>
</script>

<style>
/* XOBO-MART NAVIGATION IMPROVED SPACING & ALIGNMENT */
.header .container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    height: 80px;
    padding: 0 3rem;
    gap: 2rem;
}

.nav-brand {
    flex-shrink: 0;
}

.search-container {
    flex: 1;
    max-width: 600px;
    margin: 0 2rem;
}

.nav-right {
    display: flex;
    align-items: center;
    gap: 2rem;
    flex-shrink: 0;
}

/* Cart Menu Improved Styling */
.cart-menu {
    position: relative;
}

.cart-link {
    display: flex;
    align-items: center;
    color: var(--xobo-primary);
    text-decoration: none;
    font-size: 1.2rem;
    transition: all 0.3s;
    padding: 0.5rem;
    border-radius: 50%;
}

.cart-link:hover {
    color: var(--xobo-primary-hover);
    background: var(--xobo-light-gray);
}

.cart-count {
    position: absolute;
    top: -5px;
    right: -5px;
    background: var(--xobo-accent);
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 0.7rem;
    font-weight: bold;
    min-width: 18px;
    text-align: center;
    line-height: 1.2;
    border: 2px solid white;
}

/* User Menu - Xobo-Mart Style */
.user-menu {
    position: relative;
}

.user-dropdown-toggle {
    display: flex;
    align-items: center;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 50%;
    transition: all 0.3s;
}

.user-dropdown-toggle:hover {
    background: var(--xobo-light-gray);
}

.user-dropdown-toggle i {
    font-size: 1.5rem;
    color: var(--xobo-primary);
    transition: color 0.3s;
}

.user-dropdown-toggle:hover i {
    color: var(--xobo-primary-hover);
}

.user-dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    background: var(--xobo-white);
    box-shadow: 0 4px 12px var(--xobo-shadow);
    border-radius: 8px;
    overflow: hidden;
    z-index: 1000;
    min-width: 220px;
    border: 1px solid var(--xobo-border);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
}

.user-dropdown-menu.show {
    display: block;
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.user-dropdown-menu .dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    text-decoration: none;
    font-size: 0.9rem;
    color: var(--xobo-gray);
    transition: all 0.3s;
    border-bottom: 1px solid var(--xobo-border);
}

.user-dropdown-menu .dropdown-item:last-child {
    border-bottom: none;
}

.user-dropdown-menu .dropdown-item:hover {
    background: var(--xobo-light-gray);
    color: var(--xobo-primary);
}

.user-dropdown-menu .dropdown-item i {
    width: 16px;
    text-align: center;
    font-size: 0.9rem;
}

.user-welcome {
    padding: 1rem;
    background: var(--xobo-light-gray);
    border-bottom: 1px solid var(--xobo-border);
    text-align: center;
}

.welcome-text {
    font-size: 0.8rem;
    color: var(--xobo-gray);
    margin-bottom: 0.3rem;
    font-weight: 500;
}

.user-email {
    font-size: 0.9rem;
    color: var(--xobo-primary);
    font-weight: 600;
    word-break: break-word;
}

.logout-btn:hover {
    background: #fef2f2 !important;
    color: var(--xobo-accent) !important;
}

/* Auth Link with Icon */
.auth-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--xobo-primary);
    text-decoration: none;
    font-weight: 500;
    font-size: 1rem;
    padding: 0.75rem 1.25rem;
    border: 2px solid var(--xobo-primary);
    border-radius: 6px;
    transition: all 0.3s;
}

.auth-link:hover {
    background: var(--xobo-primary);
    color: white;
}

.auth-link i {
    font-size: 1rem;
}

/* Mobile Responsive - Improved */
@media (max-width: 992px) {
    .header .container {
        flex-direction: column;
        gap: 1rem;
        padding: 1rem 2rem;
        height: auto;
    }
    
    .search-container {
        order: 2;
        max-width: 100%;
        margin: 0;
        width: 100%;
    }
    
    .nav-right {
        order: 3;
        justify-content: center;
        gap: 2.5rem;
    }
    
    .nav-brand {
        order: 1;
    }
}

@media (max-width: 768px) {
    .header .container {
        padding: 1rem;
        gap: 0.75rem;
    }
    
    .nav-right {
        gap: 2rem;
    }
    
    .user-dropdown-menu {
        right: -50px;
        min-width: 200px;
    }
    
    .cart-link,
    .user-dropdown-toggle i {
        font-size: 1.1rem;
    }
}

@media (max-width: 480px) {
    .search-container {
        min-width: 100%;
    }
    
    .auth-link {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }
    
    .nav-right {
        gap: 1.5rem;
    }
}
</style> 