<?php
/**
 * Krua Thai - Payments Management (Enhanced with Status Update)
 * File: admin/payments.php
 * Description: Complete payments management system with transaction tracking, refunds, and status updates
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
            case 'update_payment_status':
                $result = updatePaymentStatus($pdo, $_POST['id'], $_POST['status'], $_POST['notes'] ?? '');
                echo json_encode($result);
                exit;
                
            case 'process_refund':
                $result = processRefund($pdo, $_POST['id'], $_POST['amount'], $_POST['reason']);
                echo json_encode($result);
                exit;
                
            case 'get_payment_details':
                $result = getPaymentDetails($pdo, $_POST['id']);
                echo json_encode($result);
                exit;
                
            case 'export_payments':
                exportPayments($pdo, $_POST);
                exit;
                
            case 'bulk_update_status':
                $result = bulkUpdatePaymentStatus($pdo, $_POST['payment_ids'], $_POST['status']);
                echo json_encode($result);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Enhanced Database Functions
function updatePaymentStatus($pdo, $paymentId, $status, $notes = '') {
    try {
        $pdo->beginTransaction();
        
        // Validate status
        $validStatuses = ['pending', 'completed', 'failed', 'refunded', 'partial_refund'];
        if (!in_array($status, $validStatuses)) {
            throw new Exception('Invalid payment status');
        }
        
        // Get current payment info
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            throw new Exception('Payment not found');
        }
        
        // Update payment status
        $updateFields = ['status = ?', 'updated_at = NOW()'];
        $updateParams = [$status];
        
        // Add payment_date if changing to completed
        if ($status === 'completed' && !$payment['payment_date']) {
            $updateFields[] = 'payment_date = NOW()';
        }
        
        // Add failure reason if changing to failed
        if ($status === 'failed' && $notes) {
            $updateFields[] = 'failure_reason = ?';
            $updateParams[] = $notes;
        }
        
        $updateParams[] = $paymentId;
        
        $sql = "UPDATE payments SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateParams);
        
        // Update related subscription status if payment is completed
        if ($status === 'completed' && $payment['subscription_id']) {
            // Get subscription details first
            $subStmt = $pdo->prepare("SELECT billing_cycle FROM subscriptions WHERE id = ?");
            $subStmt->execute([$payment['subscription_id']]);
            $subscription = $subStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($subscription) {
                $baseDate = $payment['billing_period_start'] ?: date('Y-m-d');
                $nextBillingDate = $baseDate;
                
                if ($subscription['billing_cycle'] === 'weekly') {
                    $nextBillingDate = date('Y-m-d', strtotime($baseDate . ' +7 days'));
                } elseif ($subscription['billing_cycle'] === 'monthly') {
                    $nextBillingDate = date('Y-m-d', strtotime($baseDate . ' +1 month'));
                }
                
                $stmt = $pdo->prepare("
                    UPDATE subscriptions 
                    SET status = 'active', next_billing_date = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nextBillingDate, $payment['subscription_id']]);
            }
        }
        
        // Try to log the status change (if table exists)
        try {
            $logId = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare("
                INSERT INTO payment_status_log (id, payment_id, old_status, new_status, changed_by, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $logId,
                $paymentId, 
                $payment['status'], 
                $status, 
                $_SESSION['user_id'], 
                $notes
            ]);
        } catch (Exception $e) {
            // Ignore if table doesn't exist
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Payment status updated successfully'];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function bulkUpdatePaymentStatus($pdo, $paymentIds, $status) {
    try {
        $pdo->beginTransaction();
        
        $validStatuses = ['pending', 'completed', 'failed', 'refunded', 'partial_refund'];
        if (!in_array($status, $validStatuses)) {
            throw new Exception('Invalid payment status');
        }
        
        $placeholders = str_repeat('?,', count($paymentIds) - 1) . '?';
        $params = array_merge($paymentIds, [$status]);
        
        $stmt = $pdo->prepare("
            UPDATE payments 
            SET status = ?, updated_at = NOW() 
            WHERE id IN ($placeholders)
        ");
        
        // Reorder params: status first, then IDs
        $params = [$status];
        $params = array_merge($params, $paymentIds);
        
        $stmt->execute($params);
        $affectedRows = $stmt->rowCount();
        
        $pdo->commit();
        return ['success' => true, 'message' => "Updated $affectedRows payments successfully"];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function processRefund($pdo, $paymentId, $refundAmount, $refundReason) {
    try {
        $pdo->beginTransaction();
        
        // Get payment details
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            throw new Exception('Payment not found');
        }
        
        if ($payment['status'] !== 'completed') {
            throw new Exception('Can only refund completed payments');
        }
        
        $maxRefund = $payment['amount'] - $payment['refund_amount'];
        if ($refundAmount > $maxRefund) {
            throw new Exception('Refund amount exceeds available amount');
        }
        
        // Update payment with refund information
        $newRefundAmount = $payment['refund_amount'] + $refundAmount;
        $newStatus = ($newRefundAmount >= $payment['amount']) ? 'refunded' : 'partial_refund';
        
        $stmt = $pdo->prepare("
            UPDATE payments 
            SET refund_amount = ?, 
                refund_reason = CONCAT(COALESCE(refund_reason, ''), ?, '\n'), 
                refunded_at = NOW(), 
                status = ?,
                updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$newRefundAmount, $refundReason, $newStatus, $paymentId]);
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Refund processed successfully'];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error processing refund: ' . $e->getMessage()];
    }
}

function getPaymentDetails($pdo, $paymentId) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                   u.email as customer_email,
                   u.phone as customer_phone,
                   s.billing_cycle,
                   sp.name as plan_name
            FROM payments p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN subscriptions s ON p.subscription_id = s.id
            LEFT JOIN subscription_plans sp ON s.plan_id = sp.id
            WHERE p.id = ?
        ");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payment) {
            // Get status history separately (if table exists)
            try {
                $historyStmt = $pdo->prepare("
                    SELECT CONCAT(old_status, ' → ', new_status, ' (', 
                                  DATE_FORMAT(created_at, '%d/%m/%Y %H:%i'), 
                                  CASE WHEN notes != '' AND notes IS NOT NULL THEN CONCAT(': ', notes) ELSE '' END, ')') as history_line
                    FROM payment_status_log 
                    WHERE payment_id = ? 
                    ORDER BY created_at DESC
                ");
                $historyStmt->execute([$paymentId]);
                $history = $historyStmt->fetchAll(PDO::FETCH_COLUMN);
                $payment['status_history'] = implode("\n", $history);
            } catch (Exception $e) {
                // Table doesn't exist yet, ignore
                $payment['status_history'] = '';
            }
            
            return ['success' => true, 'data' => $payment];
        } else {
            return ['success' => false, 'message' => 'Payment not found'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function exportPayments($pdo, $filters) {
    try {
        $whereConditions = ['1=1'];
        $params = [];
        
        if (!empty($filters['status'])) {
            $whereConditions[] = 'p.status = ?';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['payment_method'])) {
            $whereConditions[] = 'p.payment_method = ?';
            $params[] = $filters['payment_method'];
        }
        
        if (!empty($filters['date_from'])) {
            $whereConditions[] = 'DATE(p.payment_date) >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = 'DATE(p.payment_date) <= ?';
            $params[] = $filters['date_to'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                   u.email as customer_email,
                   sp.name as plan_name
            FROM payments p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN subscriptions s ON p.subscription_id = s.id
            LEFT JOIN subscription_plans sp ON s.plan_id = sp.id
            WHERE $whereClause
            ORDER BY p.payment_date DESC
        ");
        $stmt->execute($params);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="payments_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'Payment ID', 'Transaction ID', 'Customer', 'Email', 'Plan', 
            'Amount', 'Currency', 'Fee', 'Net Amount', 'Payment Method', 
            'Status', 'Payment Date', 'Billing Period', 'Refund Amount'
        ]);
        
        foreach ($payments as $payment) {
            fputcsv($output, [
                $payment['id'],
                $payment['transaction_id'],
                $payment['customer_name'],
                $payment['customer_email'],
                $payment['plan_name'],
                $payment['amount'],
                $payment['currency'],
                $payment['fee_amount'],
                $payment['net_amount'],
                ucfirst(str_replace('_', ' ', $payment['payment_method'])),
                ucfirst(str_replace('_', ' ', $payment['status'])),
                $payment['payment_date'],
                ($payment['billing_period_start'] && $payment['billing_period_end']) 
                    ? $payment['billing_period_start'] . ' to ' . $payment['billing_period_end'] 
                    : '',
                $payment['refund_amount']
            ]);
        }
        
        fclose($output);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Export error: ' . $e->getMessage()]);
    }
}

// Create payment_status_log table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payment_status_log (
            id CHAR(36) PRIMARY KEY,
            payment_id CHAR(36) NOT NULL,
            old_status VARCHAR(50),
            new_status VARCHAR(50),
            changed_by CHAR(36),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_payment_id (payment_id),
            INDEX idx_created_at (created_at)
        )
    ");
} catch (Exception $e) {
    // Table might already exist or other error, continue
    error_log("Payment status log table creation: " . $e->getMessage());
}

// Fetch Data
try {
    // Filters
    $statusFilter = $_GET['status'] ?? '';
    $paymentMethodFilter = $_GET['payment_method'] ?? '';
    $dateFromFilter = $_GET['date_from'] ?? '';
    $dateToFilter = $_GET['date_to'] ?? '';
    $search = $_GET['search'] ?? '';
    $sortBy = $_GET['sort'] ?? 'payment_date';
    
    // Main payments query
    $whereConditions = ['1=1'];
    $params = [];
    
    if ($statusFilter) {
        $whereConditions[] = 'p.status = ?';
        $params[] = $statusFilter;
    }
    
    if ($paymentMethodFilter) {
        $whereConditions[] = 'p.payment_method = ?';
        $params[] = $paymentMethodFilter;
    }
    
    if ($dateFromFilter) {
        $whereConditions[] = 'DATE(p.payment_date) >= ?';
        $params[] = $dateFromFilter;
    }
    
    if ($dateToFilter) {
        $whereConditions[] = 'DATE(p.payment_date) <= ?';
        $params[] = $dateToFilter;
    }
    
    if ($search) {
        $whereConditions[] = '(p.transaction_id LIKE ? OR CONCAT(u.first_name, " ", u.last_name) LIKE ? OR u.email LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $stmt = $pdo->prepare("
        SELECT p.*, 
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.email as customer_email,
               sp.name as plan_name
        FROM payments p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN subscriptions s ON p.subscription_id = s.id
        LEFT JOIN subscription_plans sp ON s.plan_id = sp.id
        WHERE $whereClause
        ORDER BY p.$sortBy DESC
        LIMIT 50
    ");
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_payments,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_payments,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
            SUM(CASE WHEN status IN ('refunded', 'partial_refund') THEN 1 ELSE 0 END) as refunded_payments,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as total_revenue,
            COALESCE(SUM(CASE WHEN status = 'completed' AND DATE(payment_date) = CURDATE() THEN amount ELSE 0 END), 0) as today_revenue,
            COALESCE(SUM(CASE WHEN status = 'completed' AND MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE()) THEN amount ELSE 0 END), 0) as month_revenue,
            COALESCE(SUM(refund_amount), 0) as total_refunds,
            COALESCE(AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END), 0) as avg_transaction_value
        FROM payments p
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Revenue by payment method
    $stmt = $pdo->prepare("
        SELECT payment_method, 
               COUNT(*) as transaction_count,
               SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_amount
        FROM payments 
        WHERE status = 'completed'
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ");
    $stmt->execute();
    $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $payments = [];
    $stats = [
        'total_payments' => 0, 'completed_payments' => 0, 'pending_payments' => 0, 
        'failed_payments' => 0, 'refunded_payments' => 0, 'total_revenue' => 0, 
        'today_revenue' => 0, 'month_revenue' => 0, 'total_refunds' => 0, 'avg_transaction_value' => 0
    ];
    $paymentMethods = [];
    error_log("Payments error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments Management - Krua Thai Admin</title>
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

        /* Filters */
        .filters-section {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
        }

        .filters-row {
            display: grid;
            grid-template-columns: 1fr 150px 150px 150px 150px 150px 150px;
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
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

        /* Payment Cards */
        .payments-grid {
            display: grid;
            gap: 1.5rem;
        }

        .payment-card {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            overflow: hidden;
            transition: var(--transition);
        }

        .payment-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .payment-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .payment-meta {
            flex: 1;
        }

        .payment-id {
            font-weight: 600;
            color: var(--curry);
            margin-bottom: 0.25rem;
        }

        .payment-customer {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .payment-date {
            font-size: 0.8rem;
            color: var(--text-gray);
            margin-bottom: 0.5rem;
        }

        .payment-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .payment-body {
            padding: 1.5rem;
        }

        .payment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .payment-detail {
            text-align: center;
        }

        .payment-detail-label {
            font-size: 0.8rem;
            color: var(--text-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .payment-detail-value {
            font-weight: 600;
            color: var(--text-dark);
        }

        /* Status Badges */
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

        .status-pending {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .status-completed {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .status-failed {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .status-refunded {
            background: rgba(149, 165, 166, 0.1);
            color: #95a5a6;
        }

        .status-partial_refund {
            background: rgba(230, 126, 34, 0.1);
            color: #e67e22;
        }

        .method-badge {
            background: var(--cream);
            color: var(--text-dark);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Enhanced Modals */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            backdrop-filter: blur(4px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-medium);
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        /* Status Update Form */
        .status-update-form {
            display: grid;
            gap: 1rem;
        }

        .status-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .status-option {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
        }

        .status-option:hover:not([style*="cursor: not-allowed"]) {
            border-color: var(--curry);
            background: rgba(207, 114, 58, 0.05);
        }

        .status-option.selected {
            border-color: var(--curry);
            background: rgba(207, 114, 58, 0.1);
        }

        .status-option input[type="radio"] {
            margin-right: 0.75rem;
        }

        .status-option input[type="radio"]:disabled {
            opacity: 0.5;
        }

        .status-option-label {
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notes-section {
            margin-top: 1rem;
        }

        .notes-textarea {
            width: 100%;
            min-height: 100px;
            padding: 0.75rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-family: inherit;
            resize: vertical;
        }

        /* Bulk Actions */
        .bulk-actions {
            background: var(--white);
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-light);
            display: none;
        }

        .bulk-actions.show {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .bulk-counter {
            font-weight: 600;
            color: var(--curry);
        }

        /* Checkbox styling */
        .payment-checkbox {
            position: absolute;
            top: 1rem;
            left: 1rem;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .payment-card {
            position: relative;
        }

        .payment-card.selected {
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters-row {
                grid-template-columns: 1fr;
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
                    <a href="payments.php" class="nav-item active">
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
                        <h1 class="page-title">Payments Management</h1>
                        <p class="page-subtitle">Monitor transactions, process refunds, and update payment status</p>
                    </div>
                    <div class="header-actions">
                        <button type="button" class="btn btn-secondary" onclick="exportPayments()">
                            <i class="fas fa-download"></i>
                            Export CSV
                        </button>
                        <button type="button" class="btn btn-primary" onclick="refreshPage()">
                            <i class="fas fa-sync-alt"></i>
                            Refresh
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions -->
            <div class="bulk-actions" id="bulkActions">
                <div class="bulk-counter">
                    <span id="selectedCount">0</span> payments selected
                </div>
                <select id="bulkStatusSelect" class="form-control" style="width: auto;">
                    <option value="">Select Status</option>
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                    <option value="failed">Failed</option>
                </select>
                <button type="button" class="btn btn-primary" onclick="bulkUpdateStatus()">
                    <i class="fas fa-edit"></i>
                    Update Status
                </button>
                <button type="button" class="btn btn-secondary" onclick="clearSelection()">
                    <i class="fas fa-times"></i>
                    Clear
                </button>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--curry), #e67e22);">
                            <i class="fas fa-credit-card"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_payments']); ?></div>
                    <div class="stat-label">Total Payments</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #27ae60, #229954);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['completed_payments']); ?></div>
                    <div class="stat-label">Completed</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['pending_payments']); ?></div>
                    <div class="stat-label">Pending</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['failed_payments']); ?></div>
                    <div class="stat-label">Failed</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--sage), #27ae60);">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="stat-value">₿<?php echo number_format($stats['total_revenue'], 0); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                    <div class="stat-value">₿<?php echo number_format($stats['today_revenue'], 0); ?></div>
                    <div class="stat-label">Today's Revenue</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                            <i class="fas fa-undo"></i>
                        </div>
                    </div>
                    <div class="stat-value">₿<?php echo number_format($stats['total_refunds'], 0); ?></div>
                    <div class="stat-label">Total Refunds</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #1abc9c, #16a085);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stat-value">₿<?php echo number_format($stats['avg_transaction_value'], 0); ?></div>
                    <div class="stat-label">Avg Transaction</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filters-row">
                        <div class="form-group">
                            <label class="form-label">Search</label>
                            <input type="text" 
                                   name="search" 
                                   class="form-control" 
                                   placeholder="Search by transaction ID, customer..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                <option value="refunded" <?php echo $statusFilter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                <option value="partial_refund" <?php echo $statusFilter === 'partial_refund' ? 'selected' : ''; ?>>Partial Refund</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" class="form-control">
                                <option value="">All Methods</option>
                                <option value="apple_pay" <?php echo $paymentMethodFilter === 'apple_pay' ? 'selected' : ''; ?>>Apple Pay</option>
                                <option value="google_pay" <?php echo $paymentMethodFilter === 'google_pay' ? 'selected' : ''; ?>>Google Pay</option>
                                <option value="paypal" <?php echo $paymentMethodFilter === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                                <option value="credit_card" <?php echo $paymentMethodFilter === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                                <option value="bank_transfer" <?php echo $paymentMethodFilter === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Date From</label>
                            <input type="date" 
                                   name="date_from" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($dateFromFilter); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Date To</label>
                            <input type="date" 
                                   name="date_to" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($dateToFilter); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Sort By</label>
                            <select name="sort" class="form-control">
                                <option value="payment_date" <?php echo $sortBy === 'payment_date' ? 'selected' : ''; ?>>Payment Date</option>
                                <option value="amount" <?php echo $sortBy === 'amount' ? 'selected' : ''; ?>>Amount</option>
                                <option value="status" <?php echo $sortBy === 'status' ? 'selected' : ''; ?>>Status</option>
                                <option value="created_at" <?php echo $sortBy === 'created_at' ? 'selected' : ''; ?>>Created Date</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                                Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Payments Grid -->
            <div class="payments-grid">
                <?php if (empty($payments)): ?>
                    <div class="text-center" style="padding: 3rem; color: var(--text-gray);">
                        <i class="fas fa-credit-card" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                        <h3>No Payments Found</h3>
                        <p>No payments match your current filters.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($payments as $payment): ?>
                        <div class="payment-card" data-payment-id="<?php echo $payment['id']; ?>">
                            <input type="checkbox" class="payment-checkbox" onchange="togglePaymentSelection(this)">
                            <div class="payment-header">
                                <div class="payment-meta">
                                    <div class="payment-id">
                                        <?php echo htmlspecialchars($payment['transaction_id'] ?: 'TXN-' . substr($payment['id'], 0, 8)); ?>
                                    </div>
                                    <div class="payment-customer"><?php echo htmlspecialchars($payment['customer_name']); ?></div>
                                    <div class="payment-date">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo $payment['payment_date'] ? date('d/m/Y H:i', strtotime($payment['payment_date'])) : 'Not processed'; ?>
                                    </div>
                                    <?php if ($payment['plan_name']): ?>
                                        <div class="payment-date">
                                            <i class="fas fa-tags"></i>
                                            Plan: <?php echo htmlspecialchars($payment['plan_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="payment-actions">
                                    <button type="button" class="btn btn-sm btn-secondary btn-icon" 
                                            onclick="viewPayment('<?php echo $payment['id']; ?>')" 
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-warning btn-icon" 
                                            onclick="updateStatus('<?php echo $payment['id']; ?>')" 
                                            title="Update Status">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($payment['status'] === 'completed' && $payment['refund_amount'] == 0): ?>
                                        <button type="button" class="btn btn-sm btn-danger btn-icon" 
                                                onclick="processRefund('<?php echo $payment['id']; ?>')" 
                                                title="Process Refund">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="payment-body">
                                <div class="payment-details">
                                    <div class="payment-detail">
                                        <div class="payment-detail-label">Amount</div>
                                        <div class="payment-detail-value" style="color: var(--curry); font-size: 1.1rem;">
                                            ₿<?php echo number_format($payment['amount'], 2); ?>
                                            <small style="color: var(--text-gray);"><?php echo $payment['currency']; ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="payment-detail">
                                        <div class="payment-detail-label">Status</div>
                                        <div class="payment-detail-value">
                                            <span class="status-badge status-<?php echo $payment['status']; ?>">
                                                <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                                <?php echo ucfirst(str_replace('_', ' ', $payment['status'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="payment-detail">
                                        <div class="payment-detail-label">Method</div>
                                        <div class="payment-detail-value">
                                            <span class="method-badge">
                                                <i class="fas fa-<?php 
                                                    switch($payment['payment_method']) {
                                                        case 'apple_pay': echo 'apple'; break;
                                                        case 'google_pay': echo 'google'; break;
                                                        case 'paypal': echo 'paypal'; break;
                                                        case 'credit_card': echo 'credit-card'; break;
                                                        case 'bank_transfer': echo 'university'; break;
                                                        default: echo 'credit-card';
                                                    }
                                                ?>"></i>
                                                <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="payment-detail">
                                        <div class="payment-detail-label">Net Amount</div>
                                        <div class="payment-detail-value">
                                            ₿<?php echo number_format($payment['net_amount'], 2); ?>
                                            <?php if ($payment['fee_amount'] > 0): ?>
                                                <small style="color: var(--text-gray);">(Fee: ₿<?php echo number_format($payment['fee_amount'], 2); ?>)</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($payment['refund_amount'] > 0): ?>
                                    <div class="payment-detail">
                                        <div class="payment-detail-label">Refunded</div>
                                        <div class="payment-detail-value" style="color: #e74c3c;">
                                            ₿<?php echo number_format($payment['refund_amount'], 2); ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="payment-detail">
                                        <div class="payment-detail-label">Provider</div>
                                        <div class="payment-detail-value">
                                            <?php echo htmlspecialchars($payment['payment_provider'] ?: 'N/A'); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($payment['billing_period_start'] && $payment['billing_period_end']): ?>
                                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-light);">
                                        <small style="color: var(--text-gray);">
                                            <i class="fas fa-calendar-alt"></i>
                                            Billing Period: <?php echo date('M d', strtotime($payment['billing_period_start'])); ?> - <?php echo date('M d, Y', strtotime($payment['billing_period_end'])); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($payment['failure_reason']): ?>
                                    <div style="margin-top: 1rem; padding: 0.75rem; background: rgba(231, 76, 60, 0.1); border-radius: var(--radius-sm); border-left: 3px solid #e74c3c;">
                                        <small style="color: #c0392b;">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <strong>Failure Reason:</strong> <?php echo htmlspecialchars($payment['failure_reason']); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($payment['refund_reason']): ?>
                                    <div style="margin-top: 1rem; padding: 0.75rem; background: rgba(230, 126, 34, 0.1); border-radius: var(--radius-sm); border-left: 3px solid #e67e22;">
                                        <small style="color: #d68910;">
                                            <i class="fas fa-undo"></i>
                                            <strong>Refund Reason:</strong> <?php echo htmlspecialchars($payment['refund_reason']); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- View Payment Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-credit-card"></i>
                    Payment Details
                </h2>
                <button type="button" class="modal-close" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('viewModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-edit"></i>
                    Update Payment Status
                </h2>
                <button type="button" class="modal-close" onclick="closeModal('statusModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="statusForm" class="status-update-form">
                    <input type="hidden" id="statusPaymentId" name="payment_id">
                    
                    <div class="form-group">
                        <label class="form-label">Current Status</label>
                        <div id="currentStatus" class="form-control" style="background: #f8f9fa; cursor: not-allowed;"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">New Status</label>
                        <div class="status-options">
                            <div class="status-option">
                                <input type="radio" name="status" value="pending" id="status_pending">
                                <label for="status_pending" class="status-option-label">
                                    <i class="fas fa-clock" style="color: #f39c12;"></i>
                                    Pending
                                </label>
                            </div>
                            <div class="status-option">
                                <input type="radio" name="status" value="completed" id="status_completed">
                                <label for="status_completed" class="status-option-label">
                                    <i class="fas fa-check-circle" style="color: #27ae60;"></i>
                                    Completed
                                </label>
                            </div>
                            <div class="status-option">
                                <input type="radio" name="status" value="failed" id="status_failed">
                                <label for="status_failed" class="status-option-label">
                                    <i class="fas fa-times-circle" style="color: #e74c3c;"></i>
                                    Failed
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="notes-section">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea id="statusNotes" 
                                  name="notes" 
                                  class="notes-textarea" 
                                  placeholder="Add notes about this status change..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitStatusUpdate()">
                    <i class="fas fa-save"></i>
                    Update Status
                </button>
            </div>
        </div>
    </div>

    <!-- Refund Modal -->
    <div id="refundModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-undo"></i>
                    Process Refund
                </h2>
                <button type="button" class="modal-close" onclick="closeModal('refundModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="refundForm">
                    <input type="hidden" id="refundPaymentId" name="payment_id">
                    <div class="form-group">
                        <label class="form-label">Original Amount</label>
                        <input type="text" id="originalAmount" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Refund Amount</label>
                        <input type="number" 
                               id="refundAmount" 
                               name="refund_amount" 
                               class="form-control" 
                               step="0.01" 
                               min="0.01"
                               placeholder="Enter refund amount"
                               required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Refund Reason</label>
                        <textarea id="refundReason" 
                                  name="refund_reason" 
                                  class="form-control" 
                                  rows="3" 
                                  placeholder="Explain why this refund is being processed..."
                                  required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('refundModal')">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="submitRefund()">
                    <i class="fas fa-undo"></i>
                    Process Refund
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        let selectedPayments = new Set();

        // Auto-submit form when filters change
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.filters-form');
            const inputs = form.querySelectorAll('select, input[type="date"]');
            
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    if (this.name !== 'search') {
                        form.submit();
                    }
                });
            });
            
            // Search on Enter
            const searchInput = form.querySelector('input[name="search"]');
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    form.submit();
                }
            });

            // Setup status option selection
            document.querySelectorAll('.status-option').forEach(option => {
                option.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                        updateStatusOptionSelection();
                    }
                });
            });
        });

        // Payment selection for bulk actions
        function togglePaymentSelection(checkbox) {
            const paymentId = checkbox.closest('.payment-card').dataset.paymentId;
            const card = checkbox.closest('.payment-card');
            
            if (checkbox.checked) {
                selectedPayments.add(paymentId);
                card.classList.add('selected');
            } else {
                selectedPayments.delete(paymentId);
                card.classList.remove('selected');
            }
            
            updateBulkActions();
        }

        function updateBulkActions() {
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            selectedCount.textContent = selectedPayments.size;
            
            if (selectedPayments.size > 0) {
                bulkActions.classList.add('show');
            } else {
                bulkActions.classList.remove('show');
            }
        }

        function clearSelection() {
            selectedPayments.clear();
            document.querySelectorAll('.payment-checkbox').forEach(cb => cb.checked = false);
            document.querySelectorAll('.payment-card').forEach(card => card.classList.remove('selected'));
            updateBulkActions();
        }

        function bulkUpdateStatus() {
            const status = document.getElementById('bulkStatusSelect').value;
            if (!status) {
                showToast('Please select a status', 'error');
                return;
            }
            
            if (selectedPayments.size === 0) {
                showToast('No payments selected', 'error');
                return;
            }
            
            if (!confirm(`Update ${selectedPayments.size} payments to ${status}?`)) {
                return;
            }
            
            fetch('payments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=bulk_update_status&payment_ids=${Array.from(selectedPayments).join(',')}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message || 'Error updating payments', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error updating payments', 'error');
            });
        }

        // View payment details
        function viewPayment(paymentId) {
            fetch('payments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_payment_details&id=${paymentId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const payment = data.data;
                    document.getElementById('viewModalBody').innerHTML = generatePaymentDetailsHTML(payment);
                    openModal('viewModal');
                } else {
                    showToast('Error loading payment details', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading payment details', 'error');
            });
        }

        function generatePaymentDetailsHTML(payment) {
            return `
                <div style="display: grid; gap: 1.5rem;">
                    <div>
                        <h4>Payment Information</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                            <div>
                                <strong>Transaction ID:</strong><br>
                                <code>${payment.transaction_id || 'N/A'}</code>
                            </div>
                            <div>
                                <strong>External Payment ID:</strong><br>
                                <code>${payment.external_payment_id || 'N/A'}</code>
                            </div>
                            <div>
                                <strong>Payment Provider:</strong><br>
                                ${payment.payment_provider || 'N/A'}
                            </div>
                            <div>
                                <strong>Payment Date:</strong><br>
                                ${payment.payment_date ? new Date(payment.payment_date).toLocaleString('th-TH') : 'Not processed'}
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h4>Customer Information</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                            <div>
                                <strong>Name:</strong><br>
                                ${payment.customer_name}
                            </div>
                            <div>
                                <strong>Email:</strong><br>
                                ${payment.customer_email}
                            </div>
                            <div>
                                <strong>Phone:</strong><br>
                                ${payment.customer_phone || 'N/A'}
                            </div>
                            <div>
                                <strong>Plan:</strong><br>
                                ${payment.plan_name || 'N/A'} (${payment.billing_cycle || 'N/A'})
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h4>Financial Details</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 1rem;">
                            <div>
                                <strong>Amount:</strong><br>
                                <span style="color: var(--curry); font-size: 1.2rem; font-weight: 600;">
                                    ₿${parseFloat(payment.amount).toLocaleString('th-TH', {minimumFractionDigits: 2})}
                                </span>
                            </div>
                            <div>
                                <strong>Fee:</strong><br>
                                ₿${parseFloat(payment.fee_amount).toLocaleString('th-TH', {minimumFractionDigits: 2})}
                            </div>
                            <div>
                                <strong>Net Amount:</strong><br>
                                <span style="color: var(--sage); font-weight: 600;">
                                    ₿${parseFloat(payment.net_amount).toLocaleString('th-TH', {minimumFractionDigits: 2})}
                                </span>
                            </div>
                            <div>
                                <strong>Currency:</strong><br>
                                ${payment.currency}
                            </div>
                            ${payment.refund_amount > 0 ? `
                                <div>
                                    <strong>Refund Amount:</strong><br>
                                    <span style="color: #e74c3c; font-weight: 600;">
                                        ₿${parseFloat(payment.refund_amount).toLocaleString('th-TH', {minimumFractionDigits: 2})}
                                    </span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                    
                    <div>
                        <h4>Status & Method</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 1rem;">
                            <div>
                                <strong>Status:</strong><br>
                                <span class="status-badge status-${payment.status}">
                                    ${payment.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                </span>
                            </div>
                            <div>
                                <strong>Payment Method:</strong><br>
                                <span class="method-badge">
                                    ${payment.payment_method.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    ${payment.status_history ? `
                        <div>
                            <h4>Status History</h4>
                            <div style="background: #f8f9fa; padding: 1rem; border-radius: var(--radius-sm); margin-top: 1rem; white-space: pre-line;">
                                ${payment.status_history}
                            </div>
                        </div>
                    ` : ''}
                    
                    ${payment.billing_period_start && payment.billing_period_end ? `
                        <div>
                            <h4>Billing Period</h4>
                            <div style="margin-top: 1rem;">
                                <strong>Period:</strong> ${new Date(payment.billing_period_start).toLocaleDateString('th-TH')} - ${new Date(payment.billing_period_end).toLocaleDateString('th-TH')}
                            </div>
                        </div>
                    ` : ''}
                    
                    ${payment.description ? `
                        <div>
                            <h4>Description</h4>
                            <div style="background: var(--cream); padding: 1rem; border-radius: var(--radius-sm); margin-top: 1rem;">
                                ${payment.description}
                            </div>
                        </div>
                    ` : ''}
                    
                    ${payment.failure_reason ? `
                        <div>
                            <h4>Failure Information</h4>
                            <div style="background: rgba(231, 76, 60, 0.1); padding: 1rem; border-radius: var(--radius-sm); border-left: 3px solid #e74c3c; margin-top: 1rem;">
                                <strong>Reason:</strong> ${payment.failure_reason}
                            </div>
                        </div>
                    ` : ''}
                    
                    ${payment.refund_reason ? `
                        <div>
                            <h4>Refund Information</h4>
                            <div style="background: rgba(230, 126, 34, 0.1); padding: 1rem; border-radius: var(--radius-sm); border-left: 3px solid #e67e22; margin-top: 1rem;">
                                <strong>Refund Date:</strong> ${new Date(payment.refunded_at).toLocaleString('th-TH')}<br>
                                <strong>Reason:</strong> ${payment.refund_reason}
                            </div>
                        </div>
                    ` : ''}
                    
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <button class="btn btn-warning btn-sm" onclick="closeModal('viewModal'); updateStatus('${payment.id}');">
                            <i class="fas fa-edit"></i> Update Status
                        </button>
                        
                        ${payment.status === 'completed' && payment.refund_amount == 0 ? `
                            <button class="btn btn-danger btn-sm" onclick="closeModal('viewModal'); processRefund('${payment.id}');">
                                <i class="fas fa-undo"></i> Process Refund
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        // Update payment status
        function updateStatus(paymentId) {
            // Get current payment details first
            fetch('payments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_payment_details&id=${paymentId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const payment = data.data;
                    const currentStatus = payment.status;
                    
                    document.getElementById('statusPaymentId').value = paymentId;
                    document.getElementById('currentStatus').textContent = currentStatus.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
                    document.getElementById('statusNotes').value = '';
                    
                    // Reset and setup radio selections
                    document.querySelectorAll('input[name="status"]').forEach(radio => {
                        const statusOption = radio.closest('.status-option');
                        
                        if (radio.value === currentStatus) {
                            // Disable current status
                            radio.disabled = true;
                            statusOption.style.opacity = '0.5';
                            statusOption.style.cursor = 'not-allowed';
                            statusOption.style.background = '#f8f9fa';
                            
                            // Add disabled indicator
                            let disabledLabel = statusOption.querySelector('.disabled-label');
                            if (!disabledLabel) {
                                disabledLabel = document.createElement('small');
                                disabledLabel.className = 'disabled-label';
                                disabledLabel.style.color = '#6c757d';
                                disabledLabel.style.fontSize = '0.75rem';
                                disabledLabel.style.fontStyle = 'italic';
                                disabledLabel.textContent = ' (Current)';
                                statusOption.querySelector('label').appendChild(disabledLabel);
                            }
                        } else {
                            // Enable other statuses
                            radio.disabled = false;
                            radio.checked = false;
                            statusOption.style.opacity = '1';
                            statusOption.style.cursor = 'pointer';
                            statusOption.style.background = '';
                            
                            // Remove disabled indicator if exists
                            const disabledLabel = statusOption.querySelector('.disabled-label');
                            if (disabledLabel) {
                                disabledLabel.remove();
                            }
                        }
                    });
                    
                    updateStatusOptionSelection();
                    openModal('statusModal');
                } else {
                    showToast('Error loading payment details', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading payment details', 'error');
            });
        }

        function updateStatusOptionSelection() {
            document.querySelectorAll('.status-option').forEach(option => {
                const radio = option.querySelector('input[type="radio"]');
                if (radio && radio.checked) {
                    option.classList.add('selected');
                } else {
                    option.classList.remove('selected');
                }
            });
        }

        function submitStatusUpdate() {
            const paymentId = document.getElementById('statusPaymentId').value;
            const selectedStatus = document.querySelector('input[name="status"]:checked:not(:disabled)');
            const notes = document.getElementById('statusNotes').value.trim();
            
            if (!selectedStatus) {
                showToast('Please select a new status different from current status', 'error');
                return;
            }
            
            const currentStatusText = document.getElementById('currentStatus').textContent.toLowerCase().replace(' ', '_');
            
            if (selectedStatus.value === currentStatusText) {
                showToast('Please select a different status from the current one', 'error');
                return;
            }
            
            fetch('payments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_payment_status&id=${paymentId}&status=${selectedStatus.value}&notes=${encodeURIComponent(notes)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Payment status updated successfully', 'success');
                    closeModal('statusModal');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message || 'Error updating status', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error updating status', 'error');
            });
        }

        // Process refund
        function processRefund(paymentId) {
            // First get payment details to populate the form
            fetch('payments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_payment_details&id=${paymentId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const payment = data.data;
                    document.getElementById('refundPaymentId').value = paymentId;
                    document.getElementById('originalAmount').value = `₿${parseFloat(payment.amount).toLocaleString('th-TH', {minimumFractionDigits: 2})} ${payment.currency}`;
                    document.getElementById('refundAmount').value = '';
                    document.getElementById('refundAmount').max = payment.amount;
                    document.getElementById('refundReason').value = '';
                    openModal('refundModal');
                } else {
                    showToast('Error loading payment details', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading payment details', 'error');
            });
        }

        // Submit refund
        function submitRefund() {
            const paymentId = document.getElementById('refundPaymentId').value;
            const refundAmount = document.getElementById('refundAmount').value;
            const refundReason = document.getElementById('refundReason').value.trim();
            
            if (!refundAmount || !refundReason) {
                showToast('Please fill in all required fields', 'error');
                return;
            }
            
            if (parseFloat(refundAmount) <= 0) {
                showToast('Refund amount must be greater than 0', 'error');
                return;
            }
            
            fetch('payments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=process_refund&id=${paymentId}&amount=${refundAmount}&reason=${encodeURIComponent(refundReason)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Refund processed successfully', 'success');
                    closeModal('refundModal');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message || 'Error processing refund', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error processing refund', 'error');
            });
        }

        // Export payments
        function exportPayments() {
            const form = document.querySelector('.filters-form');
            const formData = new FormData(form);
            formData.append('action', 'export_payments');
            
            fetch('payments.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    return response.blob();
                }
                throw new Error('Export failed');
            })
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = 'payments_export_' + new Date().toISOString().split('T')[0] + '.csv';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                showToast('Export completed successfully', 'success');
            })
            .catch(error => {
                console.error('Export error:', error);
                showToast('Error exporting payments', 'error');
            });
        }

        // Refresh page
        function refreshPage() {
            location.reload();
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
            
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportPayments();
            }
            
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshPage();
            }
        });

        console.log('Enhanced Krua Thai Payments Management initialized successfully');
    </script>
</body>
</html>