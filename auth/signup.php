<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Registration Disabled - XOBO MART';
include '../includes/header.php';
?>

<style>
.disabled-container {
    max-width: 600px;
    margin: 4rem auto;
    padding: 2rem;
    background: var(--xobo-white);
    border-radius: 8px;
    box-shadow: 0 2px 10px var(--xobo-shadow);
    text-align: center;
}

.disabled-header {
    margin-bottom: 2rem;
}

.disabled-header h1 {
    color: var(--xobo-primary);
    font-size: 1.8rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.disabled-icon {
    font-size: 4rem;
    color: var(--xobo-primary);
    margin-bottom: 1rem;
}

.disabled-message {
    color: var(--xobo-gray);
    font-size: 1.1rem;
    line-height: 1.6;
    margin-bottom: 2rem;
}

.admin-info {
    background: #f0f9ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.admin-info h3 {
    color: var(--xobo-primary);
    margin-bottom: 1rem;
    font-size: 1.2rem;
}

.admin-info p {
    color: var(--xobo-gray);
    margin-bottom: 0.5rem;
}

.contact-section {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.contact-section h3 {
    color: var(--xobo-primary);
    margin-bottom: 1rem;
}

.feature-list {
    text-align: left;
    margin: 1.5rem 0;
}

.feature-list li {
    margin-bottom: 0.5rem;
    color: var(--xobo-gray);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.feature-list li i {
    color: var(--xobo-primary);
    width: 16px;
}

.btn-contact {
    display: inline-block;
    padding: 12px 24px;
    background: var(--xobo-primary);
    color: white;
    text-decoration: none;
    border-radius: 4px;
    font-weight: 500;
    transition: background 0.3s;
    margin: 0.5rem;
}

.btn-contact:hover {
    background: var(--xobo-primary-hover);
    color: white;
}

.btn-secondary {
    display: inline-block;
    padding: 12px 24px;
    background: var(--xobo-gray);
    color: white;
    text-decoration: none;
    border-radius: 4px;
    font-weight: 500;
    transition: background 0.3s;
    margin: 0.5rem;
}

.btn-secondary:hover {
    background: #374151;
    color: white;
}

@media (max-width: 768px) {
    .disabled-container {
        margin: 2rem 1rem;
        padding: 1.5rem;
    }
    
    .disabled-icon {
        font-size: 3rem;
    }
}
</style>

<div class="disabled-container">
    <div class="disabled-header">
        <div class="disabled-icon">
            <i class="fas fa-user-shield"></i>
        </div>
        <h1>Registration by Invitation Only</h1>
    </div>

    <div class="disabled-message">
        <p>Public registration has been disabled. XOBO MART now operates on an invitation-only basis for enhanced security and better service quality.</p>
    </div>

    <div class="admin-info">
        <h3><i class="fas fa-info-circle"></i> How It Works</h3>
        <ul class="feature-list">
            <li><i class="fas fa-check"></i> Companies are created by system administrators</li>
            <li><i class="fas fa-check"></i> User accounts are created and managed by admins</li>
            <li><i class="fas fa-check"></i> Each company operates in its own secure environment</li>
            <li><i class="fas fa-check"></i> Complete data isolation between companies</li>
            <li><i class="fas fa-check"></i> Enhanced security and access control</li>
        </ul>
    </div>

    <div class="contact-section">
        <h3><i class="fas fa-envelope"></i> Need Access?</h3>
        <p>If you're a business owner interested in using XOBO MART for your company, please contact our administrators to get started.</p>
        <p style="margin-bottom: 1.5rem;">Our team will set up your company profile and create user accounts for your team members.</p>
        
        <div>
            <a href="mailto:admin@xobo.com" class="btn-contact">
                <i class="fas fa-envelope"></i> Contact Admin
            </a>
            <a href="/xobo-vision/auth/login.php" class="btn-secondary">
                <i class="fas fa-sign-in-alt"></i> Login
            </a>
        </div>
    </div>

    <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--xobo-border);">
        <p style="color: var(--xobo-gray); font-size: 0.9rem;">
            <i class="fas fa-shield-alt"></i> 
            This change ensures better security, data privacy, and personalized service for all our business clients.
        </p>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 