<?php
// subscription-status.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Complaint Submission Function ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complain') {
    $subscription_id = $_POST['subscription_id'];
    $category = $_POST['category'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $priority = $_POST['priority'] ?? 'medium';
    
    // Generate unique complaint ID and number
    $complaint_id = bin2hex(random_bytes(16));
    $complaint_number = 'CMP-' . date('Ymd') . '-' . strtoupper(substr(md5($complaint_id), 0, 4));
    
    try {
        // Insert complaint with subscription_id (column was renamed from order_id)
        $stmt = $pdo->prepare("
            INSERT INTO complaints (
                id, complaint_number, user_id, subscription_id, category, 
                priority, title, description, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $result = $stmt->execute([
            $complaint_id, 
            $complaint_number, 
            $user_id, 
            $subscription_id, 
            $category, 
            $priority, 
            $title, 
            $description
        ]);
        
        if ($result) {
            $_SESSION['flash_message'] = "Complaint submitted successfully. Reference: " . $complaint_number;
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = "Failed to submit complaint. Please try again.";
            $_SESSION['flash_type'] = 'error';
        }
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = "Error submitting complaint: " . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
    
    header("Location: subscription-status.php");
    exit();
}

// --- Status Update Function (Remove pause, keep cancel and renew) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id = $_POST['id'];
    $action = $_POST['action'];
    $status_map = [
        'cancel' => 'cancelled',
        'renew' => 'active'
    ];
    if (isset($status_map[$action])) {
        $new_status = $status_map[$action];
        $stmt = $pdo->prepare("UPDATE subscriptions SET status = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$new_status, $id, $user_id]);
        header("Location: subscription-status.php");
        exit();
    }
}

// --- Fetch Subscription Data ---
$stmt = $pdo->prepare("
    SELECT s.*, sp.name AS plan_name, sp.meals_per_week, sp.final_price, sp.plan_type
    FROM subscriptions s 
    JOIN subscription_plans sp ON s.plan_id = sp.id
    WHERE s.user_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$user_id]);
$subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch selected menus for each subscription ---
$subscription_menus = [];
foreach ($subs as $sub) {
    $stmt = $pdo->prepare("
        SELECT sm.*, m.name as menu_name, m.name_thai, m.base_price, m.main_image_url, 
               m.calories_per_serving, m.protein_g, m.carbs_g, m.fat_g,
               mc.name as category_name
        FROM subscription_menus sm
        JOIN menus m ON sm.menu_id = m.id
        LEFT JOIN menu_categories mc ON m.category_id = mc.id
        WHERE sm.subscription_id = ?
        ORDER BY sm.delivery_date, sm.created_at
    ");
    $stmt->execute([$sub['id']]);
    $subscription_menus[$sub['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStatusText($status) {
    $map = [
        'active' => 'Active',
        'paused' => 'Paused',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
        'pending_payment' => 'Pending Payment'
    ];
    return $map[$status] ?? $status;
}

function getDayName($day) {
    $days = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday'
    ];
    return $days[$day] ?? $day;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subscription Status - Somdul Table</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Manage your Thai meal subscriptions with Somdul Table">
    
    <!-- BaticaSans Font Import -->
    <link rel="preconnect" href="https://ydpschool.com">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
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
            --shadow-large: 0 16px 48px rgba(189, 147, 121, 0.35);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #3498db;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--cream) 0%, #f8f9fa 100%);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
            font-weight: 400;
        }

        /* Header */
        .header {
            background: var(--white);
            box-shadow: var(--shadow-soft);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            text-decoration: none;
            color: inherit;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--curry), var(--brown));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.5rem;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--curry);
            font-family: 'BaticaSans', sans-serif;
        }

        .header-nav {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .nav-link {
            text-decoration: none;
            color: var(--text-gray);
            font-weight: 500;
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-sm);
        }

        .nav-link:hover {
            background: var(--cream);
            color: var(--curry);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 2rem 4rem;
        }

        /* Progress Bar */
        .progress-container {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 3rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
        }

        .progress-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .progress-step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            border-radius: var(--radius-xl);
            font-weight: 600;
            font-size: 0.95rem;
            font-family: 'BaticaSans', sans-serif;
            background: var(--cream);
            color: var(--text-gray);
            border: 2px solid var(--cream);
            transition: var(--transition);
            white-space: nowrap;
        }

        .progress-step.completed {
            background: var(--success);
            color: var(--white);
            border-color: var(--success);
        }

        .progress-arrow {
            color: var(--sage);
            font-size: 1.2rem;
            font-weight: 600;
        }

        /* Page Title */
        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            font-family: 'BaticaSans', sans-serif;
            text-align: center;
            margin-bottom: 2rem;
            color: var(--text-dark);
        }

        .page-title i {
            color: var(--curry);
            margin-right: 0.5rem;
        }

        /* Flash Messages */
        .flash-message {
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border: 2px solid;
        }

        .flash-message i {
            font-size: 1.2rem;
        }

        .flash-message.success {
            background: linear-gradient(135deg, rgba(39, 174, 96, 0.1), rgba(39, 174, 96, 0.05));
            border-color: var(--success);
            color: var(--success);
        }

        .flash-message.error {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.1), rgba(231, 76, 60, 0.05));
            border-color: var(--danger);
            color: var(--danger);
        }

        /* Main Content Card */
        .main-card {
            background: var(--white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-medium);
            overflow: hidden;
            position: relative;
            border: 1px solid var(--border-light);
        }

        .main-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--curry), var(--brown), var(--sage));
        }

        .card-header {
            padding: 2rem;
            border-bottom: 1px solid var(--border-light);
            background: linear-gradient(135deg, rgba(207, 114, 58, 0.05), rgba(189, 147, 121, 0.05));
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            font-family: 'BaticaSans', sans-serif;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-title i {
            color: var(--curry);
        }

        /* Table */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'BaticaSans', sans-serif;
        }

        th, td {
            padding: 1.2rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }

        th {
            background: var(--cream);
            color: var(--text-dark);
            font-weight: 700;
            font-size: 0.9rem;
            font-family: 'BaticaSans', sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            color: var(--text-dark);
            font-weight: 500;
            font-family: 'BaticaSans', sans-serif;
        }

        tbody tr:hover {
            background: rgba(207, 114, 58, 0.02);
        }

        /* Status Badges */
        .status {
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-xl);
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status.active {
            background: linear-gradient(135deg, rgba(39, 174, 96, 0.1), rgba(39, 174, 96, 0.05));
            color: var(--success);
            border: 1px solid var(--success);
        }

        .status.paused {
            background: linear-gradient(135deg, rgba(243, 156, 18, 0.1), rgba(243, 156, 18, 0.05));
            color: var(--warning);
            border: 1px solid var(--warning);
        }

        .status.cancelled {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.1), rgba(231, 76, 60, 0.05));
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        .status.expired {
            background: linear-gradient(135deg, rgba(127, 140, 141, 0.1), rgba(127, 140, 141, 0.05));
            color: var(--text-gray);
            border: 1px solid var(--text-gray);
        }

        .status.pending_payment {
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(52, 152, 219, 0.05));
            color: var(--info);
            border: 1px solid var(--info);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 200px;
        }

        .btn-action {
            background: var(--white);
            border: 1px solid var(--border-light);
            padding: 0.7rem 1.2rem;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            font-weight: 500;
            font-family: 'BaticaSans', sans-serif;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-align: center;
            width: 100%;
            color: var(--text-dark);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .btn-action:hover {
            background: var(--cream);
            border-color: var(--sage);
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-view {
            border-color: var(--sage);
            color: var(--sage);
        }

        .btn-view:hover {
            background: var(--sage);
            color: var(--white);
            border-color: var(--sage);
        }

        .btn-complain {
            border-color: var(--warning);
            color: var(--warning);
        }

        .btn-complain:hover {
            background: var(--warning);
            color: var(--white);
            border-color: var(--warning);
        }

        .btn-cancel {
            border-color: var(--brown);
            color: var(--brown);
        }

        .btn-cancel:hover {
            background: var(--brown);
            color: var(--white);
            border-color: var(--brown);
        }

        .btn-renew {
            border-color: var(--sage);
            color: var(--sage);
        }

        .btn-renew:hover {
            background: var(--sage);
            color: var(--white);
            border-color: var(--sage);
        }

        .btn-disabled {
            background: var(--cream);
            border-color: var(--border-light);
            color: var(--text-gray);
            cursor: not-allowed;
        }

        .btn-action:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .btn-action:disabled:hover {
            transform: none;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            background: var(--cream);
            border-color: var(--border-light);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--radius-xl);
            padding: 0;
            width: 90%;
            max-width: 800px;
            max-height: 85vh;
            overflow: hidden;
            box-shadow: var(--shadow-large);
            position: relative;
            transform: scale(0.8);
            opacity: 0;
            transition: var(--transition);
            border: 1px solid var(--border-light);
        }

        .modal.show .modal-content {
            transform: scale(1);
            opacity: 1;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 700;
            font-family: 'BaticaSans', sans-serif;
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--white);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: var(--transition);
        }

        .modal-close:hover {
            background: rgba(255,255,255,0.2);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 2rem;
            max-height: 600px;
            overflow-y: auto;
            font-family: 'BaticaSans', sans-serif;
        }

        /* Complaint Form */
        .complaint-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .form-control {
            padding: 0.8rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            font-family: 'BaticaSans', sans-serif;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 2px rgba(207, 114, 58, 0.1);
        }

        .form-control-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1rem;
        }

        .btn-submit {
            background: var(--curry);
            color: var(--white);
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: var(--radius-md);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-submit:hover {
            background: var(--brown);
            transform: translateY(-1px);
        }

        .btn-cancel-form {
            background: var(--cream);
            color: var(--text-dark);
            border: 1px solid var(--border-light);
            padding: 0.8rem 1.5rem;
            border-radius: var(--radius-md);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-cancel-form:hover {
            background: var(--border-light);
        }

        /* Details Section */
        .detail-section {
            margin-bottom: 2rem;
        }

        .detail-title {
            font-size: 1.1rem;
            font-weight: 700;
            font-family: 'BaticaSans', sans-serif;
            color: var(--curry);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .detail-item {
            background: var(--cream);
            padding: 1rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-light);
        }

        .detail-label {
            font-size: 0.85rem;
            color: var(--text-gray);
            margin-bottom: 0.3rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
        }

        .detail-value {
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            color: var(--text-dark);
        }

        .delivery-days-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .delivery-day-tag {
            background: var(--curry);
            color: var(--white);
            padding: 0.3rem 0.8rem;
            border-radius: var(--radius-lg);
            font-size: 0.8rem;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
        }

        /* Menu Cards */
        .menus-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .menu-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            transition: var(--transition);
        }

        .menu-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .menu-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            background: linear-gradient(135deg, var(--cream), var(--sage));
        }

        .menu-content {
            padding: 1rem;
        }

        .menu-name {
            font-weight: 700;
            font-family: 'BaticaSans', sans-serif;
            color: var(--text-dark);
            margin-bottom: 0.3rem;
            font-size: 1rem;
        }

        .menu-name-thai {
            color: var(--text-gray);
            font-size: 0.9rem;
            font-family: 'BaticaSans', sans-serif;
            margin-bottom: 0.5rem;
        }

        .menu-category {
            background: var(--sage);
            color: var(--white);
            padding: 0.2rem 0.6rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            display: inline-block;
            margin-bottom: 0.5rem;
        }

        .menu-nutrition {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: var(--text-gray);
            margin-bottom: 0.5rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .menu-price {
            font-weight: 700;
            font-family: 'BaticaSans', sans-serif;
            color: var(--curry);
            font-size: 1.1rem;
        }

        .menu-day {
            background: var(--curry);
            color: var(--white);
            padding: 0.3rem 0.8rem;
            border-radius: var(--radius-lg);
            font-size: 0.8rem;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            position: absolute;
            top: 0.5rem;
            left: 0.5rem;
        }

        .menu-card {
            position: relative;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-gray);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.3;
            color: var(--sage);
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .empty-state .btn {
            background: var(--white);
            color: var(--curry);
            border: 2px solid var(--curry);
            padding: 1rem 2rem;
            border-radius: var(--radius-lg);
            text-decoration: none;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .empty-state .btn:hover {
            background: var(--curry);
            color: var(--white);
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(207, 114, 58, 0.2);
        }

        /* Navigation */
        .bottom-nav {
            padding: 2rem;
            border-top: 1px solid var(--border-light);
            background: var(--cream);
        }

        .back-link {
            color: var(--curry);
            text-decoration: none;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }

        .back-link:hover {
            color: var(--brown);
            transform: translateX(-3px);
        }

        /* Price Display */
        .price {
            font-weight: 700;
            font-family: 'BaticaSans', sans-serif;
            color: var(--curry);
            font-size: 1.1rem;
        }

        /* Plan Name */
        .plan-name {
            font-weight: 700;
            font-family: 'BaticaSans', sans-serif;
            color: var(--text-dark);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 1rem 1rem 3rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .table-container {
                font-size: 0.9rem;
            }

            th, td {
                padding: 0.8rem 0.5rem;
            }

            .action-buttons {
                flex-direction: row;
                justify-content: flex-start;
                flex-wrap: wrap;
            }

            .btn-action {
                padding: 0.5rem 0.8rem;
                font-size: 0.75rem;
                margin-bottom: 0.3rem;
                min-width: auto;
                flex: 1;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

            .menus-grid {
                grid-template-columns: 1fr;
            }

            .progress-bar {
                gap: 0.5rem;
            }

            .progress-step {
                font-size: 0.8rem;
                padding: 0.6rem 1rem;
            }

            .progress-arrow {
                font-size: 1rem;
            }

            .modal-content {
                width: 95%;
                max-height: 90vh;
            }
        }

        @media (max-width: 480px) {
            .header-container {
                padding: 1rem;
            }

            .logo-text {
                font-size: 1.5rem;
            }

            .progress-step {
                font-size: 0.7rem;
                padding: 0.5rem 0.8rem;
            }

            .table-container {
                font-size: 0.8rem;
            }

            th, td {
                padding: 0.6rem 0.3rem;
            }

            .card-header {
                padding: 1.5rem 1rem;
            }

            .bottom-nav {
                padding: 1.5rem 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <a href="home2.php" class="logo">
                <div class="logo-icon">S</div>
                <div class="logo-text">Somdul Table</div>
            </a>
            <nav class="header-nav">
                <a href="menus.php" class="nav-link">Menu</a>
                <a href="about.php" class="nav-link">About Us</a>
                <a href="contact.php" class="nav-link">Contact</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="nav-link">Dashboard</a>
                    <a href="logout.php" class="nav-link">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="nav-link">Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <div class="container">
        <!-- Progress Bar -->
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-step completed">
                    <i class="fas fa-check-circle"></i>
                    <span>Choose Plan</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step completed">
                    <i class="fas fa-check-circle"></i>
                    <span>Select Meals</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step completed">
                    <i class="fas fa-check-circle"></i>
                    <span>Payment</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step completed">
                    <i class="fas fa-check-double"></i>
                    <span>Complete</span>
                </div>
            </div>
        </div>

        <!-- Page Title -->
        <h1 class="page-title">
            <i class="fas fa-clipboard-list"></i>
            Order Status
        </h1>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="flash-message <?php echo $_SESSION['flash_type']; ?>">
                <i class="fas fa-<?php echo $_SESSION['flash_type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="main-card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-list-alt"></i>
                    Your Order Plans
                </h2>
            </div>

            <?php if (empty($subs)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Order Plans Yet</h3>
                    <p>Start your healthy eating journey with authentic Thai food</p>
                    <a href="subscribe.php" class="btn">
                        <i class="fas fa-plus"></i>
                        Order Your First Plan
                    </a>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fas fa-box"></i> Plan</th>
                                <th><i class="fas fa-utensils"></i> Meals</th>
                                <th><i class="fas fa-money-bill"></i> Price</th>
                                <th><i class="fas fa-info-circle"></i> Status</th>
                                <th><i class="fas fa-calendar-alt"></i> Delivery Date</th>
                                <th><i class="fas fa-cogs"></i> Manage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subs as $sub): ?>
                            <tr>
                                <td>
                                    <span class="plan-name"><?= htmlspecialchars($sub['plan_name']) ?></span>
                                </td>
                                <td><?= $sub['meals_per_week'] ?> meals/week</td>
                                <td>
                                    <span class="price">$<?= number_format($sub['final_price'], 2) ?></span>
                                    <span style="color: var(--text-gray); font-size: 0.9rem;">
                                        /<?= $sub['plan_type'] === 'weekly' ? 'week' : 'month' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status <?= $sub['status'] ?>"><?= getStatusText($sub['status']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($sub['start_date']) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- View Details Button -->
                                        <button onclick="viewDetails('<?= htmlspecialchars($sub['id']) ?>')" class="btn-action btn-view">
                                            <i class="fas fa-eye"></i> View Details
                                        </button>
                                        
                                        <!-- Complain Button -->
                                        <button onclick="openComplaintModal('<?= htmlspecialchars($sub['id']) ?>', '<?= htmlspecialchars($sub['plan_name']) ?>')" class="btn-action btn-complain">
                                            <i class="fas fa-exclamation-triangle"></i> Complain
                                        </button>
                                        
                                        <!-- Management Buttons -->
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($sub['id']) ?>">
                                            <?php if ($sub['status'] === 'active'): ?>
                                                <button name="action" value="cancel" class="btn-action btn-cancel" onclick="return confirm('Are you sure you want to cancel this order plan?')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            <?php elseif ($sub['status'] === 'cancelled'): ?>
                                                <button name="action" value="renew" class="btn-action btn-renew" onclick="return confirm('Do you want to renew this order plan?')">
                                                    <i class="fas fa-redo"></i> Renew
                                                </button>
                                            <?php else: ?>
                                                <button disabled class="btn-action btn-disabled">
                                                    <i class="fas fa-ban"></i> Cannot Manage
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="bottom-nav">
                <a href="dashboard.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Modal for Subscription Details -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-clipboard-list"></i>
                    Order Details
                </h3>
                <button class="modal-close" onclick="closeModal('detailsModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Modal for Complaint Form -->
    <div id="complaintModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Submit Complaint
                </h3>
                <button class="modal-close" onclick="closeModal('complaintModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" class="complaint-form">
                    <input type="hidden" name="action" value="complain">
                    <input type="hidden" name="subscription_id" id="complaint_subscription_id">
                    
                    <div class="form-group">
                        <label class="form-label">Order Plan</label>
                        <input type="text" id="complaint_plan_name" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Complaint Category *</label>
                        <select name="category" class="form-control" required>
                            <option value="">Select a category</option>
                            <option value="food_quality">Food Quality</option>
                            <option value="delivery_late">Late Delivery</option>
                            <option value="delivery_wrong">Wrong Delivery</option>
                            <option value="missing_items">Missing Items</option>
                            <option value="damaged_package">Damaged Package</option>
                            <option value="customer_service">Customer Service</option>
                            <option value="billing">Billing Issue</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Complaint Title *</label>
                        <input type="text" name="title" class="form-control" placeholder="Brief description of the issue" required maxlength="200">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Detailed Description *</label>
                        <textarea name="description" class="form-control form-control-textarea" placeholder="Please provide detailed information about your complaint..." required></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn-cancel-form" onclick="closeModal('complaintModal')">Cancel</button>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Submit Complaint
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Subscription data for modal
        const subscriptionData = <?= json_encode($subs) ?>;
        const menuData = <?= json_encode($subscription_menus) ?>;

        // Add animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate table rows
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    row.style.transition = 'all 0.6s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Modal close on backdrop click
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal(this.id);
                    }
                });
            });

            // Keyboard shortcut for modal
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal('detailsModal');
                    closeModal('complaintModal');
                }
            });
        });

        function openComplaintModal(subscriptionId, planName) {
            document.getElementById('complaint_subscription_id').value = subscriptionId;
            document.getElementById('complaint_plan_name').value = planName;
            document.getElementById('complaintModal').classList.add('show');
        }

        function viewDetails(subscriptionId) {
            const subscription = subscriptionData.find(sub => sub.id === subscriptionId);
            const menus = menuData[subscriptionId] || [];
            
            if (!subscription) return;

            const deliveryDays = JSON.parse(subscription.delivery_days || '[]');
            const dayNames = {
                'monday': 'Monday',
                'tuesday': 'Tuesday', 
                'wednesday': 'Wednesday',
                'thursday': 'Thursday',
                'friday': 'Friday',
                'saturday': 'Saturday',
                'sunday': 'Sunday'
            };

            const statusColors = {
                'active': '#27ae60',
                'paused': '#f39c12',
                'cancelled': '#e74c3c',
                'expired': '#636e72',
                'pending_payment': '#3498db'
            };

            // Group menus by day
            const menusByDay = {};
            menus.forEach(menu => {
                const deliveryDate = menu.delivery_date;
                if (!menusByDay[deliveryDate]) {
                    menusByDay[deliveryDate] = [];
                }
                menusByDay[deliveryDate].push(menu);
            });

            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = `
                <div class="detail-section">
                    <div class="detail-title">
                        <i class="fas fa-info-circle"></i>
                        Basic Information
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Plan Name</div>
                            <div class="detail-value">${subscription.plan_name}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Type</div>
                            <div class="detail-value">${subscription.plan_type === 'weekly' ? 'Weekly' : 'Monthly'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Meals per Week</div>
                            <div class="detail-value">${subscription.meals_per_week} meals</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Price</div>
                            <div class="detail-value" style="color: var(--curry); font-weight: 700;">
                                $${Number(subscription.final_price).toLocaleString()}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <div class="detail-title">
                        <i class="fas fa-calendar-alt"></i>
                        Duration and Delivery
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Delivery Date</div>
                            <div class="detail-value">${subscription.start_date}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Next Billing Date</div>
                            <div class="detail-value">${subscription.next_billing_date || '-'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Preferred Delivery Time</div>
                            <div class="detail-value">
                                ${subscription.preferred_delivery_time === 'morning' ? 'Morning (8:00-12:00)' :
                                  subscription.preferred_delivery_time === 'afternoon' ? 'Afternoon (12:00-16:00)' :
                                  subscription.preferred_delivery_time === 'evening' ? 'Evening (16:00-20:00)' : 'Flexible'}
                            </div>
                        </div>
                    </div>
                    
                    ${deliveryDays.length > 0 ? `
                    <div style="margin-top: 1rem;">
                        <div class="detail-label">Delivery Days</div>
                        <div class="delivery-days-list">
                            ${deliveryDays.map(day => `<span class="delivery-day-tag">${dayNames[day] || day}</span>`).join('')}
                        </div>
                    </div>
                    ` : ''}
                </div>

                <div class="detail-section">
                    <div class="detail-title">
                        <i class="fas fa-chart-line"></i>
                        Status and Settings
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Current Status</div>
                            <div class="detail-value">
                                <span style="color: ${statusColors[subscription.status] || '#636e72'}; font-weight: 700;">
                                    ${getStatusText(subscription.status)}
                                </span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Auto Renewal</div>
                            <div class="detail-value">${subscription.auto_renew == 1 ? '✅ Enabled' : '❌ Disabled'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Order Date</div>
                            <div class="detail-value">${subscription.created_at}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Last Updated</div>
                            <div class="detail-value">${subscription.updated_at}</div>
                        </div>
                    </div>
                </div>

                ${menus.length > 0 ? `
                <div class="detail-section">
                    <div class="detail-title">
                        <i class="fas fa-utensils"></i>
                        Selected Meals (${menus.length} items)
                    </div>
                    ${Object.keys(menusByDay).length > 0 ? 
                        Object.keys(menusByDay).sort().map(date => `
                            <div style="margin-bottom: 1.5rem;">
                                <h4 style="color: var(--curry); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-calendar-day"></i>
                                    Delivery Date: ${date}
                                </h4>
                                <div class="menus-grid" style="grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));">
                                    ${menusByDay[date].map(menu => `
                                        <div class="menu-card">
                                            ${menu.main_image_url ? 
                                                `<img src="${menu.main_image_url}" alt="${menu.menu_name}" class="menu-image" onerror="this.style.display='none'">` :
                                                `<div class="menu-image" style="display: flex; align-items: center; justify-content: center; color: var(--text-gray);">
                                                    <i class="fas fa-utensils" style="font-size: 2rem;"></i>
                                                </div>`
                                            }
                                            <div class="menu-content">
                                                <div class="menu-name">${menu.menu_name}</div>
                                                ${menu.name_thai ? `<div class="menu-name-thai">${menu.name_thai}</div>` : ''}
                                                ${menu.category_name ? `<div class="menu-category">${menu.category_name}</div>` : ''}
                                                
                                                <div class="menu-nutrition">
                                                    ${menu.calories_per_serving ? `<span><i class="fas fa-fire"></i> ${menu.calories_per_serving} cal</span>` : ''}
                                                    ${menu.protein_g ? `<span><i class="fas fa-drumstick-bite"></i> ${menu.protein_g}g</span>` : ''}
                                                </div>
                                                
                                                <div class="menu-price">$${Number(menu.base_price).toLocaleString()}</div>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `).join('') :
                        `<div style="text-align: center; padding: 2rem; color: var(--text-gray);">
                            <i class="fas fa-utensils" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                            <p>No meals selected yet</p>
                        </div>`
                    }
                </div>
                ` : ''}

                ${subscription.special_instructions ? `
                <div class="detail-section">
                    <div class="detail-title">
                        <i class="fas fa-sticky-note"></i>
                        Special Instructions
                    </div>
                    <div class="detail-item">
                        <div class="detail-value">${subscription.special_instructions}</div>
                    </div>
                </div>
                ` : ''}
            `;

            document.getElementById('detailsModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function getStatusText(status) {
            const statusMap = {
                'active': 'Active',
                'paused': 'Paused',
                'cancelled': 'Cancelled',
                'expired': 'Expired',
                'pending_payment': 'Pending Payment'
            };
            return statusMap[status] || status;
        }
    </script>
</body>
</html>