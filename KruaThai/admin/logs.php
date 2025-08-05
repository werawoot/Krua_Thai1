<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../login.php?redirect=admin/logs");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get current user's role
$role_query = "SELECT role FROM users WHERE id = ?";
$stmt = mysqli_prepare($connection, $role_query);
mysqli_stmt_bind_param($stmt, "s", $user_id);
mysqli_stmt_execute($stmt);
$current_user_role = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['role'];
mysqli_stmt_close($stmt);

// Check admin permissions
if ($current_user_role !== 'admin') {
    header("Location: ../dashboard.php");
    exit();
}

// Pagination and filtering
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$search = $_GET['search'] ?? '';
$action_filter = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Read and parse activity log
function parseActivityLog($search = '', $action_filter = '', $date_from = '', $date_to = '', $page = 1, $per_page = 50) {
    // Corrected path - activity.log is in ../logs/ directory
    $log_file = dirname(__DIR__) . '/logs/activity.log';
    
    if (!file_exists($log_file)) {
        return array('logs' => array(), 'total' => 0);
    }
    
    $logs = array();
    $file = fopen($log_file, 'r');
    
    if ($file) {
        while (($line = fgets($file)) !== false) {
            $log_entry = json_decode(trim($line), true);
            if ($log_entry) {
                // Apply filters
                $include = true;
                
                // Search filter
                if ($search && !empty($search)) {
                    $search_text = strtolower($search);
                    $searchable = strtolower(
                        $log_entry['action'] . ' ' . 
                        (isset($log_entry['user_id']) ? $log_entry['user_id'] : '') . ' ' . 
                        (isset($log_entry['ip_address']) ? $log_entry['ip_address'] : '') . ' ' .
                        json_encode(isset($log_entry['details']) ? $log_entry['details'] : array())
                    );
                    if (strpos($searchable, $search_text) === false) {
                        $include = false;
                    }
                }
                
                // Action filter
                if ($action_filter && $log_entry['action'] !== $action_filter) {
                    $include = false;
                }
                
                // Date filters
                if ($date_from && $log_entry['timestamp'] < $date_from . ' 00:00:00') {
                    $include = false;
                }
                if ($date_to && $log_entry['timestamp'] > $date_to . ' 23:59:59') {
                    $include = false;
                }
                
                if ($include) {
                    $logs[] = $log_entry;
                }
            }
        }
        fclose($file);
    }
    
    // Sort by timestamp (newest first)
    usort($logs, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    $total = count($logs);
    $offset = ($page - 1) * $per_page;
    $logs = array_slice($logs, $offset, $per_page);
    
    return array('logs' => $logs, 'total' => $total);
}

// Get user names for display
function getUserName($connection, $user_id) {
    if (!$user_id) return 'System';
    
    static $user_cache = array();
    if (isset($user_cache[$user_id])) {
        return $user_cache[$user_id];
    }
    
    $stmt = mysqli_prepare($connection, "SELECT first_name, last_name, role FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "s", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($user) {
        $name = trim($user['first_name'] . ' ' . $user['last_name']) . ' (' . ucfirst($user['role']) . ')';
        $user_cache[$user_id] = $name;
        return $name;
    }
    
    $user_cache[$user_id] = 'Unknown User';
    return 'Unknown User';
}

// Get unique actions for filter dropdown
function getUniqueActions($logs) {
    $actions = array();
    foreach ($logs as $log) {
        $actions[] = $log['action'];
    }
    return array_unique($actions);
}

$result = parseActivityLog($search, $action_filter, $date_from, $date_to, $page, $per_page);
$logs = $result['logs'];
$total_logs = $result['total'];
$total_pages = ceil($total_logs / $per_page);

// Get all logs for unique actions (limit to recent for performance)
$all_result = parseActivityLog('', '', '', '', 1, 1000);
$unique_actions = getUniqueActions($all_result['logs']);
sort($unique_actions);

$page_title = "System Activity Log";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Somdul Table</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* System Log Styles */
        @import url('https://ydpschool.com/fonts/');

        * {
            font-family: 'BaticaSans', Arial, sans-serif;
        }

        /* Layout with sidebar */
        .admin-container {
            min-height: 100vh;
            background: #f5f2ed;
        }

        .admin-main-content {
            margin-left: 280px; /* Account for fixed sidebar width */
            padding: 20px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .content-wrapper {
            max-width: 100%;
            margin: 0 auto;
            position: relative;
        }

        .admin-header {
            background: linear-gradient(135deg, #bd9379, #a67c5f);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(189, 147, 121, 0.1);
        }

        .admin-header .container {
            max-width: 100%;
            padding: 0 30px;
        }

        .header-content h1 {
            font-size: 2.5rem;
            margin: 0 0 10px 0;
            font-weight: 600;
        }

        .header-content p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
        }

        .breadcrumb {
            font-size: 14px;
            margin-bottom: 15px;
            opacity: 0.8;
        }

        .breadcrumb a {
            color: white;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .breadcrumb span {
            margin: 0 8px;
        }

        /* Filters Section */
        .filters-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(189, 147, 121, 0.1);
            margin-bottom: 25px;
            border: 1px solid #ece8e1;
        }

        .filters-form .filter-row {
            display: flex;
            gap: 20px;
            align-items: end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 160px;
            flex: 1;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: #bd9379;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group input,
        .filter-group select {
            padding: 12px 16px;
            border: 2px solid #ece8e1;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'BaticaSans', Arial, sans-serif;
            transition: all 0.3s ease;
            background: white;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #bd9379;
            box-shadow: 0 0 0 3px rgba(189, 147, 121, 0.1);
        }

        .filter-group .btn {
            margin-top: 0;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            border: 2px solid;
        }

        .btn-primary {
            background: #bd9379;
            color: white;
            border-color: #bd9379;
        }

        .btn-primary:hover {
            background: #a67c5f;
            border-color: #a67c5f;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: white;
            color: #bd9379;
            border-color: #bd9379;
        }

        .btn-secondary:hover {
            background: #bd9379;
            color: white;
            transform: translateY(-1px);
        }

        /* Results Info */
        .results-info {
            background: linear-gradient(135deg, #ece8e1, #f5f2ed);
            padding: 15px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            color: #bd9379;
            font-size: 14px;
            font-weight: 500;
            border-left: 4px solid #bd9379;
        }

        /* Log Table */
        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(189, 147, 121, 0.1);
            border: 1px solid #ece8e1;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .admin-table thead th {
            background: linear-gradient(135deg, #bd9379, #a67c5f);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 12px;
            padding: 18px 15px;
            text-align: left;
            border: none;
        }

        .admin-table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #f0f0f0;
        }

        .admin-table tbody tr:hover {
            background: rgba(189, 147, 121, 0.05);
            transform: translateX(2px);
        }

        .admin-table tbody td {
            padding: 15px;
            vertical-align: top;
            font-size: 13px;
            border: none;
        }

        /* Log Entry Status Colors */
        .log-entry.log-login-success { 
            border-left: 3px solid #4CAF50; 
            background: rgba(76, 175, 80, 0.02);
        }

        .log-entry.log-login-failed { 
            border-left: 3px solid #f44336; 
            background: rgba(244, 67, 54, 0.02);
        }

        .log-entry.log-user-registered { 
            border-left: 3px solid #2196F3; 
            background: rgba(33, 150, 243, 0.02);
        }

        .log-entry.log-menu-created,
        .log-entry.log-menu-deleted,
        .log-entry.log-menu-availability-changed { 
            border-left: 3px solid #FF9800; 
            background: rgba(255, 152, 0, 0.02);
        }

        .log-entry.log-order-status-updated,
        .log-entry.log-subscription-created { 
            border-left: 3px solid #9C27B0; 
            background: rgba(156, 39, 176, 0.02);
        }

        /* Action Badges */
        .action-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .action-login-success { background: #4CAF50; color: white; }
        .action-login-failed { background: #f44336; color: white; }
        .action-user-registered { background: #2196F3; color: white; }
        .action-user-logout { background: #9E9E9E; color: white; }
        .action-menu-created { background: #FF9800; color: white; }
        .action-menu-deleted { background: #f44336; color: white; }
        .action-menu-availability-changed { background: #FF5722; color: white; }
        .action-order-status-updated { background: #9C27B0; color: white; }
        .action-subscription-created { background: #673AB7; color: white; }
        .action-password-reset-requested { background: #FFC107; color: #333; }
        .action-category-created { background: #00BCD4; color: white; }

        /* Timestamp styling */
        .timestamp-main {
            font-weight: 600;
            color: #333;
            font-size: 13px;
        }

        .timestamp-time {
            font-size: 11px;
            color: #666;
            margin-top: 2px;
            font-family: 'Courier New', monospace;
        }

        /* User column */
        .user {
            font-weight: 500;
            color: #bd9379;
            max-width: 150px;
            word-wrap: break-word;
        }

        /* IP Address styling */
        .ip-address code {
            background: #f5f5f5;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            color: #333;
            border: 1px solid #ddd;
        }

        /* Details column */
        .log-details summary {
            cursor: pointer;
            color: #bd9379;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 10px;
            background: #f8f6f3;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .log-details summary:hover {
            background: #ece8e1;
            color: #a67c5f;
        }

        .log-details[open] summary {
            margin-bottom: 10px;
            background: #bd9379;
            color: white;
        }

        .log-details pre {
            background: #f8f8f8;
            padding: 12px;
            border-radius: 8px;
            font-size: 10px;
            max-height: 200px;
            overflow-y: auto;
            margin: 0;
            border: 1px solid #e0e0e0;
            font-family: 'Courier New', monospace;
            line-height: 1.4;
        }

        .no-details {
            color: #999;
            font-style: italic;
            font-size: 11px;
            padding: 6px 10px;
            background: #f9f9f9;
            border-radius: 6px;
        }

        /* User Agent styling */
        .user-agent-short {
            font-size: 12px;
            background: #f0f0f0;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-block;
            cursor: help;
        }

        /* No results styling */
        .no-results {
            text-align: center;
            padding: 40px;
            color: #999;
            font-style: italic;
            font-size: 16px;
            background: #f9f9f9;
        }

        /* Pagination styling */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 30px 0;
            padding: 20px;
        }

        .page-btn {
            padding: 10px 15px;
            background: white;
            color: #bd9379;
            text-decoration: none;
            border: 2px solid #ece8e1;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .page-btn:hover {
            background: #bd9379;
            color: white;
            border-color: #bd9379;
            transform: translateY(-1px);
        }

        .page-btn.active {
            background: #bd9379;
            color: white;
            border-color: #bd9379;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .filters-form .filter-row {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }
            
            .filter-group {
                min-width: unset;
                flex: none;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            .admin-table {
                min-width: 800px;
            }
            
            /* Mobile sidebar adjustments */
            .admin-main-content {
                margin-left: 0 !important; /* No sidebar margin on mobile */
                padding-top: 80px; /* Account for mobile toggle button */
            }
        }

        /* Large screen optimizations */
        @media (min-width: 1440px) {
            .admin-main-content {
                padding: 30px;
            }
            
            .content-wrapper {
                max-width: 1400px;
                margin: 0 auto;
            }
        }

        /* Very large screens */
        @media (min-width: 1920px) {
            .content-wrapper {
                max-width: 1600px;
            }
        }
    </style>
</head>
<body>

<div class="admin-container">
    <?php 
    // Include sidebar - try different possible paths
    $sidebar_paths = [
        'includes/sidebar.php',
        '../includes/sidebar.php',
        './includes/sidebar.php'
    ];
    
    $sidebar_loaded = false;
    foreach ($sidebar_paths as $sidebar_path) {
        if (file_exists($sidebar_path)) {
            include $sidebar_path;
            $sidebar_loaded = true;
            break;
        }
    }
    
    if (!$sidebar_loaded) {
        // If no sidebar found, adjust layout
        echo '<style>.admin-main-content { margin-left: 20px; }</style>';
    }
    ?>
    
    <div class="admin-main-content">
        <div class="content-wrapper">
            <div class="admin-header">
                <div class="container">
                    <div class="header-content">
                        <div class="breadcrumb">
                            <a href="../dashboard.php">Dashboard</a>
                            <span>›</span>
                            <span>System Log</span>
                        </div>
                        <h1>System Activity Log</h1>
                        <p>Monitor all system activities, user actions, and security events</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="search">Search:</label>
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search in logs...">
                        </div>
                        
                        <div class="filter-group">
                            <label for="action">Action:</label>
                            <select id="action" name="action">
                                <option value="">All Actions</option>
                                <?php foreach ($unique_actions as $action) { ?>
                                    <option value="<?php echo htmlspecialchars($action); ?>" 
                                            <?php echo $action_filter === $action ? 'selected' : ''; ?>>
                                        <?php echo ucwords(str_replace(array('_', 'admin'), array(' ', 'Admin '), $action)); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="date_from">From:</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="date_to">To:</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="logs.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Results Info -->
            <div class="results-info">
                <p>Showing <?php echo count($logs); ?> of <?php echo $total_logs; ?> log entries 
                   (Page <?php echo $page; ?> of <?php echo $total_pages; ?>)</p>
            </div>

            <!-- Logs Table -->
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Action</th>
                            <th>User</th>
                            <th>IP Address</th>
                            <th>Details</th>
                            <th>User Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)) { ?>
                            <tr>
                                <td colspan="6" class="no-results">No log entries found</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($logs as $log) { ?>
                                <tr class="log-entry log-<?php echo str_replace('_', '-', $log['action']); ?>">
                                    <td class="timestamp">
                                        <div class="timestamp-main">
                                            <?php echo date('M j, Y', strtotime($log['timestamp'])); ?>
                                        </div>
                                        <div class="timestamp-time">
                                            <?php echo date('H:i:s', strtotime($log['timestamp'])); ?>
                                        </div>
                                    </td>
                                    <td class="action">
                                        <span class="action-badge action-<?php echo str_replace('_', '-', $log['action']); ?>">
                                            <?php echo ucwords(str_replace(array('_', 'admin'), array(' ', 'Admin '), $log['action'])); ?>
                                        </span>
                                    </td>
                                    <td class="user">
                                        <?php echo htmlspecialchars(getUserName($connection, isset($log['user_id']) ? $log['user_id'] : null)); ?>
                                    </td>
                                    <td class="ip-address">
                                        <code><?php echo htmlspecialchars(isset($log['ip_address']) ? $log['ip_address'] : 'N/A'); ?></code>
                                    </td>
                                    <td class="details">
                                        <?php if (!empty($log['details'])) { ?>
                                            <details class="log-details">
                                                <summary>View Details</summary>
                                                <pre><?php echo htmlspecialchars(json_encode($log['details'], JSON_PRETTY_PRINT)); ?></pre>
                                            </details>
                                        <?php } else { ?>
                                            <span class="no-details">No additional details</span>
                                        <?php } ?>
                                    </td>
                                    <td class="user-agent">
                                        <span class="user-agent-short" title="<?php echo htmlspecialchars(isset($log['user_agent']) ? $log['user_agent'] : 'N/A'); ?>">
                                            <?php 
                                            $ua = isset($log['user_agent']) ? $log['user_agent'] : 'N/A';
                                            if (strpos($ua, 'Chrome') !== false) echo '🌐 Chrome';
                                            elseif (strpos($ua, 'Firefox') !== false) echo '🦊 Firefox';
                                            elseif (strpos($ua, 'Safari') !== false) echo '🧭 Safari';
                                            elseif (strpos($ua, 'Edge') !== false) echo '🔷 Edge';
                                            else echo '💻 Other';
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1) { ?>
                <div class="pagination">
                    <?php if ($page > 1) { ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($action_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" 
                           class="page-btn">← Previous</a>
                    <?php } ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++) { ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($action_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" 
                           class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php } ?>
                    
                    <?php if ($page < $total_pages) { ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($action_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" 
                           class="page-btn">Next →</a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<script>
// Highlight recent entries
document.addEventListener('DOMContentLoaded', function() {
    const now = new Date();
    const fiveMinutesAgo = new Date(now.getTime() - 5 * 60 * 1000);
    
    document.querySelectorAll('.log-entry').forEach(row => {
        const timestampMain = row.querySelector('.timestamp-main');
        const timestampTime = row.querySelector('.timestamp-time');
        
        if (timestampMain && timestampTime) {
            const timestampText = timestampMain.textContent + ' ' + timestampTime.textContent;
            const logTime = new Date(timestampText);
            
            if (logTime > fiveMinutesAgo) {
                row.style.background = 'rgba(189, 147, 121, 0.1)';
                row.style.borderLeft = '4px solid #bd9379';
            }
        }
    });
});

console.log('🔍 Somdul Table System Log loaded successfully');
</script>

</body>
</html>