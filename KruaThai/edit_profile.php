<?php
/**
 * Somdul Table - Edit Profile Page
 * File: edit_profile.php
 * Status: PRODUCTION READY ✅
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php?redirect=edit_profile.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$errors = [];
$success_message = "";

// 🔥 AJAX Handler at the top
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $response = ['success' => false, 'errors' => []];

    // Get current user password hash for verification
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $current_password_hash = $user_data['password_hash'] ?? '';
    } catch (Exception $e) {
        $response['errors'][] = "Database connection error";
        echo json_encode($response);
        exit();
    }

    if ($action === 'update_profile') {
        // Sanitize all inputs
        $first_name = sanitizeInput($_POST['first_name'] ?? '');
        $last_name = sanitizeInput($_POST['last_name'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $date_of_birth = sanitizeInput($_POST['date_of_birth'] ?? '');
        $gender = sanitizeInput($_POST['gender'] ?? '');
        $delivery_address = sanitizeInput($_POST['delivery_address'] ?? '');
        $city = sanitizeInput($_POST['city'] ?? '');
        $zip_code = sanitizeInput($_POST['zip_code'] ?? '');
        $delivery_instructions = sanitizeInput($_POST['delivery_instructions'] ?? '');
        $dietary_preferences = isset($_POST['dietary_preferences']) ? json_encode($_POST['dietary_preferences']) : '[]';
        $allergies = isset($_POST['allergies']) ? json_encode($_POST['allergies']) : '[]';
        $spice_level = sanitizeInput($_POST['spice_level'] ?? 'medium');

        // Validation
        if (empty($first_name)) $response['errors'][] = "Please enter your first name";
        if (empty($last_name)) $response['errors'][] = "Please enter your last name";

        if (empty($response['errors'])) {
            try {
                $sql = "UPDATE users SET 
                            first_name = ?, last_name = ?, phone = ?, date_of_birth = ?, gender = ?,
                            delivery_address = ?, city = ?, zip_code = ?, delivery_instructions = ?,
                            dietary_preferences = ?, allergies = ?, spice_level = ?, updated_at = NOW() 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $first_name, $last_name, $phone, $date_of_birth, $gender, 
                    $delivery_address, $city, $zip_code, $delivery_instructions, 
                    $dietary_preferences, $allergies, $spice_level, 
                    $user_id
                ]);

                $response['success'] = true;
                $response['message'] = "Profile updated successfully";
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
            } catch (Exception $e) {
                $response['errors'][] = "Error updating profile: " . $e->getMessage();
            }
        }
    } 
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($current_password)) $response['errors'][] = "Please enter your current password";
        if (empty($new_password)) $response['errors'][] = "Please enter a new password";
        if (strlen($new_password) < 8) $response['errors'][] = "New password must be at least 8 characters";
        if ($new_password !== $confirm_password) $response['errors'][] = "New password and confirmation do not match";
        
        if (empty($response['errors'])) {
            if (password_verify($current_password, $current_password_hash)) {
                try {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$hashed_password, $user_id]);
                    $response['success'] = true;
                    $response['message'] = "Password changed successfully";
                } catch (Exception $e) {
                    $response['errors'][] = "Error changing password";
                }
            } else {
                $response['errors'][] = "Current password is incorrect";
            }
        }
    }
    
    echo json_encode($response);
    exit();
}

// Fetch current user data for page display
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $current_user = [];
}

if (!$current_user) {
    die("Unable to fetch user data");
}

$page_title = "Edit Profile";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Somdul Table</title>
    
    <!-- BaticaSans Font Import -->
    <link rel="preconnect" href="https://ydpschool.com">
    <style>
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

        /* CSS Custom Properties for Somdul Table Design System */
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
            background-color: var(--cream);
            font-weight: 400;
        }

        /* Typography */
        h1, h2, h3, h4, h5, h6 {
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
            line-height: 1.2;
            color: var(--text-dark);
        }

     .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            z-index: 1000;
            transition: var(--transition);
            box-shadow: var(--shadow-soft);
        }
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            text-decoration: none;
            color: var(--text-dark);
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--curry);
            font-family: 'BaticaSans', sans-serif;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-gray);
            font-weight: 500;
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
        }

        .nav-links a:hover {
            color: var(--curry);
        }

        .nav-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            box-shadow: var(--shadow-soft);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
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

        /* Main Content */
        .main-content {
            margin-top: 120px;
            min-height: 100vh;
            padding: 2rem;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--curry) 0%, var(--brown) 100%);
            color: var(--white);
            padding: 3rem 0;
            margin-bottom: 3rem;
            border-radius: var(--radius-lg);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .profile-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .profile-header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .profile-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
            align-items: start;
        }

        .profile-sidebar {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-soft);
            position: static;

        }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            text-align: left;
            width: 100%;
            background: transparent;
            font-family: inherit;
            font-size: 1rem;
            color: var(--text-gray);
        }

        .menu-item:hover {
            background: var(--cream);
            color: var(--text-dark);
        }

        .menu-item.active {
            background: var(--curry);
            color: var(--white);
        }

        .menu-icon {
            font-size: 1.2rem;
        }

        .profile-content {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }

        .tab-content {
            display: none;
            padding: 2rem;
        }

        .tab-content.active {
            display: block;
        }

        .section-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--cream);
        }

        .section-header h2 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .section-header p {
            color: var(--text-gray);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .required {
            color: #e74c3c;
        }

        .form-input, .form-select, .form-textarea {
            padding: 0.8rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-family: inherit;
            transition: var(--transition);
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .form-input:disabled {
            background: #f8f9fa;
            cursor: not-allowed;
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--cream);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
        }

        .checkbox-item:hover {
            background: var(--sage);
            color: var(--white);
        }

        .checkbox-item input[type="checkbox"] {
            width: 1.2rem;
            height: 1.2rem;
        }

        .spice-level-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
        }

        .spice-option input[type="radio"] {
            display: none;
        }

        .spice-label {
            text-align: center;
            padding: 1rem;
            background: var(--cream);
            border-radius: var(--radius-md);
            cursor: pointer;
            border: 2px solid transparent;
            transition: var(--transition);
        }

        .spice-option input[type="radio"]:checked + .spice-label {
            background: var(--curry);
            color: var(--white);
            border-color: var(--curry);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid var(--cream);
        }

        .input-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-gray);
            font-size: 1.2rem;
        }

        .password-toggle:hover {
            color: var(--curry);
        }

        /* Alert Styles */
        .alert {
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Loading state */
        .loading {
            opacity: 0.7;
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
            border-top: 2px solid var(--curry);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }
            
            .profile-sidebar {
                position: static;
            }
            
            .sidebar-menu {
                flex-direction: row;
                overflow-x: auto;
            }
            
            .menu-item {
                min-width: 200px;
            }
        }

        @media (max-width: 768px) {
            .navbar {
                top: 0;
            }
            
            .main-content {
                margin-top: 80px;
                padding: 1rem;
            }
            
            .profile-header {
                margin-bottom: 2rem;
                padding: 2rem 0;
            }
            
            .profile-header h1 {
                font-size: 2rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .nav-links {
                display: none;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <a href="home2.php" class="logo">
                <img src="./assets/image/LOGO_BG.png" alt="Somdul Table" style="height: 40px; width: auto;">
                <span class="logo-text">Somdul Table</span>
            </a>
            
            <ul class="nav-links">
                <li><a href="./menus.php">Menu</a></li>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            
            <div class="nav-actions">
                <a href="logout.php" class="btn btn-secondary">Sign Out</a>
                <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="container">
                <h1>Edit Profile</h1>
                <p>Manage your account settings and preferences</p>
            </div>
        </div>

        <div class="container">
            <div class="profile-layout">
                <!-- Sidebar Navigation -->
                <aside class="profile-sidebar">
                    <nav class="sidebar-menu">
                        <button class="menu-item active" data-tab="profile">
                            <span class="menu-icon">👤</span>
                            <span>Personal Info</span>
                        </button>
                        <button class="menu-item" data-tab="preferences">
                            <span class="menu-icon">🍽️</span>
                            <span>Food Preferences</span>
                        </button>
                        <button class="menu-item" data-tab="security">
                            <span class="menu-icon">🔒</span>
                            <span>Security</span>
                        </button>
                    </nav>
                </aside>

                <!-- Main Content -->
                <main class="profile-content">
                    <!-- Personal Information Tab -->
                    <div class="tab-content active" id="profile-tab">
                        <form id="profileForm">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="section-header">
                                <h2>Personal Information</h2>
                                <p>Update your basic profile information</p>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="first_name" class="form-label">First Name <span class="required">*</span></label>
                                    <input type="text" id="first_name" name="first_name" class="form-input" 
                                           value="<?= htmlspecialchars($current_user['first_name']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="last_name" class="form-label">Last Name <span class="required">*</span></label>
                                    <input type="text" id="last_name" name="last_name" class="form-input" 
                                           value="<?= htmlspecialchars($current_user['last_name']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" id="email" class="form-input" 
                                           value="<?= htmlspecialchars($current_user['email']); ?>" disabled>
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" class="form-input" 
                                           value="<?= htmlspecialchars($current_user['phone'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-input" 
                                           value="<?= htmlspecialchars($current_user['date_of_birth'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select id="gender" name="gender" class="form-select">
                                        <option value="">Select Gender</option>
                                        <option value="male" <?= ($current_user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?= ($current_user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="other" <?= ($current_user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="section-header" style="margin-top: 2rem;">
                                <h2>Delivery Address</h2>
                                <p>Where should we deliver your meals?</p>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label for="delivery_address" class="form-label">Address</label>
                                    <input type="text" id="delivery_address" name="delivery_address" class="form-input" 
                                           value="<?= htmlspecialchars($current_user['delivery_address'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" id="city" name="city" class="form-input" 
                                           value="<?= htmlspecialchars($current_user['city'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="zip_code" class="form-label">ZIP Code</label>
                                    <input type="text" id="zip_code" name="zip_code" class="form-input" 
                                           value="<?= htmlspecialchars($current_user['zip_code'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group full-width">
                                    <label for="delivery_instructions" class="form-label">Delivery Instructions</label>
                                    <textarea id="delivery_instructions" name="delivery_instructions" class="form-textarea"><?= htmlspecialchars($current_user['delivery_instructions'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>

                    <!-- Food Preferences Tab -->
                    <div class="tab-content" id="preferences-tab">
                        <form id="preferencesForm">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="section-header">
                                <h2>Food Preferences</h2>
                                <p>Let us know about your dietary restrictions and preferences</p>
                            </div>
                            
                            <div class="form-group full-width">
                                <label class="form-label">Dietary Restrictions</label>
                                <div class="checkbox-grid">
                                    <?php 
                                    $dietary_preferences = json_decode($current_user['dietary_preferences'] ?? '[]', true); 
                                    $preferences = [
                                        'vegetarian' => 'Vegetarian',
                                        'vegan' => 'Vegan', 
                                        'halal' => 'Halal',
                                        'gluten_free' => 'Gluten Free',
                                        'dairy_free' => 'Dairy Free',
                                        'low_sodium' => 'Low Sodium'
                                    ];
                                    foreach ($preferences as $key => $label): 
                                    ?>
                                        <label class="checkbox-item">
                                            <input type="checkbox" 
                                               id="diet_<?php echo $key; ?>" 
                                               name="dietary_preferences[]" 
                                               value="<?php echo $key; ?>"
                                               <?php echo in_array($key, $dietary_preferences) ? 'checked' : ''; ?>
                                            >
                                            <span class="checkbox-label"><?php echo $label; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-group full-width">
                                <label class="form-label">Allergies</label>
                                <div class="checkbox-grid">
                                    <?php 
                                    $allergies = json_decode($current_user['allergies'] ?? '[]', true); 
                                    $allergy_options = [
                                        'nuts' => 'Nuts',
                                        'shellfish' => 'Shellfish', 
                                        'eggs' => 'Eggs',
                                        'soy' => 'Soy',
                                        'fish' => 'Fish',
                                        'sesame' => 'Sesame'
                                    ];
                                    foreach ($allergy_options as $key => $label): 
                                    ?>
                                        <label class="checkbox-item">
                                            <input type="checkbox" 
                                               id="allergy_<?php echo $key; ?>" 
                                               name="allergies[]" 
                                               value="<?php echo $key; ?>"
                                               <?php echo in_array($key, $allergies) ? 'checked' : ''; ?>
                                            >
                                            <span class="checkbox-label"><?php echo $label; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-group full-width">
                                <label class="form-label">Preferred Spice Level</label>
                                <div class="spice-level-selector">
                                    <?php 
                                    $spice_levels = [
                                        'mild' => ['icon' => '🟢', 'text' => 'Mild'],
                                        'medium' => ['icon' => '🟡', 'text' => 'Medium'],
                                        'hot' => ['icon' => '🟠', 'text' => 'Hot'],
                                        'extra_hot' => ['icon' => '🔴', 'text' => 'Extra Hot']
                                    ];
                                    foreach ($spice_levels as $key => $data): ?>
                                        <label class="spice-option">
                                            <input 
                                                type="radio" 
                                                name="spice_level" 
                                                value="<?php echo $key; ?>"
                                                <?php echo ($current_user['spice_level'] ?? 'medium') === $key ? 'checked' : ''; ?>
                                            >
                                            <div class="spice-label">
                                                <div class="spice-icon"><?php echo $data['icon']; ?></div>
                                                <div class="spice-text"><?php echo $data['text']; ?></div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Save Preferences</button>
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>

                    <!-- Security Tab -->
                    <div class="tab-content" id="security-tab">
                        <form id="passwordForm">
                            <input type="hidden" name="action" value="change_password">

                            <div class="section-header">
                                <h2>Change Password</h2>
                                <p>Update your password to keep your account secure</p>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label for="current_password" class="form-label">Current Password <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <input type="password" id="current_password" name="current_password" class="form-input" required>
                                        <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                                            👁️
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_password" class="form-label">New Password <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <input type="password" id="new_password" name="new_password" class="form-input" required>
                                        <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                            👁️
                                        </button>
                                    </div>
                                    <small class="form-hint">Password must be at least 8 characters long</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">Confirm New Password <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                            👁️
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Change Password</button>
                                <button type="button" class="btn btn-secondary" onclick="resetPasswordForm()">Reset</button>
                            </div>
                        </form>

                        <div class="section-header" style="margin-top: 3rem;">
                            <h2>Account Security</h2>
                            <p>Manage your account security settings</p>
                        </div>

                        <div class="security-info">
                            <div class="info-item">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?= htmlspecialchars($current_user['email']); ?></span>
                                <span class="status-badge <?= $current_user['email_verified'] ? 'verified' : 'pending'; ?>">
                                    <?= $current_user['email_verified'] ? '✅ Verified' : '⏳ Pending'; ?>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Account Status:</span>
                                <span class="info-value"><?= ucfirst($current_user['status'] ?? 'active'); ?></span>
                                <span class="status-badge active">✅ Active</span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Last Login:</span>
                                <span class="info-value"><?= date('M j, Y g:i A', strtotime($current_user['last_login'] ?? 'now')); ?></span>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <div id="messageContainer" style="position: fixed; top: 20px; right: 20px; z-index: 2000;"></div>

    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const menuItems = document.querySelectorAll('.menu-item');
            const tabContents = document.querySelectorAll('.tab-content');

            menuItems.forEach(item => {
                item.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');
                    
                    // Remove active class from all menu items and tabs
                    menuItems.forEach(mi => mi.classList.remove('active'));
                    tabContents.forEach(tc => tc.classList.remove('active'));
                    
                    // Add active class to clicked item and corresponding tab
                    this.classList.add('active');
                    document.getElementById(targetTab + '-tab').classList.add('active');
                });
            });
        });

        // Password toggle functionality
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const toggle = field.nextElementSibling;
            
            if (field.type === 'password') {
                field.type = 'text';
                toggle.textContent = '🙈';
            } else {
                field.type = 'password';
                toggle.textContent = '👁️';
            }
        }

        // Reset password form
        function resetPasswordForm() {
            document.getElementById('passwordForm').reset();
            // Reset all password toggles
            ['current_password', 'new_password', 'confirm_password'].forEach(id => {
                const field = document.getElementById(id);
                const toggle = field.nextElementSibling;
                field.type = 'password';
                toggle.textContent = '👁️';
            });
        }

        // Show message function
        function showMessage(message, type = 'success') {
            const container = document.getElementById('messageContainer');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `
                <span>${message}</span>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; margin-left: 1rem;">×</button>
            `;
            container.appendChild(alertDiv);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentElement) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // AJAX form submission for profile
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            
            // Show loading state
            submitBtn.classList.add('loading');
            submitBtn.textContent = 'Saving...';
            submitBtn.disabled = true;
            
            fetch('edit_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message || 'Profile updated successfully!', 'success');
                } else {
                    const errorMessage = data.errors ? data.errors.join('<br>') : 'An error occurred';
                    showMessage(errorMessage, 'error');
                }
            })
            .catch(error => {
                showMessage('Network error. Please try again.', 'error');
            })
            .finally(() => {
                // Reset button state
                submitBtn.classList.remove('loading');
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });

        // AJAX form submission for preferences (same form, different fields)
        document.getElementById('preferencesForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            
            // Show loading state
            submitBtn.classList.add('loading');
            submitBtn.textContent = 'Saving...';
            submitBtn.disabled = true;
            
            fetch('edit_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message || 'Preferences updated successfully!', 'success');
                } else {
                    const errorMessage = data.errors ? data.errors.join('<br>') : 'An error occurred';
                    showMessage(errorMessage, 'error');
                }
            })
            .catch(error => {
                showMessage('Network error. Please try again.', 'error');
            })
            .finally(() => {
                // Reset button state
                submitBtn.classList.remove('loading');
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });

        // AJAX form submission for password change
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            
            // Show loading state
            submitBtn.classList.add('loading');
            submitBtn.textContent = 'Changing...';
            submitBtn.disabled = true;
            
            fetch('edit_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message || 'Password changed successfully!', 'success');
                    this.reset(); // Clear the form
                    resetPasswordForm(); // Reset password toggles
                } else {
                    const errorMessage = data.errors ? data.errors.join('<br>') : 'An error occurred';
                    showMessage(errorMessage, 'error');
                }
            })
            .catch(error => {
                showMessage('Network error. Please try again.', 'error');
            })
            .finally(() => {
                // Reset button state
                submitBtn.classList.remove('loading');
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });

        // Additional styles for security info
        const additionalStyles = `
            .security-info {
                background: var(--cream);
                border-radius: var(--radius-md);
                padding: 1.5rem;
                margin-top: 2rem;
            }
            
            .info-item {
                display: flex;
                align-items: center;
                gap: 1rem;
                padding: 0.75rem 0;
                border-bottom: 1px solid var(--border-light);
            }
            
            .info-item:last-child {
                border-bottom: none;
            }
            
            .info-label {
                font-weight: 600;
                min-width: 120px;
                color: var(--text-dark);
            }
            
            .info-value {
                flex: 1;
                color: var(--text-gray);
            }
            
            .status-badge {
                padding: 0.25rem 0.75rem;
                border-radius: 20px;
                font-size: 0.85rem;
                font-weight: 600;
            }
            
            .status-badge.verified,
            .status-badge.active {
                background: #d4edda;
                color: #155724;
            }
            
            .status-badge.pending {
                background: #fff3cd;
                color: #856404;
            }
            
            .spice-icon {
                font-size: 1.5rem;
                margin-bottom: 0.5rem;
            }
            
            .spice-text {
                font-weight: 600;
                font-size: 0.9rem;
            }
        `;
        
        // Add additional styles to head
        const styleSheet = document.createElement('style');
        styleSheet.textContent = additionalStyles;
        document.head.appendChild(styleSheet);
    </script>
</body>
</html>