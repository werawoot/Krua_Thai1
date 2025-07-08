<?php
/**
 * Krua Thai - User Registration Page
 * File: register.php
 * Description: Complete registration form with validation and email verification
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'classes/User.php';

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success_message = '';
$form_data = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize form data
    $form_data = [
        'first_name' => sanitizeInput($_POST['first_name'] ?? ''),
        'last_name' => sanitizeInput($_POST['last_name'] ?? ''),
        'email' => sanitizeInput($_POST['email'] ?? ''),
        'phone' => sanitizeInput($_POST['phone'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'date_of_birth' => sanitizeInput($_POST['date_of_birth'] ?? ''),
        'gender' => sanitizeInput($_POST['gender'] ?? ''),
        'delivery_address' => sanitizeInput($_POST['delivery_address'] ?? ''),
        'address_line_2' => sanitizeInput($_POST['address_line_2'] ?? ''),
        'city' => sanitizeInput($_POST['city'] ?? ''),
        'state' => sanitizeInput($_POST['state'] ?? ''),
        'zip_code' => sanitizeInput($_POST['zip_code'] ?? ''),
        'spice_level' => sanitizeInput($_POST['spice_level'] ?? 'medium'),
        'dietary_preferences' => $_POST['dietary_preferences'] ?? [],
        'allergies' => sanitizeInput($_POST['allergies'] ?? ''),
        'delivery_instructions' => sanitizeInput($_POST['delivery_instructions'] ?? ''),
        'terms_accepted' => isset($_POST['terms_accepted'])
    ];

    // Validation
    if (empty($form_data['first_name'])) {
        $errors[] = "First name is required";
    } elseif (strlen($form_data['first_name']) < 2) {
        $errors[] = "First name must be at least 2 characters";
    }

    if (empty($form_data['last_name'])) {
        $errors[] = "Last name is required";
    } elseif (strlen($form_data['last_name']) < 2) {
        $errors[] = "Last name must be at least 2 characters";
    }

    if (empty($form_data['email'])) {
        $errors[] = "Email address is required";
    } elseif (!validateEmail($form_data['email'])) {
        $errors[] = "Please enter a valid email address";
    } elseif (!isEmailDomainAllowed($form_data['email'])) {
        $errors[] = "Email domain is not allowed. Please use a different email provider";
    }

    if (empty($form_data['phone'])) {
        $errors[] = "Phone number is required";
    } elseif (!validatePhone($form_data['phone'])) {
        $errors[] = "Please enter a valid Thai phone number (e.g., 0812345678)";
    }

    if (empty($form_data['password'])) {
        $errors[] = "Password is required";
    } elseif (!validatePassword($form_data['password'])) {
        $errors[] = "Password must be at least 8 characters with uppercase, lowercase, and number";
    }

    if ($form_data['password'] !== $form_data['confirm_password']) {
        $errors[] = "Passwords do not match";
    }

    if (empty($form_data['delivery_address'])) {
        $errors[] = "Delivery address is required";
    } elseif (strlen($form_data['delivery_address']) < 10) {
        $errors[] = "Please enter a complete delivery address";
    }

    if (empty($form_data['zip_code'])) {
        $errors[] = "ZIP code is required";
    } elseif (!validateZipCode($form_data['zip_code'])) {
        $errors[] = "Please enter a valid 5-digit ZIP code";
    }

    if (!$form_data['terms_accepted']) {
        $errors[] = "You must accept the Terms and Conditions to continue";
    }

    // Check for existing email and phone
    if (empty($errors)) {
        $user = new User($db);
        
        $user->email = $form_data['email'];
        if ($user->emailExists()) {
            $errors[] = "An account with this email address already exists";
        }

        $user->phone = cleanPhoneNumber($form_data['phone']);
        if ($user->phoneExists()) {
            $errors[] = "An account with this phone number already exists";
        }
    }

    // Create user if no errors
    if (empty($errors)) {
        $user = new User($db);
        
        // Set user properties
        $user->first_name = $form_data['first_name'];
        $user->last_name = $form_data['last_name'];
        $user->email = $form_data['email'];
        $user->phone = cleanPhoneNumber($form_data['phone']);
        $user->password_hash = $form_data['password']; // Will be hashed in create() method
        $user->date_of_birth = !empty($form_data['date_of_birth']) ? $form_data['date_of_birth'] : null;
        $user->gender = !empty($form_data['gender']) ? $form_data['gender'] : null;
        $user->delivery_address = $form_data['delivery_address'];
        $user->address_line_2 = $form_data['address_line_2'];
        $user->city = $form_data['city'];
        $user->state = $form_data['state'];
        $user->zip_code = $form_data['zip_code'];
        $user->spice_level = $form_data['spice_level'];
        $user->delivery_instructions = $form_data['delivery_instructions'];
        
        // Handle dietary preferences
        if (!empty($form_data['dietary_preferences']) && is_array($form_data['dietary_preferences'])) {
            $user->dietary_preferences = json_encode($form_data['dietary_preferences']);
        } else {
            $user->dietary_preferences = null;
        }
        
        // Handle allergies
        if (!empty($form_data['allergies'])) {
            $user->allergies = json_encode(array_map('trim', explode(',', $form_data['allergies'])));
        } else {
            $user->allergies = null;
        }

        // Create user account
        if ($user->create()) {
            // Send verification email
            $email_sent = sendVerificationEmail(
                $user->email, 
                $user->first_name, 
                $user->email_verification_token
            );
            
            if ($email_sent) {
                $success_message = "Registration successful! Please check your email (" . $user->email . ") for a verification link to activate your account.";
            } else {
                $success_message = "Registration successful! However, we couldn't send the verification email. Please contact our support team at support@kruathai.com";
            }
            
            // Clear form data on success
            $form_data = [];
            
        } else {
            $errors[] = "Registration failed due to a technical error. Please try again or contact support";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Krua Thai - Healthy Thai Meals Delivered</title>
    <meta name="description" content="Join Krua Thai for authentic, healthy Thai meals delivered to your door. Fresh ingredients, traditional recipes, modern nutrition.">
    <meta name="keywords" content="Thai food delivery, healthy meals, authentic Thai cuisine, meal subscription">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --olive: #3d4028;
            --matcha: #4e4f22;
            --brown: #866028;
            --cream: #d1b990;
            --light-cream: #f5ede4;
            --white: #ffffff;
            --gray: #6c757d;
            --light-gray: #f8f9fa;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --shadow: rgba(61, 64, 40, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", sans-serif;
            line-height: 1.6;
            color: var(--olive);
            background: linear-gradient(135deg, var(--light-cream) 0%, #f9f5ed 100%);
            min-height: 100vh;
            font-size: 16px;
        }

        .container {
            max-width: 480px;
            margin: 0 auto;
            padding: 0 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .header {
            padding: 1.5rem 0 1rem;
            text-align: center;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            color: var(--olive);
            text-decoration: none;
            margin-bottom: 1rem;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--white), var(--cream));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px var(--shadow);
            border: 2px solid rgba(255, 255, 255, 0.8);
            position: relative;
        }

        .rice-grain {
            width: 10px;
            height: 18px;
            background: linear-gradient(180deg, var(--olive), var(--matcha));
            border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
            transform: rotate(-3deg);
            box-shadow: inset 1px 0 2px rgba(255, 255, 255, 0.3);
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .welcome-text {
            color: var(--gray);
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .tagline {
            color: var(--olive);
            font-size: 1.1rem;
            font-weight: 600;
        }

        /* Form Container */
        .form-container {
            background: var(--white);
            border-radius: 20px;
            padding: 2rem;
            margin: 1rem 0;
            box-shadow: 0 8px 30px var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.8);
            flex: 1;
        }

        .form-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--olive);
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .form-subtitle {
            color: var(--gray);
            text-align: center;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            border: 2px solid;
        }

        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border-color: var(--success);
            color: var(--success);
        }

        .alert-error {
            background-color: rgba(220, 53, 69, 0.1);
            border-color: var(--danger);
            color: var(--danger);
        }

        .alert ul {
            margin: 0;
            padding-left: 1.2rem;
        }

        .alert ul li {
            margin-bottom: 0.3rem;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--olive);
            font-size: 0.95rem;
        }

        .required {
            color: var(--danger);
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 1rem 1.2rem;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
            color: var(--olive);
            -webkit-appearance: none;
            appearance: none;
            font-family: inherit;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--brown);
            box-shadow: 0 0 0 3px rgba(134, 96, 40, 0.1);
            transform: translateY(-1px);
        }

        input.error {
            border-color: var(--danger);
            background-color: #fff5f5;
        }

        input.success {
            border-color: var(--success);
            background-color: #f0fff4;
        }

        /* Custom Select */
        select {
            background-image: url("data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIiIGhlaWdodD0iOCIgdmlld0JveD0iMCAwIDEyIDgiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGQ9Ik0xIDFMNiA2TDExIDEiIHN0cm9rZT0iIzZjNzU3ZCIgc3Ryb2tlLXdpZHRoPSIyIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiLz4KPC9zdmc+");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 12px;
            padding-right: 3rem;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        /* Password Strength */
        .password-strength {
            margin-top: 0.5rem;
            display: none;
        }

        .strength-bars {
            display: flex;
            gap: 3px;
            margin-bottom: 0.5rem;
        }

        .strength-bar {
            height: 4px;
            flex: 1;
            background: #e9ecef;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .strength-bar.active {
            background: var(--success);
        }

        .strength-bar.weak {
            background: var(--danger);
        }

        .strength-bar.medium {
            background: var(--warning);
        }

        .strength-text {
            font-size: 0.85rem;
            color: var(--gray);
        }

        /* Checkbox Groups */
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin-top: 0.5rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1rem;
            background: var(--light-cream);
            border-radius: 25px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .checkbox-item:hover {
            border-color: var(--cream);
            transform: translateY(-1px);
        }

        .checkbox-item input[type="checkbox"] {
            width: auto;
            margin: 0;
            transform: scale(1.1);
        }

        .checkbox-item input[type="checkbox"]:checked {
            accent-color: var(--brown);
        }

        .checkbox-item.checked {
            background: var(--brown);
            color: var(--white);
            border-color: var(--brown);
        }

        /* Terms Checkbox */
        .terms-checkbox {
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
            margin: 1.5rem 0;
            padding: 1rem;
            background: rgba(61, 64, 40, 0.05);
            border-radius: 12px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .terms-checkbox:hover {
            border-color: rgba(61, 64, 40, 0.1);
        }

        .terms-checkbox input[type="checkbox"] {
            width: auto;
            margin-top: 0.2rem;
            transform: scale(1.2);
            accent-color: var(--brown);
        }

        .terms-text {
            font-size: 0.9rem;
            color: var(--gray);
            line-height: 1.5;
        }

        .terms-text a {
            color: var(--brown);
            text-decoration: none;
            font-weight: 600;
            border-bottom: 1px solid transparent;
            transition: border-bottom-color 0.3s;
        }

        .terms-text a:hover {
            border-bottom-color: var(--brown);
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(45deg, var(--brown), #a67c00);
            color: var(--white);
            padding: 1.2rem 2rem;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 700;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(134, 96, 40, 0.3);
            margin-top: 1rem;
        }

        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(45deg, #a67c00, var(--brown));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(134, 96, 40, 0.4);
        }

        .btn-primary:disabled {
            background: var(--gray);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
            opacity: 0.7;
        }

        /* Login Link */
        .login-link {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e9ecef;
            color: var(--gray);
        }

        .login-link a {
            color: var(--brown);
            text-decoration: none;
            font-weight: 600;
            border-bottom: 1px solid transparent;
            transition: border-bottom-color 0.3s;
        }

        .login-link a:hover {
            border-bottom-color: var(--brown);
        }

        /* Loading State */
        .loading {
            position: relative;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid transparent;
            border-top: 2px solid var(--white);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Mobile Responsive */
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 0 15px;
            }
            
            .form-container {
                padding: 1.5rem;
                margin: 0.5rem 0;
            }
            
            .checkbox-group {
                gap: 0.5rem;
            }
            
            .checkbox-item {
                font-size: 0.85rem;
                padding: 0.5rem 0.8rem;
            }
        }

        /* Focus indicators for accessibility */
        input:focus-visible,
        select:focus-visible,
        textarea:focus-visible,
        button:focus-visible {
            outline: 2px solid var(--brown);
            outline-offset: 2px;
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            :root {
                --shadow: rgba(0, 0, 0, 0.3);
            }
            
            .form-container {
                border: 2px solid var(--olive);
            }
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <div class="rice-grain"></div>
                </div>
                <span class="logo-text">Krua Thai</span>
            </a>
            <p class="welcome-text">Welcome to healthy Thai cuisine</p>
            <h1 class="tagline">Authentic Meals, Made Healthy</h1>
        </div>

        <div class="form-container">
            <h2 class="form-title">Join Krua Thai</h2>
            <p class="form-subtitle">Start your healthy Thai meal journey today</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" role="alert" aria-live="polite">
                    <strong>Please correct the following errors:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success" role="alert" aria-live="polite">
                    <strong>✅ Success!</strong><br>
                    <?php echo htmlspecialchars($success_message); ?>
                    <br><br>
                    <strong>Next steps:</strong>
                    <ol style="margin: 10px 0 0 20px;">
                        <li>Check your email inbox (and spam folder)</li>
                        <li>Click the verification link</li>
                        <li>Start ordering healthy Thai meals!</li>
                    </ol>
                    <p style="margin-top: 15px;">
                        <a href="login.php" style="color: var(--brown); font-weight: 600;">Already verified? Sign in here →</a>
                    </p>
                </div>
            <?php else: ?>

            <form method="POST" action="" id="registrationForm" novalidate>
                <!-- Personal Information -->
                <fieldset style="border: none; padding: 0; margin: 0;">
                    <legend style="font-size: 1.1rem; font-weight: 600; color: var(--olive); margin-bottom: 1rem;">Personal Information</legend>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name <span class="required">*</span></label>
                            <input type="text" 
                                   id="first_name" 
                                   name="first_name" 
                                   required 
                                   autocomplete="given-name"
                                   value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>"
                                   aria-describedby="first_name_help">
                            <small id="first_name_help" style="color: var(--gray); font-size: 0.85rem;">Your first name as you'd like it to appear</small>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name <span class="required">*</span></label>
                            <input type="text" 
                                   id="last_name" 
                                   name="last_name" 
                                   required 
                                   autocomplete="family-name"
                                   value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               required 
                               autocomplete="email"
                               value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                               aria-describedby="email_help">
                        <small id="email_help" style="color: var(--gray); font-size: 0.85rem;">We'll send order updates and verification to this email</small>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number <span class="required">*</span></label>
                        <input type="tel" 
                               id="phone" 
                               name="phone" 
                               placeholder="0812345678" 
                               required 
                               autocomplete="tel"
                               value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>"
                               aria-describedby="phone_help">
                        <small id="phone_help" style="color: var(--gray); font-size: 0.85rem;">Thai phone number for delivery coordination</small>
                    </div>
                </fieldset>

                <!-- Password Section -->
                <fieldset style="border: none; padding: 0; margin: 2rem 0 0 0;">
                    <legend style="font-size: 1.1rem; font-weight: 600; color: var(--olive); margin-bottom: 1rem;">Account Security</legend>
                    
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               required 
                               autocomplete="new-password"
                               aria-describedby="password_help">
                        <div class="password-strength" id="passwordStrength">
                            <div class="strength-bars">
                                <div class="strength-bar"></div>
                                <div class="strength-bar"></div>
                                <div class="strength-bar"></div>
                                <div class="strength-bar"></div>
                            </div>
                            <div class="strength-text" id="strengthText">Enter a password</div>
                        </div>
                        <small id="password_help" style="color: var(--gray); font-size: 0.85rem;">At least 8 characters with uppercase, lowercase, and number</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               required 
                               autocomplete="new-password"
                               aria-describedby="confirm_password_help">
                        <small id="confirm_password_help" style="color: var(--gray); font-size: 0.85rem;">Repeat your password to confirm</small>
                    </div>
                </fieldset>

                <!-- Optional Information -->
                <fieldset style="border: none; padding: 0; margin: 2rem 0 0 0;">
                    <legend style="font-size: 1.1rem; font-weight: 600; color: var(--olive); margin-bottom: 1rem;">Additional Information <span style="font-weight: normal; color: var(--gray);">(Optional)</span></legend>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" 
                                   id="date_of_birth" 
                                   name="date_of_birth" 
                                   autocomplete="bday"
                                   value="<?php echo htmlspecialchars($form_data['date_of_birth'] ?? ''); ?>"
                                   max="<?php echo date('Y-m-d', strtotime('-13 years')); ?>">
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender" autocomplete="sex">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo (($form_data['gender'] ?? '') == 'male') ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo (($form_data['gender'] ?? '') == 'female') ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo (($form_data['gender'] ?? '') == 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <!-- Delivery Information -->
                <fieldset style="border: none; padding: 0; margin: 2rem 0 0 0;">
                    <legend style="font-size: 1.1rem; font-weight: 600; color: var(--olive); margin-bottom: 1rem;">Delivery Information</legend>
                    
                    <div class="form-group">
                        <label for="delivery_address">Delivery Address <span class="required">*</span></label>
                        <textarea id="delivery_address" 
                                  name="delivery_address" 
                                  rows="3" 
                                  required 
                                  autocomplete="street-address"
                                  placeholder="Enter your complete delivery address including building name, room number, and any landmarks"
                                  aria-describedby="address_help"><?php echo htmlspecialchars($form_data['delivery_address'] ?? ''); ?></textarea>
                        <small id="address_help" style="color: var(--gray); font-size: 0.85rem;">Please provide a detailed address for accurate delivery</small>
                    </div>

                    <div class="form-group">
                        <label for="address_line_2">Address Line 2</label>
                        <input type="text" 
                               id="address_line_2" 
                               name="address_line_2" 
                               placeholder="Apartment, suite, unit, building, floor, etc."
                               autocomplete="address-line2"
                               value="<?php echo htmlspecialchars($form_data['address_line_2'] ?? ''); ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City</label>
                            <input type="text" 
                                   id="city" 
                                   name="city" 
                                   autocomplete="address-level2"
                                   placeholder="e.g., Bangkok"
                                   value="<?php echo htmlspecialchars($form_data['city'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="state">State/Province</label>
                            <input type="text" 
                                   id="state" 
                                   name="state" 
                                   autocomplete="address-level1"
                                   placeholder="e.g., Bangkok"
                                   value="<?php echo htmlspecialchars($form_data['state'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="zip_code">ZIP Code <span class="required">*</span></label>
                        <input type="text" 
                               id="zip_code" 
                               name="zip_code" 
                               placeholder="10110" 
                               required 
                               autocomplete="postal-code"
                               maxlength="5"
                               pattern="\d{5}"
                               value="<?php echo htmlspecialchars($form_data['zip_code'] ?? ''); ?>"
                               aria-describedby="zip_help">
                        <small id="zip_help" style="color: var(--gray); font-size: 0.85rem;">5-digit Thai postal code</small>
                    </div>

                    <div class="form-group">
                        <label for="delivery_instructions">Delivery Instructions</label>
                        <textarea id="delivery_instructions" 
                                  name="delivery_instructions" 
                                  rows="2" 
                                  placeholder="Special delivery instructions, access codes, or notes for the delivery person"
                                  maxlength="500"><?php echo htmlspecialchars($form_data['delivery_instructions'] ?? ''); ?></textarea>
                    </div>
                </fieldset>

                <!-- Food Preferences -->
                <fieldset style="border: none; padding: 0; margin: 2rem 0 0 0;">
                    <legend style="font-size: 1.1rem; font-weight: 600; color: var(--olive); margin-bottom: 1rem;">Food Preferences</legend>
                    
                    <div class="form-group">
                        <label for="spice_level">Preferred Spice Level</label>
                        <select id="spice_level" name="spice_level" aria-describedby="spice_help">
                            <option value="mild" <?php echo (($form_data['spice_level'] ?? 'medium') == 'mild') ? 'selected' : ''; ?>>Mild 🌶️ - Very gentle heat</option>
                            <option value="medium" <?php echo (($form_data['spice_level'] ?? 'medium') == 'medium') ? 'selected' : ''; ?>>Medium 🌶️🌶️ - Moderate spice (recommended)</option>
                            <option value="hot" <?php echo (($form_data['spice_level'] ?? 'medium') == 'hot') ? 'selected' : ''; ?>>Hot 🌶️🌶️🌶️ - Authentic Thai heat</option>
                            <option value="extra_hot" <?php echo (($form_data['spice_level'] ?? 'medium') == 'extra_hot') ? 'selected' : ''; ?>>Extra Hot 🌶️🌶️🌶️🌶️ - For spice lovers</option>
                        </select>
                        <small id="spice_help" style="color: var(--gray); font-size: 0.85rem;">You can always adjust this later in your profile</small>
                    </div>

                    <div class="form-group">
                        <label>Dietary Preferences (Optional)</label>
                        <div class="checkbox-group" role="group" aria-labelledby="dietary_preferences_label">
                            <?php 
                            $dietary_options = [
                                'vegetarian' => 'Vegetarian',
                                'vegan' => 'Vegan',
                                'gluten_free' => 'Gluten-Free',
                                'keto' => 'Keto-Friendly',
                                'low_sodium' => 'Low Sodium',
                                'diabetic_friendly' => 'Diabetic-Friendly'
                            ];
                            $selected_preferences = $form_data['dietary_preferences'] ?? [];
                            ?>
                            <?php foreach ($dietary_options as $value => $label): ?>
                                <div class="checkbox-item">
                                    <input type="checkbox" 
                                           id="diet_<?php echo $value; ?>" 
                                           name="dietary_preferences[]" 
                                           value="<?php echo $value; ?>"
                                           <?php echo in_array($value, $selected_preferences) ? 'checked' : ''; ?>>
                                    <label for="diet_<?php echo $value; ?>"><?php echo $label; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small style="color: var(--gray); font-size: 0.85rem;">Select all that apply - we'll recommend meals that match your preferences</small>
                    </div>

                    <div class="form-group">
                        <label for="allergies">Food Allergies or Intolerances (Optional)</label>
                        <input type="text" 
                               id="allergies" 
                               name="allergies" 
                               placeholder="e.g., Peanuts, Shellfish, Dairy, Soy"
                               maxlength="200"
                               value="<?php echo htmlspecialchars($form_data['allergies'] ?? ''); ?>"
                               aria-describedby="allergies_help">
                        <small id="allergies_help" style="color: var(--gray); font-size: 0.85rem;">Separate multiple allergies with commas. This helps us recommend safe meals for you</small>
                    </div>
                </fieldset>

                <!-- Terms and Conditions -->
                <div class="terms-checkbox">
                    <input type="checkbox" 
                           id="terms_accepted" 
                           name="terms_accepted" 
                           required 
                           aria-describedby="terms_description"
                           <?php echo ($form_data['terms_accepted'] ?? false) ? 'checked' : ''; ?>>
                    <div class="terms-text" id="terms_description">
                        <strong>I agree to the following:</strong><br>
                        • <a href="terms.php" target="_blank" rel="noopener">Terms and Conditions</a> and 
                        <a href="privacy.php" target="_blank" rel="noopener">Privacy Policy</a><br>
                        • Receiving email notifications about my orders, account updates, and promotions from Krua Thai<br>
                        • Age confirmation: I am at least 13 years old<br>
                        • Accuracy: The information I provided is accurate and complete
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-primary" id="submitBtn" aria-describedby="submit_help">
                    <span id="submit_text">Create My Account</span>
                </button>
                <small id="submit_help" style="display: block; text-align: center; margin-top: 0.5rem; color: var(--gray); font-size: 0.85rem;">
                    By clicking this button, you agree to create your Krua Thai account
                </small>
            </form>

            <?php endif; ?>

            <div class="login-link">
                Already have an account? <a href="login.php">Sign In</a>
            </div>
        </div>
    </div>

    <script>
        // Password strength checker
        const passwordInput = document.getElementById('password');
        const strengthDiv = document.getElementById('passwordStrength');
        const strengthBars = document.querySelectorAll('.strength-bar');
        const strengthText = document.getElementById('strengthText');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            strengthDiv.style.display = password ? 'block' : 'none';
            
            let strength = 0;
            let feedback = [];

            // Check password criteria
            if (password.length >= 8) strength++;
            else feedback.push('at least 8 characters');

            if (/[a-z]/.test(password)) strength++;
            else feedback.push('lowercase letter');

            if (/[A-Z]/.test(password)) strength++;
            else feedback.push('uppercase letter');

            if (/\d/.test(password)) strength++;
            else feedback.push('number');

            if (/[@$!%*?&]/.test(password)) {
                strength++;
            }

            // Reset bars
            strengthBars.forEach(bar => {
                bar.classList.remove('active', 'weak', 'medium');
            });

            // Set strength visualization
            for (let i = 0; i < Math.min(strength, 4); i++) {
                strengthBars[i].classList.add('active');
                if (strength <= 2) strengthBars[i].classList.add('weak');
                else if (strength <= 3) strengthBars[i].classList.add('medium');
            }

            // Update feedback text
            if (strength >= 4) {
                strengthText.textContent = 'Strong password! ✓';
                strengthText.style.color = '#28a745';
            } else if (strength >= 2) {
                strengthText.textContent = 'Good, but could be stronger';
                strengthText.style.color = '#ffc107';
            } else if (password) {
                strengthText.textContent = `Needs: ${feedback.join(', ')}`;
                strengthText.style.color = '#dc3545';
            }
        });

        // Password confirmation validation
        const confirmPasswordInput = document.getElementById('confirm_password');

        function validatePasswordMatch() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (confirmPassword && password !== confirmPassword) {
                confirmPasswordInput.classList.add('error');
                confirmPasswordInput.classList.remove('success');
                return false;
            } else if (confirmPassword && password === confirmPassword) {
                confirmPasswordInput.classList.remove('error');
                confirmPasswordInput.classList.add('success');
                return true;
            } else {
                confirmPasswordInput.classList.remove('error', 'success');
                return confirmPassword === '';
            }
        }

        confirmPasswordInput.addEventListener('input', validatePasswordMatch);
        passwordInput.addEventListener('input', function() {
            if (confirmPasswordInput.value) {
                validatePasswordMatch();
            }
        });

        // Phone number formatting and validation
        const phoneInput = document.getElementById('phone');
        phoneInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 10) value = value.slice(0, 10);
            this.value = value;
            
            // Validate format
            if (value && !/^0[0-9]{8,9}$/.test(value)) {
                this.classList.add('error');
            } else if (value) {
                this.classList.remove('error');
                this.classList.add('success');
            } else {
                this.classList.remove('error', 'success');
            }
        });

        // ZIP code validation
        const zipInput = document.getElementById('zip_code');
        zipInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 5) value = value.slice(0, 5);
            this.value = value;
            
            if (value && value.length === 5) {
                this.classList.remove('error');
                this.classList.add('success');
            } else if (value) {
                this.classList.add('error');
                this.classList.remove('success');
            } else {
                this.classList.remove('error', 'success');
            }
        });

        // Email validation
        const emailInput = document.getElementById('email');
        emailInput.addEventListener('blur', function() {
            const email = this.value;
            if (email && !isValidEmail(email)) {
                this.classList.add('error');
                this.classList.remove('success');
            } else if (email) {
                this.classList.remove('error');
                this.classList.add('success');
            }
        });

        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        // Checkbox styling
        document.querySelectorAll('.checkbox-item input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const item = this.closest('.checkbox-item');
                if (this.checked) {
                    item.classList.add('checked');
                } else {
                    item.classList.remove('checked');
                }
            });
            
            // Initialize checked state
            if (checkbox.checked) {
                checkbox.closest('.checkbox-item').classList.add('checked');
            }
        });

        // Form submission handling
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            const termsAccepted = document.getElementById('terms_accepted').checked;
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submit_text');

            // Final validation
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match! Please check and try again.');
                confirmPasswordInput.focus();
                return;
            }

            if (!termsAccepted) {
                e.preventDefault();
                alert('Please accept the Terms and Conditions to continue.');
                document.getElementById('terms_accepted').focus();
                return;
            }

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            submitText.textContent = 'Creating Account...';
            
            // Re-enable after timeout (in case of server errors)
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
                submitText.textContent = 'Create My Account';
            }, 10000);
        });

        // Auto-focus on first empty required field
        document.addEventListener('DOMContentLoaded', function() {
            const firstEmptyRequired = document.querySelector('input[required]:not([value]), input[required][value=""]');
            if (firstEmptyRequired) {
                firstEmptyRequired.focus();
            }
        });

        // Real-time validation feedback
        const requiredFields = ['first_name', 'last_name', 'email', 'phone', 'password', 'delivery_address', 'zip_code'];
        
        requiredFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.addEventListener('blur', function() {
                    if (this.value.trim() === '') {
                        this.classList.add('error');
                    } else {
                        this.classList.remove('error');
                        if (!this.classList.contains('success')) {
                            this.classList.add('success');
                        }
                    }
                });
            }
        });

        // Accessibility improvements
        document.addEventListener('keydown', function(e) {
            // Escape key to clear focus
            if (e.key === 'Escape') {
                document.activeElement.blur();
            }
        });

        // Progress indication
        function updateFormProgress() {
            const required = document.querySelectorAll('input[required], textarea[required]');
            const filled = Array.from(required).filter(field => field.value.trim() !== '').length;
            const progress = Math.round((filled / required.length) * 100);
            
            // You could add a progress bar here if desired
            console.log(`Form completion: ${progress}%`);
        }

        // Monitor form completion
        document.querySelectorAll('input, textarea, select').forEach(field => {
            field.addEventListener('input', updateFormProgress);
        });

        // Initial progress check
        updateFormProgress();
    </script>
</body>
</html>