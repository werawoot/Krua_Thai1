<?php
/**
 * Beautiful Edit Profile Page with Password Change
 * File: edit_profile.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php?redirect=edit_profile");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$errors = [];
$success_message = "";

// Get current user data with error handling
$current_user = null;
if (isset($connection) && mysqli_ping($connection)) {
    $stmt = mysqli_prepare($connection, "SELECT * FROM users WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $user_id);
        mysqli_stmt_execute($stmt);
        $current_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    }
}

// Fallback if database fails
if (!$current_user) {
    $current_user = [
        'id' => $user_id,
        'first_name' => explode(' ', $_SESSION['user_name'] ?? 'User')[0],
        'last_name' => explode(' ', $_SESSION['user_name'] ?? 'User User')[1] ?? '',
        'email' => $_SESSION['user_email'] ?? 'user@example.com',
        'phone' => '',
        'date_of_birth' => '',
        'gender' => '',
        'delivery_address' => '',
        'address_line_2' => '',
        'city' => '',
        'state' => '',
        'zip_code' => '',
        'delivery_instructions' => '',
        'dietary_preferences' => '[]',
        'allergies' => '[]',
        'spice_level' => 'medium',
        'status' => 'active',
        'email_verified' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'last_login' => date('Y-m-d H:i:s'),
        'password_hash' => ''
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        // Get form data
        $first_name = sanitizeInput($_POST['first_name'] ?? '');
        $last_name = sanitizeInput($_POST['last_name'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $date_of_birth = sanitizeInput($_POST['date_of_birth'] ?? '');
        $gender = sanitizeInput($_POST['gender'] ?? '');
        $dietary_preferences = isset($_POST['dietary_preferences']) ? json_encode($_POST['dietary_preferences']) : '[]';
        $allergies = isset($_POST['allergies']) ? json_encode($_POST['allergies']) : '[]';
        $spice_level = sanitizeInput($_POST['spice_level'] ?? 'medium');
        
        // Validate required fields
        if (empty($first_name)) $errors[] = "กรุณากรอกชื่อ";
        if (empty($last_name)) $errors[] = "กรุณากรอกนามสกุล";
        
        // Validate phone
        if (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
            $errors[] = "เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 หลัก";
        }
        
        if (empty($errors)) {
            // Try to update if database is available
            if (isset($connection) && mysqli_ping($connection)) {
                $update_stmt = mysqli_prepare($connection,
                    "UPDATE users SET first_name = ?, last_name = ?, phone = ?, date_of_birth = ?, gender = ?, dietary_preferences = ?, allergies = ?, spice_level = ?, updated_at = NOW() WHERE id = ?");
                
                if ($update_stmt) {
                    mysqli_stmt_bind_param($update_stmt, "sssssssss", $first_name, $last_name, $phone, $date_of_birth, $gender, $dietary_preferences, $allergies, $spice_level, $user_id);
                    
                    if (mysqli_stmt_execute($update_stmt)) {
                        $success_message = "อัพเดทข้อมูลส่วนตัวเรียบร้อยแล้ว";
                        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                        
                        // Update current_user array
                        $current_user['first_name'] = $first_name;
                        $current_user['last_name'] = $last_name;
                        $current_user['phone'] = $phone;
                        $current_user['date_of_birth'] = $date_of_birth;
                        $current_user['gender'] = $gender;
                        $current_user['dietary_preferences'] = $dietary_preferences;
                        $current_user['allergies'] = $allergies;
                        $current_user['spice_level'] = $spice_level;
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการอัพเดทข้อมูล";
                    }
                    mysqli_stmt_close($update_stmt);
                } else {
                    $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง";
                }
            } else {
                $success_message = "ข้อมูลได้รับการปรับปรุงในเซสชัน (ฐานข้อมูลไม่พร้อมใช้งาน)";
            }
        }
    }
    
    // Handle password change
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate password fields
        if (empty($current_password)) {
            $errors[] = "กรุณากรอกรหัสผ่านปัจจุบัน";
        }
        if (empty($new_password)) {
            $errors[] = "กรุณากรอกรหัสผ่านใหม่";
        }
        if (strlen($new_password) < 8) {
            $errors[] = "รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร";
        }
        if ($new_password !== $confirm_password) {
            $errors[] = "รหัสผ่านใหม่และการยืนยันไม่ตรงกัน";
        }
        
        if (empty($errors)) {
            // Check if database is available
            if (isset($connection) && mysqli_ping($connection)) {
                // Verify current password
                if (!empty($current_user['password_hash']) && password_verify($current_password, $current_user['password_hash'])) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $password_stmt = mysqli_prepare($connection, "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                    
                    if ($password_stmt) {
                        mysqli_stmt_bind_param($password_stmt, "ss", $hashed_password, $user_id);
                        
                        if (mysqli_stmt_execute($password_stmt)) {
                            $success_message = "เปลี่ยนรหัสผ่านเรียบร้อยแล้ว";
                            $current_user['password_hash'] = $hashed_password;
                        } else {
                            $errors[] = "เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน";
                        }
                        mysqli_stmt_close($password_stmt);
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง";
                    }
                } else {
                    $errors[] = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
                }
            } else {
                $errors[] = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาลองใหม่อีกครั้ง";
            }
        }
    }
}

$page_title = "แก้ไขข้อมูลส่วนตัว";
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Krua Thai</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            color: var(--olive);
            background: var(--light-cream);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        .profile-header {
            background: linear-gradient(135deg, var(--olive) 0%, var(--matcha) 100%);
            color: var(--white);
            padding: 2rem 0;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 40%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="70" cy="30" r="15" fill="rgba(255,255,255,0.1)"/></svg>');
            opacity: 0.3;
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .breadcrumb {
            margin-bottom: 1rem;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .breadcrumb a {
            color: var(--cream);
            text-decoration: none;
            transition: color 0.3s;
        }

        .breadcrumb a:hover {
            color: var(--white);
        }

        .breadcrumb span {
            margin: 0 0.5rem;
            opacity: 0.7;
        }

        .profile-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .profile-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Layout */
        .profile-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        /* Sidebar */
        .profile-sidebar {
            background: var(--white);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px var(--shadow);
            height: fit-content;
            position: sticky;
            top: 2rem;
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
            background: transparent;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: left;
            width: 100%;
        }

        .menu-item:hover {
            background: var(--light-cream);
        }

        .menu-item.active {
            background: linear-gradient(45deg, var(--brown), #a67c00);
            color: var(--white);
        }

        .menu-icon {
            font-size: 1.2rem;
        }

        .menu-text {
            font-weight: 500;
        }

        /* Content */
        .profile-content {
            background: var(--white);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px var(--shadow);
        }

        /* Alerts */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
        }

        .alert-icon {
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .error-list {
            margin: 0;
            padding-left: 1.5rem;
        }

        .error-list li {
            margin-bottom: 0.5rem;
        }

        /* Tabs */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .section-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--light-cream);
        }

        .section-header h2 {
            color: var(--olive);
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .section-header p {
            color: var(--gray);
        }

        /* Forms */
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
            color: var(--olive);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .required {
            color: var(--danger);
        }

        .form-input,
        .form-select,
        .form-textarea {
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
            color: var(--olive);
            font-family: inherit;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--brown);
            box-shadow: 0 0 0 3px rgba(134, 96, 40, 0.1);
        }

        .form-input:disabled {
            background: var(--light-gray);
            color: var(--gray);
            cursor: not-allowed;
        }

        .form-hint {
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 0.5rem;
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* Checkboxes */
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--light-cream);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .checkbox-item:hover {
            background: var(--cream);
        }

        .checkbox-item input[type="checkbox"] {
            transform: scale(1.2);
            accent-color: var(--brown);
        }

        .checkbox-label {
            font-weight: 500;
            font-size: 0.95rem;
        }

        /* Spice Level */
        .spice-level-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .spice-option {
            cursor: pointer;
        }

        .spice-option input[type="radio"] {
            display: none;
        }

        .spice-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem;
            background: var(--light-cream);
            border-radius: 10px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .spice-option input[type="radio"]:checked + .spice-label {
            background: var(--brown);
            color: var(--white);
            border-color: var(--brown);
            transform: translateY(-2px);
        }

        .spice-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .spice-text {
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* Security Section */
        .security-section {
            background: var(--light-cream);
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }

        .security-section h3 {
            color: var(--olive);
            margin-bottom: 1.5rem;
            font-size: 1.2rem;
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
            font-size: 1.2rem;
            color: var(--gray);
            transition: color 0.3s;
        }

        .password-toggle:hover {
            color: var(--brown);
        }

        .password-match {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .password-match.match {
            color: var(--success);
        }

        .password-match.no-match {
            color: var(--danger);
        }

        /* Security Info */
        .security-info {
            background: var(--light-gray);
            padding: 2rem;
            border-radius: 15px;
        }

        .security-info h3 {
            color: var(--olive);
            margin-bottom: 1.5rem;
            font-size: 1.2rem;
        }

        .info-grid {
            display: grid;
            gap: 1rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #dee2e6;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--gray);
            font-weight: 500;
        }

        .info-value {
            color: var(--olive);
            font-weight: 600;
        }

        /* Buttons */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid var(--light-cream);
            flex-wrap: wrap;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--brown), #a67c00);
            color: var(--white);
            padding: 1rem 2rem;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(134, 96, 40, 0.3);
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .btn-secondary {
            background: transparent;
            color: var(--brown);
            padding: 1rem 2rem;
            border: 2px solid var(--brown);
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary:hover {
            background: var(--brown);
            color: var(--white);
        }

        .btn-link {
            color: var(--brown);
            text-decoration: none;
            font-weight: 600;
            padding: 1rem 0;
            border-bottom: 1px solid transparent;
            transition: border-bottom-color 0.3s;
        }

        .btn-link:hover {
            border-bottom-color: var(--brown);
        }

        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid transparent;
            border-top: 2px solid var(--white);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .btn-spinner {
            display: none;
            align-items: center;
            gap: 0.5rem;
        }

        /* Password Form Specific */
        .password-form {
            margin-top: 1rem;
        }

        .password-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .btn-danger {
            background: linear-gradient(45deg, var(--danger), #c82333);
            color: var(--white);
            padding: 1rem 2rem;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .btn-danger:hover:not(:disabled) {
            background: linear-gradient(45deg, #c82333, var(--danger));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }

        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .profile-layout {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .profile-sidebar {
                position: static;
            }
            
            .sidebar-menu {
                flex-direction: row;
                overflow-x: auto;
                gap: 1rem;
                padding-bottom: 0.5rem;
            }
            
            .menu-item {
                flex-shrink: 0;
                min-width: 150px;
            }
        }

        @media (max-width: 768px) {
            .profile-header h1 {
                font-size: 2rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions,
            .password-actions {
                flex-direction: column;
            }
            
            .btn-primary,
            .btn-secondary,
            .btn-danger {
                text-align: center;
            }
            
            .checkbox-grid {
                grid-template-columns: 1fr;
            }
            
            .spice-level-selector {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 15px;
            }
            
            .profile-content,
            .profile-sidebar {
                padding: 1.5rem;
            }
            
            .security-section,
            .security-info {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="profile-header">
        <div class="container">
            <div class="header-content">
                <div class="breadcrumb">
                    <a href="dashboard.php">แดชบอร์ด</a>
                    <span>›</span>
                    <span>แก้ไขข้อมูลส่วนตัว</span>
                </div>
                <h1>แก้ไขข้อมูลส่วนตัว</h1>
                <p>จัดการข้อมูลส่วนตัวและการตั้งค่าบัญชีของคุณ</p>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="profile-layout">
            <!-- Sidebar Navigation -->
            <div class="profile-sidebar">
                <div class="sidebar-menu">
                    <button class="menu-item active" data-tab="personal">
                        <span class="menu-icon">👤</span>
                        <span class="menu-text">ข้อมูลส่วนตัว</span>
                    </button>
                    <button class="menu-item" data-tab="preferences">
                        <span class="menu-icon">🍽️</span>
                        <span class="menu-text">ความชอบอาหาร</span>
                    </button>
                    <button class="menu-item" data-tab="security">
                        <span class="menu-icon">🔒</span>
                        <span class="menu-text">ความปลอดภัย</span>
                    </button>
                </div>
            </div>

            <!-- Main Content -->
            <div class="profile-content">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <div class="alert-icon">⚠️</div>
                        <div class="alert-content">
                            <ul class="error-list">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <div class="alert-icon">✅</div>
                        <div class="alert-content">
                            <p><?php echo htmlspecialchars($success_message); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Personal Information Tab -->
                <div class="tab-content active" id="personal-tab">
                    <div class="section-header">
                        <h2>ข้อมูลส่วนตัว</h2>
                        <p>ข้อมูลพื้นฐานของคุณ</p>
                    </div>

                    <form method="POST" class="profile-form" id="profileForm">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="first_name" class="form-label">
                                    ชื่อ <span class="required">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="first_name" 
                                    name="first_name" 
                                    class="form-input"
                                    value="<?php echo htmlspecialchars($current_user['first_name']); ?>"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="last_name" class="form-label">
                                    นามสกุล <span class="required">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="last_name" 
                                    name="last_name" 
                                    class="form-input"
                                    value="<?php echo htmlspecialchars($current_user['last_name']); ?>"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="email" class="form-label">อีเมล</label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    class="form-input"
                                    value="<?php echo htmlspecialchars($current_user['email']); ?>"
                                    disabled
                                >
                                <small class="form-hint">อีเมลไม่สามารถเปลี่ยนได้ กรุณาติดต่อฝ่ายสนับสนุน</small>
                            </div>

                            <div class="form-group">
                                <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                                <input 
                                    type="tel" 
                                    id="phone" 
                                    name="phone" 
                                    class="form-input"
                                    value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>"
                                    placeholder="0812345678"
                                    pattern="[0-9]{10}"
                                >
                            </div>

                            <div class="form-group">
                                <label for="date_of_birth" class="form-label">วันเกิด</label>
                                <input 
                                    type="date" 
                                    id="date_of_birth" 
                                    name="date_of_birth" 
                                    class="form-input"
                                    value="<?php echo $current_user['date_of_birth'] ?? ''; ?>"
                                    max="<?php echo date('Y-m-d'); ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="gender" class="form-label">เพศ</label>
                                <select id="gender" name="gender" class="form-select">
                                    <option value="">เลือกเพศ</option>
                                    <option value="male" <?php echo ($current_user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>ชาย</option>
                                    <option value="female" <?php echo ($current_user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>หญิง</option>
                                    <option value="other" <?php echo ($current_user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>อื่นๆ</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-primary" id="saveBtn">
                                <span class="btn-text">บันทึกการเปลี่ยนแปลง</span>
                                <div class="btn-spinner">
                                    <div class="spinner"></div>
                                    <span>กำลังบันทึก...</span>
                                </div>
                            </button>
                            <a href="dashboard.php" class="btn-secondary">ยกเลิก</a>
                        </div>
                    </form>
                </div>

                <!-- Preferences Tab -->
                <div class="tab-content" id="preferences-tab">
                    <div class="section-header">
                        <h2>ความชอบอาหาร</h2>
                        <p>ตั้งค่าความชอบด้านอาหารของคุณ</p>
                    </div>

                    <form method="POST" class="profile-form" id="preferencesForm">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label class="form-label">ข้อจำกัดด้านอาหาร</label>
                                <div class="checkbox-grid">
                                    <?php 
                                    $dietary_preferences = json_decode($current_user['dietary_preferences'] ?? '[]', true);
                                    $preferences = [
                                        'vegetarian' => 'มังสวิรัติ',
                                        'vegan' => 'วีแกน',
                                        'halal' => 'ฮาลาล',
                                        'gluten_free' => 'ไม่มีกลูเตน',
                                        'dairy_free' => 'ไม่มีแลคโตส',
                                        'low_sodium' => 'โซเดียมต่ำ'
                                    ];
                                    foreach ($preferences as $key => $label): ?>
                                        <label class="checkbox-item">
                                            <input 
                                                type="checkbox" 
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
                                <label class="form-label">อาหารที่แพ้</label>
                                <div class="checkbox-grid">
                                    <?php 
                                    $allergies = json_decode($current_user['allergies'] ?? '[]', true);
                                    $allergy_list = [
                                        'nuts' => 'ถั่วทุกชนิด',
                                        'shellfish' => 'กุ้ง หอย ปู',
                                        'fish' => 'ปลา',
                                        'eggs' => 'ไข่',
                                        'milk' => 'นม',
                                        'soy' => 'ถั่วเหลือง'
                                    ];
                                    foreach ($allergy_list as $key => $label): ?>
                                        <label class="checkbox-item">
                                            <input 
                                                type="checkbox" 
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
                                <label class="form-label">ระดับความเผ็ด</label>
                                <div class="spice-level-selector">
                                    <?php 
                                    $spice_levels = [
                                        'mild' => ['icon' => '🟢', 'text' => 'ไม่เผ็ด'],
                                        'medium' => ['icon' => '🟡', 'text' => 'เผ็ดปานกลาง'],
                                        'hot' => ['icon' => '🟠', 'text' => 'เผ็ด'],
                                        'extra_hot' => ['icon' => '🔴', 'text' => 'เผ็ดมาก']
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
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-primary">
                                <span>บันทึกความชอบ</span>
                            </button>
                            <a href="dashboard.php" class="btn-secondary">ยกเลิก</a>
                        </div>
                    </form>
                </div>

                <!-- Security Tab -->
                <div class="tab-content" id="security-tab">
                    <div class="section-header">
                        <h2>ความปลอดภัย</h2>
                        <p>จัดการรหัสผ่านและการตั้งค่าความปลอดภัย</p>
                    </div>

                    <div class="security-section">
                        <h3>เปลี่ยนรหัสผ่าน</h3>
                        <form method="POST" class="password-form" id="passwordForm">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="current_password" class="form-label">
                                        รหัสผ่านปัจจุบัน <span class="required">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <input 
                                            type="password" 
                                            id="current_password" 
                                            name="current_password" 
                                            class="form-input"
                                            placeholder="••••••••"
                                            required
                                        >
                                        <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                                            👁️
                                        </button>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="new_password" class="form-label">
                                        รหัสผ่านใหม่ <span class="required">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <input 
                                            type="password" 
                                            id="new_password" 
                                            name="new_password" 
                                            class="form-input"
                                            placeholder="••••••••"
                                            minlength="8"
                                            required
                                        >
                                        <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                            👁️
                                        </button>
                                    </div>
                                    <small class="form-hint">รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร</small>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">
                                        ยืนยันรหัสผ่านใหม่ <span class="required">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <input 
                                            type="password" 
                                            id="confirm_password" 
                                            name="confirm_password" 
                                            class="form-input"
                                            placeholder="••••••••"
                                            required
                                        >
                                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                            👁️
                                        </button>
                                    </div>
                                    <div class="password-match" id="passwordMatch"></div>
                                </div>
                            </div>

                            <div class="password-actions">
                                <button type="submit" class="btn-danger" id="changePasswordBtn">
                                    <span class="btn-text">เปลี่ยนรหัสผ่าน</span>
                                    <div class="btn-spinner">
                                        <div class="spinner"></div>
                                        <span>กำลังเปลี่ยน...</span>
                                    </div>
                                </button>
                                <button type="button" class="btn-secondary" onclick="clearPasswordForm()">
                                    ล้างฟอร์ม
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="security-info">
                        <h3>ข้อมูลบัญชี</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">สถานะบัญชี</span>
                                <span class="info-value">
                                    <?php 
                                    $status_map = [
                                        'active' => '✅ ใช้งานได้',
                                        'inactive' => '⚠️ ไม่ได้ใช้งาน',
                                        'suspended' => '🚫 ถูกระงับ',
                                        'pending_verification' => '⏳ รอการยืนยัน'
                                    ];
                                    echo $status_map[$current_user['status']] ?? $current_user['status'];
                                    ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">การยืนยันอีเมล</span>
                                <span class="info-value">
                                    <?php echo $current_user['email_verified'] ? '✅ ยืนยันแล้ว' : '❌ ยังไม่ยืนยัน'; ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">สมาชิกตั้งแต่</span>
                                <span class="info-value">
                                    <?php echo date('j F Y', strtotime($current_user['created_at'])); ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">เข้าสู่ระบบล่าสุด</span>
                                <span class="info-value">
                                    <?php 
                                    if ($current_user['last_login']) {
                                        echo date('j F Y เวลา H:i น.', strtotime($current_user['last_login']));
                                    } else {
                                        echo 'ไม่มีข้อมูล';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="dashboard.php" class="btn-link">← กลับไปแดชบอร์ด</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            const menuItems = document.querySelectorAll('.menu-item');
            const tabContents = document.querySelectorAll('.tab-content');

            menuItems.forEach(item => {
                item.addEventListener('click', function() {
                    const targetTab = this.dataset.tab;
                    
                    // Remove active class from all menu items and tab contents
                    menuItems.forEach(mi => mi.classList.remove('active'));
                    tabContents.forEach(tc => tc.classList.remove('active'));
                    
                    // Add active class to clicked menu item and corresponding tab
                    this.classList.add('active');
                    document.getElementById(targetTab + '-tab').classList.add('active');
                });
            });

            // Password matching validation
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const passwordMatch = document.getElementById('passwordMatch');

            function checkPasswordMatch() {
                if (newPassword.value && confirmPassword.value) {
                    if (newPassword.value === confirmPassword.value) {
                        passwordMatch.textContent = '✅ รหัสผ่านตรงกัน';
                        passwordMatch.className = 'password-match match';
                    } else {
                        passwordMatch.textContent = '❌ รหัสผ่านไม่ตรงกัน';
                        passwordMatch.className = 'password-match no-match';
                    }
                } else {
                    passwordMatch.textContent = '';
                    passwordMatch.className = 'password-match';
                }
            }

            if (newPassword && confirmPassword) {
                newPassword.addEventListener('input', checkPasswordMatch);
                confirmPassword.addEventListener('input', checkPasswordMatch);
            }

            // Form submission with loading state
            const profileForm = document.getElementById('profileForm');
            const passwordForm = document.getElementById('passwordForm');
            const preferencesForm = document.getElementById('preferencesForm');

            // Profile form submission
            if (profileForm) {
                const saveBtn = profileForm.querySelector('#saveBtn');
                const btnText = saveBtn.querySelector('.btn-text');
                const btnSpinner = saveBtn.querySelector('.btn-spinner');

                profileForm.addEventListener('submit', function() {
                    saveBtn.disabled = true;
                    btnText.style.display = 'none';
                    btnSpinner.style.display = 'flex';
                });
            }

            // Password form submission
            if (passwordForm) {
                const changePasswordBtn = passwordForm.querySelector('#changePasswordBtn');
                const btnText = changePasswordBtn.querySelector('.btn-text');
                const btnSpinner = changePasswordBtn.querySelector('.btn-spinner');

                passwordForm.addEventListener('submit', function(e) {
                    // Validate password match before submission
                    if (newPassword.value !== confirmPassword.value) {
                        e.preventDefault();
                        alert('รหัสผ่านใหม่และการยืนยันไม่ตรงกัน');
                        return;
                    }

                    if (newPassword.value.length < 8) {
                        e.preventDefault();
                        alert('รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร');
                        return;
                    }

                    changePasswordBtn.disabled = true;
                    btnText.style.display = 'none';
                    btnSpinner.style.display = 'flex';
                });
            }

            // Auto-hide success messages
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.opacity = '0';
                    setTimeout(() => {
                        successAlert.remove();
                    }, 300);
                }, 5000);
            }
        });

        // Password toggle functionality
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                button.textContent = '🙈';
            } else {
                input.type = 'password';
                button.textContent = '👁️';
            }
        }

        // Clear password form
        function clearPasswordForm() {
            document.getElementById('current_password').value = '';
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
            document.getElementById('passwordMatch').textContent = '';
            document.getElementById('passwordMatch').className = 'password-match';
        }

        // Form validation enhancement
        document.querySelectorAll('input[required]').forEach(input => {
            input.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    this.style.borderColor = 'var(--danger)';
                } else {
                    this.style.borderColor = 'var(--success)';
                }
            });

            input.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.style.borderColor = '#e9ecef';
                }
            });
        });

        // Phone number formatting
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').substring(0, 10);
            });
        }

        // Smooth scrolling for mobile tab navigation
        const sidebarMenu = document.querySelector('.sidebar-menu');
        if (sidebarMenu && window.innerWidth <= 1024) {
            const menuItems = document.querySelectorAll('.menu-item');
            menuItems.forEach(item => {
                item.addEventListener('click', function() {
                    this.scrollIntoView({ behavior: 'smooth', inline: 'center' });
                });
            });
        }

        // Password strength indicator (optional enhancement)
        const newPasswordInput = document.getElementById('new_password');
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = calculatePasswordStrength(password);
                // You can add visual feedback here
            });
        }

        function calculatePasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            return strength;
        }
    </script>
</body>
</html>