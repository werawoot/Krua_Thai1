<?php
/**
 * Data Deletion Request Processing - Somdul Table
 * File: process-deletion.php
 */

// Start session for potential user authentication
session_start();

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: data-deletion.php');
    exit();
}

// Include database configuration and functions
require_once 'config/database.php';
require_once 'includes/functions.php';

// Initialize variables
$errors = [];
$success = false;

// Validate and sanitize input data
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
$phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
$user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_STRING);
$deletion_reason = filter_input(INPUT_POST, 'deletion_reason', FILTER_SANITIZE_STRING);
$additional_info = filter_input(INPUT_POST, 'additional_info', FILTER_SANITIZE_STRING);
$facebook_data = filter_input(INPUT_POST, 'facebook_data', FILTER_SANITIZE_STRING);
$confirm_deletion = filter_input(INPUT_POST, 'confirm_deletion', FILTER_SANITIZE_STRING);
$identity_confirmation = filter_input(INPUT_POST, 'identity_confirmation', FILTER_SANITIZE_STRING);

// Validation
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Valid email address is required.";
}

if (empty($full_name) || strlen(trim($full_name)) < 2) {
    $errors[] = "Full name is required and must be at least 2 characters.";
}

if ($confirm_deletion !== 'yes') {
    $errors[] = "You must confirm that you understand the deletion is permanent.";
}

if ($identity_confirmation !== 'yes') {
    $errors[] = "You must confirm your identity as the account owner.";
}

