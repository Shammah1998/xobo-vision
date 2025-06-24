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
    <!-- SIMPLIFIED NAVIGATION - LOGO ONLY -->
    <nav class="header">
        <div class="container">
            <div class="nav-brand">
                <a href="/xobo-vision/">
                    <img src="/xobo-vision/assets/images/xobo-logo.png" alt="XOBO MART" class="logo">
                </a>
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
/* SIMPLIFIED NAVIGATION - LOGO POSITIONED LEFT */
.header .container {
    display: flex;
    justify-content: flex-start;
    align-items: center;
    height: 80px;
    padding: 0 2rem;
}

.nav-brand {
    flex-shrink: 0;
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