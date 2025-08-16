<?php
/**
 * Krua Thai - User Management (Hard Delete Version)
 * File: admin/users.php
 * Description: Complete user management with Hard Delete functionality
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Database connection - Simplified and more reliable
$connection = null;
try {
    // ใช้ config จาก database.php
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // เช็ค connection error
    if ($connection->connect_error) {
        die("Connection failed: " . $connection->connect_error);
    }
    
    // Set charset
    $connection->set_charset("utf8mb4");
    
    // Test connection
    if (!mysqli_ping($connection)) {
        die("Database connection lost");
    }
    
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Set error reporting for mysqli
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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
                
                if (!$result) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($connection)]);
                    break;
                }
                
                $user = mysqli_fetch_assoc($result);
                
                if ($user) {
                    echo json_encode(['success' => true, 'user' => $user]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                }
                break;
                
            case 'create_user':
                $first_name = mysqli_real_escape_string($connection, $_POST['first_name']);
                $last_name = mysqli_real_escape_string($connection, $_POST['last_name']);
                $email = mysqli_real_escape_string($connection, $_POST['email']);
                $phone = mysqli_real_escape_string($connection, $_POST['phone']);
                $role = mysqli_real_escape_string($connection, $_POST['role']);
                $status = mysqli_real_escape_string($connection, $_POST['status']);
                $city = mysqli_real_escape_string($connection, $_POST['city']);
                $delivery_address = mysqli_real_escape_string($connection, $_POST['delivery_address']);
                $password = mysqli_real_escape_string($connection, $_POST['password']);
                
                // Validate required fields
                if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
                    echo json_encode(['success' => false, 'message' => 'First name, last name, email, and password are required']);
                    break;
                }
                
                // Validate email uniqueness
                $email_check = "SELECT id FROM users WHERE email = '$email'";
                $email_result = mysqli_query($connection, $email_check);
                
                if (mysqli_num_rows($email_result) > 0) {
                    echo json_encode(['success' => false, 'message' => 'Email already exists']);
                    break;
                }
                
                // Generate UUID for new user
                $user_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
                
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $query = "INSERT INTO users (
                         id, first_name, last_name, email, phone, role, status, 
                         city, delivery_address, password_hash, created_at, updated_at
                         ) VALUES (
                         '$user_id', '$first_name', '$last_name', '$email', '$phone', 
                         '$role', '$status', '$city', '$delivery_address', 
                         '$hashed_password', NOW(), NOW()
                         )";
                
                if (mysqli_query($connection, $query)) {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'User created successfully',
                        'user_id' => $user_id
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to create user: ' . mysqli_error($connection)]);
                }
                break;
                
            case 'update_user':
                $user_id = mysqli_real_escape_string($connection, $_POST['user_id']);
                $first_name = mysqli_real_escape_string($connection, $_POST['first_name']);
                $last_name = mysqli_real_escape_string($connection, $_POST['last_name']);
                $email = mysqli_real_escape_string($connection, $_POST['email']);
                $phone = mysqli_real_escape_string($connection, $_POST['phone']);
                $role = mysqli_real_escape_string($connection, $_POST['role']);
                $status = mysqli_real_escape_string($connection, $_POST['status']);
                $city = mysqli_real_escape_string($connection, $_POST['city']);
                $delivery_address = mysqli_real_escape_string($connection, $_POST['delivery_address']);
                
                // Validate required fields
                if (empty($first_name) || empty($last_name) || empty($email)) {
                    echo json_encode(['success' => false, 'message' => 'First name, last name, and email are required']);
                    break;
                }
                
                // Validate email uniqueness
                $email_check = "SELECT id FROM users WHERE email = '$email' AND id != '$user_id'";
                $email_result = mysqli_query($connection, $email_check);
                
                if (mysqli_num_rows($email_result) > 0) {
                    echo json_encode(['success' => false, 'message' => 'Email already exists']);
                    break;
                }
                
                $query = "UPDATE users SET 
                         first_name = '$first_name',
                         last_name = '$last_name', 
                         email = '$email',
                         phone = '$phone',
                         role = '$role',
                         status = '$status',
                         city = '$city',
                         delivery_address = '$delivery_address',
                         updated_at = NOW()
                         WHERE id = '$user_id'";
                
                if (mysqli_query($connection, $query)) {
                    echo json_encode(['success' => true, 'message' => 'User updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update user: ' . mysqli_error($connection)]);
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
                
            case 'reset_password':
                $user_id = mysqli_real_escape_string($connection, $_POST['user_id']);
                
                // Generate temporary password
                $temp_password = bin2hex(random_bytes(8));
                $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                
                $query = "UPDATE users SET 
                         password_hash = '$hashed_password',
                         failed_login_attempts = 0,
                         locked_until = NULL,
                         updated_at = NOW()
                         WHERE id = '$user_id'";
                
                if (mysqli_query($connection, $query)) {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Password reset successfully',
                        'temp_password' => $temp_password
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
                }
                break;
                
            case 'delete_user':
                // 🗑️ HARD DELETE - ลบจริงๆ จากฐานข้อมูล
                
                if (empty($_POST['user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'User ID is required']);
                    break;
                }
                
                $user_id = mysqli_real_escape_string($connection, $_POST['user_id']);
                
                try {
                    // ป้องกัน admin ลบตัวเอง
                    if ($user_id === $_SESSION['user_id']) {
                        echo json_encode([
                            'success' => false, 
                            'message' => 'You cannot delete your own account'
                        ]);
                        break;
                    }
                    
                    // Get user information ก่อนลบ
                    $user_query = "SELECT id, role, first_name, last_name, status, email FROM users WHERE id = '$user_id'";
                    $user_result = mysqli_query($connection, $user_query);
                    
                    if (!$user_result || mysqli_num_rows($user_result) === 0) {
                        echo json_encode(['success' => false, 'message' => 'User not found']);
                        break;
                    }
                    
                    $user = mysqli_fetch_assoc($user_result);
                    $full_name = trim($user['first_name'] . ' ' . $user['last_name']);
                    
                    // ป้องกันการลบ admin คนสุดท้าย
                    if ($user['role'] === 'admin') {
                        $admin_count_query = "SELECT COUNT(*) as count FROM users WHERE role = 'admin'";
                        $admin_count_result = mysqli_query($connection, $admin_count_query);
                        
                        if ($admin_count_result) {
                            $admin_count = mysqli_fetch_assoc($admin_count_result)['count'];
                            if ($admin_count <= 1) {
                                echo json_encode([
                                    'success' => false, 
                                    'message' => 'Cannot delete the last admin user'
                                ]);
                                break;
                            }
                        }
                    }
                    
                    // เช็คว่ามี related data หรือไม่ (เพื่อแจ้งเตือน)
                    $related_data = [];
                    
                    // Check subscriptions
                    $subs_result = mysqli_query($connection, "SELECT COUNT(*) as count FROM subscriptions WHERE user_id = '$user_id'");
                    if ($subs_result) {
                        $subs_count = mysqli_fetch_assoc($subs_result)['count'];
                        if ($subs_count > 0) {
                            $related_data[] = "$subs_count subscription(s)";
                        }
                    }
                    
                    // Check orders
                    $orders_result = mysqli_query($connection, "SELECT COUNT(*) as count FROM orders WHERE user_id = '$user_id'");
                    if ($orders_result) {
                        $orders_count = mysqli_fetch_assoc($orders_result)['count'];
                        if ($orders_count > 0) {
                            $related_data[] = "$orders_count order(s)";
                        }
                    }
                    
                    // Check payments
                    $payments_result = mysqli_query($connection, "SELECT COUNT(*) as count FROM payments WHERE user_id = '$user_id'");
                    if ($payments_result) {
                        $payments_count = mysqli_fetch_assoc($payments_result)['count'];
                        if ($payments_count > 0) {
                            $related_data[] = "$payments_count payment(s)";
                        }
                    }
                    
                    // สร้าง warning message ถ้ามี related data
                    $warning_message = '';
                    if (!empty($related_data)) {
                        $warning_message = "\n\n⚠️ Warning: This user has " . implode(", ", $related_data) . " that will become orphaned records.";
                    }
                    
                    // ลบ user จริงๆ จากฐานข้อมูล
                    $delete_query = "DELETE FROM users WHERE id = '$user_id'";
                    $delete_result = mysqli_query($connection, $delete_query);
                    
                    if ($delete_result) {
                        $affected_rows = mysqli_affected_rows($connection);
                        
                        if ($affected_rows > 0) {
                            echo json_encode([
                                'success' => true, 
                                'message' => "✅ User \"$full_name\" has been permanently deleted from the database" . $warning_message,
                                'user_name' => $full_name
                            ]);
                        } else {
                            echo json_encode([
                                'success' => false, 
                                'message' => 'No user was deleted. User may not exist.'
                            ]);
                        }
                    } else {
                        // จัดการ error (เช่น Foreign Key Constraint)
                        $mysql_error = mysqli_error($connection);
                        
                        if (strpos(strtolower($mysql_error), 'foreign key') !== false) {
                            echo json_encode([
                                'success' => false, 
                                'message' => "❌ Cannot delete user \"$full_name\" because they have related data.\n\nTo fix this:\n1. Delete their subscriptions/orders first, or\n2. Ask system administrator to disable foreign key checks",
                                'technical_error' => $mysql_error,
                                'has_constraints' => true
                            ]);
                        } else {
                            echo json_encode([
                                'success' => false, 
                                'message' => 'Failed to delete user: ' . $mysql_error
                            ]);
                        }
                    }
                    
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Server error: ' . $e->getMessage()
                    ]);
                }
                break;

            case 'force_delete_user':
                // Force delete - ลบพร้อมจัดการ foreign keys
                if (empty($_POST['user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'User ID is required']);
                    break;
                }
                
                $user_id = mysqli_real_escape_string($connection, $_POST['user_id']);
                
                try {
                    // Get user info
                    $user_query = "SELECT first_name, last_name FROM users WHERE id = '$user_id'";
                    $user_result = mysqli_query($connection, $user_query);
                    
                    if (!$user_result || mysqli_num_rows($user_result) === 0) {
                        echo json_encode(['success' => false, 'message' => 'User not found']);
                        break;
                    }
                    
                    $user = mysqli_fetch_assoc($user_result);
                    $full_name = trim($user['first_name'] . ' ' . $user['last_name']);
                    
                    // Start transaction
                    mysqli_autocommit($connection, false);
                    
                    // ปิด foreign key checks ชั่วคราว
                    mysqli_query($connection, "SET foreign_key_checks = 0");
                    
                    // ลบ user
                    $delete_query = "DELETE FROM users WHERE id = '$user_id'";
                    $result = mysqli_query($connection, $delete_query);
                    
                    // เปิด foreign key checks กลับ
                    mysqli_query($connection, "SET foreign_key_checks = 1");
                    
                    if ($result && mysqli_affected_rows($connection) > 0) {
                        mysqli_commit($connection);
                        mysqli_autocommit($connection, true);
                        
                        echo json_encode([
                            'success' => true, 
                            'message' => "✅ User \"$full_name\" has been force deleted from database"
                        ]);
                    } else {
                        throw new Exception('Failed to delete user');
                    }
                    
                } catch (Exception $e) {
                    mysqli_query($connection, "SET foreign_key_checks = 1");
                    mysqli_rollback($connection);
                    mysqli_autocommit($connection, true);
                    
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Error force deleting user: ' . $e->getMessage()
                    ]);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
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
if ($users_result) {
    while ($user = mysqli_fetch_assoc($users_result)) {
        $users[] = $user;
    }
}

// Get total count for pagination
$count_query = "SELECT COUNT(DISTINCT u.id) as total FROM users u $where_clause";
$count_result = mysqli_query($connection, $count_query);
$total_users = $count_result ? mysqli_fetch_assoc($count_result)['total'] : 0;
$total_pages = ceil($total_users / $per_page);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
    SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) as customers,
    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_30_days
    FROM users";

$stats_result = mysqli_query($connection, $stats_query);
$stats = $stats_result ? mysqli_fetch_assoc($stats_result) : [
    'total_users' => 0,
    'active_users' => 0,
    'customers' => 0,
    'new_users_30_days' => 0
];
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
            border: 2px solid var(--border-light);
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
            display: flex !important;
            opacity: 1;
            align-items: center;
            justify-content: center;
        }

        .modal-dialog {
            background: var(--white);
            border-radius: var(--radius-md);
            max-width: 900px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.9);
            transition: transform 0.3s ease;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
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
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
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
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            color: var(--text-dark);
            background: rgba(0, 0, 0, 0.1);
            border-radius: 50%;
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

        /* Edit Modal Specific Styles */
        .form-section {
            background: var(--cream);
            padding: 1.5rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1.5rem;
        }

        .form-section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .edit-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .quick-action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.5rem;
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
                max-height: 95vh;
            }

            .actions {
                flex-direction: column;
                gap: 0.25rem;
            }

            .edit-form-grid {
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
                        <button class="btn btn-success" onclick="addUser()">
                            <i class="fas fa-user-plus"></i>
                            Add New User
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
                                                    if (isset($user['last_login']) && $user['last_login']) {
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
                                            <button class="btn btn-icon btn-warning btn-sm" onclick="editUser('<?= $user['id'] ?>')" title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <!-- <button class="btn btn-icon btn-success btn-sm" onclick="resetPassword('<?= $user['id'] ?>')" title="Reset Password">
                                                <i class="fas fa-key"></i>
                                            </button> -->
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
                <button class="modal-close" onclick="closeModal()" type="button">&times;</button>
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

    <!-- Enhanced Add User Modal -->
    <div class="modal" id="addUserModal">
        <div class="modal-dialog" style="max-width: 900px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-user-plus" style="color: var(--curry); margin-right: 0.5rem;"></i>
                    Add New User
                </h3>
                <button class="modal-close" onclick="closeAddModal()" type="button">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addUserForm">
                    <!-- Basic Information -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-user"></i>
                            Basic Information
                        </div>
                        <div class="edit-form-grid">
                            <div class="form-group">
                                <label class="form-label">First Name *</label>
                                <input type="text" id="addFirstName" name="first_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Last Name *</label>
                                <input type="text" id="addLastName" name="last_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email *</label>
                                <input type="email" id="addEmail" name="email" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="text" id="addPhone" name="phone" class="form-control">
                            </div>
                        </div>
                    </div>

                    <!-- Account Setup -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-key"></i>
                            Account Setup
                        </div>
                        <div class="edit-form-grid">
                            <div class="form-group">
                                <label class="form-label">Password *</label>
                                <input type="password" id="addPassword" name="password" class="form-control" required minlength="8">
                                <small style="color: var(--text-gray); font-size: 0.8rem;">Minimum 8 characters</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm Password *</label>
                                <input type="password" id="addConfirmPassword" name="confirm_password" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <!-- Role & Status -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-user-cog"></i>
                            Role & Status
                        </div>
                        <div class="edit-form-grid">
                            <div class="form-group">
                                <label class="form-label">Role *</label>
                                <select id="addRole" name="role" class="form-control form-select" required>
                                    <option value="customer">Customer</option>
                                    <option value="admin">Admin</option>
                                    <option value="kitchen">Kitchen Staff</option>
                                    <option value="rider">Delivery Rider</option>
                                    <option value="support">Support Staff</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status *</label>
                                <select id="addStatus" name="status" class="form-control form-select" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="pending_verification">Pending Verification</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Address Information -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Address Information
                        </div>
                        <div class="edit-form-grid">
                            <div class="form-group">
                                <label class="form-label">City</label>
                                <input type="text" id="addCity" name="city" class="form-control">
                            </div>
                            <div class="form-group" style="grid-column: span 2;">
                                <label class="form-label">Delivery Address</label>
                                <textarea id="addDeliveryAddress" name="delivery_address" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--border-light);">
                        <button type="button" class="btn btn-secondary" onclick="closeAddModal()">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-success" id="createUserBtn">
                            <i class="fas fa-user-plus"></i>
                            Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Enhanced Edit User Modal -->
    <div class="modal" id="editUserModal">
        <div class="modal-dialog" style="max-width: 900px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-user-edit" style="color: var(--curry); margin-right: 0.5rem;"></i>
                    Edit User Details
                </h3>
                <button class="modal-close" onclick="closeEditModal()" type="button">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editUserForm">
                    <input type="hidden" id="editUserId" name="user_id">
                    
                    <!-- Basic Information -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-user"></i>
                            Basic Information
                        </div>
                        <div class="edit-form-grid">
                            <div class="form-group">
                                <label class="form-label">First Name *</label>
                                <input type="text" id="editFirstName" name="first_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Last Name *</label>
                                <input type="text" id="editLastName" name="last_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email *</label>
                                <input type="email" id="editEmail" name="email" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="text" id="editPhone" name="phone" class="form-control">
                            </div>
                        </div>
                    </div>

                    <!-- Role & Status -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-user-cog"></i>
                            Role & Status
                        </div>
                        <div class="edit-form-grid">
                            <div class="form-group">
                                <label class="form-label">Role *</label>
                                <select id="editRole" name="role" class="form-control form-select" required>
                                    <option value="customer">Customer</option>
                                    <option value="admin">Admin</option>
                                    <option value="kitchen">Kitchen Staff</option>
                                    <option value="rider">Delivery Rider</option>
                                    <option value="support">Support Staff</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status *</label>
                                <select id="editStatus" name="status" class="form-control form-select" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                    <option value="pending_verification">Pending Verification</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Address Information -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Address Information
                        </div>
                        <div class="edit-form-grid">
                            <div class="form-group">
                                <label class="form-label">City</label>
                                <input type="text" id="editCity" name="city" class="form-control">
                            </div>
                            <div class="form-group" style="grid-column: span 2;">
                                <label class="form-label">Delivery Address</label>
                                <textarea id="editDeliveryAddress" name="delivery_address" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--border-light);">
                        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" id="saveUserBtn">
                            <i class="fas fa-save"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<!-- Export Modal -->
    <div class="modal" id="exportModal">
        <div class="modal-dialog" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-download" style="color: var(--curry); margin-right: 0.5rem;"></i>
                    Export Users
                </h3>
                <button class="modal-close" onclick="closeExportModal()" type="button">&times;</button>
            </div>
            <div class="modal-body">
                <form id="exportForm">
                    <!-- Export Format -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-file-export"></i>
                            Export Format
                        </div>
                        <div class="form-group">
                            <label class="form-label">File Format</label>
                          <select name="format" class="form-control form-select">
    <option value="csv">CSV (Opens in Excel)</option>
