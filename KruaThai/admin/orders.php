<?php
/**
 * Krua Thai - Complete Working Admin Orders Management
 * File: admin/orders.php
 * Description: Fully functional with contact customer feature
 */

// --- 1. การตั้งค่าพื้นฐานและ Session ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
date_default_timezone_set('Asia/Bangkok');

// --- 2. เรียกใช้ไฟล์ที่จำเป็น ---
require_once '../config/database.php';
require_once '../includes/functions.php';

// --- 3. การป้องกัน CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- 4. ตรวจสอบสิทธิ์ Admin ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// --- 5. จัดการ AJAX Requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // ล้าง output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json; charset=utf-8');
    
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF Token.']);
        exit;
    }

    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        switch ($_POST['action']) {
            case 'update_status':
                $orderId = $_POST['order_id'] ?? null;
                $status = $_POST['status'] ?? null;
                $reason = $_POST['reason'] ?? '';
                
                $allowed = ['active', 'paused', 'cancelled'];
                if (!$orderId || !$status || !in_array($status, $allowed)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
                    exit;
                }

                $sql = "UPDATE subscriptions SET status = ?, updated_at = NOW()";
                $params = [$status];

                if ($status === 'cancelled') {
                    $sql .= ", cancellation_reason = ?, cancelled_at = NOW(), cancelled_by = ?";
                    array_push($params, $reason, $_SESSION['user_id']);
                }

                $sql .= " WHERE id = ?";
                $params[] = $orderId;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Order status updated successfully.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'No changes were made.']);
                }
                exit;

            case 'get_details':
                $orderId = $_POST['order_id'] ?? null;
                if (!$orderId) {
                    echo json_encode(['success' => false, 'message' => 'Order ID is missing.']);
                    exit;
                }
                
                // ดึงข้อมูลหลักของ Order (Subscription)
                $stmt = $pdo->prepare("
                    SELECT s.*, u.first_name, u.last_name, u.email, u.phone, 
                           u.delivery_address, u.city, u.zip_code,
                           sp.name_thai as plan_name, sp.meals_per_week, sp.plan_type
                    FROM subscriptions s
                    JOIN users u ON s.user_id = u.id
                    JOIN subscription_plans sp ON s.plan_id = sp.id
                    WHERE s.id = ?
                ");
                $stmt->execute([$orderId]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$order) {
                    echo json_encode(['success' => false, 'message' => 'Order not found.']);
                    exit;
                }

                // ดึงรายการเมนูที่ลูกค้าเลือกไว้
                $stmt = $pdo->prepare("
                    SELECT sm.delivery_date, m.name_thai as menu_name, m.name as menu_name_en, 
                           sm.quantity, sm.status as menu_status, m.base_price
                    FROM subscription_menus sm
                    JOIN menus m ON sm.menu_id = m.id
                    WHERE sm.subscription_id = ?
                    ORDER BY sm.delivery_date ASC
                    LIMIT 20
                ");
                $stmt->execute([$orderId]);
                $order['menus'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // ดึงข้อมูลการชำระเงิน
                $stmt = $pdo->prepare("
                    SELECT payment_method, transaction_id, amount, status, 
                           payment_date, created_at, currency
                    FROM payments 
                    WHERE subscription_id = ? 
                    ORDER BY created_at DESC
                    LIMIT 5
                ");
                $stmt->execute([$orderId]);
                $order['payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'data' => $order]);
                exit;

            case 'export_orders':
                $format = $_POST['format'] ?? 'csv';
                $status_filter = $_POST['status_filter'] ?? '';
                $search = $_POST['search'] ?? '';
                
                $whereConditions = [];
                $params = [];
                
                if ($status_filter) { 
                    $whereConditions[] = "s.status = ?"; 
                    $params[] = $status_filter; 
                }
                if ($search) {
                    $whereConditions[] = "(s.id LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
                    $searchTerm = "%$search%";
                    array_push($params, $searchTerm, $searchTerm, $searchTerm);
                }
                
                $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

                $sql = "
                    SELECT s.id, s.status, s.start_date, s.next_billing_date, s.total_amount,
                           CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                           u.email, u.phone, u.delivery_address, u.city,
                           sp.name_thai as plan_name, sp.meals_per_week,
                           s.created_at, s.updated_at
                    FROM subscriptions s
                    JOIN users u ON s.user_id = u.id
                    JOIN subscription_plans sp ON s.plan_id = sp.id
                    $whereClause
                    ORDER BY s.created_at DESC
                    LIMIT 500
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($format === 'csv') {
                    $filename = 'orders_export_' . date('Y-m-d_H-i-s') . '.csv';
                    $data = [['Order ID', 'Customer Name', 'Email', 'Phone', 'Plan', 'Status', 'Start Date', 'Next Bill', 'Amount', 'Address', 'Created At']];
                    
                    foreach ($orders as $order) {
                        $data[] = [
                            substr($order['id'], 0, 8) . '...',
                            $order['customer_name'],
                            $order['email'],
                            $order['phone'] ?? 'N/A',
                            $order['plan_name'],
                            $order['status'],
                            $order['start_date'],
                            $order['next_billing_date'],
                            number_format($order['total_amount'], 0),
                            ($order['delivery_address'] ?? '') . ', ' . ($order['city'] ?? ''),
                            $order['created_at']
                        ];
                    }
                    
                    echo json_encode([
                        'success' => true, 
                        'data' => $data, 
                        'filename' => $filename,
                        'count' => count($orders)
                    ]);
                } else {
                    $filename = 'orders_export_' . date('Y-m-d_H-i-s') . '.json';
                    echo json_encode([
                        'success' => true, 
                        'data' => $orders, 
                        'filename' => $filename,
                        'count' => count($orders)
                    ]);
                }
                exit;

            case 'send_email':
                $orderId = $_POST['order_id'] ?? null;
                $subject = $_POST['subject'] ?? '';
                $message = $_POST['message'] ?? '';
                
                if (!$orderId || !$subject || !$message) {
                    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
                    exit;
                }
                
                // ดึงข้อมูลลูกค้า
                $stmt = $pdo->prepare("
                    SELECT u.email, u.first_name, u.last_name, s.id as subscription_id
                    FROM subscriptions s
                    JOIN users u ON s.user_id = u.id
                    WHERE s.id = ?
                ");
                $stmt->execute([$orderId]);
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$customer) {
                    echo json_encode(['success' => false, 'message' => 'Customer not found.']);
                    exit;
                }
                
                // ส่งอีเมลจริง (ตัวอย่าง - ใช้ mail function หรือ PHPMailer)
                $to = $customer['email'];
                $emailSubject = "[Krua Thai] " . $subject;
                $emailBody = "
                    <html>
                    <head><title>$subject</title></head>
                    <body>
                        <h2>สวัสดี คุณ{$customer['first_name']} {$customer['last_name']}</h2>
                        <p>$message</p>
                        <br>
                        <p>ขอบคุณที่ใช้บริการ Krua Thai</p>
                        <p>ทีมงาน Krua Thai</p>
                    </body>
                    </html>
                ";
                
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: noreply@kruathai.com" . "\r\n";
                
                // ส่งอีเมล (ในตัวอย่างนี้จะ simulate การส่ง)
                $emailSent = mail($to, $emailSubject, $emailBody, $headers);
                
                if ($emailSent) {
                    echo json_encode(['success' => true, 'message' => 'Email sent successfully to ' . $to]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to send email.']);
                }
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}

// --- 6. ดึงข้อมูลสำหรับแสดงผลบนหน้าเว็บ ---
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $status_filter = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    // สร้าง WHERE clause
    $whereConditions = [];
    $params = [];
    $countParams = [];
    
    if ($status_filter) { 
        $whereConditions[] = "s.status = ?"; 
        $params[] = $status_filter;
        $countParams[] = $status_filter;
    }
    if ($search) {
        $whereConditions[] = "(s.id LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
        $searchTerm = "%$search%";
        array_push($params, $searchTerm, $searchTerm, $searchTerm);
        array_push($countParams, $searchTerm, $searchTerm, $searchTerm);
    }
    
    $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

    // นับจำนวนรายการทั้งหมด
    $countSql = "
        SELECT COUNT(*) as total
        FROM subscriptions s
        JOIN users u ON s.user_id = u.id
        JOIN subscription_plans sp ON s.plan_id = sp.id
        $whereClause
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $totalItems = $countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $limit);

    // ดึงข้อมูลออเดอร์
    $sql = "
        SELECT s.id, s.status, s.start_date, s.next_billing_date, s.total_amount,
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.email, u.phone,
               sp.name_thai as plan_name, sp.meals_per_week,
               s.created_at, s.updated_at
        FROM subscriptions s
        JOIN users u ON s.user_id = u.id
        JOIN subscription_plans sp ON s.plan_id = sp.id
        $whereClause
        ORDER BY s.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ดึงสถิติเร็วๆ
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_orders,
            SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused_orders,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_orders,
            AVG(total_amount) as avg_order_value
        FROM subscriptions
    ");
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $page_error = "เกิดข้อผิดพลาดในการดึงข้อมูลออเดอร์: " . $e->getMessage();
    $orders = [];
    $stats = ['total_orders' => 0, 'active_orders' => 0, 'paused_orders' => 0, 'cancelled_orders' => 0, 'today_orders' => 0, 'avg_order_value' => 0];
    $totalItems = 0;
    $totalPages = 0;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - Krua Thai Admin</title>
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

        .logo {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .logo-image {
            max-width: 80px;
            max-height: 80px;
            width: auto;
            height: auto;
            object-fit: contain;
            filter: brightness(1.1) contrast(1.2);
            transition: transform 0.3s ease;
        }

        .logo-image:hover {
            transform: scale(1.05);
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

        .nav-section {
            margin-bottom: 1.5rem;
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
            cursor: pointer;
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

        /* Header */
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
            margin-bottom: 1rem;
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
            position: relative;
            overflow: hidden;
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
            box-shadow: var(--shadow-soft);
        }

        .btn-secondary:hover {
            background: var(--cream);
            transform: translateY(-1px);
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

        .btn-info {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: var(--white);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--curry), var(--brown));
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--white);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        /* Filter Section */
        .filter-section {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
        }

        .filter-form {
            display: grid;
            grid-template-columns: 2fr 1fr auto auto;
            gap: 1rem;
            align-items: flex-end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .form-control {
            width: 100%;
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

        /* Table Container */
        .table-container {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            border: 1px solid var(--border-light);
        }

        .table-header {
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }

        .table th {
            background: var(--cream);
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody tr {
            transition: var(--transition);
        }

        .table tbody tr:hover {
            background: #fafafa;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            color: var(--white);
            font-size: 0.9rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-btn:hover {
            transform: scale(1.1);
        }

        .action-btn.view {
            background: var(--curry);
        }

        .action-btn.contact {
            background: #3498db;
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            background: var(--white);
            border-top: 1px solid var(--border-light);
        }

        .pagination-info {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .pagination {
            display: flex;
            gap: 0.5rem;
        }

        .pagination .page-btn {
            width: 36px;
            height: 36px;
            border: 1px solid var(--border-light);
            background: var(--white);
            color: var(--text-dark);
            border-radius: var(--radius-sm);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            text-decoration: none;
        }

        .pagination .page-btn:hover {
            background: var(--curry);
            color: var(--white);
            border-color: var(--curry);
        }

        .pagination .page-btn.active {
            background: var(--curry);
            color: var(--white);
            border-color: var(--curry);
        }

        .pagination .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }

        .modal {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-medium);
            max-width: 800px;
            width: 90vw;
            max-height: 80vh;
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

        /* Detail sections */
        .detail-section {
            margin-bottom: 2rem;
        }

        .detail-section h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--curry);
            border-bottom: 2px solid var(--cream);
            padding-bottom: 0.5rem;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .detail-value {
            color: var(--text-gray);
            font-size: 0.95rem;
        }

        .menu-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            padding: 1rem;
        }

        .menu-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-light);
        }

        .menu-item:last-child {
            border-bottom: none;
        }

        .payment-list {
            max-height: 150px;
            overflow-y: auto;
        }

        .payment-item {
            background: #f8f9fa;
            padding: 0.75rem;
            border-radius: var(--radius-sm);
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Contact form */
        .contact-form {
            display: grid;
            gap: 1rem;
        }

        .contact-form .form-group {
            display: flex;
            flex-direction: column;
        }

        .contact-form textarea {
            min-height: 100px;
            resize: vertical;
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

        .toast.warning {
            border-left-color: #f39c12;
        }

        /* Loading */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--curry);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-gray);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--curry);
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .status-paused {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .status-cancelled {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        /* Responsive */
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

            .filter-form {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .table th,
            .table td {
                padding: 0.5rem;
                font-size: 0.8rem;
            }

            .pagination-container {
                flex-direction: column;
                gap: 1rem;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
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
                    <a href="orders.php" class="nav-item active">
                        <i class="nav-icon fas fa-shopping-cart"></i>
                        <span>Orders</span>
                    </a>
                    <a href="menus.php" class="nav-item">
                        <i class="nav-icon fas fa-utensils"></i>
                        <span>Menus</span>
                    </a>
                    <a href="subscriptions.php" class="nav-item">
                        <i class="nav-icon fas fa-calendar-alt"></i>
                        <span>Subscriptions</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Management</div>
                    <a href="users.php" class="nav-item">
                        <i class="nav-icon fas fa-users"></i>
                        <span>Users</span>
                    </a>
                    <a href="inventory.php" class="nav-item">
                        <i class="nav-icon fas fa-boxes"></i>
                        <span>Inventory</span>
                    </a>
                    <a href="delivery-zones.php" class="nav-item">
                        <i class="nav-icon fas fa-map-marked-alt"></i>
                        <span>Delivery Zones</span>
                    </a>
                    <a href="reviews.php" class="nav-item">
                        <i class="nav-icon fas fa-star"></i>
                        <span>Reviews</span>
                    </a>
                    <a href="complaints.php" class="nav-item">
                        <i class="nav-icon fas fa-exclamation-triangle"></i>
                        <span>Complaints</span>
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
                    <a href="settings.php" class="nav-item">
                        <i class="nav-icon fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <a href="#" class="nav-item" onclick="logout()">
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
                        <h1 class="page-title">
                            <i class="fas fa-shopping-cart" style="color: var(--curry); margin-right: 0.5rem;"></i>
                            Orders Management
                        </h1>
                        <p class="page-subtitle">Manage customer subscriptions and orders</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-secondary" onclick="refreshOrders()">
                            <i class="fas fa-sync-alt"></i>
                            Refresh
                        </button>
                        <button class="btn btn-success" onclick="exportOrders('csv')">
                            <i class="fas fa-file-csv"></i>
                            Export CSV
                        </button>
                        <button class="btn btn-info" onclick="exportOrders('json')">
                            <i class="fas fa-file-code"></i>
                            Export JSON
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--curry), #e67e22);">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_orders']) ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--sage), #27ae60);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['active_orders']) ?></div>
                    <div class="stat-label">Active Orders</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                            <i class="fas fa-pause-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['paused_orders']) ?></div>
                    <div class="stat-label">Paused Orders</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['today_orders']) ?></div>
                    <div class="stat-label">Today's Orders</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="stat-value">₿<?= number_format($stats['avg_order_value'], 0) ?></div>
                    <div class="stat-label">Avg Order Value</div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label>Search Orders</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Order ID, Customer name, Email..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <label>Status Filter</label>
                        <select name="status" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="paused" <?php echo $status_filter === 'paused' ? 'selected' : ''; ?>>Paused</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Search
                        </button>
                    </div>
                    <div class="form-group">
                        <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                            <i class="fas fa-times"></i>
                            Clear
                        </button>
                    </div>
                </form>
            </div>

            <!-- Orders Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3 class="table-title">
                        <i class="fas fa-list" style="color: var(--curry); margin-right: 0.5rem;"></i>
                        Orders List
                    </h3>
                    <div>
                        Showing <?= count($orders) ?> of <?= number_format($totalItems) ?> orders
                    </div>
                </div>
                
                <?php if (isset($page_error)): ?>
                    <div style="padding: 2rem; text-align: center; color: #e74c3c;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                        <h3>Error Loading Orders</h3>
                        <p><?php echo htmlspecialchars($page_error); ?></p>
                        <button class="btn btn-primary" onclick="window.location.reload()">
                            <i class="fas fa-sync-alt"></i>
                            Retry
                        </button>
                    </div>
                <?php elseif (empty($orders)): ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>No Orders Found</h3>
                        <p>No orders match your current filters. Try adjusting your search criteria.</p>
                        <button class="btn btn-primary" onclick="clearFilters()">
                            <i class="fas fa-times"></i>
                            Clear Filters
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Plan</th>
                                    <th>Status</th>
                                    <th>Start Date</th>
                                    <th>Next Bill</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <small style="font-family: monospace; color: var(--text-gray);">
                                            <?= htmlspecialchars(substr($order['id'], 0, 8)) ?>...
                                        </small>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;">
                                            <?= htmlspecialchars($order['customer_name']) ?>
                                        </div>
                                        <small style="color: var(--text-gray);">
                                            <?= htmlspecialchars($order['email']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($order['plan_name']) ?></div>
                                        <small style="color: var(--text-gray);">
                                            <?= $order['meals_per_week'] ?> meals/week
                                        </small>
                                    </td>
                                    <td>
                                        <select class="form-control" 
                                                onchange="updateOrderStatus('<?= $order['id'] ?>', this.value, '<?= $order['status'] ?>')">
                                            <option value="active" <?= $order['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="paused" <?= $order['status'] === 'paused' ? 'selected' : '' ?>>Paused</option>
                                            <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                    </td>
                                    <td>
                                        <?= date('M d, Y', strtotime($order['start_date'])) ?>
                                    </td>
                                    <td>
                                        <?= date('M d, Y', strtotime($order['next_billing_date'])) ?>
                                    </td>
                                    <td>
                                        <strong style="color: var(--curry);">
                                            ₿<?= number_format($order['total_amount'], 0) ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="action-btn view" 
                                                    onclick="viewOrderDetails('<?= htmlspecialchars($order['id']) ?>')"
                                                    title="View Details"
                                                    type="button">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="action-btn contact" 
                                                    onclick="contactCustomer('<?= htmlspecialchars($order['id']) ?>', '<?= htmlspecialchars($order['email']) ?>')"
                                                    title="Contact Customer"
                                                    type="button">
                                                <i class="fas fa-envelope"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?= ($page - 1) * $limit + 1 ?> to <?= min($page * $limit, $totalItems) ?> of <?= number_format($totalItems) ?> results
                        </div>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>" 
                                   class="page-btn">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>" 
                                   class="page-btn <?= $i === $page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>" 
                                   class="page-btn">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal-overlay" id="orderModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Order Details</h3>
                <button class="modal-close" onclick="closeModal('orderModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="orderModalBody">
                <div style="text-align: center; padding: 2rem;">
                    <div class="loading"></div>
                    <p>Loading order details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('orderModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Contact Customer Modal -->
    <div class="modal-overlay" id="contactModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Contact Customer</h3>
                <button class="modal-close" onclick="closeModal('contactModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form class="contact-form" id="contactForm">
                    <input type="hidden" id="contactOrderId" name="order_id">
                    <div class="form-group">
                        <label>Customer Email</label>
                        <input type="email" id="contactEmail" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" id="contactSubject" name="subject" class="form-control" 
                               placeholder="Enter email subject..." required>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea id="contactMessage" name="message" class="form-control" 
                                  placeholder="Enter your message..." required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('contactModal')">Cancel</button>
                <button class="btn btn-primary" onclick="sendEmail()">
                    <i class="fas fa-paper-plane"></i>
                    Send Email
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
        
        // Update order status
        function updateOrderStatus(orderId, newStatus, currentStatus) {
            if (newStatus === currentStatus) {
                return; // No change
            }
            
            let reason = '';
            if (newStatus === 'cancelled') {
                reason = prompt("Please enter a reason for cancellation:");
                if (reason === null || reason.trim() === '') {
                    // Revert dropdown
                    document.querySelector(`select[onchange*="${orderId}"]`).value = currentStatus;
                    showToast("Cancellation requires a reason.", 'error');
                    return;
                }
            }
            
            if (!confirm(`Are you sure you want to change the status to "${newStatus}"?`)) {
                // Revert dropdown
                document.querySelector(`select[onchange*="${orderId}"]`).value = currentStatus;
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('order_id', orderId);
            formData.append('status', newStatus);
            formData.append('reason', reason);
            formData.append('csrf_token', CSRF_TOKEN);

            fetch('orders.php', { 
                method: 'POST', 
                body: formData 
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    // Update the row background to indicate change
                    const row = document.querySelector(`select[onchange*="${orderId}"]`).closest('tr');
                    row.style.background = '#e8f5e8';
                    setTimeout(() => {
                        row.style.background = '';
                    }, 2000);
                } else {
                    showToast(data.message, 'error');
                    // Revert dropdown
                    document.querySelector(`select[onchange*="${orderId}"]`).value = currentStatus;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('orderModalBody').innerHTML = `
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #e74c3c; margin-bottom: 1rem;"></i>
                        <h3>Connection Error</h3>
                        <p>Failed to load order details. Please try again.</p>
                        <button class="btn btn-primary" onclick="viewOrderDetails('${orderId}')">
                            <i class="fas fa-sync-alt"></i> Retry
                        </button>
                    </div>
                `;
            });
        }

        // Display order details - ENHANCED VERSION
        function displayOrderDetails(order) {
            console.log('Displaying order details:', order);
            
            // Safely handle null/undefined values
            const safeGet = (obj, key, defaultValue = 'N/A') => {
                return obj && obj[key] !== null && obj[key] !== undefined ? obj[key] : defaultValue;
            };
            
            const formatDate = (dateString) => {
                if (!dateString) return 'No date';
                try {
                    return new Date(dateString).toLocaleDateString('th-TH', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                } catch (e) {
                    return dateString;
                }
            };
            
            const formatCurrency = (amount) => {
                const num = parseFloat(amount || 0);
                return `₿${num.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
            };

            // Build menus list with better formatting
            const menusList = order.menus && order.menus.length > 0 ? 
                order.menus.map((menu, index) => `
                    <div class="menu-item" style="border-left: 3px solid var(--curry); padding-left: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 0.25rem;">
                                    ${index + 1}. ${safeGet(menu, 'menu_name')}
                                </div>
                                ${menu.menu_name_en ? `
                                    <div style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: 0.25rem;">
                                        ${menu.menu_name_en}
                                    </div>
                                ` : ''}
                                <div style="display: flex; gap: 1rem; font-size: 0.85rem; color: var(--text-gray);">
                                    <span><i class="fas fa-calendar"></i> ${formatDate(menu.delivery_date)}</span>
                                    <span><i class="fas fa-utensils"></i> Qty: ${safeGet(menu, 'quantity', 1)}</span>
                                    <span class="status-badge status-${safeGet(menu, 'menu_status', 'scheduled').toLowerCase()}">
                                        ${safeGet(menu, 'menu_status', 'scheduled')}
                                    </span>
                                </div>
                            </div>
                            <div style="text-align: right; margin-left: 1rem;">
                                <div style="color: var(--curry); font-weight: 600; font-size: 1.1rem;">
                                    ${formatCurrency(menu.base_price)}
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-gray);">
                                    per item
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('') : `
                    <div style="text-align: center; padding: 2rem; color: var(--text-gray);">
                        <i class="fas fa-utensils" style="font-size: 2rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                        <p>No menus found for this order</p>
                    </div>
                `;

            // Build payments list with better formatting
            const paymentsList = order.payments && order.payments.length > 0 ? 
                order.payments.map((payment, index) => {
                    const statusColor = payment.status === 'completed' ? '#27ae60' : 
                                      payment.status === 'pending' ? '#f39c12' : '#e74c3c';
                    const statusIcon = payment.status === 'completed' ? 'check-circle' : 
                                     payment.status === 'pending' ? 'clock' : 'times-circle';
                    
                    return `
                        <div class="payment-item" style="border-left: 3px solid ${statusColor};">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="flex: 1;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                        <strong style="color: var(--text-dark);">
                                            ${safeGet(payment, 'payment_method', 'Unknown Method').toUpperCase()}
                                        </strong>
                                        <i class="fas fa-${statusIcon}" style="color: ${statusColor};"></i>
                                    </div>
                                    ${payment.transaction_id ? `
                                        <div style="font-family: monospace; font-size: 0.85rem; color: var(--text-gray); margin-bottom: 0.25rem;">
                                            TXN: ${payment.transaction_id}
                                        </div>
                                    ` : ''}
                                    <div style="font-size: 0.85rem; color: var(--text-gray);">
                                        <i class="fas fa-calendar"></i> ${formatDate(payment.payment_date || payment.created_at)}
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="color: var(--curry); font-weight: 600; font-size: 1.1rem;">
                                        ${safeGet(payment, 'currency', '₿')}${formatCurrency(payment.amount).replace('₿', '')}
                                    </div>
                                    <div style="font-size: 0.8rem; color: ${statusColor}; font-weight: 500; text-transform: uppercase;">
                                        ${safeGet(payment, 'status', 'unknown')}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('') : `
                    <div style="text-align: center; padding: 2rem; color: var(--text-gray);">
                        <i class="fas fa-credit-card" style="font-size: 2rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                        <p>No payment records found</p>
                    </div>
                `;

            // Get status color for badge
            const getStatusColor = (status) => {
                switch(status) {
                    case 'active': return '#27ae60';
                    case 'paused': return '#f39c12';
                    case 'cancelled': return '#e74c3c';
                    default: return '#7f8c8d';
                }
            };

            document.getElementById('orderModalBody').innerHTML = `
                <div class="detail-section">
                    <h4><i class="fas fa-user" style="color: var(--curry);"></i> Customer Information</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Full Name</div>
                            <div class="detail-value" style="font-weight: 600;">
                                ${safeGet(order, 'first_name')} ${safeGet(order, 'last_name')}
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Email Address</div>
                            <div class="detail-value">
                                <a href="mailto:${safeGet(order, 'email')}" style="color: var(--curry); text-decoration: none;">
                                    <i class="fas fa-envelope"></i> ${safeGet(order, 'email')}
                                </a>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Phone Number</div>
                            <div class="detail-value">
                                ${order.phone ? `<i class="fas fa-phone"></i> ${order.phone}` : 'No phone number'}
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Delivery Address</div>
                            <div class="detail-value" style="line-height: 1.4;">
                                <i class="fas fa-map-marker-alt" style="color: var(--curry);"></i>
                                ${safeGet(order, 'delivery_address')}<br>
                                ${safeGet(order, 'city')} ${safeGet(order, 'zip_code')}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <h4><i class="fas fa-shopping-cart" style="color: var(--curry);"></i> Subscription Details</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Plan Name</div>
                            <div class="detail-value" style="font-weight: 600; color: var(--curry);">
                                ${safeGet(order, 'plan_name')}
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Plan Type</div>
                            <div class="detail-value">
                                ${safeGet(order, 'plan_type', '').toUpperCase()}
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Meals per Week</div>
                            <div class="detail-value">
                                <i class="fas fa-utensils"></i> ${safeGet(order, 'meals_per_week', 0)} meals
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Current Status</div>
                            <div class="detail-value">
                                <span class="status-badge" style="background-color: ${getStatusColor(order.status)}; color: white;">
                                    <i class="fas fa-circle" style="font-size: 0.6rem;"></i>
                                    ${safeGet(order, 'status', 'unknown').toUpperCase()}
                                </span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Total Amount</div>
                            <div class="detail-value" style="color: var(--curry); font-weight: 700; font-size: 1.2rem;">
                                ${formatCurrency(order.total_amount)}
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Start Date</div>
                            <div class="detail-value">
                                <i class="fas fa-calendar-plus"></i> ${formatDate(order.start_date)}
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Next Billing Date</div>
                            <div class="detail-value">
                                <i class="fas fa-calendar-alt"></i> ${formatDate(order.next_billing_date)}
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Created Date</div>
                            <div class="detail-value">
                                <i class="fas fa-calendar"></i> ${formatDate(order.created_at)}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <h4>
                        <i class="fas fa-utensils" style="color: var(--curry);"></i> 
                        Selected Menus 
                        <span style="background: var(--curry); color: white; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.8rem; margin-left: 0.5rem;">
                            ${order.menus ? order.menus.length : 0} items
                        </span>
                    </h4>
                    <div class="menu-list" style="max-height: 300px; overflow-y: auto; border: 1px solid var(--border-light); border-radius: var(--radius-sm); padding: 1rem;">
                        ${menusList}
                    </div>
                </div>

                <div class="detail-section">
                    <h4>
                        <i class="fas fa-credit-card" style="color: var(--curry);"></i> 
                        Payment History 
                        <span style="background: var(--sage); color: white; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.8rem; margin-left: 0.5rem;">
                            ${order.payments ? order.payments.length : 0} transactions
                        </span>
                    </h4>
                    <div class="payment-list" style="max-height: 250px; overflow-y: auto;">
                        ${paymentsList}
                    </div>
                </div>
                
                <div class="detail-section" style="background: var(--cream); padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
                    <h4 style="margin-bottom: 0.5rem;"><i class="fas fa-info-circle" style="color: var(--curry);"></i> Order Summary</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; font-size: 0.9rem;">
                        <div><strong>Order ID:</strong> ${safeGet(order, 'id', '').substring(0, 8)}...</div>
                        <div><strong>Billing Cycle:</strong> ${safeGet(order, 'billing_cycle', 'weekly')}</div>
                        <div><strong>Auto Renew:</strong> ${order.auto_renew ? 'Yes' : 'No'}</div>
                        <div><strong>Last Updated:</strong> ${formatDate(order.updated_at)}</div>
                    </div>
                </div>
            `;
        }

        // Contact customer
        function contactCustomer(orderId, customerEmail) {
            document.getElementById('contactOrderId').value = orderId;
            document.getElementById('contactEmail').value = customerEmail;
            document.getElementById('contactSubject').value = '';
            document.getElementById('contactMessage').value = '';
            openModal('contactModal');
        }

        // Send email
        function sendEmail() {
            const orderId = document.getElementById('contactOrderId').value;
            const subject = document.getElementById('contactSubject').value.trim();
            const message = document.getElementById('contactMessage').value.trim();

            if (!subject || !message) {
                showToast('Please fill in both subject and message.', 'warning');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'send_email');
            formData.append('order_id', orderId);
            formData.append('subject', subject);
            formData.append('message', message);
            formData.append('csrf_token', CSRF_TOKEN);

            // Show loading state
            const sendBtn = document.querySelector('#contactModal .btn-primary');
            const originalText = sendBtn.innerHTML;
            sendBtn.innerHTML = '<span class="loading"></span> Sending...';
            sendBtn.disabled = true;

            fetch('orders.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal('contactModal');
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to send email. Please try again.', 'error');
            })
            .finally(() => {
                sendBtn.innerHTML = originalText;
                sendBtn.disabled = false;
            });
        }

        // Export orders
        function exportOrders(format) {
            const statusFilter = new URLSearchParams(window.location.search).get('status') || '';
            const search = new URLSearchParams(window.location.search).get('search') || '';
            
            const formData = new FormData();
            formData.append('action', 'export_orders');
            formData.append('format', format);
            formData.append('status_filter', statusFilter);
            formData.append('search', search);
            formData.append('csrf_token', CSRF_TOKEN);

            // Show loading state
            const exportBtn = event.target;
            const originalText = exportBtn.innerHTML;
            exportBtn.innerHTML = '<span class="loading"></span> Exporting...';
            exportBtn.disabled = true;

            fetch('orders.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (format === 'csv') {
                        downloadCSV(data.data, data.filename);
                    } else {
                        downloadJSON(data.data, data.filename);
                    }
                    showToast(`${format.toUpperCase()} export completed! ${data.count} records exported.`, 'success');
                } else {
                    showToast(`Export failed: ${data.message}`, 'error');
                }
            })
            .catch(error => {
                console.error('Export error:', error);
                showToast('Export failed. Please try again.', 'error');
            })
            .finally(() => {
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            });
        }

        // Download CSV file
        function downloadCSV(data, filename) {
            let csvContent = '';
            
            if (Array.isArray(data) && data.length > 0) {
                csvContent = data.map(row => 
                    row.map(field => {
                        if (field === null || field === undefined) {
                            return '""';
                        }
                        return `"${String(field).replace(/"/g, '""')}"`;
                    }).join(',')
                ).join('\n');
            } else {
                csvContent = '"No data available"';
            }
            
            const BOM = '\uFEFF';
            const blob = new Blob([BOM + csvContent], { 
                type: 'text/csv;charset=utf-8;' 
            });
            
            const link = document.createElement('a');
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', filename || 'orders_export.csv');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                setTimeout(() => URL.revokeObjectURL(url), 100);
            }
        }

        // Download JSON file
        function downloadJSON(data, filename) {
            try {
                const jsonContent = JSON.stringify(data, null, 2);
                const blob = new Blob([jsonContent], { 
                    type: 'application/json;charset=utf-8;' 
                });
                
                const link = document.createElement('a');
                if (link.download !== undefined) {
                    const url = URL.createObjectURL(blob);
                    link.setAttribute('href', url);
                    link.setAttribute('download', filename || 'orders_export.json');
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    setTimeout(() => URL.revokeObjectURL(url), 100);
                }
            } catch (error) {
                console.error('JSON download error:', error);
                showToast('Failed to download JSON file', 'error');
            }
        }

        // Refresh orders
        function refreshOrders() {
            showToast('Refreshing orders...', 'info');
            window.location.reload();
        }

        // Clear filters
        function clearFilters() {
            window.location.href = 'orders.php';
        }

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Toast notification system
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const icon = type === 'success' ? 'check-circle' : 
                        type === 'error' ? 'exclamation-circle' : 
                        type === 'warning' ? 'exclamation-triangle' : 'info-circle';
            
            toast.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-${icon}"></i>
                        <span>${message}</span>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" 
                            style="background: none; border: none; cursor: pointer; color: var(--text-gray);">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            container.appendChild(toast);
            
            // Show toast
            setTimeout(() => toast.classList.add('show'), 100);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                showToast('Logging out...', 'info');
                window.location.href = '../auth/logout.php';
            }
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                closeModal(e.target.id);
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape key to close modals
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal-overlay');
                modals.forEach(modal => {
                    if (modal.style.display === 'flex') {
                        closeModal(modal.id);
                    }
                });
            }
            
            // Ctrl+R for refresh
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshOrders();
            }
            
            // Ctrl+E for CSV export
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportOrders('csv');
            }
        });

        // Search form auto-submit on Enter
        document.querySelector('input[name="search"]').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });

        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }

        // Add mobile menu button for responsive design
        if (window.innerWidth <= 768) {
            const mobileMenuBtn = document.createElement('button');
            mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            mobileMenuBtn.className = 'btn btn-secondary';
            mobileMenuBtn.style.cssText = `
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 1001;
                width: 40px;
                height: 40px;
                padding: 0;
                display: flex;
                align-items: center;
                justify-content: center;
            `;
            mobileMenuBtn.onclick = toggleSidebar;
            document.body.appendChild(mobileMenuBtn);
        }

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                const sidebar = document.getElementById('sidebar');
                sidebar.classList.remove('show');
            }
        });

        // DEBUG: Test functions - ADD THIS FOR TESTING
        function testModal() {
            console.log('Testing modal...');
            openModal('orderModal');
            document.getElementById('orderModalBody').innerHTML = `
                <div style="text-align: center; padding: 2rem;">
                    <h3>✅ Modal is working!</h3>
                    <p>This is a test modal.</p>
                    <button class="btn btn-primary" onclick="closeModal('orderModal')">Close Test</button>
                </div>
            `;
        }
        
        function testOrderDetails() {
            console.log('Testing order details...');
            const testOrderId = '<?= !empty($orders) ? $orders[0]['id'] : 'test-id' ?>';
            console.log('Test order ID:', testOrderId);
            viewOrderDetails(testOrderId);
        }
        
        // Make functions globally available for testing
        window.testModal = testModal;
        window.testOrderDetails = testOrderDetails;
        window.viewOrderDetails = viewOrderDetails;
        window.contactCustomer = contactCustomer;
        window.openModal = openModal;
        window.closeModal = closeModal;
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Krua Thai Orders Management initialized successfully');
            console.log('CSRF Token:', CSRF_TOKEN);
            
            // Test if jQuery is loaded (if needed)
            if (typeof $ !== 'undefined') {
                console.log('jQuery is available');
            }
            
            // Add click event listeners to action buttons as backup
            document.addEventListener('click', function(e) {
                // Handle view button clicks
                if (e.target.closest('.action-btn.view')) {
                    e.preventDefault();
                    const btn = e.target.closest('.action-btn.view');
                    const orderId = btn.getAttribute('data-order-id') || 
                                   btn.getAttribute('onclick')?.match(/'([^']+)'/)?.[1];
                    if (orderId) {
                        console.log('View button clicked for order:', orderId);
                        viewOrderDetails(orderId);
                    } else {
                        console.error('No order ID found');
                    }
                }
                
                // Handle contact button clicks
                if (e.target.closest('.action-btn.contact')) {
                    e.preventDefault();
                    const btn = e.target.closest('.action-btn.contact');
                    const onclickAttr = btn.getAttribute('onclick');
                    if (onclickAttr) {
                        const matches = onclickAttr.match(/'([^']+)'/g);
                        if (matches && matches.length >= 2) {
                            const orderId = matches[0].replace(/'/g, '');
                            const email = matches[1].replace(/'/g, '');
                            console.log('Contact button clicked for order:', orderId, 'email:', email);
                            contactCustomer(orderId, email);
                        }
                    }
                }
            });
            
            // Add loading states to form submissions
            const filterForm = document.querySelector('.filter-form');
            if (filterForm) {
                filterForm.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        const originalContent = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<span class="loading"></span> Searching...';
                        submitBtn.disabled = true;
                        
                        // Re-enable after 3 seconds (fallback)
                        setTimeout(() => {
                            submitBtn.innerHTML = originalContent;
                            submitBtn.disabled = false;
                        }, 3000);
                    }
                });
            }
            
            // Test modal functionality
            console.log('Testing modal elements...');
            const orderModal = document.getElementById('orderModal');
            const contactModal = document.getElementById('contactModal');
            console.log('Order modal found:', !!orderModal);
            console.log('Contact modal found:', !!contactModal);
            
            // Add keyboard shortcut info to console
            console.log('Keyboard shortcuts: Esc (close modal), Ctrl+R (refresh), Ctrl+E (export CSV)');
        });

        // Performance monitoring
        window.addEventListener('load', function() {
            const loadTime = performance.now();
            console.log(`Orders page loaded in ${Math.round(loadTime)}ms`);
        });
    </script>
</body>
</html>
             