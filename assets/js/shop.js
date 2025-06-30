// Shop functionality and AJAX operations

document.addEventListener('DOMContentLoaded', function() {
    // Initialize cart functionality
    initializeCart();
    
    // Initialize form validations
    initializeFormValidations();
    
    // Initialize quantity controls
    initializeQuantityControls();
});

// Cart functionality
function initializeCart() {
    // Add to cart with AJAX (if needed in future)
    const addToCartForms = document.querySelectorAll('.add-to-cart-form');
    
    addToCartForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const button = form.querySelector('.add-to-cart-btn');
            const originalText = button.textContent;
            
            // Visual feedback
            button.textContent = 'Adding...';
            button.disabled = true;
            
            // Re-enable after form submission
            setTimeout(() => {
                button.textContent = originalText;
                button.disabled = false;
            }, 1000);
        });
    });
}

// Form validations
function initializeFormValidations() {
    // Password confirmation validation
    const signupForm = document.querySelector('form[action=""]');
    if (signupForm && signupForm.querySelector('input[name="confirm_password"]')) {
        const password = signupForm.querySelector('input[name="password"]');
        const confirmPassword = signupForm.querySelector('input[name="confirm_password"]');
        
        confirmPassword.addEventListener('blur', function() {
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }
        });
        
        password.addEventListener('input', function() {
            if (confirmPassword.value && password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }
        });
    }
    
    // Email validation
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (input.value && !emailRegex.test(input.value)) {
                input.setCustomValidity('Please enter a valid email address');
            } else {
                input.setCustomValidity('');
            }
        });
    });
}

// Quantity controls
function initializeQuantityControls() {
    const quantityInputs = document.querySelectorAll('input[type="number"]');
    
    quantityInputs.forEach(input => {
        // Prevent negative values
        input.addEventListener('input', function() {
            if (parseInt(this.value) < 0) {
                this.value = 0;
            }
        });
        
        // Add increment/decrement buttons if needed
        if (input.closest('.quantity-input')) {
            addQuantityButtons(input);
        }
    });
}

// Add quantity increment/decrement buttons
function addQuantityButtons(input) {
    const container = input.closest('.quantity-input');
    
    // Check if buttons already exist
    if (container.querySelector('.qty-btn')) {
        return;
    }
    
    const decrementBtn = document.createElement('button');
    decrementBtn.type = 'button';
    decrementBtn.className = 'qty-btn qty-decrement';
    decrementBtn.innerHTML = '-';
    decrementBtn.addEventListener('click', function() {
        const currentValue = parseInt(input.value) || 0;
        if (currentValue > (input.min ? parseInt(input.min) : 0)) {
            input.value = currentValue - 1;
            input.dispatchEvent(new Event('change'));
        }
    });
    
    const incrementBtn = document.createElement('button');
    incrementBtn.type = 'button';
    incrementBtn.className = 'qty-btn qty-increment';
    incrementBtn.innerHTML = '+';
    incrementBtn.addEventListener('click', function() {
        const currentValue = parseInt(input.value) || 0;
        const maxValue = input.max ? parseInt(input.max) : 999;
        if (currentValue < maxValue) {
            input.value = currentValue + 1;
            input.dispatchEvent(new Event('change'));
        }
    });
    
    // Wrap input with quantity controls
    const wrapper = document.createElement('div');
    wrapper.className = 'qty-controls';
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(decrementBtn);
    wrapper.appendChild(input);
    wrapper.appendChild(incrementBtn);
}

// Utility functions
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} notification`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1000;
        min-width: 300px;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    .qty-controls {
        display: flex;
        align-items: center;
        gap: 0;
        max-width: 120px;
    }
    
    .qty-btn {
        background: #f8f9fa;
        border: 1px solid #ced4da;
        width: 30px;
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-weight: bold;
        transition: background-color 0.2s;
    }
    
    .qty-btn:hover {
        background: #e9ecef;
    }
    
    .qty-decrement {
        border-radius: 4px 0 0 4px;
    }
    
    .qty-increment {
        border-radius: 0 4px 4px 0;
    }
    
    .qty-controls input {
        border-left: none;
        border-right: none;
        border-radius: 0;
        text-align: center;
        width: 60px;
        height: 38px;
    }
    
    .qty-controls input:focus {
        z-index: 1;
        position: relative;
    }
`;
document.head.appendChild(style);

// Cart update functionality
function updateCartDisplay() {
    const cartLinks = document.querySelectorAll('a[href*="cart"]');
    // This would be enhanced with actual cart count if using AJAX
}

// Auto-save cart (if implementing AJAX cart)
function autoSaveCart() {
    // Implementation for auto-saving cart to session/database
    // This would be used for better UX if implementing AJAX functionality
}

// Search functionality (if implementing product search)
function initializeSearch() {
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch(this.value);
            }, 300);
        });
    }
}

function performSearch(query) {
    // Implementation for product search
    // This would filter products or make AJAX calls for search results
}

// Loading states
function showLoading(element) {
    element.style.opacity = '0.6';
    element.style.pointerEvents = 'none';
}

function hideLoading(element) {
    element.style.opacity = '1';
    element.style.pointerEvents = 'auto';
}

// Confirmation dialogs
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Format currency (client-side)
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES',
        minimumFractionDigits: 2
    }).format(amount);
} 