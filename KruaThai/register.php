<?php
/**
 * Somdul Table - User Registration Page with Facebook Integration (Fixed)
 * File: register.php
 * Description: Complete registration form with Facebook Sign-In (works without email permission)
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

// Facebook Configuration - Replace with your actual App ID and App Secret
$facebook_app_id = '631595452837020';
$facebook_app_secret = '482f136ed9104737a847a6df73a0d034';

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success_message = '';
$form_data = [];

// Handle Facebook Registration
if (isset($_POST['facebook_signup']) && $_POST['facebook_signup'] === '1') {
    $facebook_access_token = $_POST['facebook_access_token'] ?? '';
    $facebook_user_id = $_POST['facebook_user_id'] ?? '';
    
    if ($facebook_access_token && $facebook_user_id) {
        // Verify Facebook access token (without email field since it may not be available)
        $verify_url = "https://graph.facebook.com/me?access_token=" . urlencode($facebook_access_token) . "&fields=id,first_name,last_name,name";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $verify_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $facebook_data = json_decode($response, true);
            
            if ($facebook_data && isset($facebook_data['id']) && $facebook_data['id'] === $facebook_user_id) {
                $user = new User($db);
                
                // Check if user already exists by Facebook ID
                $stmt = $db->prepare("SELECT * FROM users WHERE facebook_id = :facebook_id");
                $stmt->bindParam(':facebook_id', $facebook_user_id);
                $stmt->execute();
                $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_user) {
                    // User already exists, log them in
                    $_SESSION['user_id'] = $existing_user['id'];
                    $_SESSION['user_email'] = $existing_user['email'];
                    $_SESSION['user_name'] = $existing_user['first_name'] . ' ' . $existing_user['last_name'];
                    $_SESSION['login_method'] = 'facebook';
                    
                    header('Location: dashboard.php?welcome=1');
                    exit();
                } else {
                    // Create new user account with Facebook data
                    $user->first_name = $facebook_data['first_name'] ?? '';
                    $user->last_name = $facebook_data['last_name'] ?? '';
                    
                    // Generate a temporary email using Facebook ID if no email available
                    $user->email = 'facebook_' . $facebook_user_id . '@temp.somdultable.com';
                    $user->facebook_id = $facebook_user_id;
                    $user->is_email_verified = 0; // Will be updated when they provide real email
                    $user->registration_method = 'facebook';
                    
                    // These will be required to complete during profile setup
                    $user->phone = null;
                    $user->password_hash = null;
                    $user->delivery_address = null;
                    $user->zip_code = null;
                    $user->spice_level = 'medium';
                    
                    if ($user->create()) {
                        // Log the user in
                        $_SESSION['user_id'] = $user->id;
                        $_SESSION['user_email'] = $user->email;
                        $_SESSION['user_name'] = $user->first_name . ' ' . $user->last_name;
                        $_SESSION['login_method'] = 'facebook';
                        $_SESSION['facebook_user'] = true;
                        $_SESSION['needs_email_update'] = true; // Flag to require real email
                        
                        // Redirect to complete profile page
                        header('Location: complete-profile.php?facebook=1&step=email');
                        exit();
                    } else {
                        $errors[] = "Failed to create your account. Please try again or contact support.";
                    }
                }
            } else {
                $errors[] = "Facebook verification failed. Please try again.";
            }
        } else {
            $errors[] = "Unable to verify Facebook account. Please try again.";
        }
    } else {
        $errors[] = "Facebook authentication data is missing. Please try again.";
    }
}

// Process regular form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['facebook_signup'])) {
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
        $errors[] = "Please enter a valid phone number (e.g., +1234567890)";
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
        $user->registration_method = 'email';
        
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
                $success_message = "Registration successful! However, we couldn't send the verification email. Please contact our support team at support@somdultable.com";
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
    <title>Join Somdul Table - Authentic Thai Meals Delivered</title>
    <meta name="description" content="Join Somdul Table for authentic, healthy Thai meals delivered to your door. Fresh ingredients, traditional recipes, modern nutrition.">
    <meta name="keywords" content="Thai food delivery, healthy meals, authentic Thai cuisine, meal subscription">
    
    <!-- Facebook SDK -->
    <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v18.0&appId=<?php echo $facebook_app_id; ?>&autoLogAppEvents=1"></script>
    
    <!-- BaticaSans Font Import -->
    <link rel="preconnect" href="https://ydpschool.com">
    <style>
        /* BaticaSans Font Family */
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Regular.woff2') format('woff2'),
                 url('https://ydpschool.com/fonts/BaticaSans-Regular.woff') format('woff'),
                 url('https://ydpschool.com/fonts/BaticaSans-Regular.ttf') format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Bold.woff2') format('woff2'),
                 url('https://ydpschool.com/fonts/BaticaSans-Bold.woff') format('woff'),
                 url('https://ydpschool.com/fonts/BaticaSans-Bold.ttf') format('truetype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Medium.woff2') format('woff2'),
                 url('https://ydpschool.com/fonts/BaticaSans-Medium.woff') format('woff'),
                 url('https://ydpschool.com/fonts/BaticaSans-Medium.ttf') format('truetype');
            font-weight: 500;
            font-style: normal;
            font-display: swap;
        }

        /* CSS Custom Properties - Matching Somdul Table Design System */
        :root {
            --brown: #bd9379;
            --cream: #ece8e1;
            --sage: #adb89d;
            --curry: #cf723a;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-gray: #7f8c8d;
            --border-light: #e8e8e8;
            --shadow-soft: 0 4px 12px rgba(189, 147, 121, 0.15);
            --shadow-medium: 0 8px 24px rgba(189, 147, 121, 0.25);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background: linear-gradient(135deg, var(--cream) 0%, #f8f9fa 100%);
            min-height: 100vh;
            font-size: 16px;
            font-weight: 400;
        }

        /* Typography using BaticaSans */
        h1, h2, h3, h4, h5, h6 {
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
            line-height: 1.2;
            color: var(--text-dark);
        }

        .container {
            max-width: 500px;
            margin: 0 auto;
            padding: 0 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Back to Home Link */
        .back-to-home {
            position: fixed;
            top: 2rem;
            left: 2rem;
            z-index: 1000;
        }

        .back-to-home a {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--curry);
            text-decoration: none;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            background: var(--white);
            padding: 0.8rem 1.2rem;
            border-radius: 50px;
            box-shadow: var(--shadow-soft);
            transition: var(--transition);
            border: 2px solid var(--curry);
        }

        .back-to-home a:hover {
            background: var(--curry);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        /* Registration Container */
        .register-container {
            background: var(--white);
            border-radius: 20px;
            box-shadow: var(--shadow-medium);
            border: 1px solid rgba(255, 255, 255, 0.8);
            width: 100%;
            max-width: 500px;
            margin: 2rem auto;
            overflow: hidden;
            flex: 1;
        }

        .register-header {
            background: linear-gradient(135deg, var(--curry) 0%, var(--brown) 100%);
            padding: 2.5rem 2rem 2rem;
            text-align: center;
            color: var(--white);
            position: relative;
        }

        .register-header::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 60%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="70" cy="30" r="15" fill="rgba(255,255,255,0.1)"/><circle cx="85" cy="60" r="8" fill="rgba(255,255,255,0.05)"/><circle cx="60" cy="75" r="12" fill="rgba(255,255,255,0.08)"/></svg>');
            background-size: 200px 200px;
            opacity: 0.3;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
            text-decoration: none;
            color: inherit;
            transition: var(--transition);
            cursor: pointer;
        }

        .logo:hover {
            transform: translateY(-2px);
            filter: brightness(1.1);
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--white), var(--cream));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.8);
        }

        .logo-text {
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            font-family: 'BaticaSans', sans-serif;
        }

        .register-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 400;
            position: relative;
            z-index: 1;
            font-family: 'BaticaSans', sans-serif;
        }

        /* Form Container */
        .register-form {
            padding: 2.5rem 2rem;
            max-height: 70vh;
            overflow-y: auto;
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            text-align: center;
            font-family: 'BaticaSans', sans-serif;
        }

        .form-subtitle {
            color: var(--text-gray);
            text-align: center;
            margin-bottom: 2rem;
            font-size: 0.95rem;
            font-family: 'BaticaSans', sans-serif;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            font-weight: 500;
            border: 2px solid;
            font-family: 'BaticaSans', sans-serif;
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

        fieldset {
            border: none;
            padding: 0;
            margin: 2rem 0 0 0;
        }

        fieldset:first-of-type {
            margin-top: 0;
        }

        legend {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
            font-family: 'BaticaSans', sans-serif;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.95rem;
            font-family: 'BaticaSans', sans-serif;
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
            border: 2px solid var(--border-light);
            border-radius: var(--radius-md);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--white);
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
            -webkit-appearance: none;
            appearance: none;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
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

        small {
            color: var(--text-gray);
            font-size: 0.85rem;
            font-family: 'BaticaSans', sans-serif;
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
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
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
            background: rgba(189, 147, 121, 0.05);
            border-radius: 25px;
            border: 2px solid transparent;
            transition: var(--transition);
            cursor: pointer;
            font-size: 0.9rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .checkbox-item:hover {
            background: rgba(189, 147, 121, 0.1);
            transform: translateY(-1px);
        }

        .checkbox-item input[type="checkbox"] {
            width: auto;
            margin: 0;
            transform: scale(1.1);
            accent-color: var(--curry);
        }

        .checkbox-item.checked {
            background: var(--curry);
            color: var(--white);
            border-color: var(--curry);
        }

        /* Terms Checkbox */
        .terms-checkbox {
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
            margin: 1.5rem 0;
            padding: 1rem;
            background: rgba(207, 114, 58, 0.05);
            border-radius: var(--radius-md);
            border: 2px solid transparent;
            transition: var(--transition);
        }

        .terms-checkbox:hover {
            background: rgba(207, 114, 58, 0.08);
        }

        .terms-checkbox input[type="checkbox"] {
            width: auto;
            margin-top: 0.2rem;
            transform: scale(1.2);
            accent-color: var(--curry);
        }

        .terms-text {
            font-size: 0.9rem;
            color: var(--text-gray);
            line-height: 1.5;
            font-family: 'BaticaSans', sans-serif;
        }

        .terms-text a {
            color: var(--curry);
            text-decoration: none;
            font-weight: 600;
            border-bottom: 1px solid transparent;
            transition: border-bottom-color 0.3s;
        }

        .terms-text a:hover {
            border-bottom-color: var(--curry);
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            padding: 1.2rem 2rem;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 700;
            font-size: 1.1rem;
            width: 100%;
            transition: var(--transition);
            box-shadow: var(--shadow-soft);
            margin-top: 1rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--brown), var(--curry));
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-primary:disabled {
            background: var(--text-gray);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
            opacity: 0.7;
        }

        .btn-secondary {
            background: transparent;
            color: var(--curry);
            padding: 0.8rem 1.5rem;
            border: 2px solid var(--curry);
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: var(--transition);
            font-size: 0.9rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .btn-secondary:hover {
            background: var(--curry);
            color: var(--white);
            transform: translateY(-1px);
            box-shadow: var(--shadow-soft);
        }

        /* Login Link */
        .login-link {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-light);
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        .login-link a {
            color: var(--curry);
            text-decoration: none;
            font-weight: 600;
            border-bottom: 1px solid transparent;
            transition: border-bottom-color 0.3s;
        }

        .login-link a:hover {
            border-bottom-color: var(--curry);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 2rem 0;
            color: var(--text-gray);
            font-size: 0.9rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-light);
        }

        .divider span {
            padding: 0 1rem;
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

        /* Social Login Styles */
        .social-login-section {
            margin: 2rem 0 1.5rem;
        }
        
        .social-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.9rem;
        }
        
        .social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            width: 100%;
            padding: 1rem 1.5rem;
            border: 2px solid;
            border-radius: 12px;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            position: relative;
            overflow: hidden;
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        .social-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .social-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .social-icon {
            width: 22px;
            height: 22px;
            flex-shrink: 0;
        }
        
        .facebook-btn {
            background: linear-gradient(135deg, #1877f2 0%, #4267B2 100%);
            border-color: #1877f2;
            color: white;
        }
        
        /* Google Button - Pure Clean Style */
        .google-btn {
            background: white;
            border-color: #dadce0;
            color: #3c4043;
            box-shadow: 0 1px 2px 0 rgba(60,64,67,.30), 0 1px 3px 1px rgba(60,64,67,.15);
            font-weight: 500;
        }

        .google-btn:hover {
            background: #f9f9f9;
            border-color: #dadce0;
            color: #3c4043;
            box-shadow: 0 1px 2px 0 rgba(60,64,67,.30), 0 2px 6px 2px rgba(60,64,67,.15);
            transform: translateY(-1px);
        }

        .google-btn .social-icon {
            background: none;
            border-radius: 0;
            padding: 0;
            color: transparent;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%234285F4' d='M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z'/%3E%3Cpath fill='%2334A853' d='M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z'/%3E%3Cpath fill='%23FBBC04' d='M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z'/%3E%3Cpath fill='%23EA4335' d='M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z'/%3E%3C/svg%3E");
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }

        .apple-btn {
            background: linear-gradient(135deg, #000000 0%, #333333 100%);
            border-color: #000000;
            color: white;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .back-to-home {
                top: 1rem;
                left: 1rem;
            }
            
            .back-to-home a {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 0 15px;
            }
            
            .register-container {
                margin: 1rem auto;
            }
            
            .register-form {
                padding: 2rem 1.5rem;
            }
            
            .register-header {
                padding: 2rem 1.5rem 1.5rem;
            }
            
            .logo-text {
                font-size: 1.6rem;
            }
            
            .form-title {
                font-size: 1.3rem;
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
            outline: 2px solid var(--curry);
            outline-offset: 2px;
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .register-container {
                border: 2px solid var(--text-dark);
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

        /* Facebook notice */
        .facebook-notice {
            background: rgba(24, 119, 242, 0.1);
            border: 1px solid rgba(24, 119, 242, 0.3);
            border-radius: var(--radius-md);
            padding: 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #1877f2;
            font-family: 'BaticaSans', sans-serif;
        }
    </style>
</head>
<body>
    <!-- Back to Home Link -->
    <div class="back-to-home">
        <a href="home2.php">
            <span>←</span>
            <span>Back to Home</span>
        </a>
    </div>

    <div class="container">
        <div class="register-container">
            <!-- Header -->
            <div class="register-header">
                <a href="home2.php" class="logo">
                    <img src="./assets/image/LOGO_BG.png" alt="Somdul Table" style="height: 50px; width: auto; border-radius: 50%;">
                    <span class="logo-text">Somdul Table</span>
                </a>
                <p class="register-subtitle">Join the authentic Thai cuisine experience</p>
            </div>

            <!-- Form -->
            <div class="register-form">
                <h1 class="form-title">Join Somdul Table</h1>
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
                            <a href="login.php" style="color: var(--curry); font-weight: 600;">Already verified? Sign in here →</a>
                        </p>
                    </div>
                <?php else: ?>

                <!-- Social Login Section -->
                <div class="social-login-section">
                    <div class="facebook-notice">
                        <strong>Quick Sign-up:</strong> Use Facebook to create your account instantly! You'll complete your delivery details in the next step.
                    </div>
                    
                    <div class="social-buttons">
                        <button type="button" class="social-btn facebook-btn" id="facebookSignupBtn" onclick="signupWithFacebook()">
                            <svg class="social-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                            <span>Continue with Facebook</span>
                        </button>

                        <button type="button" class="social-btn google-btn" onclick="signupWithGoogle()">
                            <svg class="social-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                            </svg>
                            <span>Continue with Google</span>
                        </button>

                        <button type="button" class="social-btn apple-btn" onclick="signupWithApple()">
                            <svg class="social-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12.152 6.896c-.948 0-2.415-1.078-3.96-1.04-2.04.027-3.91 1.183-4.961 3.014-2.117 3.675-.546 9.103 1.519 12.09 1.013 1.454 2.208 3.09 3.792 3.039 1.52-.065 2.09-.987 3.935-.987 1.831 0 2.35.987 3.96.948 1.637-.026 2.676-1.48 3.676-2.948 1.156-1.688 1.636-3.325 1.662-3.415-.039-.013-3.182-1.221-3.22-4.857-.026-3.04 2.48-4.494 2.597-4.559-1.429-2.09-3.623-2.324-4.39-2.376-2-.156-3.675 1.09-4.61 1.09zM15.53 3.83c.843-1.012 1.4-2.427 1.245-3.83-1.207.052-2.662.805-3.532 1.818-.78.896-1.454 2.338-1.273 3.714 1.338.104 2.715-.688 3.559-1.701"/>
                            </svg>
                            <span>Sign up with Apple</span>
                        </button>
                    </div>
                </div>

                <div class="divider">
                    <span>Or register with email</span>
                </div>

                <form method="POST" action="" id="registrationForm" novalidate>
                    <!-- Personal Information -->
                    <fieldset>
                        <legend>Personal Information</legend>
                        
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
                                <small id="first_name_help">Your first name as you'd like it to appear</small>
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
                            <small id="email_help">We'll send order updates and verification to this email</small>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number <span class="required">*</span></label>
                            <input type="tel" 
                                   id="phone" 
                                   name="phone" 
                                   placeholder="+1234567890" 
                                   required 
                                   autocomplete="tel"
                                   value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>"
                                   aria-describedby="phone_help">
                            <small id="phone_help">Phone number for delivery coordination</small>
                        </div>
                    </fieldset>

                    <!-- Password Section -->
                    <fieldset>
                        <legend>Account Security</legend>
                        
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
                            <small id="password_help">At least 8 characters with uppercase, lowercase, and number</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   required 
                                   autocomplete="new-password"
                                   aria-describedby="confirm_password_help">
                            <small id="confirm_password_help">Repeat your password to confirm</small>
                        </div>
                    </fieldset>

                    <!-- Optional Information -->
                    <fieldset>
                        <legend>Additional Information <span style="font-weight: normal; color: var(--text-gray);">(Optional)</span></legend>
                        
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
                    <fieldset>
                        <legend>Delivery Information</legend>
                        
                        <div class="form-group">
                            <label for="delivery_address">Delivery Address <span class="required">*</span></label>
                            <textarea id="delivery_address" 
                                      name="delivery_address" 
                                      rows="3" 
                                      required 
                                      autocomplete="street-address"
                                      placeholder="Enter your complete delivery address including building name, room number, and any landmarks"
                                      aria-describedby="address_help"><?php echo htmlspecialchars($form_data['delivery_address'] ?? ''); ?></textarea>
                            <small id="address_help">Please provide a detailed address for accurate delivery</small>
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
                                       placeholder="e.g., New York"
                                       value="<?php echo htmlspecialchars($form_data['city'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="state">State/Province</label>
                                <input type="text" 
                                       id="state" 
                                       name="state" 
                                       autocomplete="address-level1"
                                       placeholder="e.g., NY"
                                       value="<?php echo htmlspecialchars($form_data['state'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="zip_code">ZIP Code <span class="required">*</span></label>
                            <input type="text" 
                                   id="zip_code" 
                                   name="zip_code" 
                                   placeholder="12345" 
                                   required 
                                   autocomplete="postal-code"
                                   maxlength="5"
                                   pattern="\d{5}"
                                   value="<?php echo htmlspecialchars($form_data['zip_code'] ?? ''); ?>"
                                   aria-describedby="zip_help">
                            <small id="zip_help">5-digit US postal code</small>
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
                    <fieldset>
                        <legend>Food Preferences</legend>
                        
                        <div class="form-group">
                            <label for="spice_level">Preferred Spice Level</label>
                            <select id="spice_level" name="spice_level" aria-describedby="spice_help">
                                <option value="mild" <?php echo (($form_data['spice_level'] ?? 'medium') == 'mild') ? 'selected' : ''; ?>>Mild 🌶️ - Very gentle heat</option>
                                <option value="medium" <?php echo (($form_data['spice_level'] ?? 'medium') == 'medium') ? 'selected' : ''; ?>>Medium 🌶️🌶️ - Moderate spice (recommended)</option>
                                <option value="hot" <?php echo (($form_data['spice_level'] ?? 'medium') == 'hot') ? 'selected' : ''; ?>>Hot 🌶️🌶️🌶️ - Authentic Thai heat</option>
                                <option value="extra_hot" <?php echo (($form_data['spice_level'] ?? 'medium') == 'extra_hot') ? 'selected' : ''; ?>>Extra Hot 🌶️🌶️🌶️🌶️ - For spice lovers</option>
                            </select>
                            <small id="spice_help">You can always adjust this later in your profile</small>
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
                            <small>Select all that apply - we'll recommend meals that match your preferences</small>
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
                            <small id="allergies_help">Separate multiple allergies with commas. This helps us recommend safe meals for you</small>
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
                            • Receiving email notifications about my orders, account updates, and promotions from Somdul Table<br>
                            • Age confirmation: I am at least 13 years old<br>
                            • Accuracy: The information I provided is accurate and complete
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-primary" id="submitBtn" aria-describedby="submit_help">
                        <span id="submit_text">Create My Account</span>
                    </button>
                    <small id="submit_help" style="display: block; text-align: center; margin-top: 0.5rem; color: var(--text-gray);">
                        By clicking this button, you agree to create your Somdul Table account
                    </small>
                </form>

                <?php endif; ?>

                <div class="divider">
                    <span>Already have an account?</span>
                </div>

                <div style="text-align: center;">
                    <a href="login.php" class="btn-secondary">Sign In</a>
                </div>

                <div class="login-link">
                    <p>
                        <a href="home2.php">← Back to Home</a> | 
                        <a href="help.php">Need Help?</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden form for Facebook signup -->
    <form id="facebookSignupForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="facebook_signup" value="1">
        <input type="hidden" name="facebook_access_token" id="facebook_access_token">
        <input type="hidden" name="facebook_user_id" id="facebook_user_id">
    </form>

    <script>
        // Initialize Facebook SDK
        window.fbAsyncInit = function() {
            FB.init({
                appId: '<?php echo $facebook_app_id; ?>',
                cookie: true,
                xfbml: true,
                version: 'v18.0'
            });
            
            // Enable Facebook signup button
            document.getElementById('facebookSignupBtn').disabled = false;
        };

        // Facebook signup function - Updated to work without email permission
        function signupWithFacebook() {
            const facebookBtn = document.getElementById('facebookSignupBtn');
            
            // Show loading state
            facebookBtn.disabled = true;
            facebookBtn.innerHTML = '<svg class="social-icon" style="animation: spin 1s linear infinite;" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.25"/><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg><span>Connecting to Facebook...</span>';
            
            // Only request public_profile (no email permission needed)
            FB.login(function(response) {
                if (response.authResponse) {
                    // User granted permissions
                    const accessToken = response.authResponse.accessToken;
                    const userID = response.authResponse.userID;
                    
                    // Get user info (no email field)
                    FB.api('/me', {fields: 'id,first_name,last_name,name'}, function(userInfo) {
                        console.log('Facebook user info:', userInfo);
                        
                        if (userInfo.id) {
                            // Fill hidden form and submit
                            document.getElementById('facebook_access_token').value = accessToken;
                            document.getElementById('facebook_user_id').value = userID;
                            document.getElementById('facebookSignupForm').submit();
                        } else {
                            alert('Unable to get your Facebook information. Please try again.');
                            resetFacebookButton();
                        }
                    });
                } else {
                    // User cancelled login or didn't grant permissions
                    console.log('Facebook login cancelled');
                    resetFacebookButton();
                }
            }, {scope: 'public_profile'}); // Only request public profile
        }

        function resetFacebookButton() {
            const facebookBtn = document.getElementById('facebookSignupBtn');
            facebookBtn.disabled = false;
            facebookBtn.innerHTML = '<svg class="social-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg><span>Continue with Facebook</span>';
        }

        // Google and Apple signup functions (placeholders)
        function signupWithGoogle() {
            alert('Google signup will be implemented soon! For now, please use the email registration form below.');
        }
        
        function signupWithApple() {
            alert('Apple signup will be implemented soon! For now, please use the email registration form below.');
        }

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
            let value = this.value.replace(/[^\d+\-\(\)\s]/g, '');
            this.value = value;
            
            // Basic phone validation
            if (value && value.length < 10) {
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
            // Disable Facebook button until SDK loads
            document.getElementById('facebookSignupBtn').disabled = true;
            
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

        // Real-time connection status
        function updateConnectionStatus() {
            const isOnline = navigator.onLine;
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submit_text');
            
            if (!isOnline) {
                submitBtn.disabled = true;
                submitText.textContent = 'Offline - Check Connection';
            } else if (submitText.textContent === 'Offline - Check Connection') {
                submitBtn.disabled = false;
                submitText.textContent = 'Create My Account';
            }
        }

        window.addEventListener('online', updateConnectionStatus);
        window.addEventListener('offline', updateConnectionStatus);

        // Initial connection check
        updateConnectionStatus();

        // Prevent form double submission
        let formSubmitted = false;
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            if (formSubmitted) {
                e.preventDefault();
                return false;
            }
            formSubmitted = true;
        });
    </script>
</body>
</html>