<?php
/**
 * Dashboard with Error Handling
 * File: dashboard.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];

// Initialize variables with defaults
$user = null;
$subscriptions = [];
$recent_orders = [];
$nutrition_data = [];
$notifications = [];
$upcoming_delivery = null;

// Initialize stats with defaults
$active_subscriptions = 0;
$total_orders = 0;
$avg_nutrition = 0;

// Error tracking
$db_errors = [];

// Check if we have connection
if (!isset($connection)) {
    $db_errors[] = "Database connection variable not found";
} else {
    // Test connection
    if (!mysqli_ping($connection)) {
        $db_errors[] = "Database connection lost";
    }
}

// Only proceed if connection is good
if (empty($db_errors)) {
    // 1. Get user information with error handling
    try {
        $user_query = "SELECT * FROM users WHERE id = ?";
        $stmt = mysqli_prepare($connection, $user_query);
        
        if ($stmt === false) {
            $db_errors[] = "Failed to prepare user query: " . mysqli_error($connection);
        } else {
            mysqli_stmt_bind_param($stmt, "s", $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                $user = mysqli_fetch_assoc($result);
                
                if (!$user) {
                    $db_errors[] = "User not found in database";
                }
            } else {
                $db_errors[] = "Failed to execute user query: " . mysqli_stmt_error($stmt);
            }
            
            mysqli_stmt_close($stmt);
        }
    } catch (Exception $e) {
        $db_errors[] = "User query exception: " . $e->getMessage();
    }

    // 2. Get subscriptions count (simplified)
    if (empty($db_errors)) {
        try {
            $subs_query = "SELECT COUNT(*) as count FROM subscriptions WHERE user_id = ?";
            $stmt = mysqli_prepare($connection, $subs_query);
            
            if ($stmt !== false) {
                mysqli_stmt_bind_param($stmt, "s", $user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    $sub_count = mysqli_fetch_assoc($result);
                    $active_subscriptions = (int)($sub_count['count'] ?? 0);
                }
                mysqli_stmt_close($stmt);
            } else {
                // Table might not exist
                $db_errors[] = "Subscriptions table may not exist";
            }
        } catch (Exception $e) {
            $db_errors[] = "Subscriptions query error: " . $e->getMessage();
        }
    }

    // 3. Get orders count (simplified)
    if (empty($db_errors)) {
        try {
            $orders_query = "SELECT COUNT(*) as count FROM orders WHERE user_id = ?";
            $stmt = mysqli_prepare($connection, $orders_query);
            
            if ($stmt !== false) {
                mysqli_stmt_bind_param($stmt, "s", $user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    $order_count = mysqli_fetch_assoc($result);
                    $total_orders = (int)($order_count['count'] ?? 0);
                }
                mysqli_stmt_close($stmt);
            } else {
                $db_errors[] = "Orders table may not exist";
            }
        } catch (Exception $e) {
            $db_errors[] = "Orders query error: " . $e->getMessage();
        }
    }

    // 4. Get notifications count (simplified)
    $notification_count = 0;
    try {
        $notif_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ?";
        $stmt = mysqli_prepare($connection, $notif_query);
        
        if ($stmt !== false) {
            mysqli_stmt_bind_param($stmt, "s", $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                $notif_result = mysqli_fetch_assoc($result);
                $notification_count = (int)($notif_result['count'] ?? 0);
            }
            mysqli_stmt_close($stmt);
        }
    } catch (Exception $e) {
        // Notifications table may not exist, ignore error
    }

    // Update last login (if user found)
    if ($user) {
        try {
            $update_query = "UPDATE users SET last_login = NOW() WHERE id = ?";
            $stmt = mysqli_prepare($connection, $update_query);
            if ($stmt !== false) {
                mysqli_stmt_bind_param($stmt, "s", $user_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        } catch (Exception $e) {
            // Non-critical error, continue
        }
    }
}

// Fallback user data if database failed
if (!$user) {
    $user = [
        'id' => $user_id,
        'first_name' => $_SESSION['user_name'] ?? 'User',
        'last_name' => '',
        'email' => $_SESSION['user_email'] ?? 'Unknown'
    ];
}

$page_title = "Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
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

        .dashboard {
            min-height: 100vh;
            padding: 2rem 0;
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--olive) 0%, var(--matcha) 100%);
            color: var(--white);
            padding: 3rem 0;
            margin-bottom: 3rem;
            border-radius: 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .dashboard-header::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 40%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="70" cy="30" r="15" fill="rgba(255,255,255,0.1)"/></svg>');
            opacity: 0.3;
        }

        .dashboard-welcome {
            position: relative;
            z-index: 1;
        }

        .dashboard-welcome h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .dashboard-welcome p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .stats-section {
            margin-bottom: 3rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px var(--shadow);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: var(--brown);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gray);
            font-weight: 500;
        }

        .dashboard-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 3rem;
        }

        .dashboard-main {
            display: flex;
            flex-direction: column;
            gap: 3rem;
        }

        .dashboard-sidebar {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .sidebar-card {
            background: var(--white);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px var(--shadow);
        }

        .sidebar-card h3 {
            color: var(--olive);
            margin-bottom: 1.5rem;
            font-size: 1.2rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.6;
        }

        .empty-state h3 {
            color: var(--olive);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--gray);
            margin-bottom: 2rem;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--brown), #a67c00);
            color: var(--white);
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(134, 96, 40, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: var(--brown);
            padding: 0.8rem 1.5rem;
            border: 2px solid var(--brown);
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background: var(--brown);
            color: var(--white);
        }

        .btn-logout {
            background: var(--danger);
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            background: #c82333;
            transform: translateY(-1px);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--light-cream);
        }

        .section-header h2 {
            color: var(--olive);
            font-size: 1.5rem;
            margin: 0;
            font-weight: 700;
        }

        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .action-button {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--light-cream);
            border-radius: 10px;
            text-decoration: none;
            color: var(--olive);
            transition: all 0.3s ease;
        }

        .action-button:hover {
            background: var(--cream);
            transform: translateX(5px);
        }

        .action-icon {
            font-size: 1.2rem;
        }

        .action-text {
            font-weight: 500;
        }

        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
            font-family: monospace;
            font-size: 0.85rem;
        }

        .error-list {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }

        .error-list ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        .logout-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--light-cream);
            text-align: center;
        }

        @media (max-width: 1024px) {
            .dashboard-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .dashboard-sidebar {
                order: -1;
            }
        }

        @media (max-width: 768px) {
            .dashboard-welcome h1 {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="container">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="dashboard-welcome">
                    <h1>Hello, <?php echo htmlspecialchars($user['first_name']); ?>! 👋</h1>
                    <p>Welcome to your dashboard - manage your subscriptions and track your health journey</p>
                </div>
            </div>

            <!-- Show errors if any (for debugging) -->
            <?php if (!empty($db_errors)): ?>
            <div class="error-list">
                <h4>🚨 Database Issues Detected:</h4>
                <ul>
                    <?php foreach ($db_errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p><strong>Note:</strong> Some features may not work correctly. Please check your database setup.</p>
            </div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="stats-section">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $active_subscriptions; ?></div>
                        <div class="stat-label">Active Plans</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_orders; ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo round($avg_nutrition); ?>%</div>
                        <div class="stat-label">Nutrition Goals</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $notification_count ?? 0; ?></div>
                        <div class="stat-label">New Notifications</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-content">
                <!-- Main Content -->
                <div class="dashboard-main">
                    <!-- Welcome Message -->
                    <section>
                        <div class="empty-state">
                            <div class="empty-icon">🎉</div>
                            <h3>Welcome to Krua Thai!</h3>
                            <p>You have successfully logged in. Start exploring our features!</p>
                            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                                <a href="subscribe.php" class="btn-primary">Choose Plan</a>
                                <a href="menus.php" class="btn-secondary">View Menu</a>
                            </div>
                        </div>
                    </section>

                    <!-- Debug Info -->
                    <section>
                        <div class="sidebar-card">
                            <h3>🔧 Debug Information</h3>
                            <div class="debug-info">
                                <p><strong>User ID:</strong> <?php echo htmlspecialchars($user_id); ?></p>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                <p><strong>Role:</strong> <?php echo htmlspecialchars($_SESSION['user_role'] ?? 'Unknown'); ?></p>
                                <p><strong>Login Time:</strong> <?php echo date('Y-m-d H:i:s', $_SESSION['login_time'] ?? time()); ?></p>
                                <p><strong>Database Connection:</strong> <?php echo isset($connection) && mysqli_ping($connection) ? '✅ Active' : '❌ Failed'; ?></p>
                                <p><strong>Errors Count:</strong> <?php echo count($db_errors); ?></p>
                            </div>
                        </div>
                    </section>

                    <!-- Database Setup Help -->
                    <?php if (!empty($db_errors)): ?>
                    <section>
                        <div class="sidebar-card">
                            <h3>🛠️ Fix Database Issues</h3>
                            <ol style="color: var(--gray); line-height: 1.8;">
                                <li>Check if MySQL is running in MAMP/XAMPP</li>
                                <li>Verify database 'krua_thai' exists</li>
                                <li>Import krua_thai.sql file</li>
                                <li>Check database credentials in config/database.php</li>
                                <li>Run <a href="test_connection.php" target="_blank">test_connection.php</a> to debug</li>
                            </ol>
                        </div>
                    </section>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="dashboard-sidebar">
                    <!-- Quick Actions -->
                    <div class="sidebar-card">
                        <h3>🚀 Quick Actions</h3>
                        <div class="quick-actions">
                            <a href="subscribe.php" class="action-button">
                                <span class="action-icon">📦</span>
                                <span class="action-text">Choose New Plan</span>
                            </a>
                            <a href="edit_profile.php" class="action-button">
                                <span class="action-icon">👤</span>
                                <span class="action-text">Edit Profile</span>
                            </a>
                            <a href="menus.php" class="action-button">
                                <span class="action-icon">🍽️</span>
                                <span class="action-text">View All Menus</span>
                            </a>
                            <a href="nutrition-tracking.php" class="action-button">
                                <span class="action-icon">📊</span>
                                <span class="action-text">Track Nutrition</span>
                            </a>
                        </div>
                        
                        <div class="logout-section">
                            <a href="logout.php" class="btn-logout">Logout</a>
                        </div>
                    </div>

                    <!-- User Info -->
                    <div class="sidebar-card">
                        <h3>👤 User Information</h3>
                        <div style="line-height: 1.8;">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                            <p><strong>Status:</strong> <span style="color: var(--success);">✅ Online</span></p>
                            <p><strong>Database:</strong> 
                                <?php if (empty($db_errors)): ?>
                                    <span style="color: var(--success);">✅ Connected</span>
                                <?php else: ?>
                                    <span style="color: var(--danger);">❌ Issues</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <!-- Support -->
                    <div class="sidebar-card">
                        <h3>🎧 Need Help?</h3>
                        <p>If you have any issues using the system, you can contact our support team</p>
                        <div style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <a href="help.php" class="btn-secondary">Help Center</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        console.log('Dashboard loaded successfully!');
        console.log('Database errors:', <?php echo json_encode($db_errors); ?>);
        
        // Add some interactivity
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Auto-refresh page if there are database errors (for development)
        <?php if (!empty($db_errors) && count($db_errors) < 5): ?>
        setTimeout(function() {
            if (confirm('Found database issues. Reload page to retry connection?')) {
                location.reload();
            }
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>