</select>
<small style="color: var(--text-gray); font-size: 0.8rem;">
    CSV files can be opened in Excel, Google Sheets, or other spreadsheet applications
</small>
                        </div>
                    </div>
                    <!-- Export Options -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-cog"></i>
                            Export Options
                        </div>
                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="include_statistics" value="1" checked style="margin: 0;">
                                <span>Include user statistics (subscriptions, orders, spending)</span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="include_address" value="1" checked style="margin: 0;">
                                <span>Include address information</span>
                            </label>
                        </div>
                    </div>
                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--border-light);">
                        <button type="button" class="btn btn-secondary" onclick="closeExportModal()">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                        <button type="button" class="btn btn-primary" onclick="handleExport()">
                            <i class="fas fa-download"></i>
                            Export Users
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>


   

    <script>
        // Global variables
        let currentUserData = null;
        let isSubmitting = false;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit filter form on input change
            document.querySelectorAll('#filterForm input, #filterForm select').forEach(element => {
                element.addEventListener('change', function() {
                    setTimeout(() => {
                        document.getElementById('filterForm').submit();
                    }, 500);
                });
            });

            // Setup form submissions
            document.getElementById('editUserForm').addEventListener('submit', handleEditFormSubmit);
            document.getElementById('addUserForm').addEventListener('submit', handleAddFormSubmit);

            // Password confirmation validation
            document.getElementById('addConfirmPassword').addEventListener('input', validatePasswordMatch);

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey || e.metaKey) {
                    switch(e.key) {
                        case 'r':
                            e.preventDefault();
                            refreshData();
                            break;
                        case 'f':
                            e.preventDefault();
                            document.querySelector('input[name="search"]').focus();
                            break;
                    }
                }
                if (e.key === 'Escape') {
                    closeModal();
                    closeEditModal();
                    closeAddModal();
                }
            });
        });

        // View user details
        async function viewUser(userId) {
            showModal();
            document.getElementById('userModalContent').innerHTML = `
                <div class="loading">
                    <div class="spinner"></div>
                </div>
            `;

            try {
                const response = await fetch('users.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_user&user_id=${userId}`
                });

                const data = await response.json();

                if (data.success) {
                    const user = data.user;
                    document.getElementById('userModalContent').innerHTML = `
                        <div class="user-detail-section">
                            <div class="section-title">Personal Information</div>
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
                                    <div class="detail-label">City</div>
                                    <div class="detail-value">${user.city || 'Not provided'}</div>
                                </div>
                            </div>
                        </div>

                        <div class="user-detail-section">
                            <div class="section-title">Account Statistics</div>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <div class="detail-label">Active Subscriptions</div>
                                    <div class="detail-value">${user.subscription_count}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Total Orders</div>
                                    <div class="detail-value">${user.order_count}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Total Spent</div>
                                    <div class="detail-value">฿${parseFloat(user.total_spent).toLocaleString()}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Last Order</div>
                                    <div class="detail-value">${user.last_order_date ? new Date(user.last_order_date).toLocaleDateString() : 'Never'}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Member Since</div>
                                    <div class="detail-value">${new Date(user.created_at).toLocaleDateString()}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Last Updated</div>
                                    <div class="detail-value">${new Date(user.updated_at).toLocaleDateString()}</div>
                                </div>
                            </div>
                        </div>

                        ${user.delivery_address ? `
                        <div class="user-detail-section">
                            <div class="section-title">Delivery Information</div>
                            <div class="detail-grid">
                                <div class="detail-item" style="grid-column: span 2;">
                                    <div class="detail-label">Delivery Address</div>
                                    <div class="detail-value">${user.delivery_address}</div>
                                </div>
                            </div>
                        </div>
                        ` : ''}

                        <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--border-light);">
                            <button class="btn btn-warning" onclick="closeModal(); editUser('${user.id}');">
                                <i class="fas fa-edit"></i>
                                Edit User
                            </button>
                            <button class="btn btn-secondary" onclick="closeModal()">
                                <i class="fas fa-times"></i>
                                Close
                            </button>
                        </div>
                    `;
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                document.getElementById('userModalContent').innerHTML = `
                    <div style="text-align: center; padding: 2rem; color: var(--text-gray);">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem; color: #e74c3c;"></i>
                        <h3>Error Loading User</h3>
                        <p>${error.message}</p>
                        <button class="btn btn-secondary" onclick="closeModal()">Close</button>
                    </div>
                `;
            }
        }

        // Add new user
        function addUser() {
            console.log('addUser() called'); // Debug log
            try {
                showAddModal();
                document.getElementById('addUserForm').reset();
                document.getElementById('addRole').value = 'customer';
                document.getElementById('addStatus').value = 'active';
                console.log('Add user modal should be visible now'); // Debug log
            } catch (error) {
                console.error('Error in addUser():', error);
                showToast('Error opening add user modal: ' + error.message, 'error');
            }
        }

        // Handle add form submission
        async function handleAddFormSubmit(e) {
            e.preventDefault();
            
            if (isSubmitting) return;
            
            // Validate password match
            const password = document.getElementById('addPassword').value;
            const confirmPassword = document.getElementById('addConfirmPassword').value;
            
            if (password !== confirmPassword) {
                showToast('Passwords do not match!', 'error');
                return;
            }
            
            if (password.length < 8) {
                showToast('Password must be at least 8 characters long!', 'error');
                return;
            }
            
            isSubmitting = true;
            const submitBtn = document.getElementById('createUserBtn');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
            submitBtn.disabled = true;

            try {
                const formData = new FormData(e.target);
                formData.append('action', 'create_user');

                const response = await fetch('users.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showToast('User created successfully!', 'success');
                    closeAddModal();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                showToast('Error creating user: ' + error.message, 'error');
            } finally {
                isSubmitting = false;
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        }

        // Edit user
        async function editUser(userId) {
            showEditModal();
            
            try {
                const response = await fetch('users.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_user&user_id=${userId}`
                });

                const data = await response.json();

                if (data.success) {
                    currentUserData = data.user;
                    populateEditForm(data.user);
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                showToast('Error loading user data: ' + error.message, 'error');
                closeEditModal();
            }
        }

        // Populate edit form
        function populateEditForm(user) {
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editFirstName').value = user.first_name || '';
            document.getElementById('editLastName').value = user.last_name || '';
            document.getElementById('editEmail').value = user.email || '';
            document.getElementById('editPhone').value = user.phone || '';
            document.getElementById('editRole').value = user.role || 'customer';
            document.getElementById('editStatus').value = user.status || 'active';
            document.getElementById('editCity').value = user.city || '';
            document.getElementById('editDeliveryAddress').value = user.delivery_address || '';
        }

        // Handle form submission
        async function handleEditFormSubmit(e) {
            e.preventDefault();
            
            if (isSubmitting) return;
            
            isSubmitting = true;
            const submitBtn = document.getElementById('saveUserBtn');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;

            try {
                const formData = new FormData(e.target);
                formData.append('action', 'update_user');

                const response = await fetch('users.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showToast('User updated successfully!', 'success');
                    closeEditModal();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                showToast('Error updating user: ' + error.message, 'error');
            } finally {
                isSubmitting = false;
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        }

        // Reset password
        async function resetPassword(userId) {
            if (!confirm('Are you sure you want to reset this user\'s password? A temporary password will be generated.')) {
                return;
            }

            try {
                const response = await fetch('users.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=reset_password&user_id=${userId}`
                });

                const data = await response.json();

                if (data.success) {
                    showToast('Password reset successfully!', 'success');
                    
                    // Show temporary password
                    setTimeout(() => {
                        alert(`Temporary password: ${data.temp_password}\n\nPlease share this with the user securely and ask them to change it immediately.`);
                    }, 500);
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                showToast('Error resetting password: ' + error.message, 'error');
            }
        }

// Delete/Deactivate user
async function deleteUser(userId) {
    if (!confirm('Are you sure you want to deactivate this user? This will set their status to inactive and they cannot login.')) {
        return;
    }

    try {
        const response = await fetch('users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_user&user_id=${userId}`
        });

        const data = await response.json();

        if (data.success) {
            showToast('User deactivated successfully!', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            // แก้ตรงนี้ - เพิ่มการจัดการ already_inactive
            if (data.already_inactive) {
                if (confirm(`${data.message}\n\nDo you want to reactivate this user instead?`)) {
                    reactivateUser(userId);
                }
            } else if (data.has_subscriptions) {
                if (confirm(`${data.message}\n\nClick OK to force deactivate anyway.`)) {
                    forceDeleteUser(userId);
                }
            } else {
                throw new Error(data.message);
            }
        }
    } catch (error) {
        console.error('Delete error:', error);
        showToast('Error deactivating user: ' + error.message, 'error');
    }
}

// เพิ่ม function ใหม่สำหรับ force delete
async function forceDeleteUser(userId) {
    try {
        const response = await fetch('users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=force_delete_user&user_id=${userId}`
        });

        const data = await response.json();

        if (data.success) {
            showToast('User deactivated successfully!', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        showToast('Error force deactivating user: ' + error.message, 'error');
    }
}
// เพิ่ม function ใหม่สำหรับ reactivate
async function reactivateUser(userId) {
    try {
        const response = await fetch('users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=reactivate_user&user_id=${userId}`
        });

        const data = await response.json();

        if (data.success) {
            showToast('User reactivated successfully!', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        showToast('Error reactivating user: ' + error.message, 'error');
    }
}


        // Modal functions
        function showModal() {
            const modal = document.getElementById('userModal');
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
        }

        function closeModal() {
            const modal = document.getElementById('userModal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        function showAddModal() {
            console.log('showAddModal() called'); // Debug log
            const modal = document.getElementById('addUserModal');
            console.log('Modal element:', modal); // Debug log
            
            if (!modal) {
                console.error('Add user modal not found!');
                showToast('Modal element not found', 'error');
                return;
            }
            
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.add('show');
                console.log('Modal should be visible now'); // Debug log
            }, 10);
        }

        function closeAddModal() {
            const modal = document.getElementById('addUserModal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                document.getElementById('addUserForm').reset();
            }, 300);
        }

        function showEditModal() {
            const modal = document.getElementById('editUserModal');
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
        }

        function closeEditModal() {
            const modal = document.getElementById('editUserModal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                document.getElementById('editUserForm').reset();
                currentUserData = null;
            }, 300);
        }

        // Validate password match
        function validatePasswordMatch() {
            const password = document.getElementById('addPassword').value;
            const confirmPassword = document.getElementById('addConfirmPassword').value;
            const confirmField = document.getElementById('addConfirmPassword');
            
            if (confirmPassword && password !== confirmPassword) {
                confirmField.style.borderColor = '#e74c3c';
                confirmField.style.boxShadow = '0 0 0 3px rgba(231, 76, 60, 0.1)';
            } else {
                confirmField.style.borderColor = '';
                confirmField.style.boxShadow = '';
            }
        }

        // Utility functions
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (container.contains(toast)) {
                        container.removeChild(toast);
                    }
                }, 300);
            }, 4000);
        }

        function refreshData() {
            showToast('Refreshing data...', 'info');
            setTimeout(() => {
                window.location.reload();
            }, 500);
        }

       function exportUsers() {
    showExportModal();
}

