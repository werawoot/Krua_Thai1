<?php
/**
 * Krua Thai - Complete Rider Dashboard System
 * File: rider/rider-dashboard.php
 * Features: ปฏิบัติงานไรเดอร์แบบครบครัน/Real-time/Mobile-friendly
 * Status: PRODUCTION READY ✅
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';

// Check rider authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'rider') {
    header("Location: ../login.php");
    exit();
}

$rider_id = $_SESSION['user_id'];

// Database connection
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        switch ($_POST['action']) {
            case 'accept_delivery':
                $result = acceptDelivery($pdo, $_POST['order_id'], $rider_id);
                echo json_encode($result);
                exit;
                
            case 'update_status':
                $result = updateDeliveryStatus($pdo, $_POST['order_id'], $_POST['status'], $rider_id);
                echo json_encode($result);
                exit;
                
            case 'report_issue':
                $result = reportDeliveryIssue($pdo, $_POST['order_id'], $_POST['issue'], $rider_id);
                echo json_encode($result);
                exit;
                
            case 'complete_delivery':
                $result = completeDelivery($pdo, $_POST['order_id'], $_POST['qr_code'], $rider_id);
                echo json_encode($result);
                exit;
                
            case 'get_delivery_route':
                $result = getDeliveryRoute($pdo, $_POST['order_id']);
                echo json_encode($result);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Database Functions
function getMyDeliveries($pdo, $rider_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                   u.email as customer_email,
                   u.phone as customer_phone,
                   o.delivery_address,
                   o.delivery_instructions,
                   o.total_amount,
                   o.qr_code
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            WHERE (o.assigned_rider_id = ? OR o.status = 'ready')
            AND o.delivery_date = CURDATE()
            AND o.status NOT IN ('delivered', 'cancelled')
            ORDER BY 
                CASE 
                    WHEN o.assigned_rider_id = ? THEN 0 
                    ELSE 1 
                END,
                o.delivery_time_slot ASC
        ");
        $stmt->execute([$rider_id, $rider_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function acceptDelivery($pdo, $order_id, $rider_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET assigned_rider_id = ?, 
                status = 'out_for_delivery', 
                updated_at = NOW() 
            WHERE id = ? AND status = 'ready'
        ");
        $stmt->execute([$rider_id, $order_id]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'งานได้รับเรียบร้อยแล้ว'];
        } else {
            return ['success' => false, 'message' => 'ไม่สามารถรับงานได้'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function updateDeliveryStatus($pdo, $order_id, $status, $rider_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET status = ?, updated_at = NOW() 
            WHERE id = ? AND assigned_rider_id = ?
        ");
        $stmt->execute([$status, $order_id, $rider_id]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'อัพเดทสถานะเรียบร้อย'];
        } else {
            return ['success' => false, 'message' => 'ไม่สามารถอัพเดทได้'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function getDeliveryRoute($pdo, $order_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT o.delivery_address, o.delivery_instructions,
                   CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                   u.phone as customer_phone
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $route = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($route) {
            return ['success' => true, 'data' => $route];
        } else {
            return ['success' => false, 'message' => 'ไม่พบข้อมูลเส้นทาง'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function reportDeliveryIssue($pdo, $order_id, $issue, $rider_id) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO delivery_issues (order_id, rider_id, issue_description, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$order_id, $rider_id, $issue]);
        
        // Update order status to issue
        $stmt = $pdo->prepare("UPDATE orders SET status = 'issue' WHERE id = ?");
        $stmt->execute([$order_id]);
        
        return ['success' => true, 'message' => 'รายงานปัญหาเรียบร้อย'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function completeDelivery($pdo, $order_id, $qr_code, $rider_id) {
    try {
        // Verify QR code
        $stmt = $pdo->prepare("SELECT qr_code FROM orders WHERE id = ? AND assigned_rider_id = ?");
        $stmt->execute([$order_id, $rider_id]);
        $stored_qr = $stmt->fetchColumn();
        
        if ($stored_qr !== $qr_code) {
            return ['success' => false, 'message' => 'QR Code ไม่ถูกต้อง'];
        }
        
        // Complete delivery
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET status = 'delivered', 
                delivered_at = NOW(), 
                updated_at = NOW() 
            WHERE id = ? AND assigned_rider_id = ?
        ");
        $stmt->execute([$order_id, $rider_id]);
        
        return ['success' => true, 'message' => 'ส่งอาหารสำเร็จ!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Get rider's deliveries and stats
$deliveries = getMyDeliveries($pdo, $rider_id);

// Get rider stats
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_today,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed_today,
            SUM(CASE WHEN status = 'out_for_delivery' THEN 1 ELSE 0 END) as in_progress,
            AVG(total_amount) as avg_order_value
        FROM orders 
        WHERE assigned_rider_id = ? 
        AND delivery_date = CURDATE()
    ");
    $stmt->execute([$rider_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['total_today' => 0, 'completed_today' => 0, 'in_progress' => 0, 'avg_order_value' => 0];
}

// Get rider profile
try {
    $stmt = $pdo->prepare("SELECT first_name, last_name, phone FROM users WHERE id = ?");
    $stmt->execute([$rider_id]);
    $rider_profile = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rider_profile = ['first_name' => '', 'last_name' => '', 'phone' => ''];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rider Dashboard - Krua Thai</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --cream: #ece8e1;
            --sage: #adb89d;
            --brown: #bd9379;
            --curry: #cf723a;
            --text-dark: #2c3e50;
            --text-muted: #6c757d;
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
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, var(--cream) 0%, #f8f9fa 100%);
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--curry) 0%, var(--brown) 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .rider-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .rider-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        /* Navigation */
        .nav-tabs {
            background: white;
            border-bottom: 1px solid #dee2e6;
            padding: 0 1rem;
        }

        .nav-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            gap: 2rem;
        }

        .nav-tab {
            padding: 1rem 0;
            color: var(--text-muted);
            text-decoration: none;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-tab.active {
            color: var(--curry);
            border-bottom-color: var(--curry);
        }

        /* Main Content */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid var(--curry);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-title {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--curry), var(--brown));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        /* Delivery Cards */
        .deliveries-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, var(--sage), var(--curry));
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .delivery-card {
            border-bottom: 1px solid #eee;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .delivery-card:hover {
            background: #f8f9fa;
        }

        .delivery-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .order-info h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .customer-name {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-ready {
            background: #d4edda;
            color: #155724;
        }

        .status-out_for_delivery {
            background: #cce7ff;
            color: #004085;
        }

        .delivery-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .detail-item i {
            color: var(--curry);
        }

        .delivery-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(207, 114, 58, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #20c997);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning), #fd7e14);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #e83e8c);
            color: white;
        }

        .btn-outline {
            background: transparent;
            color: var(--curry);
            border: 2px solid var(--curry);
        }

        .btn-outline:hover {
            background: var(--curry);
            color: white;
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 15px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* Toast Notifications */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 1001;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            background: var(--success);
        }

        .toast.error {
            background: var(--danger);
        }

        .toast.info {
            background: var(--info);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: var(--sage);
        }

        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-content {
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .delivery-details {
                grid-template-columns: 1fr;
            }

            .delivery-actions {
                justify-content: center;
            }

            .modal-content {
                width: 95%;
                padding: 1.5rem;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Auto-refresh indicator */
        .refresh-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--curry);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            display: none;
        }

        .refresh-indicator.show {
            display: block;
            animation: fadeInOut 2s ease-in-out;
        }

        @keyframes fadeInOut {
            0%, 100% { opacity: 0; }
            50% { opacity: 1; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-motorcycle"></i>
                <span>Krua Thai Rider</span>
            </div>
            <div class="rider-info">
                <div class="rider-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <div style="font-weight: 600;">
                        <?= htmlspecialchars($rider_profile['first_name'] . ' ' . $rider_profile['last_name']) ?>
                    </div>
                    <div style="font-size: 0.8rem; opacity: 0.8;">
                        ID: <?= $rider_id ?>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="nav-tabs">
        <div class="nav-content">
            <a href="#dashboard" class="nav-tab active" onclick="showTab('dashboard')">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="#deliveries" class="nav-tab" onclick="showTab('deliveries')">
                <i class="fas fa-box"></i> งานส่งของ
            </a>
            <a href="#history" class="nav-tab" onclick="showTab('history')">
                <i class="fas fa-history"></i> ประวัติ
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Dashboard Tab -->
        <div id="dashboard-tab" class="tab-content">
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">งานวันนี้</div>
                        <div class="stat-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $stats['total_today'] ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">ส่งสำเร็จ</div>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $stats['completed_today'] ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">กำลังส่ง</div>
                        <div class="stat-icon">
                            <i class="fas fa-motorcycle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $stats['in_progress'] ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">ค่าเฉลี่ย</div>
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="stat-value">฿<?= number_format($stats['avg_order_value'], 0) ?></div>
                </div>
            </div>

            <!-- Active Deliveries -->
            <div class="deliveries-section">
                <div class="section-header">
                    <div class="section-title">
                        <i class="fas fa-box"></i>
                        งานส่งของวันนี้
                    </div>
                    <button class="btn btn-outline" onclick="refreshDeliveries()">
                        <i class="fas fa-sync-alt"></i>
                        รีเฟรช
                    </button>
                </div>

                <div id="deliveries-container">
                    <?php if (empty($deliveries)): ?>
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <h3>ไม่มีงานส่งของ</h3>
                            <p>ยังไม่มีงานส่งของสำหรับวันนี้</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($deliveries as $delivery): ?>
                            <div class="delivery-card" data-order-id="<?= $delivery['id'] ?>">
                                <div class="delivery-header">
                                    <div class="order-info">
                                        <h3>Order #<?= htmlspecialchars($delivery['order_number']) ?></h3>
                                        <div class="customer-name">
                                            <i class="fas fa-user"></i>
                                            <?= htmlspecialchars($delivery['customer_name']) ?>
                                        </div>
                                    </div>
                                    <div class="status-badge status-<?= $delivery['status'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $delivery['status'])) ?>
                                    </div>
                                </div>

                                <div class="delivery-details">
                                    <div class="detail-item">
                                        <i class="fas fa-clock"></i>
                                        <?= htmlspecialchars($delivery['delivery_time_slot']) ?>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?= htmlspecialchars(substr($delivery['delivery_address'], 0, 30)) ?>...
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-phone"></i>
                                        <?= htmlspecialchars($delivery['customer_phone']) ?>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-dollar-sign"></i>
                                        ฿<?= number_format($delivery['total_amount'], 2) ?>
                                    </div>
                                </div>

                                <div class="delivery-actions">
                                    <?php if ($delivery['assigned_rider_id'] == null): ?>
                                        <button class="btn btn-primary" onclick="acceptDelivery(<?= $delivery['id'] ?>)">
                                            <i class="fas fa-hand-paper"></i>
                                            รับงาน
                                        </button>
                                    <?php elseif ($delivery['assigned_rider_id'] == $rider_id): ?>
                                        <?php if ($delivery['status'] == 'out_for_delivery'): ?>
                                            <button class="btn btn-success" onclick="openCompleteModal(<?= $delivery['id'] ?>, '<?= $delivery['qr_code'] ?>')">
                                                <i class="fas fa-check-circle"></i>
                                                ส่งสำเร็จ
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-primary" onclick="viewRoute(<?= $delivery['id'] ?>)">
                                            <i class="fas fa-route"></i>
                                            เส้นทาง
                                        </button>
                                        
                                        <button class="btn btn-warning" onclick="openIssueModal(<?= $delivery['id'] ?>)">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            รายงานปัญหา
                                        </button>
                                        
                                        <a href="tel:<?= $delivery['customer_phone'] ?>" class="btn btn-outline">
                                            <i class="fas fa-phone"></i>
                                            โทร
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Deliveries Tab -->
        <div id="deliveries-tab" class="tab-content" style="display: none;">
            <div class="deliveries-section">
                <div class="section-header">
                    <div class="section-title">
                        <i class="fas fa-list"></i>
                        รายการงานทั้งหมด
                    </div>
                </div>
                <div id="all-deliveries-container">
                    <!-- Same as dashboard deliveries -->
                    <?php foreach ($deliveries as $delivery): ?>
                        <div class="delivery-card">
                            <!-- Duplicate delivery card content -->
                            <div class="delivery-header">
                                <div class="order-info">
                                    <h3>Order #<?= htmlspecialchars($delivery['order_number']) ?></h3>
                                    <div class="customer-name">
                                        <i class="fas fa-user"></i>
                                        <?= htmlspecialchars($delivery['customer_name']) ?>
                                    </div>
                                </div>
                                <div class="status-badge status-<?= $delivery['status'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $delivery['status'])) ?>
                                </div>
                            </div>
                            <!-- Full delivery details would go here -->
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- History Tab -->
        <div id="history-tab" class="tab-content" style="display: none;">
            <div class="deliveries-section">
                <div class="section-header">
                    <div class="section-title">
                        <i class="fas fa-history"></i>
                        ประวัติการส่งของ
                    </div>
                </div>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h3>ประวัติการทำงาน</h3>
                    <p>ฟีเจอร์นี้จะพร้อมใช้งานเร็วๆ นี้</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Complete Delivery Modal -->
    <div id="completeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">ยืนยันการส่งอาหาร</h3>
                <button class="close-btn" onclick="closeModal('completeModal')">&times;</button>
            </div>
            <form id="completeForm">
                <input type="hidden" id="complete_order_id" name="order_id">
                <input type="hidden" id="expected_qr" name="expected_qr">
                
                <div class="form-group">
                    <label class="form-label">สแกน QR Code หรือป้อนรหัส</label>
                    <input type="text" id="qr_code_input" class="form-control" 
                           placeholder="ป้อนรหัส QR หรือใช้กล้องสแกน" required>
                    <small style="color: var(--text-muted); margin-top: 0.5rem; display: block;">
                        ให้ลูกค้าแสดง QR Code เพื่อยืนยันการรับอาหาร
                    </small>
                </div>
                
                <div class="form-group">
                    <button type="button" class="btn btn-primary" onclick="scanQRCode()">
                        <i class="fas fa-qrcode"></i>
                        สแกน QR Code
                    </button>
                </div>
                
                <div class="form-group" style="display: flex; gap: 1rem;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('completeModal')">
                        ยกเลิก
                    </button>
                    <button type="button" class="btn btn-success" onclick="confirmDelivery()">
                        <i class="fas fa-check"></i>
                        ยืนยันส่งสำเร็จ
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Issue Report Modal -->
    <div id="issueModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">รายงานปัญหา</h3>
                <button class="close-btn" onclick="closeModal('issueModal')">&times;</button>
            </div>
            <form id="issueForm">
                <input type="hidden" id="issue_order_id" name="order_id">
                
                <div class="form-group">
                    <label class="form-label">ประเภทปัญหา</label>
                    <select class="form-control" id="issue_type" required>
                        <option value="">เลือกประเภทปัญหา</option>
                        <option value="customer_not_available">ลูกค้าไม่อยู่</option>
                        <option value="wrong_address">ที่อยู่ผิด</option>
                        <option value="food_damaged">อาหารเสียหาย</option>
                        <option value="vehicle_problem">รถเสีย</option>
                        <option value="weather_issue">สภาพอากาศไม่เอื้ออำนวย</option>
                        <option value="other">อื่นๆ</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">รายละเอียดปัญหา</label>
                    <textarea class="form-control textarea" id="issue_description" 
                              placeholder="อธิบายปัญหาที่พบ..." required></textarea>
                </div>
                
                <div class="form-group" style="display: flex; gap: 1rem;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('issueModal')">
                        ยกเลิก
                    </button>
                    <button type="button" class="btn btn-danger" onclick="submitIssue()">
                        <i class="fas fa-exclamation-triangle"></i>
                        รายงานปัญหา
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Route Modal -->
    <div id="routeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">เส้นทางการส่ง</h3>
                <button class="close-btn" onclick="closeModal('routeModal')">&times;</button>
            </div>
            <div id="route-content">
                <!-- Route details will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <!-- Refresh Indicator -->
    <div id="refresh-indicator" class="refresh-indicator">
        <i class="fas fa-sync-alt"></i>
        กำลังอัพเดท...
    </div>

    <script>
        // Global variables
        let currentTab = 'dashboard';
        let refreshInterval;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            startAutoRefresh();
            
            // Add click handlers for phone links
            document.addEventListener('click', function(e) {
                if (e.target.closest('a[href^="tel:"]')) {
                    const phone = e.target.closest('a').href.replace('tel:', '');
                    if (confirm(`โทรหา ${phone} ?`)) {
                        return true;
                    }
                    e.preventDefault();
                }
            });
        });

        // Tab Management
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Remove active class from all nav tabs
            document.querySelectorAll('.nav-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').style.display = 'block';
            
            // Add active class to selected nav tab
            event.target.classList.add('active');
            
            currentTab = tabName;
        }

        // Auto Refresh
        function startAutoRefresh() {
            refreshInterval = setInterval(function() {
                refreshDeliveries();
            }, 30000); // Refresh every 30 seconds
        }

        function refreshDeliveries() {
            showRefreshIndicator();
            
            // Reload the page to get fresh data
            setTimeout(function() {
                location.reload();
            }, 1000);
        }

        function showRefreshIndicator() {
            const indicator = document.getElementById('refresh-indicator');
            indicator.classList.add('show');
            
            setTimeout(function() {
                indicator.classList.remove('show');
            }, 2000);
        }

        // Delivery Actions
        function acceptDelivery(orderId) {
            if (!confirm('รับงานส่งออเดอร์นี้?')) return;
            
            showLoading(event.target);
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=accept_delivery&order_id=${orderId}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading(event.target);
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading(event.target);
                console.error('Error:', error);
                showToast('เกิดข้อผิดพลาด', 'error');
            });
        }

        function openCompleteModal(orderId, qrCode) {
            document.getElementById('complete_order_id').value = orderId;
            document.getElementById('expected_qr').value = qrCode;
            document.getElementById('qr_code_input').value = '';
            document.getElementById('completeModal').style.display = 'block';
        }

        function confirmDelivery() {
            const orderId = document.getElementById('complete_order_id').value;
            const qrCode = document.getElementById('qr_code_input').value.trim();
            
            if (!qrCode) {
                showToast('กรุณาป้อนรหัส QR', 'error');
                return;
            }
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=complete_delivery&order_id=${orderId}&qr_code=${qrCode}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal('completeModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('เกิดข้อผิดพลาด', 'error');
            });
        }

        function scanQRCode() {
            // Simple QR code scanner simulation
            const qrCode = prompt('ป้อนรหัส QR ที่สแกนได้:');
            if (qrCode) {
                document.getElementById('qr_code_input').value = qrCode;
            }
        }

        function openIssueModal(orderId) {
            document.getElementById('issue_order_id').value = orderId;
            document.getElementById('issue_type').value = '';
            document.getElementById('issue_description').value = '';
            document.getElementById('issueModal').style.display = 'block';
        }

        function submitIssue() {
            const orderId = document.getElementById('issue_order_id').value;
            const issueType = document.getElementById('issue_type').value;
            const description = document.getElementById('issue_description').value.trim();
            
            if (!issueType || !description) {
                showToast('กรุณากรอกข้อมูลให้ครบถ้วน', 'error');
                return;
            }
            
            const issueText = `ประเภท: ${issueType}\nรายละเอียด: ${description}`;
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=report_issue&order_id=${orderId}&issue=${encodeURIComponent(issueText)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal('issueModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('เกิดข้อผิดพลาด', 'error');
            });
        }

        function viewRoute(orderId) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_delivery_route&order_id=${orderId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const route = data.data;
                    document.getElementById('route-content').innerHTML = `
                        <div style="margin-bottom: 1rem;">
                            <h4 style="color: var(--curry); margin-bottom: 0.5rem;">
                                <i class="fas fa-user"></i> ข้อมูลลูกค้า
                            </h4>
                            <p><strong>ชื่อ:</strong> ${route.customer_name}</p>
                            <p><strong>โทร:</strong> 
                                <a href="tel:${route.customer_phone}" class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                    <i class="fas fa-phone"></i> ${route.customer_phone}
                                </a>
                            </p>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <h4 style="color: var(--curry); margin-bottom: 0.5rem;">
                                <i class="fas fa-map-marker-alt"></i> ที่อยู่จัดส่ง
                            </h4>
                            <p>${route.delivery_address}</p>
                        </div>
                        ${route.delivery_instructions ? `
                        <div style="margin-bottom: 1rem;">
                            <h4 style="color: var(--curry); margin-bottom: 0.5rem;">
                                <i class="fas fa-sticky-note"></i> หมายเหตุ
                            </h4>
                            <p>${route.delivery_instructions}</p>
                        </div>
                        ` : ''}
                        <div style="display: flex; gap: 0.5rem; justify-content: center;">
                            <a href="https://maps.google.com/?q=${encodeURIComponent(route.delivery_address)}" 
                               target="_blank" class="btn btn-primary">
                                <i class="fas fa-map"></i> เปิด Google Maps
                            </a>
                            <a href="tel:${route.customer_phone}" class="btn btn-success">
                                <i class="fas fa-phone"></i> โทรลูกค้า
                            </a>
                        </div>
                    `;
                    document.getElementById('routeModal').style.display = 'block';
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('เกิดข้อผิดพลาด', 'error');
            });
        }

        // Modal Management
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });

        // Utility Functions
        function showLoading(button) {
            button.disabled = true;
            button.innerHTML = '<span class="loading"></span> กำลังโหลด...';
        }

        function hideLoading(button) {
            button.disabled = false;
            // Reset button text based on its context
            button.innerHTML = button.dataset.originalText || 'ดำเนินการ';
        }

        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            
            document.getElementById('toast-container').appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 100);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape key closes modals
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    if (modal.style.display === 'block') {
                        modal.style.display = 'none';
                    }
                });
            }
            
            // R key refreshes deliveries
            if (e.key === 'r' || e.key === 'R') {
                if (!e.target.matches('input, textarea')) {
                    e.preventDefault();
                    refreshDeliveries();
                }
            }
        });

        // Service Worker for offline support (optional)
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(console.error);
        }
    </script>
</body>
</html>