// Process the deletion request if no errors
if (empty($errors)) {
    try {
        // Check if user exists in database
        $user_exists = false;
        $found_user_id = null;
        
        if (isset($connection)) {
            // Try to find user by email first
            $stmt = mysqli_prepare($connection, "SELECT id, first_name, last_name, email FROM users WHERE email = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $user = mysqli_fetch_assoc($result);
                
                if ($user) {
                    $user_exists = true;
                    $found_user_id = $user['id'];
                    
                    // Verify the name matches (flexible matching)
                    $db_full_name = trim($user['first_name'] . ' ' . $user['last_name']);
                    $provided_name = trim($full_name);
                    
                    // Simple name matching - could be enhanced
                    if (strcasecmp($db_full_name, $provided_name) !== 0) {
                        $errors[] = "The provided name does not match our records.";
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }
        
        if (empty($errors)) {
            // Generate a unique deletion request ID
            $deletion_request_id = uniqid('del_', true);
            $request_date = date('Y-m-d H:i:s');
            $estimated_completion = date('Y-m-d', strtotime('+30 days'));
            
            // Create deletion request record
            $deletion_data = [
                'request_id' => $deletion_request_id,
                'email' => $email,
                'full_name' => $full_name,
                'phone' => $phone ?: null,
                'user_id' => $user_exists ? $found_user_id : $user_id,
                'deletion_reason' => $deletion_reason ?: null,
                'additional_info' => $additional_info ?: null,
                'include_facebook_data' => ($facebook_data === 'yes') ? 1 : 0,
                'request_date' => $request_date,
                'status' => 'pending',
                'estimated_completion' => $estimated_completion,
                'user_exists' => $user_exists ? 1 : 0,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ];
            
            // Store deletion request in database
            if (isset($connection)) {
                $insert_query = "INSERT INTO data_deletion_requests 
                    (request_id, email, full_name, phone, user_id, deletion_reason, 
                     additional_info, include_facebook_data, request_date, status, 
                     estimated_completion, user_exists, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = mysqli_prepare($connection, $insert_query);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "sssssssissssss", 
                        $deletion_data['request_id'],
                        $deletion_data['email'],
                        $deletion_data['full_name'],
                        $deletion_data['phone'],
                        $deletion_data['user_id'],
                        $deletion_data['deletion_reason'],
                        $deletion_data['additional_info'],
                        $deletion_data['include_facebook_data'],
                        $deletion_data['request_date'],
                        $deletion_data['status'],
                        $deletion_data['estimated_completion'],
                        $deletion_data['user_exists'],
                        $deletion_data['ip_address'],
                        $deletion_data['user_agent']
                    );
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success = true;
                        
                        // If user exists, immediately suspend their account
                        if ($user_exists && $found_user_id) {
                            $suspend_query = "UPDATE users SET status = 'deletion_requested', updated_at = NOW() WHERE id = ?";
                            $suspend_stmt = mysqli_prepare($connection, $suspend_query);
                            if ($suspend_stmt) {
                                mysqli_stmt_bind_param($suspend_stmt, "s", $found_user_id);
                                mysqli_stmt_execute($suspend_stmt);
                                mysqli_stmt_close($suspend_stmt);
                            }
                        }
                        
                        // Send confirmation email
                        send_deletion_confirmation_email($email, $full_name, $deletion_request_id, $estimated_completion);
                        
                        // Send notification to admin team
                        send_admin_deletion_notification($deletion_data);
                        
                    } else {
                        $errors[] = "Failed to submit deletion request. Please try again.";
                        error_log("Deletion request insert failed: " . mysqli_error($connection));
                    }
                    
                    mysqli_stmt_close($stmt);
                } else {
                    $errors[] = "Database error occurred. Please try again.";
                    error_log("Deletion request prepare failed: " . mysqli_error($connection));
                }
            } else {
                // If no database connection, still send email notification
                $success = true;
                send_deletion_confirmation_email($email, $full_name, $deletion_request_id, $estimated_completion);
                send_admin_deletion_notification($deletion_data);
            }
        }
        
    } catch (Exception $e) {
        $errors[] = "An unexpected error occurred. Please try again.";
        error_log("Data deletion request error: " . $e->getMessage());
    }
}

/**
 * Send confirmation email to user
 */
function send_deletion_confirmation_email($email, $name, $request_id, $completion_date) {
    $subject = "Data Deletion Request Confirmation - Somdul Table";
    $headers = [
        'From: Somdul Table Privacy Team <privacy@somdultable.com>',
        'Reply-To: privacy@somdultable.com',
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #2c3e50; }
            .header { background: #cf723a; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { background: #ece8e1; padding: 15px; text-align: center; font-size: 0.9em; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>Somdul Table</h1>
            <h2>Data Deletion Request Confirmed</h2>
        </div>
        <div class='content'>
            <p>Dear " . htmlspecialchars($name) . ",</p>
            
            <p>We have received your request to delete your personal data from Somdul Table. This email confirms that your request has been submitted successfully.</p>
            
            <p><strong>Request Details:</strong></p>
            <ul>
                <li>Request ID: " . htmlspecialchars($request_id) . "</li>
                <li>Submitted: " . date('F j, Y \a\t g:i A') . "</li>
                <li>Estimated Completion: " . date('F j, Y', strtotime($completion_date)) . "</li>
            </ul>
            
            <p><strong>What happens next:</strong></p>
            <ol>
                <li>Your account access has been immediately suspended</li>
                <li>Our privacy team will verify your identity (if needed)</li>
                <li>Personal data will be deleted from our systems within 30 days</li>
                <li>You will receive a final confirmation once deletion is complete</li>
            </ol>
            
            <p><strong>Important:</strong> This action is permanent and cannot be undone. If you change your mind, please contact us within 7 days at privacy@somdultable.com.</p>
            
            <p>If you have any questions about this process, please don't hesitate to contact our Privacy Team.</p>
            
            <p>Thank you for using Somdul Table.</p>
        </div>
        <div class='footer'>
            <p>Somdul Table Privacy Team<br>
            Email: privacy@somdultable.com<br>
            Phone: +1 (555) PRIVACY</p>
        </div>
    </body>
    </html>
    ";
    
    // Send email (in production, use a proper mail service)
    mail($email, $subject, $message, implode("\r\n", $headers));
}

/**
 * Send notification to admin team
 */
function send_admin_deletion_notification($data) {
    $admin_email = "privacy@somdultable.com";
    $subject = "New Data Deletion Request - " . $data['request_id'];
    
    $message = "New data deletion request received:\n\n";
    $message .= "Request ID: " . $data['request_id'] . "\n";
    $message .= "Email: " . $data['email'] . "\n";
    $message .= "Name: " . $data['full_name'] . "\n";
    $message .= "User Exists: " . ($data['user_exists'] ? 'Yes' : 'No') . "\n";
    $message .= "Include Facebook Data: " . ($data['include_facebook_data'] ? 'Yes' : 'No') . "\n";
    $message .= "Reason: " . ($data['deletion_reason'] ?: 'Not specified') . "\n";
    $message .= "Submitted: " . $data['request_date'] . "\n";
    $message .= "IP Address: " . $data['ip_address'] . "\n\n";
    $message .= "Additional Info: " . ($data['additional_info'] ?: 'None') . "\n";
    
    mail($admin_email, $subject, $message);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $success ? 'Deletion Request Submitted' : 'Request Error'; ?> - Somdul Table</title>
    <link href="https://ydpschool.com/fonts/" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Regular.woff2') format('woff2');
            font-weight: 400;
            font-display: swap;
        }

        :root {
            --brown: #bd9379;
            --cream: #ece8e1;
            --sage: #adb89d;
            --curry: #cf723a;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-gray: #7f8c8d;
            --success: #27ae60;
            --error: #e74c3c;
            --shadow-soft: 0 4px 12px rgba(189, 147, 121, 0.15);
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--cream) 0%, #f8f9fa 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--text-dark);
        }

        .result-container {
            background: var(--white);
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            box-shadow: var(--shadow-soft);
            max-width: 600px;
            width: 100%;
        }

        .logo-section {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--curry), var(--brown));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.5rem;
            font-weight: 700;
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--curry);
        }

        .status-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }

        .success .status-icon {
            color: var(--success);
        }

        .error .status-icon {
            color: var(--error);
        }

        h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .success h1 {
            color: var(--success);
        }

        .error h1 {
            color: var(--error);
        }

        .message {
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
            color: var(--text-gray);
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            box-shadow: var(--shadow-soft);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: transparent;
            color: var(--curry);
            border: 2px solid var(--curry);
        }

        .btn-secondary:hover {
            background: var(--curry);
            color: var(--white);
        }

        .error-list {
            background: #ffebee;
            border: 1px solid var(--error);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
        }

        .error-list ul {
            list-style: none;
            padding: 0;
        }

        .error-list li {
            padding: 0.5rem 0;
            color: var(--error);
        }

        .error-list li::before {
            content: "⚠️ ";
            margin-right: 0.5rem;
        }

        .success-details {
            background: #e8f5e8;
            border: 1px solid var(--success);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
        }

        .success-details h3 {
            color: var(--success);
            margin-bottom: 1rem;
        }

        @media (max-width: 600px) {
            .result-container {
                padding: 2rem 1.5rem;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .status-icon {
                font-size: 3rem;
            }
        }
    </style>
</head>
<body>
    <div class="result-container">
        <div class="logo-section">
            <div class="logo-icon">S</div>
            <span class="logo-text">Somdul Table</span>
        </div>

        <?php if ($success): ?>
            <div class="success">
                <div class="status-icon">✅</div>
                <h1>Deletion Request Submitted</h1>
                
                <p class="message">
                    Your data deletion request has been successfully submitted. We have sent a confirmation email to <strong><?php echo htmlspecialchars($email); ?></strong> with your request details.
                </p>

                <div class="success-details">
                    <h3>What happens next:</h3>
                    <ol>
                        <li>Your account access has been immediately suspended</li>
                        <li>Our privacy team will process your request within 30 days</li>
                        <li>You will receive a final confirmation once deletion is complete</li>
                        <li>If you change your mind, contact us within 7 days</li>
                    </ol>
                </div>

                <div>
                    <a href="home2.php" class="btn btn-primary">
                        <i class="fas fa-home"></i>
                        Return to Homepage
                    </a>
                    <a href="mailto:privacy@somdultable.com" class="btn btn-secondary">
                        <i class="fas fa-envelope"></i>
                        Contact Privacy Team
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="error">
                <div class="status-icon">❌</div>
                <h1>Request Error</h1>
                
                <p class="message">
                    There were issues with your deletion request. Please review the errors below and try again.
                </p>

                <div class="error-list">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div>
                    <a href="data-deletion.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i>
                        Try Again
                    </a>
                    <a href="mailto:privacy@somdultable.com" class="btn btn-secondary">
                        <i class="fas fa-envelope"></i>
                        Contact Support
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--cream); font-size: 0.9rem; color: var(--text-gray);">
            <p>For immediate assistance, contact our Privacy Team:</p>
            <p><strong>Email:</strong> privacy@somdultable.com | <strong>Phone:</strong> +1 (555) PRIVACY</p>
        </div>
    </div>
</body>
</html>