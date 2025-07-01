<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.min.css">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>/assets/images/XDL-ICON.png">
</head>
<body>
    <!-- SIMPLIFIED NAVIGATION - LOGO ONLY -->
    <nav class="header">
        <div class="container">
            <div class="nav-brand">
                <a href="<?php echo BASE_URL; ?>/">
                    <img src="<?php echo BASE_URL; ?>/assets/images/xobo-logo.png" alt="XOBO MART" class="logo">
                </a>
            </div>
            
            <div class="nav-right">
                <?php if (isLoggedIn()): ?>
                    <div class="user-menu" id="user-menu">
                        <button class="user-dropdown-toggle">
                            <i class="fas fa-user-circle user-avatar"></i>
                            <span class="user-email"><?php echo htmlspecialchars($_SESSION['email']); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="user-dropdown-menu" id="user-dropdown-menu">
                            <div class="dropdown-header">
                                <strong><?php echo htmlspecialchars($_SESSION['email']); ?></strong>
                                <small><?php echo htmlspecialchars($_SESSION['role']); ?></small>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo BASE_URL; ?>/auth/logout" class="dropdown-item logout-link">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
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
    
    // Initialize undo button
    const undoButton = document.getElementById('undoButton');
    if (undoButton) {
        // Initially disable the undo button since there's nothing to undo
        undoButton.disabled = true;
        
        // Add click event listener
        undoButton.addEventListener('click', function() {
            // Trigger undo action
            if (window.undoLastAction && typeof window.undoLastAction === 'function') {
                window.undoLastAction();
            }
        });
    }
    
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
/* SIMPLIFIED NAVIGATION - LOGO POSITIONED LEFT */
.header .container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    height: 80px;
    padding: 0 2rem;
}

.nav-brand {
    flex-shrink: 0;
}

/* User Menu Styles */
.nav-right {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.user-menu {
    position: relative;
}

.user-dropdown-toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 6px;
    transition: background-color 0.3s;
}

.user-dropdown-toggle:hover {
    background-color: var(--xobo-light-gray);
}

.user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: none;
    font-size: 2rem;
    color: var(--xobo-primary);
    background: none;
    display: flex;
    align-items: center;
    justify-content: center;
}

.user-email {
    font-weight: 500;
    color: var(--xobo-primary);
}

.user-dropdown-toggle .fa-chevron-down {
    color: var(--xobo-gray);
    font-size: 0.8rem;
    transition: transform 0.3s;
}

.user-dropdown-toggle.expanded .fa-chevron-down {
    transform: rotate(180deg);
}

.user-dropdown-menu {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px var(--xobo-shadow);
    border: 1px solid var(--xobo-border);
    min-width: 220px;
    z-index: 1000;
    opacity: 0;
    transform: translateY(10px);
    visibility: hidden;
    transition: all 0.3s ease;
}

.user-dropdown-menu.show {
    opacity: 1;
    transform: translateY(0);
    visibility: visible;
}

.dropdown-header {
    padding: 1rem;
    border-bottom: 1px solid var(--xobo-border);
}

.dropdown-header strong {
    display: block;
    color: var(--xobo-primary);
    font-size: 0.9rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dropdown-header small {
    color: var(--xobo-gray);
    font-size: 0.8rem;
    text-transform: capitalize;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    color: var(--text-primary);
    text-decoration: none;
    font-size: 0.9rem;
    transition: background-color 0.3s;
}

.dropdown-item i {
    color: var(--xobo-gray);
    width: 16px;
    text-align: center;
}

.dropdown-item:hover {
    background-color: var(--xobo-light-gray);
}

.logout-link {
    color: var(--xobo-accent);
}

.logout-link i {
    color: var(--xobo-accent);
}

.dropdown-divider {
    height: 1px;
    background: var(--xobo-border);
    margin: 0.5rem 0;
}

/* Logo styling for simplified navigation */
.nav-brand .logo {
    transition: transform 0.3s ease;
}

.nav-brand .logo:hover {
    transform: scale(1.05);
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