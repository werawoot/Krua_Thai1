<?php
/**
 * Krua Thai - Reviews Management
 * File: admin/reviews.php
 * Description: Complete reviews management system with moderation and analytics
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
                $result = updateReviewStatus($pdo, $_POST['id'], $_POST['status']);
                echo json_encode($result);
                exit;
                
            case 'add_response':
                $result = addAdminResponse($pdo, $_POST['id'], $_POST['response'], $_SESSION['user_id']);
                echo json_encode($result);
                exit;
                
            case 'toggle_featured':
                $result = toggleFeatured($pdo, $_POST['id']);
                echo json_encode($result);
                exit;
                
            case 'get_review':
                $result = getReviewDetails($pdo, $_POST['id']);
                echo json_encode($result);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Database Functions
function updateReviewStatus($pdo, $reviewId, $status) {
    try {
        $stmt = $pdo->prepare("UPDATE reviews SET moderation_status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $reviewId]);
        return ['success' => true, 'message' => 'Review status updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()];
    }
}

function addAdminResponse($pdo, $reviewId, $response, $adminId) {
    try {
        $stmt = $pdo->prepare("UPDATE reviews SET admin_response = ?, admin_response_at = NOW(), admin_response_by = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$response, $adminId, $reviewId]);
        return ['success' => true, 'message' => 'Admin response added successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error adding response: ' . $e->getMessage()];
    }
}

function toggleFeatured($pdo, $reviewId) {
    try {
        $stmt = $pdo->prepare("UPDATE reviews SET is_featured = NOT is_featured, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$reviewId]);
        return ['success' => true, 'message' => 'Featured status updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating featured status: ' . $e->getMessage()];
    }
}

function getReviewDetails($pdo, $reviewId) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                   u.email as customer_email,
                   m.name as menu_name,
                   o.order_number,
                   CONCAT(admin.first_name, ' ', admin.last_name) as admin_name
            FROM reviews r
            JOIN users u ON r.user_id = u.id
            LEFT JOIN menus m ON r.menu_id = m.id
            LEFT JOIN orders o ON r.order_id = o.id
            LEFT JOIN users admin ON r.admin_response_by = admin.id
            WHERE r.id = ?
        ");
        $stmt->execute([$reviewId]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($review) {
            return ['success' => true, 'data' => $review];
        } else {
            return ['success' => false, 'message' => 'Review not found'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error fetching review: ' . $e->getMessage()];
    }
}

// Fetch Data
try {
    // Filters
    $statusFilter = $_GET['status'] ?? '';
    $ratingFilter = $_GET['rating'] ?? '';
    $search = $_GET['search'] ?? '';
    $sortBy = $_GET['sort'] ?? 'created_at';
    
    // Main reviews query
    $whereConditions = ['1=1'];
    $params = [];
    
    if ($statusFilter) {
        $whereConditions[] = 'r.moderation_status = ?';
        $params[] = $statusFilter;
    }
    
    if ($ratingFilter) {
        $whereConditions[] = 'r.overall_rating = ?';
        $params[] = $ratingFilter;
    }
    
    if ($search) {
        $whereConditions[] = '(r.title LIKE ? OR r.comment LIKE ? OR CONCAT(u.first_name, " ", u.last_name) LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $stmt = $pdo->prepare("
        SELECT r.*, 
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.email as customer_email,
               m.name as menu_name,
               o.order_number
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN menus m ON r.menu_id = m.id
        LEFT JOIN orders o ON r.order_id = o.id
        WHERE $whereClause
        ORDER BY r.$sortBy DESC
        LIMIT 50
    ");
    $stmt->execute($params);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_reviews,
            SUM(CASE WHEN moderation_status = 'pending' THEN 1 ELSE 0 END) as pending_reviews,
            SUM(CASE WHEN moderation_status = 'approved' THEN 1 ELSE 0 END) as approved_reviews,
            SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured_reviews,
            AVG(overall_rating) as avg_rating
        FROM reviews
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $reviews = [];
    $stats = ['total_reviews' => 0, 'pending_reviews' => 0, 'approved_reviews' => 0, 'featured_reviews' => 0, 'avg_rating' => 0];
    error_log("Reviews error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews Management - Krua Thai Admin</title>
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

        /* Stars Rating */
        .stars {
            display: flex;
            gap: 0.25rem;
            margin-bottom: 0.5rem;
        }

        .star {
            color: #ffd700;
            font-size: 1rem;
        }

        .star.empty {
            color: #e0e0e0;
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
            grid-template-columns: 1fr 150px 150px 150px 150px;
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

        /* Reviews Grid */
        .reviews-grid {
            display: grid;
            gap: 1.5rem;
        }

        .review-card {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            overflow: hidden;
            transition: var(--transition);
        }

        .review-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .review-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .review-meta {
            flex: 1;
        }

        .review-customer {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .review-date {
            font-size: 0.8rem;
            color: var(--text-gray);
            margin-bottom: 0.5rem;
        }

        .review-actions {
            display: flex;
            gap: 0.5rem;
        }

        .review-body {
            padding: 1.5rem;
        }

        .review-title {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .review-content {
            color: var(--text-dark);
            line-height: 1.6;
            margin-bottom: 1rem;
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

        .status-approved {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .status-rejected {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .badge-featured {
            background: rgba(142, 68, 173, 0.1);
            color: #8e44ad;
        }

        /* Admin Response */
        .admin-response {
            background: var(--cream);
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-top: 1rem;
        }

        .admin-response-header {
            font-size: 0.8rem;
            color: var(--text-gray);
            margin-bottom: 0.5rem;
        }

        .admin-response-content {
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
                    <a href="reviews.php" class="nav-item active">
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
                        <h1 class="page-title">
                            <i class="fas fa-star" style="margin-right: 0.5rem; color: var(--curry);"></i>
                            Reviews Management
                        </h1>
                        <p class="page-subtitle">Monitor and moderate customer reviews and feedback</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-secondary" onclick="exportReviews()">
                            <i class="fas fa-download"></i>
                            Export
                        </button>
                        <button class="btn btn-primary" onclick="showAnalytics()">
                            <i class="fas fa-chart-bar"></i>
                            Analytics
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--curry), #e67e22);">
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_reviews']) ?></div>
                    <div class="stat-label">Total Reviews</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['pending_reviews']) ?></div>
                    <div class="stat-label">Pending Reviews</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--sage), #27ae60);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['approved_reviews']) ?></div>
                    <div class="stat-label">Approved Reviews</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                            <i class="fas fa-medal"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['featured_reviews']) ?></div>
                    <div class="stat-label">Featured Reviews</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                            <i class="fas fa-heart"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['avg_rating'], 1) ?></div>
                    <div class="stat-label">Average Rating</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" id="filtersForm">
                    <div class="filters-row">
                        <div class="form-group">
                            <label class="form-label">Search Reviews</label>
                            <input type="text" 
                                   name="search" 
                                   class="form-control" 
                                   placeholder="Search by title, comment, or customer..."
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Rating</label>
                            <select name="rating" class="form-control">
                                <option value="">All Ratings</option>
                                <option value="5" <?= $ratingFilter === '5' ? 'selected' : '' ?>>5 Stars</option>
                                <option value="4" <?= $ratingFilter === '4' ? 'selected' : '' ?>>4 Stars</option>
                                <option value="3" <?= $ratingFilter === '3' ? 'selected' : '' ?>>3 Stars</option>
                                <option value="2" <?= $ratingFilter === '2' ? 'selected' : '' ?>>2 Stars</option>
                                <option value="1" <?= $ratingFilter === '1' ? 'selected' : '' ?>>1 Star</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Sort By</label>
                            <select name="sort" class="form-control">
                                <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>Date</option>
                                <option value="overall_rating" <?= $sortBy === 'overall_rating' ? 'selected' : '' ?>>Rating</option>
                                <option value="moderation_status" <?= $sortBy === 'moderation_status' ? 'selected' : '' ?>>Status</option>
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

            <!-- Reviews Grid -->
            <div class="reviews-grid">
                <?php if (!empty($reviews)): ?>
                    <?php foreach ($reviews as $review): ?>
                    <div class="review-card" data-id="<?= $review['id'] ?>">
                        <div class="review-header">
                            <div class="review-meta">
                                <div class="review-customer"><?= htmlspecialchars($review['customer_name']) ?></div>
                                <div class="review-date">
                                    <?= date('M d, Y H:i', strtotime($review['created_at'])) ?>
                                    <?php if ($review['order_number']): ?>
                                        • Order #<?= htmlspecialchars($review['order_number']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?= $i <= $review['overall_rating'] ? '' : 'empty' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                                    <span class="status-badge status-<?= $review['moderation_status'] ?>">
                                        <?= ucfirst($review['moderation_status']) ?>
                                    </span>
                                    <?php if ($review['is_featured']): ?>
                                        <span class="status-badge badge-featured">Featured</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="review-actions">
                                <button class="btn btn-icon btn-secondary btn-sm" 
                                        onclick="viewReview('<?= $review['id'] ?>')" 
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-icon btn-primary btn-sm" 
                                        onclick="respondToReview('<?= $review['id'] ?>')" 
                                        title="Respond">
                                    <i class="fas fa-reply"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="review-body">
                            <?php if ($review['title']): ?>
                                <div class="review-title"><?= htmlspecialchars($review['title']) ?></div>
                            <?php endif; ?>
                            
                            <div class="review-content">
                                <?= nl2br(htmlspecialchars($review['comment'])) ?>
                            </div>
                            
                            <?php if ($review['menu_name']): ?>
                                <div style="margin-top: 1rem; padding: 0.75rem; background: var(--cream); border-radius: var(--radius-sm);">
                                    <strong>Menu Item:</strong> <?= htmlspecialchars($review['menu_name']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($review['admin_response']): ?>
                            <div class="admin-response">
                                <div class="admin-response-header">
                                    <strong>Admin Response</strong> • 
                                    <?= date('M d, Y H:i', strtotime($review['admin_response_at'])) ?>
                                </div>
                                <div class="admin-response-content">
                                    <?= nl2br(htmlspecialchars($review['admin_response'])) ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: var(--text-gray); background: var(--white); border-radius: var(--radius-md); box-shadow: var(--shadow-soft);">
                        <i class="fas fa-star" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <h3>No reviews found</h3>
                        <p>Reviews from customers will appear here</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Review Details Modal -->
    <div class="modal" id="reviewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Review Details</h3>
                <button class="modal-close" onclick="closeModal('reviewModal')">&times;</button>
            </div>
            <div class="modal-body" id="reviewModalBody">
                <!-- Review details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('reviewModal')">Close</button>
                <button class="btn btn-success" onclick="approveReview()" id="approveBtn">Approve</button>
                <button class="btn btn-danger" onclick="rejectReview()" id="rejectBtn">Reject</button>
                <button class="btn btn-warning" onclick="toggleFeaturedReview()" id="featuredBtn">Toggle Featured</button>
            </div>
        </div>
    </div>

    <!-- Response Modal -->
    <div class="modal" id="responseModal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title">Add Admin Response</h3>
                <button class="modal-close" onclick="closeModal('responseModal')">&times;</button>
            </div>
            <form id="responseForm">
                <div class="modal-body">
                    <input type="hidden" id="responseReviewId">
                    
                    <div class="form-group">
                        <label class="form-label">Your Response</label>
                        <textarea id="responseText" class="form-control" rows="6" 
                                  placeholder="Write your response to this review..." required></textarea>
                    </div>
                    
                    <div style="background: var(--cream); padding: 1rem; border-radius: var(--radius-sm); margin-top: 1rem;">
                        <div style="font-size: 0.8rem; color: var(--text-gray); margin-bottom: 0.5rem;">Tips for responding:</div>
                        <ul style="font-size: 0.9rem; color: var(--text-dark); margin-left: 1rem;">
                            <li>Thank the customer for their feedback</li>
                            <li>Address any specific concerns mentioned</li>
                            <li>Keep it professional and friendly</li>
                            <li>Invite them to contact you directly if needed</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('responseModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-reply"></i>
                        Send Response
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Global variables
        let currentReviewId = null;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeEventListeners();
            checkPendingReviews();
        });

        // Initialize event listeners
        function initializeEventListeners() {
            // Form submissions
            document.getElementById('responseForm').addEventListener('submit', handleResponseSubmit);
            
            // Auto-submit filters
            document.querySelectorAll('#filtersForm select').forEach(element => {
                element.addEventListener('change', function() {
                    document.getElementById('filtersForm').submit();
                });
            });
            
            // Search with debounce
            let searchTimeout;
            document.querySelector('input[name="search"]').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    document.getElementById('filtersForm').submit();
                }, 500);
            });
        }

        // Check for pending reviews
        function checkPendingReviews() {
            const pendingCount = <?= $stats['pending_reviews'] ?>;
            if (pendingCount > 0) {
                showToast(`You have ${pendingCount} reviews pending moderation`, 'info');
            }
        }

        // View review details
        function viewReview(reviewId) {
            currentReviewId = reviewId;
            
            const formData = new FormData();
            formData.append('action', 'get_review');
            formData.append('id', reviewId);
            
            fetch('reviews.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayReviewDetails(data.data);
                    document.getElementById('reviewModal').classList.add('show');
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error fetching review details', 'error');
            });
        }

        // Display review details in modal
        function displayReviewDetails(review) {
            const modalBody = document.getElementById('reviewModalBody');
            
            modalBody.innerHTML = `
                <div style="margin-bottom: 1.5rem;">
                    <h4>${review.customer_name}</h4>
                    <p style="color: var(--text-gray); margin-bottom: 1rem;">
                        ${review.customer_email} • ${new Date(review.created_at).toLocaleDateString()}
                        ${review.order_number ? '• Order #' + review.order_number : ''}
                    </p>
                    <div class="stars" style="margin-bottom: 1rem;">
                        ${generateStars(review.overall_rating)}
                    </div>
                </div>
                
                ${review.title ? `<h5 style="margin-bottom: 0.5rem;">${review.title}</h5>` : ''}
                
                <div style="margin-bottom: 1.5rem; line-height: 1.6;">
                    ${review.comment.replace(/\n/g, '<br>')}
                </div>
                
                ${review.menu_name ? `
                <div style="background: var(--cream); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1rem;">
                    <strong>Menu Item:</strong> ${review.menu_name}
                </div>
                ` : ''}
                
                ${review.admin_response ? `
                <div style="background: var(--cream); padding: 1rem; border-radius: var(--radius-sm);">
                    <div style="font-size: 0.8rem; color: var(--text-gray); margin-bottom: 0.5rem;">
                        <strong>Admin Response</strong> by ${review.admin_name || 'Admin'} • 
                        ${new Date(review.admin_response_at).toLocaleDateString()}
                    </div>
                    <div>${review.admin_response.replace(/\n/g, '<br>')}</div>
                </div>
                ` : ''}
            `;
            
            // Update buttons based on review status
            updateModalButtons(review);
        }

        // Generate stars HTML
        function generateStars(rating) {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                stars += `<i class="fas fa-star ${i <= rating ? '' : 'empty'}"></i>`;
            }
            return stars;
        }

        // Update modal buttons
        function updateModalButtons(review) {
            const approveBtn = document.getElementById('approveBtn');
            const rejectBtn = document.getElementById('rejectBtn');
            const featuredBtn = document.getElementById('featuredBtn');
            
            if (review.moderation_status === 'approved') {
                approveBtn.style.display = 'none';
                rejectBtn.textContent = 'Reject';
                rejectBtn.style.display = 'inline-flex';
            } else if (review.moderation_status === 'rejected') {
                rejectBtn.style.display = 'none';
                approveBtn.textContent = 'Approve';
                approveBtn.style.display = 'inline-flex';
            } else {
                approveBtn.style.display = 'inline-flex';
                rejectBtn.style.display = 'inline-flex';
            }
            
            featuredBtn.textContent = review.is_featured ? 'Remove Featured' : 'Make Featured';
        }

        // Respond to review
        function respondToReview(reviewId) {
            currentReviewId = reviewId;
            document.getElementById('responseReviewId').value = reviewId;
            document.getElementById('responseText').value = '';
            document.getElementById('responseModal').classList.add('show');
        }

        // Handle response form submission
        function handleResponseSubmit(e) {
            e.preventDefault();
            
            const reviewId = document.getElementById('responseReviewId').value;
            const response = document.getElementById('responseText').value;
            
            const formData = new FormData();
            formData.append('action', 'add_response');
            formData.append('id', reviewId);
            formData.append('response', response);
            
            fetch('reviews.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal('responseModal');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error adding response', 'error');
            });
        }

        // Approve review
        function approveReview() {
            updateReviewStatus('approved');
        }

        // Reject review
        function rejectReview() {
            if (confirm('Are you sure you want to reject this review?')) {
                updateReviewStatus('rejected');
            }
        }

        // Toggle featured status
        function toggleFeaturedReview() {
            const formData = new FormData();
            formData.append('action', 'toggle_featured');
            formData.append('id', currentReviewId);
            
            fetch('reviews.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal('reviewModal');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error updating featured status', 'error');
            });
        }

        // Update review status
        function updateReviewStatus(status) {
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('id', currentReviewId);
            formData.append('status', status);
            
            fetch('reviews.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal('reviewModal');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error updating review status', 'error');
            });
        }

        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            currentReviewId = null;
        }

        // Export reviews
        function exportReviews() {
            showToast('Exporting reviews data...', 'info');
            
            // Create CSV export
            const rows = [['Customer', 'Date', 'Rating', 'Title', 'Comment', 'Menu', 'Status']];
            
            document.querySelectorAll('.review-card').forEach(card => {
                const customer = card.querySelector('.review-customer').textContent.trim();
                const date = card.querySelector('.review-date').textContent.trim();
                const rating = card.querySelectorAll('.star:not(.empty)').length;
                const title = card.querySelector('.review-title')?.textContent.trim() || '';
                const comment = card.querySelector('.review-content').textContent.trim();
                const menu = card.querySelector('[style*="background: var(--cream)"]')?.textContent.replace('Menu Item:', '').trim() || '';
                const status = card.querySelector('.status-badge').textContent.trim();
                
                const rowData = [customer, date, rating, title, comment, menu, status];
                rows.push(rowData);
            });
            
            const csv = rows.map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `reviews_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Show analytics
        function showAnalytics() {
            showToast('Analytics feature coming soon!', 'info');
        }

        // Toast notification system
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            toast.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
                        <span>${message}</span>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; cursor: pointer; color: var(--text-gray);">
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
                window.location.href = '../logout.php';
            }
        }

        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(modal => {
                    modal.classList.remove('show');
                });
            }
            
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.querySelector('input[name="search"]').focus();
            }
        });

        // Click outside modal to close
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });

        // Initialize system
        console.log('🍜 Krua Thai Reviews Management System Ready!');
    </script>
</body>
</html>