<?php
/**
 * Krua Thai - Settings Management
 * File: admin/settings.php
 * Description: Complete system settings management with categories and validation
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php"); 
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'update_setting':
                $result = updateSetting($pdo, $_POST['key'], $_POST['value'], $_POST['type'] ?? 'string');
                echo json_encode($result);
                exit;
                
            case 'add_setting':
                $result = addSetting($pdo, $_POST);
                echo json_encode($result);
                exit;
                
            case 'delete_setting':
                $result = deleteSetting($pdo, $_POST['key']);
                echo json_encode($result);
                exit;
                
            case 'backup_settings':
                $result = backupSettings($pdo);
                echo json_encode($result);
                exit;
                
            case 'restore_settings':
                $result = restoreSettings($pdo, $_POST['backup_data']);
                echo json_encode($result);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Database Functions
function updateSetting($pdo, $key, $value, $type = 'string') {
    try {
        // Validate based on type
        if ($type === 'number' && !is_numeric($value)) {
            return ['success' => false, 'message' => 'Invalid number format'];
        }
        
        if ($type === 'boolean') {
            $value = in_array(strtolower($value), ['true', '1', 'on', 'yes']) ? '1' : '0';
        }
        
        if ($type === 'json') {
            $decoded = json_decode($value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'message' => 'Invalid JSON format'];
            }
        }
        
        $stmt = $pdo->prepare("
            UPDATE system_settings 
            SET setting_value = ?, setting_type = ?, updated_at = NOW() 
            WHERE setting_key = ?
        ");
        $stmt->execute([$value, $type, $key]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Setting updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Setting not found or no changes made'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating setting: ' . $e->getMessage()];
    }
}

function addSetting($pdo, $data) {
    try {
        $key = $data['key'];
        $value = $data['value'];
        $type = $data['type'] ?? 'string';
        $description = $data['description'] ?? '';
        $category = $data['category'] ?? 'general';
        $isPublic = isset($data['is_public']) ? 1 : 0;
        
        // Check if key already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Setting key already exists'];
        }
        
        // Validate based on type
        if ($type === 'number' && !is_numeric($value)) {
            return ['success' => false, 'message' => 'Invalid number format'];
        }
        
        if ($type === 'json') {
            $decoded = json_decode($value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'message' => 'Invalid JSON format'];
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category, is_public)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$key, $value, $type, $description, $category, $isPublic]);
        
        return ['success' => true, 'message' => 'Setting added successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error adding setting: ' . $e->getMessage()];
    }
}

function deleteSetting($pdo, $key) {
    try {
        $stmt = $pdo->prepare("DELETE FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Setting deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Setting not found'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error deleting setting: ' . $e->getMessage()];
    }
}

function backupSettings($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM system_settings ORDER BY category, setting_key");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $backup = [
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'settings' => $settings
        ];
        
        $filename = 'krua_thai_settings_backup_' . date('Y-m-d_H-i-s') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($backup, JSON_PRETTY_PRINT);
        exit;
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error creating backup: ' . $e->getMessage()];
    }
}

function restoreSettings($pdo, $backupData) {
    try {
        $backup = json_decode($backupData, true);
        if (!$backup || !isset($backup['settings'])) {
            return ['success' => false, 'message' => 'Invalid backup format'];
        }
        
        $pdo->beginTransaction();
        
        // Clear existing settings
        $pdo->exec("DELETE FROM system_settings");
        
        // Restore settings
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category, is_public)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($backup['settings'] as $setting) {
            $stmt->execute([
                $setting['setting_key'],
                $setting['setting_value'],
                $setting['setting_type'],
                $setting['description'],
                $setting['category'],
                $setting['is_public']
            ]);
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Settings restored successfully'];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error restoring settings: ' . $e->getMessage()];
    }
}

// Fetch Data
try {
    // Get all settings grouped by category
    $stmt = $pdo->prepare("
        SELECT * FROM system_settings 
        ORDER BY category, setting_key
    ");
    $stmt->execute();
    $allSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group settings by category
    $settingsByCategory = [];
    foreach ($allSettings as $setting) {
        $category = $setting['category'] ?: 'general';
        $settingsByCategory[$category][] = $setting;
    }
    
    // Get categories with counts
    $stmt = $pdo->prepare("
        SELECT category, COUNT(*) as count
        FROM system_settings 
        GROUP BY category
        ORDER BY category
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize default settings if none exist
    if (empty($allSettings)) {
        initializeDefaultSettings($pdo);
        // Reload settings
        $stmt = $pdo->prepare("SELECT * FROM system_settings ORDER BY category, setting_key");
        $stmt->execute();
        $allSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settingsByCategory = [];
        foreach ($allSettings as $setting) {
            $category = $setting['category'] ?: 'general';
            $settingsByCategory[$category][] = $setting;
        }
    }
    
} catch (Exception $e) {
    $allSettings = [];
    $settingsByCategory = [];
    $categories = [];
    error_log("Settings error: " . $e->getMessage());
}

// Initialize default settings
function initializeDefaultSettings($pdo) {
    $defaultSettings = [
        // General Settings
        ['site_name', 'Krua Thai', 'string', 'Website name', 'general', 1],
        ['site_description', 'Authentic Thai Meals, Made Healthy', 'string', 'Website description', 'general', 1],
        ['site_url', 'https://kruathai.com', 'string', 'Website URL', 'general', 1],
        ['admin_email', 'admin@kruathai.com', 'string', 'Administrator email', 'general', 0],
        ['timezone', 'Asia/Bangkok', 'string', 'Default timezone', 'general', 1],
        ['maintenance_mode', '0', 'boolean', 'Enable maintenance mode', 'general', 0],
        
        // Business Settings
        ['restaurant_name', 'Krua Thai Restaurant', 'string', 'Restaurant name', 'business', 1],
        ['restaurant_phone', '+66-2-123-4567', 'string', 'Restaurant phone number', 'business', 1],
        ['restaurant_address', '123 Thai Street, Bangkok, Thailand', 'string', 'Restaurant address', 'business', 1],
        ['business_hours', '{"monday":"9:00-21:00","tuesday":"9:00-21:00","wednesday":"9:00-21:00","thursday":"9:00-21:00","friday":"9:00-21:00","saturday":"9:00-22:00","sunday":"9:00-22:00"}', 'json', 'Business operating hours', 'business', 1],
        ['max_orders_per_day', '100', 'number', 'Maximum orders per day', 'business', 0],
        ['order_lead_time', '24', 'number', 'Order lead time in hours', 'business', 0],
        
        // Payment Settings
        ['currency', 'THB', 'string', 'Default currency', 'payment', 1],
        ['tax_rate', '7', 'number', 'Tax rate percentage', 'payment', 0],
        ['delivery_fee', '50', 'number', 'Standard delivery fee', 'payment', 1],
        ['free_delivery_minimum', '500', 'number', 'Minimum order for free delivery', 'payment', 1],
        ['payment_methods', '["apple_pay","google_pay","paypal","credit_card","bank_transfer"]', 'json', 'Enabled payment methods', 'payment', 0],
        ['refund_policy_days', '7', 'number', 'Refund policy duration in days', 'payment', 1],
        
        // Notification Settings
        ['email_notifications', '1', 'boolean', 'Enable email notifications', 'notification', 0],
        ['sms_notifications', '0', 'boolean', 'Enable SMS notifications', 'notification', 0],
        ['push_notifications', '1', 'boolean', 'Enable push notifications', 'notification', 0],
        ['notification_email', 'notifications@kruathai.com', 'string', 'Notification sender email', 'notification', 0],
        ['order_confirmation_template', 'Thank you for your order! Your order #{order_number} has been confirmed.', 'string', 'Order confirmation message template', 'notification', 0],
        
        // SEO Settings
        ['meta_keywords', 'thai food, healthy meals, food delivery, bangkok', 'string', 'Meta keywords', 'seo', 1],
        ['meta_description', 'Order authentic Thai healthy meals delivered to your door. Fresh ingredients, traditional recipes, modern nutrition.', 'string', 'Meta description', 'seo', 1],
        ['google_analytics_id', '', 'string', 'Google Analytics tracking ID', 'seo', 0],
        ['facebook_pixel_id', '', 'string', 'Facebook Pixel ID', 'seo', 0],
        
        // Security Settings
        ['session_timeout', '3600', 'number', 'Session timeout in seconds', 'security', 0],
        ['max_login_attempts', '5', 'number', 'Maximum login attempts', 'security', 0],
        ['password_min_length', '8', 'number', 'Minimum password length', 'security', 0],
        ['require_email_verification', '1', 'boolean', 'Require email verification', 'security', 0],
        ['enable_2fa', '0', 'boolean', 'Enable two-factor authentication', 'security', 0],
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category, is_public)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($defaultSettings as $setting) {
        try {
            $stmt->execute($setting);
        } catch (Exception $e) {
            // Setting might already exist, continue
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings Management - Krua Thai Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --cream: #ece8e1;
            --sage: #adb89d;
            --brown: #bd9379;
            --curry: #cf723a;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-gray: #7f8c8d;
            --border-light: #e8e8e8;
            --shadow-soft: 0 4px 12px rgba(0,0,0,0.05);
            --shadow-medium: 0 8px 24px rgba(0,0,0,0.1);
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
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, var(--cream) 0%, #f8f6f3 100%);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, var(--brown) 0%, var(--curry) 100%);
            color: var(--white);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: var(--transition);
            box-shadow: var(--shadow-medium);
        }

        .sidebar-header {
            padding: 2rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .logo-image {
            max-width: 80px;
            max-height: 80px;
            width: auto;
            height: auto;
            object-fit: contain;
            filter: brightness(1.1) contrast(1.2);
            margin-bottom: 0.5rem;
        }

        .sidebar-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .sidebar-subtitle {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-section-title {
            padding: 0 1.5rem 0.5rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.7;
            font-weight: 600;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: var(--transition);
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: var(--white);
        }

        .nav-item.active {
            background: rgba(255, 255, 255, 0.15);
            border-left-color: var(--white);
            font-weight: 600;
        }

        .nav-icon {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 2rem;
            transition: var(--transition);
        }

        /* Page Header */
        .page-header {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--text-gray);
            font-size: 1rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), #e67e22);
            color: var(--white);
            box-shadow: var(--shadow-soft);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--text-dark);
            border: 1px solid var(--border-light);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--sage), #27ae60);
            color: var(--white);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: var(--white);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: var(--white);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        /* Categories Navigation */
        .categories-nav {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .categories-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-light);
        }

        .category-tab {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-gray);
            border-bottom: 3px solid transparent;
            flex: 1;
            text-align: center;
        }

        .category-tab:hover {
            background: var(--cream);
            color: var(--text-dark);
        }

        .category-tab.active {
            color: var(--curry);
            border-bottom-color: var(--curry);
            background: var(--cream);
        }

        .category-count {
            display: inline-block;
            background: var(--sage);
            color: var(--white);
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            margin-left: 0.5rem;
        }

        /* Settings Grid */
        .settings-section {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            text-transform: capitalize;
        }

        .settings-grid {
            padding: 1.5rem;
            display: grid;
            gap: 1.5rem;
        }

        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 1rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }

        .setting-item:hover {
            border-color: var(--curry);
            box-shadow: var(--shadow-soft);
        }

        .setting-info {
            flex: 1;
            margin-right: 1rem;
        }

        .setting-key {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .setting-description {
            font-size: 0.9rem;
            color: var(--text-gray);
            margin-bottom: 0.5rem;
        }

        .setting-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.8rem;
            color: var(--text-gray);
        }

        .setting-type {
            background: var(--sage);
            color: var(--white);
            padding: 0.125rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            text-transform: uppercase;
        }

        .setting-public {
            background: var(--curry);
            color: var(--white);
            padding: 0.125rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
        }

        .setting-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 200px;
        }

        .setting-input {
            flex: 1;
            padding: 0.5rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 0.9rem;
        }

        .setting-input:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 2px rgba(207, 114, 58, 0.1);
        }

        .setting-textarea {
            min-height: 80px;
            resize: vertical;
        }

        .setting-checkbox {
            transform: scale(1.2);
            accent-color: var(--curry);
        }

        .setting-actions {
            display: flex;
            gap: 0.25rem;
        }

        /* Form Styles */
        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 1rem;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .form-control {
            padding: 0.75rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-medium);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-gray);
            transition: var(--transition);
        }

        .modal-close:hover {
            color: var(--text-dark);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border-light);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 3000;
        }

        .toast {
            background: var(--white);
            border-left: 4px solid var(--curry);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-medium);
            padding: 1rem;
            margin-bottom: 0.5rem;
            min-width: 300px;
            transform: translateX(100%);
            transition: var(--transition);
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            border-left-color: #27ae60;
        }

        .toast.error {
            border-left-color: #e74c3c;
        }

        /* Quick Stats */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            text-align: center;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-sm);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--white);
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                width: 260px;
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .categories-tabs {
                flex-direction: column;
            }

            .setting-item {
                flex-direction: column;
                align-items: stretch;
            }

            .setting-info {
                margin-right: 0;
                margin-bottom: 1rem;
            }

            .setting-control {
                min-width: auto;
            }

            .mobile-menu-btn {
                display: block !important;
            }
        }

        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: var(--curry);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 50%;
            box-shadow: var(--shadow-medium);
            cursor: pointer;
        }

        /* Utilities */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .d-none { display: none; }
        .d-block { display: block; }
    </style>