// Show export modal
function showExportModal() {
    const modal = document.getElementById('exportModal');
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

// Close export modal
function closeExportModal() {
    const modal = document.getElementById('exportModal');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

// Handle export form submission
function handleExport() {
    const form = document.getElementById('exportForm');
    const formData = new FormData(form);
    
    // Get current filter values
    const currentSearch = new URLSearchParams(window.location.search).get('search') || '';
    const currentStatus = new URLSearchParams(window.location.search).get('status') || '';
    const currentRole = new URLSearchParams(window.location.search).get('role') || '';
    
    // Build export URL with current filters
    const params = new URLSearchParams();
    params.append('format', formData.get('format'));
    params.append('include_statistics', formData.get('include_statistics') || '0');
    params.append('include_address', formData.get('include_address') || '0');
    
    if (currentSearch) params.append('search', currentSearch);
    if (currentStatus) params.append('status', currentStatus);
    if (currentRole) params.append('role', currentRole);
    
    const exportUrl = 'export-users.php?' + params.toString();
    
    showToast('Preparing export...', 'info');
    
    // Create hidden iframe for download
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = exportUrl;
    document.body.appendChild(iframe);
    
    // Remove iframe after download
    setTimeout(() => {
        document.body.removeChild(iframe);
        showToast('Export completed!', 'success');
    }, 3000);
    
    closeExportModal();
}

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '../logout.php';
            }
        }

        // Close modals when clicking outside
window.addEventListener('click', function(e) {
    const userModal = document.getElementById('userModal');
    const editModal = document.getElementById('editUserModal');
    const addModal = document.getElementById('addUserModal');
    const exportModal = document.getElementById('exportModal');
    
    if (e.target === userModal) {
        closeModal();
    }
    
    if (e.target === editModal) {
        closeEditModal();
    }
    
    if (e.target === addModal) {
        closeAddModal();
    }
    
    if (e.target === exportModal) {
        closeExportModal();
    }
});
    </script>
</body>
</html>