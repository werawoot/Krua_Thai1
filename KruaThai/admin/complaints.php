<?php
/**
 * Krua Thai - Complaints Management
 * File: admin/complaints.php
 * Description: Complete complaints management system with tracking and resolution
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
            case 'update_status':
                $result = updateComplaintStatus($pdo, $_POST['id'], $_POST['status']);
                echo json_encode($result);
                exit;
                
            case 'update_priority':
                $result = updateComplaintPriority($pdo, $_POST['id'], $_POST['priority']);
                echo json_encode($result);
                exit;
                
            case 'assign_complaint':
                $result = assignComplaint($pdo, $_POST['id'], $_POST['assigned_to']);
                echo json_encode($result);
                exit;
                
            case 'add_resolution':
                $result = addResolution($pdo, $_POST['id'], $_POST['resolution'], $_SESSION['user_id']);
                echo json_encode($result);
                exit;
                
            case 'get_complaint':
                $result = getComplaintDetails($pdo, $_POST['id']);
                echo json_encode($result);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Database Functions
function updateComplaintStatus($pdo, $complaintId, $status) {
    try {
        $resolvedAt = ($status === 'resolved' || $status === 'closed') ? 'NOW()' : 'NULL';
        $stmt = $pdo->prepare("UPDATE complaints SET status = ?, resolution_date = $resolvedAt, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $complaintId]);
        return ['success' => true, 'message' => 'Status updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function updateComplaintPriority($pdo, $complaintId, $priority) {
    try {
        $stmt = $pdo->prepare("UPDATE complaints SET priority = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$priority, $complaintId]);
        return ['success' => true, 'message' => 'Priority updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function assignComplaint($pdo, $complaintId, $assignedTo) {
    try {
        $stmt = $pdo->prepare("UPDATE complaints SET assigned_to = ?, status = 'in_progress', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$assignedTo, $complaintId]);
        return ['success' => true, 'message' => 'Complaint assigned successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function addResolution($pdo, $complaintId, $resolution, $adminId) {
    try {
        $stmt = $pdo->prepare("UPDATE complaints SET resolution = ?, status = 'resolved', resolution_date = NOW(), assigned_to = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$resolution, $adminId, $complaintId]);
        return ['success' => true, 'message' => 'Resolution added successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function getComplaintDetails($pdo, $complaintId) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                   u.email as customer_email,
                   u.phone as customer_phone,
                   o.order_number,
                   CONCAT(assigned.first_name, ' ', assigned.last_name) as assigned_name
            FROM complaints c
            JOIN users u ON c.user_id = u.id
            LEFT JOIN orders o ON c.order_id = o.id
            LEFT JOIN users assigned ON c.assigned_to = assigned.id
            WHERE c.id = ?
        ");
        $stmt->execute([$complaintId]);
        $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($complaint) {
            return ['success' => true, 'data' => $complaint];
        } else {
            return ['success' => false, 'message' => 'Complaint not found'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Fetch Data
try {
    // Filters
    $statusFilter = $_GET['status'] ?? '';
    $priorityFilter = $_GET['priority'] ?? '';
    $categoryFilter = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';
    $sortBy = $_GET['sort'] ?? 'created_at';
    
    // Main complaints query
    $whereConditions = ['1=1'];
    $params = [];
    
    if ($statusFilter) {
        $whereConditions[] = 'c.status = ?';
        $params[] = $statusFilter;
    }
    
    if ($priorityFilter) {
        $whereConditions[] = 'c.priority = ?';
        $params[] = $priorityFilter;
    }
    
    if ($categoryFilter) {
        $whereConditions[] = 'c.category = ?';
        $params[] = $categoryFilter;
    }
    
    if ($search) {
        $whereConditions[] = '(c.title LIKE ? OR c.description LIKE ? OR CONCAT(u.first_name, " ", u.last_name) LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $stmt = $pdo->prepare("
        SELECT c.*, 
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.email as customer_email,
               o.order_number,
               CONCAT(assigned.first_name, ' ', assigned.last_name) as assigned_name
        FROM complaints c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN orders o ON c.order_id = o.id
        LEFT JOIN users assigned ON c.assigned_to = assigned.id
        WHERE $whereClause
        ORDER BY c.$sortBy DESC
        LIMIT 50
    ");
    $stmt->execute($params);
    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_complaints,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_complaints,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_complaints,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_complaints,
            SUM(CASE WHEN priority = 'critical' THEN 1 ELSE 0 END) as critical_complaints,
            AVG(CASE 
                WHEN resolution_date IS NOT NULL AND created_at IS NOT NULL 
                THEN TIMESTAMPDIFF(HOUR, created_at, resolution_date) 
                ELSE NULL 
            END) as avg_resolution_time_hours
        FROM complaints
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get admin users for assignment
    $stmt = $pdo->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE role IN ('admin', 'support') AND status = 'active'");
    $stmt->execute();
    $adminUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $complaints = [];
    $stats = ['total_complaints' => 0, 'open_complaints' => 0, 'in_progress_complaints' => 0, 'resolved_complaints' => 0, 'critical_complaints' => 0, 'avg_resolution_time_hours' => 0];
    $adminUsers = [];
    error_log("Complaints error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaints Management - Krua Thai Admin</title>
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
            grid-template-columns: 1fr 150px 150px 150px 150px 150px;
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

        /* Complaints Grid */
        .complaints-grid {
            display: grid;
            gap: 1.5rem;
        }

        .complaint-card {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            overflow: hidden;
            transition: var(--transition);
        }

        .complaint-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .complaint-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .complaint-meta {
            flex: 1;
        }

        .complaint-number {
            font-weight: 600;
            color: var(--curry);
            margin-bottom: 0.25rem;
        }

        .complaint-customer {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .complaint-date {
            font-size: 0.8rem;
            color: var(--text-gray);
            margin-bottom: 0.5rem;
        }

        .complaint-actions {
            display: flex;
            gap: 0.5rem;
        }

        .complaint-body {
            padding: 1.5rem;
        }

        .complaint-title {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .complaint-content {
            color: var(--text-dark);
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .complaint-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-light);
        }

        .complaint-detail {
            text-align: center;
        }

        .complaint-detail-label {
            font-size: 0.8rem;
            color: var(--text-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .complaint-detail-value {
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

        .status-open {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .status-in_progress {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .status-resolved {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .status-closed {
            background: rgba(149, 165, 166, 0.1);
            color: #95a5a6;
        }

        .status-escalated {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .priority-low {
            background: rgba(149, 165, 166, 0.1);
            color: #95a5a6;
        }

        .priority-medium {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .priority-high {
            background: rgba(230, 126, 34, 0.1);
            color: #e67e22;
        }

        .priority-critical {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .category-badge {
            background: var(--cream);
            color: var(--text-dark);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Resolution Section */
        .resolution {
            background: var(--cream);
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-top: 1rem;
        }

        .resolution-header {
            font-size: 0.8rem;
            color: var(--text-gray);
            margin-bottom: 0.5rem;
        }

        .resolution-content {
            color: var(--text-dark);
            line-height: 1.6;
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
            max-width: 800px;
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
                    <a href="reviews.php" class="nav-item ">
                        <i class="nav-icon fas fa-star"></i>
                        <span>Reviews</span>
                    </a>
                    <a href="complaints.php" class="nav-item active">
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
                    <a href="../logout.php" class="nav-item" onclick="logout()" style="color: rgba(255, 255, 255, 0.9);">
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
                        <h1 class="page-title">Complaints Management</h1>
                        <p class="page-subtitle">Track and resolve customer complaints efficiently</p>
                    </div>
                    <div class="header-actions">
                        <button type="button" class="btn btn-secondary" onclick="exportComplaints()">
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

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_complaints']); ?></div>
                    <div class="stat-label">Total Complaints</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['open_complaints']); ?></div>
                    <div class="stat-label">Open Complaints</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['in_progress_complaints']); ?></div>
                    <div class="stat-label">In Progress</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #27ae60, #229954);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['resolved_complaints']); ?></div>
                    <div class="stat-label">Resolved</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                            <i class="fas fa-fire"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['critical_complaints']); ?></div>
                    <div class="stat-label">Critical Priority</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--curry), var(--brown));">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo round($stats['avg_resolution_time_hours'] ?? 0, 1); ?>h</div>
                    <div class="stat-label">Avg Resolution Time</div>
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
                                   placeholder="Search complaints, customers..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="open" <?php echo $statusFilter === 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="closed" <?php echo $statusFilter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                <option value="escalated" <?php echo $statusFilter === 'escalated' ? 'selected' : ''; ?>>Escalated</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-control">
                                <option value="">All Priority</option>
                                <option value="low" <?php echo $priorityFilter === 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo $priorityFilter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo $priorityFilter === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="critical" <?php echo $priorityFilter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-control">
                                <option value="">All Categories</option>
                                <option value="food_quality" <?php echo $categoryFilter === 'food_quality' ? 'selected' : ''; ?>>Food Quality</option>
                                <option value="delivery_late" <?php echo $categoryFilter === 'delivery_late' ? 'selected' : ''; ?>>Late Delivery</option>
                                <option value="delivery_wrong" <?php echo $categoryFilter === 'delivery_wrong' ? 'selected' : ''; ?>>Wrong Delivery</option>
                                <option value="missing_items" <?php echo $categoryFilter === 'missing_items' ? 'selected' : ''; ?>>Missing Items</option>
                                <option value="damaged_package" <?php echo $categoryFilter === 'damaged_package' ? 'selected' : ''; ?>>Damaged Package</option>
                                <option value="customer_service" <?php echo $categoryFilter === 'customer_service' ? 'selected' : ''; ?>>Customer Service</option>
                                <option value="billing" <?php echo $categoryFilter === 'billing' ? 'selected' : ''; ?>>Billing</option>
                                <option value="other" <?php echo $categoryFilter === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Sort By</label>
                            <select name="sort" class="form-control">
                                <option value="created_at" <?php echo $sortBy === 'created_at' ? 'selected' : ''; ?>>Date Created</option>
                                <option value="priority" <?php echo $sortBy === 'priority' ? 'selected' : ''; ?>>Priority</option>
                                <option value="status" <?php echo $sortBy === 'status' ? 'selected' : ''; ?>>Status</option>
                                <option value="updated_at" <?php echo $sortBy === 'updated_at' ? 'selected' : ''; ?>>Last Updated</option>
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

            <!-- Complaints Grid -->
            <div class="complaints-grid">
                <?php if (empty($complaints)): ?>
                    <div class="text-center" style="padding: 3rem; color: var(--text-gray);">
                        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                        <h3>No Complaints Found</h3>
                        <p>No complaints match your current filters.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($complaints as $complaint): ?>
                        <div class="complaint-card">
                            <div class="complaint-header">
                                <div class="complaint-meta">
                                    <div class="complaint-number">#<?php echo htmlspecialchars($complaint['complaint_number']); ?></div>
                                    <div class="complaint-customer"><?php echo htmlspecialchars($complaint['customer_name']); ?></div>
                                    <div class="complaint-date">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('d/m/Y H:i', strtotime($complaint['created_at'])); ?>
                                    </div>
                                    <?php if ($complaint['order_number']): ?>
                                        <div class="complaint-date">
                                            <i class="fas fa-shopping-cart"></i>
                                            Order: <?php echo htmlspecialchars($complaint['order_number']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="complaint-actions">
                                    <button type="button" class="btn btn-sm btn-secondary btn-icon" 
                                            onclick="viewComplaint('<?php echo $complaint['id']; ?>')" 
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary btn-icon" 
                                            onclick="resolveComplaint('<?php echo $complaint['id']; ?>')" 
                                            title="Add Resolution">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="complaint-body">
                                <div class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></div>
                                <div class="complaint-content">
                                    <?php echo nl2br(htmlspecialchars(substr($complaint['description'], 0, 200))); ?>
                                    <?php if (strlen($complaint['description']) > 200): ?>
                                        <span style="color: var(--text-gray);">...</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="complaint-details">
                                    <div class="complaint-detail">
                                        <div class="complaint-detail-label">Status</div>
                                        <div class="complaint-detail-value">
                                            <span class="status-badge status-<?php echo $complaint['status']; ?>">
                                                <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                                <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="complaint-detail">
                                        <div class="complaint-detail-label">Priority</div>
                                        <div class="complaint-detail-value">
                                            <span class="status-badge priority-<?php echo $complaint['priority']; ?>">
                                                <?php echo ucfirst($complaint['priority']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="complaint-detail">
                                        <div class="complaint-detail-label">Category</div>
                                        <div class="complaint-detail-value">
                                            <span class="category-badge">
                                                <?php echo ucfirst(str_replace('_', ' ', $complaint['category'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="complaint-detail">
                                        <div class="complaint-detail-label">Assigned To</div>
                                        <div class="complaint-detail-value">
                                            <?php echo $complaint['assigned_name'] ?: 'Unassigned'; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($complaint['resolution']): ?>
                                    <div class="resolution">
                                        <div class="resolution-header">
                                            <i class="fas fa-check-circle"></i>
                                            Resolution (<?php echo date('d/m/Y H:i', strtotime($complaint['resolution_date'])); ?>)
                                        </div>
                                        <div class="resolution-content">
                                            <?php echo nl2br(htmlspecialchars($complaint['resolution'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- View Complaint Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Complaint Details</h2>
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

    <!-- Resolution Modal -->
    <div id="resolutionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add Resolution</h2>
                <button type="button" class="modal-close" onclick="closeModal('resolutionModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="resolutionForm">
                    <input type="hidden" id="resolutionComplaintId" name="complaint_id">
                    <div class="form-group">
                        <label class="form-label">Resolution Details</label>
                        <textarea id="resolutionText" 
                                  name="resolution" 
                                  class="form-control" 
                                  rows="5" 
                                  placeholder="Describe how this complaint was resolved..."
                                  required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('resolutionModal')">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitResolution()">
                    <i class="fas fa-check"></i>
                    Save Resolution
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Auto-submit form when filters change
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.filters-form');
            const inputs = form.querySelectorAll('select, input');
            
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
        });

        // View complaint details
        function viewComplaint(complaintId) {
            fetch('complaints.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_complaint&id=${complaintId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const complaint = data.data;
                    document.getElementById('viewModalBody').innerHTML = `
                        <div style="display: grid; gap: 1.5rem;">
                            <div>
                                <h4>Complaint #${complaint.complaint_number}</h4>
                                <p><strong>Customer:</strong> ${complaint.customer_name}</p>
                                <p><strong>Email:</strong> ${complaint.customer_email}</p>
                                <p><strong>Phone:</strong> ${complaint.customer_phone || 'N/A'}</p>
                                <p><strong>Order:</strong> ${complaint.order_number || 'N/A'}</p>
                            </div>
                            
                            <div>
                                <h5>Details</h5>
                                <p><strong>Title:</strong> ${complaint.title}</p>
                                <p><strong>Description:</strong></p>
                                <div style="background: var(--cream); padding: 1rem; border-radius: var(--radius-sm);">
                                    ${complaint.description.replace(/\n/g, '<br>')}
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                                <div>
                                    <strong>Status:</strong><br>
                                    <span class="status-badge status-${complaint.status}">
                                        ${complaint.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                    </span>
                                </div>
                                <div>
                                    <strong>Priority:</strong><br>
                                    <span class="status-badge priority-${complaint.priority}">
                                        ${complaint.priority.replace(/\b\w/g, l => l.toUpperCase())}
                                    </span>
                                </div>
                                <div>
                                    <strong>Category:</strong><br>
                                    <span class="category-badge">
                                        ${complaint.category.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                    </span>
                                </div>
                                <div>
                                    <strong>Assigned To:</strong><br>
                                    ${complaint.assigned_name || 'Unassigned'}
                                </div>
                            </div>
                            
                            ${complaint.resolution ? `
                                <div>
                                    <h5>Resolution</h5>
                                    <div style="background: var(--cream); padding: 1rem; border-radius: var(--radius-sm);">
                                        ${complaint.resolution.replace(/\n/g, '<br>')}
                                    </div>
                                    <small style="color: var(--text-gray);">
                                        Resolved on ${new Date(complaint.resolution_date).toLocaleDateString('th-TH')}
                                    </small>
                                </div>
                            ` : ''}
                            
                            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                <select onchange="updateStatus('${complaint.id}', this.value)" class="form-control" style="width: auto;">
                                    <option value="">Change Status</option>
                                    <option value="open" ${complaint.status === 'open' ? 'selected' : ''}>Open</option>
                                    <option value="in_progress" ${complaint.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                                    <option value="resolved" ${complaint.status === 'resolved' ? 'selected' : ''}>Resolved</option>
                                    <option value="closed" ${complaint.status === 'closed' ? 'selected' : ''}>Closed</option>
                                    <option value="escalated" ${complaint.status === 'escalated' ? 'selected' : ''}>Escalated</option>
                                </select>
                                
                                <select onchange="updatePriority('${complaint.id}', this.value)" class="form-control" style="width: auto;">
                                    <option value="">Change Priority</option>
                                    <option value="low" ${complaint.priority === 'low' ? 'selected' : ''}>Low</option>
                                    <option value="medium" ${complaint.priority === 'medium' ? 'selected' : ''}>Medium</option>
                                    <option value="high" ${complaint.priority === 'high' ? 'selected' : ''}>High</option>
                                    <option value="critical" ${complaint.priority === 'critical' ? 'selected' : ''}>Critical</option>
                                </select>
                                
                                <select onchange="assignComplaint('${complaint.id}', this.value)" class="form-control" style="width: auto;">
                                    <option value="">Assign To</option>
                                    <?php foreach ($adminUsers as $admin): ?>
                                        <option value="<?php echo $admin['id']; ?>">${complaint.assigned_to === '<?php echo $admin['id']; ?>' ? 'selected' : ''}><?php echo htmlspecialchars($admin['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    `;
                    openModal('viewModal');
                } else {
                    showToast('Error loading complaint details', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading complaint details', 'error');
            });
        }

        // Open resolution modal
        function resolveComplaint(complaintId) {
            document.getElementById('resolutionComplaintId').value = complaintId;
            document.getElementById('resolutionText').value = '';
            openModal('resolutionModal');
        }

        // Submit resolution
        function submitResolution() {
            const complaintId = document.getElementById('resolutionComplaintId').value;
            const resolution = document.getElementById('resolutionText').value.trim();
            
            if (!resolution) {
                showToast('Please enter resolution details', 'error');
                return;
            }
            
            fetch('complaints.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add_resolution&id=${complaintId}&resolution=${encodeURIComponent(resolution)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Resolution added successfully', 'success');
                    closeModal('resolutionModal');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message || 'Error adding resolution', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error adding resolution', 'error');
            });
        }

        // Update complaint status
        function updateStatus(complaintId, status) {
            if (!status) return;
            
            fetch('complaints.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_status&id=${complaintId}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Status updated successfully', 'success');
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

        // Update complaint priority
        function updatePriority(complaintId, priority) {
            if (!priority) return;
            
            fetch('complaints.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_priority&id=${complaintId}&priority=${priority}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Priority updated successfully', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message || 'Error updating priority', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error updating priority', 'error');
            });
        }

        // Assign complaint
        function assignComplaint(complaintId, assignedTo) {
            if (!assignedTo) return;
            
            fetch('complaints.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=assign_complaint&id=${complaintId}&assigned_to=${assignedTo}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Complaint assigned successfully', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message || 'Error assigning complaint', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error assigning complaint', 'error');
            });
        }

        // Export complaints
        function exportComplaints() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.open('?' + params.toString(), '_blank');
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
        });
    </script>
</body>
</html>