</head>
<body>
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <div class="admin-layout">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <img src="../assets/image/LOGO_White Trans.png" 
                         alt="Krua Thai Logo" 
                         class="logo-image"
                         loading="lazy">
                </div>
                <div class="sidebar-title">Krua Thai</div>
                <div class="sidebar-subtitle">Admin Panel</div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="dashboard.php" class="nav-item">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="orders.php" class="nav-item">
                        <i class="nav-icon fas fa-shopping-cart"></i>
                        <span>Orders</span>
                    </a>
                    <a href="subscriptions.php" class="nav-item">
                        <i class="nav-icon fas fa-calendar-alt"></i>
                        <span>Subscriptions</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Content</div>
                    <a href="menus.php" class="nav-item">
                        <i class="nav-icon fas fa-utensils"></i>
                        <span>Menus</span>
                    </a>
                    <a href="categories.php" class="nav-item">
                        <i class="nav-icon fas fa-tags"></i>
                        <span>Categories</span>
                    </a>
                    <a href="inventory.php" class="nav-item">
                        <i class="nav-icon fas fa-boxes"></i>
                        <span>Inventory</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Customer Service</div>
                    <a href="complaints.php" class="nav-item">
                        <i class="nav-icon fas fa-exclamation-triangle"></i>
                        <span>Complaints</span>
                    </a>
                    <a href="reviews.php" class="nav-item">
                        <i class="nav-icon fas fa-star"></i>
                        <span>Reviews</span>
                    </a>
                    <a href="users.php" class="nav-item">
                        <i class="nav-icon fas fa-users"></i>
                        <span>Users</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Financial</div>
                    <a href="payments.php" class="nav-item">
                        <i class="nav-icon fas fa-credit-card"></i>
                        <span>Payments</span>
                    </a>
                    <a href="reports.php" class="nav-item">
                        <i class="nav-icon fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="settings.php" class="nav-item active">
                        <i class="nav-icon fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <a href="../logout.php" class="nav-item">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="header-content">
                    <div>
                        <h1 class="page-title">System Settings</h1>
                        <p class="page-subtitle">Configure system preferences and application settings</p>
                    </div>
                    <div class="header-actions">
                        <button type="button" class="btn btn-warning" onclick="backupSettings()">
                            <i class="fas fa-download"></i>
                            Backup Settings
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="openRestoreModal()">
                            <i class="fas fa-upload"></i>
                            Restore Settings
                        </button>
                        <button type="button" class="btn btn-primary" onclick="openAddSettingModal()">
                            <i class="fas fa-plus"></i>
                            Add Setting
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="quick-stats">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--curry), #e67e22);">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="stat-value"><?php echo count($allSettings); ?></div>
                    <div class="stat-label">Total Settings</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--sage), #27ae60);">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="stat-value"><?php echo count(array_filter($allSettings, function($s) { return $s['is_public']; })); ?></div>
                    <div class="stat-label">Public Settings</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="stat-value"><?php echo count($categories); ?></div>
                    <div class="stat-label">Categories</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-value"><?php echo count(array_filter($allSettings, function($s) { return !$s['is_public']; })); ?></div>
                    <div class="stat-label">Private Settings</div>
                </div>
            </div>

            <!-- Categories Navigation -->
            <div class="categories-nav">
                <div class="categories-tabs">
                    <button class="category-tab active" onclick="showCategory('all')">
                        <i class="fas fa-th-large"></i>
                        All Settings
                        <span class="category-count"><?php echo count($allSettings); ?></span>
                    </button>
                    <?php foreach ($settingsByCategory as $category => $settings): ?>
                        <button class="category-tab" onclick="showCategory('<?php echo $category; ?>')">
                            <i class="fas fa-<?php 
                                switch($category) {
                                    case 'general': echo 'cog'; break;
                                    case 'business': echo 'store'; break;
                                    case 'payment': echo 'credit-card'; break;
                                    case 'notification': echo 'bell'; break;
                                    case 'seo': echo 'search'; break;
                                    case 'security': echo 'shield-alt'; break;
                                    default: echo 'folder';
                                }
                            ?>"></i>
                            <?php echo ucfirst($category); ?>
                            <span class="category-count"><?php echo count($settings); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Settings Sections -->
            <?php foreach ($settingsByCategory as $category => $settings): ?>
                <div class="settings-section category-section" id="category-<?php echo $category; ?>">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-<?php 
                                switch($category) {
                                    case 'general': echo 'cog'; break;
                                    case 'business': echo 'store'; break;
                                    case 'payment': echo 'credit-card'; break;
                                    case 'notification': echo 'bell'; break;
                                    case 'seo': echo 'search'; break;
                                    case 'security': echo 'shield-alt'; break;
                                    default: echo 'folder';
                                }
                            ?>" style="margin-right: 0.5rem; color: var(--curry);"></i>
                            <?php echo ucfirst($category); ?> Settings
                        </h2>
                        <span class="category-count"><?php echo count($settings); ?> settings</span>
                    </div>
                    
                    <div class="settings-grid">
                        <?php foreach ($settings as $setting): ?>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <div class="setting-key"><?php echo htmlspecialchars($setting['setting_key']); ?></div>
                                    <div class="setting-description"><?php echo htmlspecialchars($setting['description']); ?></div>
                                    <div class="setting-meta">
                                        <span class="setting-type"><?php echo $setting['setting_type']; ?></span>
                                        <?php if ($setting['is_public']): ?>
                                            <span class="setting-public">Public</span>
                                        <?php endif; ?>
                                        <span>Updated: <?php echo date('M d, Y', strtotime($setting['updated_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <div class="setting-control">
                                    <?php if ($setting['setting_type'] === 'boolean'): ?>
                                        <input type="checkbox" 
                                               class="setting-checkbox"
                                               <?php echo $setting['setting_value'] ? 'checked' : ''; ?>
                                               onchange="updateSetting('<?php echo $setting['setting_key']; ?>', this.checked ? '1' : '0', 'boolean')">
                                    <?php elseif ($setting['setting_type'] === 'json'): ?>
                                        <textarea class="setting-input setting-textarea"
                                                  placeholder="JSON value"
                                                  onchange="updateSetting('<?php echo $setting['setting_key']; ?>', this.value, 'json')"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                    <?php else: ?>
                                        <input type="<?php echo $setting['setting_type'] === 'number' ? 'number' : 'text'; ?>" 
                                               class="setting-input"
                                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                               placeholder="Enter value"
                                               onchange="updateSetting('<?php echo $setting['setting_key']; ?>', this.value, '<?php echo $setting['setting_type']; ?>')">
                                    <?php endif; ?>
                                    
                                    <div class="setting-actions">
                                        <button type="button" class="btn btn-sm btn-danger btn-icon" 
                                                onclick="deleteSetting('<?php echo $setting['setting_key']; ?>')" 
                                                title="Delete Setting">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Add Setting Modal -->
    <div id="addSettingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Setting</h2>
                <button type="button" class="modal-close" onclick="closeModal('addSettingModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addSettingForm">
                    <div class="form-group">
                        <label class="form-label">Setting Key *</label>
                        <input type="text" 
                               id="settingKey" 
                               name="key" 
                               class="form-control" 
                               placeholder="e.g., site_name, max_orders"
                               required>
                        <small style="color: var(--text-gray);">Use lowercase with underscores (snake_case)</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Setting Value *</label>
                        <textarea id="settingValue" 
                                  name="value" 
                                  class="form-control" 
                                  rows="3"
                                  placeholder="Enter the setting value"
                                  required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Data Type *</label>
                        <select id="settingType" name="type" class="form-control" required>
                            <option value="string">String</option>
                            <option value="number">Number</option>
                            <option value="boolean">Boolean</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select id="settingCategory" name="category" class="form-control" required>
                            <option value="general">General</option>
                            <option value="business">Business</option>
                            <option value="payment">Payment</option>
                            <option value="notification">Notification</option>
                            <option value="seo">SEO</option>
                            <option value="security">Security</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea id="settingDescription" 
                                  name="description" 
                                  class="form-control" 
                                  rows="2"
                                  placeholder="Describe what this setting controls"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" id="settingPublic" name="is_public">
                            <span>Make this setting public (visible to frontend)</span>
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addSettingModal')">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddSetting()">
                    <i class="fas fa-plus"></i>
                    Add Setting
                </button>
            </div>
        </div>
    </div>

    <!-- Restore Settings Modal -->
    <div id="restoreModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Restore Settings</h2>
                <button type="button" class="modal-close" onclick="closeModal('restoreModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 1rem; padding: 1rem; background: rgba(231, 76, 60, 0.1); border-radius: var(--radius-sm); border-left: 3px solid #e74c3c;">
                    <strong>⚠️ Warning:</strong> This will replace ALL current settings with the backup data. This action cannot be undone.
                </div>
                <div class="form-group">
                    <label class="form-label">Select Backup File</label>
                    <input type="file" 
                           id="backupFile" 
                           class="form-control" 
                           accept=".json"
                           onchange="handleBackupFile(this)">
                    <small style="color: var(--text-gray);">Select a JSON backup file exported from this system</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Backup Content Preview</label>
                    <textarea id="backupPreview" 
                              class="form-control" 
                              rows="10"
                              readonly
                              placeholder="Backup content will appear here..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('restoreModal')">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitRestore()" id="restoreBtn" disabled>
                    <i class="fas fa-upload"></i>
                    Restore Settings
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        let currentBackupData = null;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            showCategory('all');
        });

        // Show/hide categories
        function showCategory(category) {
            // Update active tab
            document.querySelectorAll('.category-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Show/hide sections
            document.querySelectorAll('.category-section').forEach(section => {
                if (category === 'all') {
                    section.style.display = 'block';
                } else {
                    section.style.display = section.id === `category-${category}` ? 'block' : 'none';
                }
            });
        }

        // Update setting
        function updateSetting(key, value, type) {
            fetch('settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_setting&key=${encodeURIComponent(key)}&value=${encodeURIComponent(value)}&type=${type}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Setting updated successfully', 'success');
                } else {
                    showToast(data.message || 'Error updating setting', 'error');
                    // Reload page to reset the form
                    setTimeout(() => location.reload(), 1500);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error updating setting', 'error');
                setTimeout(() => location.reload(), 1500);
            });
        }

        // Delete setting
        function deleteSetting(key) {
            if (!confirm(`Are you sure you want to delete the setting "${key}"? This action cannot be undone.`)) {
                return;
            }
            
            fetch('settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_setting&key=${encodeURIComponent(key)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Setting deleted successfully', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message || 'Error deleting setting', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error deleting setting', 'error');
            });
        }

        // Open add setting modal
        function openAddSettingModal() {
            document.getElementById('addSettingForm').reset();
            openModal('addSettingModal');
        }

        // Submit add setting
        function submitAddSetting() {
            const form = document.getElementById('addSettingForm');
            const formData = new FormData(form);
            formData.append('action', 'add_setting');
            
            // Basic validation
            const key = formData.get('key');
            const value = formData.get('value');
            const type = formData.get('type');
            
            if (!key || !value || !type) {
                showToast('Please fill in all required fields', 'error');
                return;
            }
            
            // Validate key format
            if (!/^[a-z][a-z0-9_]*$/.test(key)) {
                showToast('Setting key must use lowercase letters, numbers, and underscores only', 'error');
                return;
            }
            
            fetch('settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Setting added successfully', 'success');
                    closeModal('addSettingModal');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message || 'Error adding setting', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error adding setting', 'error');
            });
        }

        // Backup settings
        function backupSettings() {
            const link = document.createElement('a');
            link.href = 'settings.php?action=backup_settings';
            link.download = 'krua_thai_settings_backup_' + new Date().toISOString().split('T')[0] + '.json';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showToast('Settings backup downloaded', 'success');
        }

        // Open restore modal
        function openRestoreModal() {
            document.getElementById('backupFile').value = '';
            document.getElementById('backupPreview').value = '';
            document.getElementById('restoreBtn').disabled = true;
            currentBackupData = null;
            openModal('restoreModal');
        }

        // Handle backup file selection
        function handleBackupFile(input) {
            const file = input.files[0];
            if (!file) {
                document.getElementById('backupPreview').value = '';
                document.getElementById('restoreBtn').disabled = true;
                currentBackupData = null;
                return;
            }
            
            if (file.type !== 'application/json') {
                showToast('Please select a valid JSON file', 'error');
                input.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const content = e.target.result;
                    const backup = JSON.parse(content);
                    
                    if (!backup.settings || !Array.isArray(backup.settings)) {
                        throw new Error('Invalid backup format');
                    }
                    
                    document.getElementById('backupPreview').value = JSON.stringify(backup, null, 2);
                    document.getElementById('restoreBtn').disabled = false;
                    currentBackupData = content;
                    
                    showToast(`Backup loaded: ${backup.settings.length} settings found`, 'success');
                    
                } catch (error) {
                    showToast('Invalid backup file format', 'error');
                    input.value = '';
                    document.getElementById('backupPreview').value = '';
                    document.getElementById('restoreBtn').disabled = true;
                    currentBackupData = null;
                }
            };
            reader.readAsText(file);
        }

        // Submit restore
        function submitRestore() {
            if (!currentBackupData) {
                showToast('Please select a backup file first', 'error');
                return;
            }
            
            if (!confirm('Are you sure you want to restore these settings? This will replace ALL current settings and cannot be undone.')) {
                return;
            }
            
            fetch('settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=restore_settings&backup_data=${encodeURIComponent(currentBackupData)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Settings restored successfully', 'success');
                    closeModal('restoreModal');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message || 'Error restoring settings', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error restoring settings', 'error');
            });
        }

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Toast notifications
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.getElementById('toastContainer').appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 100);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Toggle sidebar for mobile
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        // Close modal on outside click
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(modal => {
                    modal.classList.remove('show');
                });
            }
            
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openAddSettingModal();
            }
            
            if (e.ctrlKey && e.key === 'b') {
                e.preventDefault();
                backupSettings();
            }
        });

        console.log('Krua Thai Settings Management initialized successfully');
    </script>
</body>
</html>