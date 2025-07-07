<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'get_user':
                $user_id = mysqli_real_escape_string($connection, $_POST['user_id']);
                
                $query = "SELECT u.*, 
                         COUNT(DISTINCT s.id) as subscription_count,
                         COUNT(DISTINCT o.id) as order_count,
                         COALESCE(SUM(p.amount), 0) as total_spent,
                         MAX(o.delivery_date) as last_order_date
                         FROM users u
                         LEFT JOIN subscriptions s ON u.id = s.user_id AND s.status = 'active'
                         LEFT JOIN orders o ON u.id = o.user_id
                         LEFT JOIN payments p ON u.id = p.user_id AND p.status = 'completed'
                         WHERE u.id = '$user_id'
                         GROUP BY u.id";
                
                $result = mysqli_query($connection, $query);
                $user = mysqli_fetch_assoc($result);
                
                if ($user) {
                    echo json_encode(['success' => true, 'user' => $user]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                }
                break;
                
            case 'update_user_status':
                $user_id = mysqli_real_escape_string($connection, $_POST['user_id']);
                $status = mysqli_real_escape_string($connection, $_POST['status']);
                
                $query = "UPDATE users SET status = '$status', updated_at = NOW() WHERE id = '$user_id'";
                
                if (mysqli_query($connection, $query)) {
                    echo json_encode(['success' => true, 'message' => 'User status updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
                }
                break;
                
            case 'update_user_role':
                $user_id = mysqli_real_escape_string($connection, $_POST['user_id']);
                $role = mysqli_real_escape_string($connection, $_POST['role']);
                
                $query = "UPDATE users SET role = '$role', updated_at = NOW() WHERE id = '$user_id'";
                
                if (mysqli_query($connection, $query)) {
                    echo json_encode(['success' => true, 'message' => 'User role updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update user role']);
                }
                break;
                
            case 'delete_user':
                $user_id = mysqli_real_escape_string($connection, $_POST['user_id']);
                
                // Check if user has active subscriptions
                $check_query = "SELECT COUNT(*) as count FROM subscriptions WHERE user_id = '$user_id' AND status = 'active'";
                $check_result = mysqli_query($connection, $check_query);
                $check = mysqli_fetch_assoc($check_result);
                
                if ($check['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete user with active subscriptions']);
                } else {
                    // Set status to inactive instead of deleting
                    $query = "UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = '$user_id'";
                    
                    if (mysqli_query($connection, $query)) {
                        echo json_encode(['success' => true, 'message' => 'User deactivated successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to deactivate user']);
                    }
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = [];
if ($status_filter) {
    $where_conditions[] = "u.status = '" . mysqli_real_escape_string($connection, $status_filter) . "'";
}
if ($role_filter) {
    $where_conditions[] = "u.role = '" . mysqli_real_escape_string($connection, $role_filter) . "'";
}
if ($search) {
    $search_escaped = mysqli_real_escape_string($connection, $search);
    $where_conditions[] = "(u.first_name LIKE '%$search_escaped%' OR u.last_name LIKE '%$search_escaped%' OR u.email LIKE '%$search_escaped%' OR u.phone LIKE '%$search_escaped%')";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get users with statistics
$users_query = "SELECT u.*, 
               COUNT(DISTINCT s.id) as subscription_count,
               COUNT(DISTINCT o.id) as order_count,
               COALESCE(SUM(p.amount), 0) as total_spent,
               MAX(o.delivery_date) as last_order_date
               FROM users u
               LEFT JOIN subscriptions s ON u.id = s.user_id AND s.status = 'active'
               LEFT JOIN orders o ON u.id = o.user_id
               LEFT JOIN payments p ON u.id = p.user_id AND p.status = 'completed'
               $where_clause
               GROUP BY u.id
               ORDER BY u.created_at DESC
               LIMIT $per_page OFFSET $offset";

$users_result = mysqli_query($connection, $users_query);
$users = [];
while ($user = mysqli_fetch_assoc($users_result)) {
    $users[] = $user;
}

// Get total count for pagination
$count_query = "SELECT COUNT(DISTINCT u.id) as total FROM users u $where_clause";
$count_result = mysqli_query($connection, $count_query);
$total_users = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_users / $per_page);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
    SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) as customers,
    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_30_days
    FROM users";

$stats_result = mysqli_query($connection, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Krua Thai Admin</title>
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

        .welcome-section {
            background: linear-gradient(135deg, var(--curry), #e67e22);
            color: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            margin-bottom: 0.5rem;
        }

        .welcome-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .welcome-time {
            opacity: 0.9;
            font-size: 0.95rem;
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

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
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

        .btn-icon {
            width: 36px;
            height: 36px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

        .stat-change {
            font-size: 0.8rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }

        .stat-change.positive {
            color: #27ae60;
        }

        /* Dashboard Card */
        .dashboard-card {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Filters */
        .filters {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            margin-bottom: 2rem;
        }

        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .form-control {
            padding: 0.75rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--white);
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
            appearance: none;
        }

        /* Table */
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--curry), var(--brown));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: 600;
            font-size: 0.875rem;
        }

        .user-details h4 {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .user-details p {
            color: var(--text-gray);
            font-size: 0.875rem;
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

        .status-active {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .status-inactive {
            background: rgba(149, 165, 166, 0.1);
            color: #95a5a6;
        }

        .status-suspended {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .status-pending-verification {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .role-admin {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .role-customer {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .role-kitchen {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .role-rider {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .role-support {
            background: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            padding: 2rem;
        }

        .page-link {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            color: var(--text-dark);
            text-decoration: none;
            transition: var(--transition);
        }

        .page-link:hover,
        .page-link.active {
            background-color: var(--curry);
            border-color: var(--curry);
            color: var(--white);
        }

        .page-info {
            color: var(--text-gray);
            font-size: 0.875rem;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.show {
            display: flex;
            opacity: 1;
            align-items: center;
            justify-content: center;
        }

        .modal-dialog {
            background: var(--white);
            border-radius: var(--radius-md);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        .modal.show .modal-dialog {
            transform: scale(1);
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
            color: var(--text-gray);
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .modal-close:hover {
            color: var(--text-dark);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .user-detail-section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border-light);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .detail-item {
            padding: 1rem;
            background-color: var(--cream);
            border-radius: var(--radius-sm);
        }

        .detail-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-gray);
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }

        .detail-value {
            font-weight: 600;
            color: var(--text-dark);
        }

        /* Loading State */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border-light);
            border-top: 4px solid var(--curry);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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

            .table {
                font-size: 0.875rem;
            }

            .modal-dialog {
                width: 95%;
                margin: 1rem;
            }

            .actions {
                flex-direction: column;
                gap: 0.25rem;
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
                    <a href="users.php" class="nav-item active">
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
                        <div class="welcome-section">
                            <div class="welcome-title">
                                <i class="fas fa-users" style="margin-right: 0.5rem;"></i>
                                User Management
                            </div>
                            <div class="welcome-time">
                                Manage all users, roles and permissions
                            </div>
                        </div>
                        <h1 class="page-title">User Management</h1>
                        <p class="page-subtitle">Monitor and manage all user accounts and permissions</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-secondary" onclick="refreshData()">
                            <i class="fas fa-sync-alt"></i>
                            Refresh
                        </button>
                        <button class="btn btn-primary" onclick="exportUsers()">
                            <i class="fas fa-download"></i>
                            Export Users
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--curry), #e67e22);">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        All registered users
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--sage), #27ae60);">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['active_users']) ?></div>
                    <div class="stat-label">Active Users</div>
                    <div class="stat-change positive">
                        <i class="fas fa-check"></i>
                        Currently active
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                            <i class="fas fa-user-friends"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['customers']) ?></div>
                    <div class="stat-label">Customers</div>
                    <div class="stat-change positive">
                        <i class="fas fa-heart"></i>
                        Customer accounts
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                            <i class="fas fa-user-plus"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['new_users_30_days']) ?></div>
                    <div class="stat-label">New This Month</div>
                    <div class="stat-change positive">
                        <i class="fas fa-calendar"></i>
                        Last 30 days
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <form method="GET" id="filterForm">
                    <div class="filters-row">
                        <div class="form-group">
                            <label class="form-label">Search Users</label>
                            <input type="text" name="search" class="form-control" placeholder="Search by name, email, or phone..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control form-select">
                                <option value="">All Status</option>
                                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                <option value="pending_verification" <?= $status_filter === 'pending_verification' ? 'selected' : '' ?>>Pending</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-control form-select">
                                <option value="">All Roles</option>
                                <option value="customer" <?= $role_filter === 'customer' ? 'selected' : '' ?>>Customer</option>
                                <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="kitchen" <?= $role_filter === 'kitchen' ? 'selected' : '' ?>>Kitchen</option>
                                <option value="rider" <?= $role_filter === 'rider' ? 'selected' : '' ?>>Rider</option>
                                <option value="support" <?= $role_filter === 'support' ? 'selected' : '' ?>>Support</option>
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

            <!-- Users Table -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-users" style="color: var(--curry); margin-right: 0.5rem;"></i>
                        Users (<?= number_format($total_users) ?>)
                    </h3>
                    <div>
                        Showing <?= ($offset + 1) ?> to <?= min($offset + $per_page, $total_users) ?> of <?= number_format($total_users) ?> users
                    </div>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (!empty($users)): ?>
                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Contact</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Statistics</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                                            </div>
                                            <div class="user-details">
                                                <h4><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h4>
                                                <p><?= htmlspecialchars($user['email']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <?php if ($user['phone']): ?>
                                                <div><i class="fas fa-phone" style="color: var(--curry); margin-right: 0.5rem;"></i><?= htmlspecialchars($user['phone']) ?></div>
                                            <?php endif; ?>
                                            <?php if ($user['city']): ?>
                                                <div style="margin-top: 0.25rem;"><i class="fas fa-map-marker-alt" style="color: var(--curry); margin-right: 0.5rem;"></i><?= htmlspecialchars($user['city']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="role-badge role-<?= $user['role'] ?>">
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= str_replace('_', '-', $user['status']) ?>">
                                            <?= ucfirst(str_replace('_', ' ', $user['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.875rem;">
                                            <div style="margin-bottom: 0.25rem;">
                                                <i class="fas fa-calendar-alt" style="color: var(--curry); margin-right: 0.5rem;"></i>
                                                <?= $user['subscription_count'] ?> subscriptions
                                            </div>
                                            <div style="margin-bottom: 0.25rem;">
                                                <i class="fas fa-shopping-cart" style="color: var(--curry); margin-right: 0.5rem;"></i>
                                                <?= $user['order_count'] ?> orders
                                            </div>
                                            <div>
                                                <i class="fas fa-dollar-sign" style="color: var(--curry); margin-right: 0.5rem;"></i>
                                                ฿<?= number_format($user['total_spent'], 0) ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.875rem;">
                                            <?php 
                                                $joinDate = new DateTime($user['created_at']);
                                                echo $joinDate->format('M d, Y');
                                            ?>
                                            <br>
                                            <span style="color: var(--text-gray);">
                                                <?php 
                                                    if ($user['last_login']) {
                                                        $lastLogin = new DateTime($user['last_login']);
                                                        echo 'Last: ' . $lastLogin->format('M d');
                                                    } else {
                                                        echo 'Never logged in';
                                                    }
                                                ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-icon btn-info btn-sm" onclick="viewUser('<?= $user['id'] ?>')" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-icon btn-warning btn-sm" onclick="editUserStatus('<?= $user['id'] ?>', '<?= $user['status'] ?>')" title="Edit Status">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($user['role'] !== 'admin'): ?>
                                            <button class="btn btn-icon btn-danger btn-sm" onclick="deleteUser('<?= $user['id'] ?>')" title="Deactivate">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: var(--text-gray);">
                        <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; color: var(--curry);"></i>
                        <h3>No users found</h3>
                        <p>No users match your current filter criteria</p>
                    </div>
                    <?php endif; ?>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= ($page - 1) ?>&status=<?= $status_filter ?>&role=<?= $role_filter ?>&search=<?= urlencode($search) ?>" class="page-link">
                                <i class="fas fa-chevron-left"></i>
                                Previous
                            </a>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="?page=<?= $i ?>&status=<?= $status_filter ?>&role=<?= $role_filter ?>&search=<?= urlencode($search) ?>" 
                               class="page-link <?= $i === $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= ($page + 1) ?>&status=<?= $status_filter ?>&role=<?= $role_filter ?>&search=<?= urlencode($search) ?>" class="page-link">
                                Next
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>

                        <span class="page-info">
                            Page <?= $page ?> of <?= $total_pages ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- User Detail Modal -->
    <div class="modal" id="userModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="modal-title">User Details</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="userModalContent">
                    <div class="loading">
                        <div class="spinner"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Global variables
        let currentUser = null;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeFilters();
            initializeTooltips();
            
            // Show welcome message
            showToast('User management system loaded successfully', 'success');
            
            console.log('User Management System initialized');
            console.log('Users loaded:', <?= count($users) ?>);
            console.log('Total users:', <?= $total_users ?>);
        });

        // Initialize filters with auto-submit
        function initializeFilters() {
            const filterForm = document.getElementById('filterForm');
            const inputs = filterForm.querySelectorAll('input, select');
            
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    if (this.type !== 'text') {
                        filterForm.submit();
                    }
                });
                
                if (input.type === 'text') {
                    let timeout;
                    input.addEventListener('input', function() {
                        clearTimeout(timeout);
                        timeout = setTimeout(() => {
                            filterForm.submit();
                        }, 500);
                    });
                }
            });
        }

        // Initialize tooltips
        function initializeTooltips() {
            // Add hover effects to stat cards
            document.querySelectorAll('.stat-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
        }

        // View user details
        async function viewUser(userId) {
            const modal = document.getElementById('userModal');
            const content = document.getElementById('userModalContent');
            
            // Reset content
            content.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
            modal.classList.add('show');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_user&user_id=${userId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const user = data.user;
                    content.innerHTML = generateUserDetailHTML(user);
                } else {
                    content.innerHTML = `<div style="color: #e74c3c; text-align: center; padding: 2rem;">${data.message}</div>`;
                }
            } catch (error) {
                content.innerHTML = `<div style="color: #e74c3c; text-align: center; padding: 2rem;">Error loading user details</div>`;
                console.error('Error:', error);
            }
        }

        // Generate user detail HTML
        function generateUserDetailHTML(user) {
            return `
                <div class="user-detail-section">
                    <h4 class="section-title">
                        <i class="fas fa-user" style="color: var(--curry); margin-right: 0.5rem;"></i>
                        Basic Information
                    </h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Full Name</div>
                            <div class="detail-value">${user.first_name} ${user.last_name}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Email</div>
                            <div class="detail-value">${user.email}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Phone</div>
                            <div class="detail-value">${user.phone || 'Not provided'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Role</div>
                            <div class="detail-value">
                                <span class="role-badge role-${user.role}">${user.role.charAt(0).toUpperCase() + user.role.slice(1)}</span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Status</div>
                            <div class="detail-value">
                                <span class="status-badge status-${user.status.replace('_', '-')}">${user.status.replace('_', ' ').toUpperCase()}</span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Joined Date</div>
                            <div class="detail-value">${new Date(user.created_at).toLocaleDateString()}</div>
                        </div>
                    </div>
                </div>

                <div class="user-detail-section">
                    <h4 class="section-title">
                        <i class="fas fa-chart-bar" style="color: var(--curry); margin-right: 0.5rem;"></i>
                        Statistics
                    </h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Active Subscriptions</div>
                            <div class="detail-value">${user.subscription_count || 0}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Total Orders</div>
                            <div class="detail-value">${user.order_count || 0}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Total Spent</div>
                            <div class="detail-value">฿${parseFloat(user.total_spent || 0).toLocaleString()}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Last Order</div>
                            <div class="detail-value">${user.last_order_date ? new Date(user.last_order_date).toLocaleDateString() : 'Never'}</div>
                        </div>
                    </div>
                </div>

                ${user.delivery_address ? `
                <div class="user-detail-section">
                    <h4 class="section-title">
                        <i class="fas fa-map-marker-alt" style="color: var(--curry); margin-right: 0.5rem;"></i>
                        Address Information
                    </h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Delivery Address</div>
                            <div class="detail-value">${user.delivery_address}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">City</div>
                            <div class="detail-value">${user.city || 'Not provided'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Zip Code</div>
                            <div class="detail-value">${user.zip_code || 'Not provided'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Country</div>
                            <div class="detail-value">${user.country || 'Thailand'}</div>
                        </div>
                    </div>
                </div>
                ` : ''}

                <div class="user-detail-section">
                    <h4 class="section-title">
                        <i class="fas fa-cog" style="color: var(--curry); margin-right: 0.5rem;"></i>
                        Quick Actions
                    </h4>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button class="btn btn-warning btn-sm" onclick="editUserStatus('${user.id}', '${user.status}')">
                            <i class="fas fa-edit"></i> Change Status
                        </button>
                        <button class="btn btn-info btn-sm" onclick="editUserRole('${user.id}', '${user.role}')">
                            <i class="fas fa-user-cog"></i> Change Role
                        </button>
                        ${user.role !== 'admin' ? `
                        <button class="btn btn-danger btn-sm" onclick="deleteUser('${user.id}')">
                            <i class="fas fa-trash"></i> Deactivate User
                        </button>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        // Edit user status
        async function editUserStatus(userId, currentStatus) {
            const statuses = ['active', 'inactive', 'suspended', 'pending_verification'];
            const statusNames = {
                'active': 'Active',
                'inactive': 'Inactive', 
                'suspended': 'Suspended',
                'pending_verification': 'Pending Verification'
            };
            
            const newStatus = prompt(`Select new status for user:\n\n1. active - Active\n2. inactive - Inactive\n3. suspended - Suspended\n4. pending_verification - Pending Verification\n\nCurrent: ${statusNames[currentStatus]}\n\nType: active, inactive, suspended, or pending_verification`);
            
            if (newStatus && newStatus !== currentStatus && statuses.includes(newStatus)) {
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=update_user_status&user_id=${userId}&status=${newStatus}`
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showToast('User status updated successfully', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(data.message, 'error');
                    }
                } catch (error) {
                    showToast('Error updating user status', 'error');
                    console.error('Error:', error);
                }
            }
        }

        // Edit user role
        async function editUserRole(userId, currentRole) {
            const roles = ['customer', 'admin', 'kitchen', 'rider', 'support'];
            const roleNames = {
                'customer': 'Customer',
                'admin': 'Admin',
                'kitchen': 'Kitchen Staff',
                'rider': 'Delivery Rider',
                'support': 'Support Staff'
            };
            
            const newRole = prompt(`Select new role for user:\n\n1. customer - Customer\n2. admin - Admin\n3. kitchen - Kitchen Staff\n4. rider - Delivery Rider\n5. support - Support Staff\n\nCurrent: ${roleNames[currentRole]}\n\nType: customer, admin, kitchen, rider, or support`);
            
            if (newRole && newRole !== currentRole && roles.includes(newRole)) {
                if (confirm(`Are you sure you want to change user role to ${roleNames[newRole]}?`)) {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=update_user_role&user_id=${userId}&role=${newRole}`
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            showToast('User role updated successfully', 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showToast(data.message, 'error');
                        }
                    } catch (error) {
                        showToast('Error updating user role', 'error');
                        console.error('Error:', error);
                    }
                }
            }
        }

        // Delete user
        async function deleteUser(userId) {
            if (confirm('Are you sure you want to deactivate this user? This action will set their status to inactive.')) {
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=delete_user&user_id=${userId}`
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showToast('User deactivated successfully', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(data.message, 'error');
                    }
                } catch (error) {
                    showToast('Error deactivating user', 'error');
                    console.error('Error:', error);
                }
            }
        }

        // Export users
        function exportUsers() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.open('?' + params.toString());
            showToast('Preparing export file...', 'info');
        }

        // Refresh data
        function refreshData() {
            showToast('Refreshing data...', 'info');
            location.reload();
        }

        // Close modal
        function closeModal() {
            const modal = document.getElementById('userModal');
            modal.classList.remove('show');
        }

        // Show toast notification
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                        <span>${message}</span>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; cursor: pointer; color: var(--text-gray);">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            // Show toast
            setTimeout(() => toast.classList.add('show'), 100);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('userModal');
            if (event.target === modal) {
                closeModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            // Ctrl/Cmd + R for refresh
            if ((event.ctrlKey || event.metaKey) && event.key === 'r') {
                event.preventDefault();
                refreshData();
            }
            
            // Ctrl/Cmd + F for search focus
            if ((event.ctrlKey || event.metaKey) && event.key === 'f') {
                event.preventDefault();
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
            
            // Ctrl/Cmd + E for export
            if ((event.ctrlKey || event.metaKey) && event.key === 'e') {
                event.preventDefault();
                exportUsers();
            }
        });

        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }

        // Add loading states to action buttons
        document.querySelectorAll('.actions .btn').forEach(button => {
            button.addEventListener('click', function() {
                if (!this.onclick || !this.onclick.toString().includes('window.location')) {
                    const icon = this.querySelector('i');
                    const originalClass = icon.className;
                    icon.className = 'fas fa-spinner fa-spin';
                    
                    setTimeout(() => {
                        icon.className = originalClass;
                    }, 2000);
                }
            });
        });

        // Performance monitoring
        window.addEventListener('load', function() {
            const loadTime = performance.now();
            console.log(`User Management page loaded in ${Math.round(loadTime)}ms`);
        });

        // Show initial messages
        <?php if (empty($users)): ?>
        showToast('No users found with current filters', 'info');
        <?php endif; ?>

        <?php if (isset($_SESSION['message'])): ?>
        showToast('<?= addslashes($_SESSION['message']) ?>', '<?= $_SESSION['message_type'] ?? 'info' ?>');
        <?php 
        unset($_SESSION['message']); 
        unset($_SESSION['message_type']); 
        ?>
        <?php endif; ?>

        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                showToast('Logging out...', 'info');
                window.location.href = '../logout.php';
            }
        }

        console.log('Krua Thai User Management System initialized successfully');
    </script>
</body>
</html>