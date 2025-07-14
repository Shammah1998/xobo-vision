<?php
require_once __DIR__ . '/config/config.php';
session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Only allow logged-in users (could restrict to admin_user or admin if needed)
if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/auth/login');
    exit;
}

// Only allow admin_user to send invites
if ($_SESSION['role'] !== 'admin_user') {
    header('Location: ' . BASE_URL . '/index');
    exit;
}

$message = '';
$error = '';

function generateTempPassword($length = 12) {
    return bin2hex(random_bytes($length / 2));
}

function sendInviteEmail($recipientEmail, $recipientName, $tempPassword) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = 'mail.xobodelivery.co.ke';
        $mail->SMTPAuth = true;
        $mail->Username = 'noreply@xobodelivery.co.ke';
        $mail->Password = '@xobomart2025';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        $mail->setFrom('noreply@xobodelivery.co.ke', 'Xobo Delivery');
        
        // Recipients
        $mail->addAddress($recipientEmail, $recipientName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to Xobo Delivery - Your Account is Ready';
        
        $emailBody = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #16234d;'>Welcome to Xobo Delivery!</h2>
                
                <p style='color: #333;'>Dear $recipientName,</p>
                
                <p style='color: #333;'>We are delighted to welcome you to Xobo Delivery, your trusted logistics partner. Your new account is now active and ready for use.</p>
                
                <h3 style='color: #16234d;'>Here are your account details:</h3>
                <ul style='background: #f8f9fa; padding: 15px; border-left: 4px solid #16234d; color: #333;'>
                    <li><strong>Account Name:</strong> $recipientName</li>
                    <li><strong>Username/Email:</strong> $recipientEmail</li>
                    <li><strong>Temporary Password:</strong> $tempPassword</li>
                </ul>
                
                <div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px; margin: 15px 0; color: #333;'>
                    <strong>Important:</strong> This is a temporary password valid for 3 hours. Please log in and change your password immediately.
                </div>

                <div style='text-align: center; margin: 32px 0 24px 0;'>
                  <a href='https://panel.xobodelivery.co.ke/auth/login' style='display: inline-block; background: #16234d; color: #fff; text-decoration: none; font-weight: 600; padding: 12px 32px; border-radius: 6px; font-size: 1.1rem;'>
                    Login to Xobo Delivery
                  </a>
                </div>
                
                <p style='color: #333;'>If you have any questions, need assistance, or require customized logistics solutions, our dedicated customer support team is available to assist you. You can reach us at <a href='mailto:info@xobo.co.ke' style='color: #16234d;'>info@xobo.co.ke</a> or call us at +254 799 396 000.</p>
                
                <p style='color: #333;'>Thank you for choosing Xobo Delivery. We are committed to providing you with reliable and efficient logistics services tailored to your needs.</p>
                
                <p style='color: #333;'>We look forward to serving you and simplifying your logistics challenges.</p>
                
                <p style='margin-top: 30px; color: #333;'>
                    Best regards,<br>
                    <strong>The Xobo Delivery Team</strong>
                </p>
                
                <hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>
                <p style='font-size: 12px; color: #666;'>
                    This is an automated message. Please do not reply to this email.
                </p>
            </div>
        </body>
        </html>";
        
        $mail->Body = $emailBody;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    
    if (empty($name) || empty($email)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'A user with this email already exists.';
            } else {
                // Generate temporary password
                $tempPassword = generateTempPassword();
                $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
                
                // Get current user's company
                $companyId = $_SESSION['company_id'];
                
                // Insert new user
                $stmt = $pdo->prepare("INSERT INTO users (company_id, email, name, password, role, created_at) VALUES (?, ?, ?, ?, 'user', NOW())");
                $stmt->execute([$companyId, $email, $name, $hashedPassword]);
                
                // Send invite email
                if (sendInviteEmail($email, $name, $tempPassword)) {
                    $message = "Invitation sent successfully to $name ($email). They will receive an email with their login credentials.";
                    // Clear form
                    $_POST = [];
                } else {
                    $error = "User created but failed to send email. Please contact the user manually.";
                }
            }
        } catch (PDOException $e) {
            $error = 'Failed to create user. Please try again.';
            error_log("Database Error: " . $e->getMessage());
        }
    }
}

$pageTitle = 'Invite User';
include __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width: 500px; margin: 3rem auto 0 auto;">
    <div class="card" style="background: #fff; border-radius: 8px; box-shadow: 0 2px 10px var(--xobo-shadow); padding: 2.5rem 2rem;">
        <div style="display: flex; align-items: center; gap: 1.2rem; margin-bottom: 1.5rem;">
            <i class="fas fa-user-plus" style="font-size: 2.2rem; color: var(--xobo-primary);"></i>
            <div>
                <h2 style="margin: 0; color: var(--xobo-primary); font-size: 1.5rem; font-weight: 600;">Invite User</h2>
                <p style="margin: 0.25rem 0 0 0; color: var(--xobo-gray); font-size: 1rem;">Send an invitation to a new user by email.</p>
            </div>
        </div>
        <?php if ($message): ?>
            <div class="alert alert-success" style="margin-bottom: 1rem;"> <?php echo htmlspecialchars($message); ?> </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 1rem;"> <?php echo htmlspecialchars($error); ?> </div>
        <?php endif; ?>
        <form method="POST" style="display: flex; flex-direction: column; gap: 1.2rem;">
            <div class="form-group">
                <label for="name" style="font-weight: 600; color: var(--xobo-primary);">Full Name</label>
                <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div class="form-group">
                <label for="email" style="font-weight: 600; color: var(--xobo-primary);">Email Address</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required style="padding: 0.7rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <button type="submit" class="btn" id="inviteBtn" style="min-width: 140px; text-align: center; display: flex; align-items: center; justify-content: center; position: relative;">
                Send Invite
                <span class="spinner" id="inviteSpinner" style="display: none; margin-left: 12px; width: 20px; height: 20px;"></span>
            </button>
        </form>
    </div>
</div>
<style>
.spinner {
  border: 3px solid #f3f3f3;
  border-top: 3px solid #16234d;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  animation: spin 0.7s linear infinite;
}
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var form = document.querySelector('form');
  var btn = document.getElementById('inviteBtn');
  var spinner = document.getElementById('inviteSpinner');
  if (form && btn && spinner) {
    form.addEventListener('submit', function() {
      btn.disabled = true;
      spinner.style.display = 'inline-block';
    });
  }
  // If success message is present, redirect after 2s
  var success = document.querySelector('.alert-success');
  if (success) {
    btn.disabled = true;
    spinner.style.display = 'inline-block';
    setTimeout(function() {
      window.location.href = 'profile.php';
    }, 2000);
  }
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?> 