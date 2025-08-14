<?php
/**
 * Krua Thai - User Export System
 * File: admin/export-users.php
 * Description: Export user data to CSV/Excel format with filtering options
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php"); 
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Convert PDO to mysqli for compatibility with existing code
    $host = 'localhost';
    $username = 'root';
    $password = 'root';  // Adjust according to your setup
    $database_name = 'krua_thai';
    $port = 8889; // MAMP port, change if different
    
    $connection = new mysqli($host, $username, $password, $database_name, $port);
    
    if ($connection->connect_error) {
        die("Connection failed: " . $connection->connect_error);
    }
    
    $connection->set_charset("utf8");
    
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get filter parameters (same as users.php)
$status_filter = $_GET['status'] ?? '';
$role_filter = $_GET['role'] ?? '';
$search = $_GET['search'] ?? '';
$export_format = $_GET['format'] ?? 'csv';
$include_statistics = $_GET['include_statistics'] ?? '1';
$include_address = $_GET['include_address'] ?? '1';

// Build WHERE clause with same logic as users.php
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

// Get users with statistics for export
$query = "SELECT u.*, 
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
         ORDER BY u.created_at DESC";

$result = mysqli_query($connection, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($connection));
}

$users = [];
while ($user = mysqli_fetch_assoc($result)) {
    $users[] = $user;
}

// Build export data
$export_data = [];

// Header row
$headers = [
    'User ID',
    'First Name',
    'Last Name',
    'Full Name',
    'Email',
    'Phone',
    'Role',
    'Status',
    'Created Date',
    'Last Updated'
];

if ($include_address === '1') {
    $headers = array_merge($headers, [
        'City',
        'Delivery Address'
    ]);
}

if ($include_statistics === '1') {
    $headers = array_merge($headers, [
        'Active Subscriptions',
        'Total Orders',
        'Total Spent (฿)',
        'Last Order Date'
    ]);
}

$export_data[] = $headers;

// Data rows
foreach ($users as $user) {
    $row = [
        $user['id'],
        $user['first_name'],
        $user['last_name'],
        $user['first_name'] . ' ' . $user['last_name'],
        $user['email'],
        $user['phone'] ?: 'N/A',
        ucfirst($user['role']),
        ucfirst(str_replace('_', ' ', $user['status'])),
        date('Y-m-d H:i:s', strtotime($user['created_at'])),
        date('Y-m-d H:i:s', strtotime($user['updated_at']))
    ];
    
    if ($include_address === '1') {
        $row = array_merge($row, [
            $user['city'] ?: 'N/A',
            $user['delivery_address'] ?: 'N/A'
        ]);
    }
    
    if ($include_statistics === '1') {
        $row = array_merge($row, [
            $user['subscription_count'],
            $user['order_count'],
            number_format($user['total_spent'], 2),
            $user['last_order_date'] ? date('Y-m-d', strtotime($user['last_order_date'])) : 'Never'
        ]);
    }
    
    $export_data[] = $row;
}

// Generate filename
$timestamp = date('Y-m-d_H-i-s');
$filter_suffix = '';
if ($status_filter) $filter_suffix .= '_' . $status_filter;
if ($role_filter) $filter_suffix .= '_' . $role_filter;
if ($search) $filter_suffix .= '_search';

$filename = 'krua_thai_users_' . $timestamp . $filter_suffix;

// Export as CSV only
exportToCSV($export_data, $filename);

// Log export activity
logExportActivity('user_export', $_SESSION['user_id'], [
    'format' => $export_format,
    'total_users' => count($users),
    'filters' => [
        'status' => $status_filter,
        'role' => $role_filter,
        'search' => $search
    ]
]);

/**
 * Export data to CSV format
 */
function exportToCSV($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    // Add BOM for UTF-8 to ensure proper encoding in Excel
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    foreach ($data as $row) {
        // Clean data for CSV
        $cleaned_row = array_map(function($cell) {
            // Remove line breaks and extra spaces
            $cell = preg_replace('/\s+/', ' ', trim($cell));
            // Escape quotes
            $cell = str_replace('"', '""', $cell);
            return $cell;
        }, $row);
        
        fputcsv($output, $cleaned_row, ',', '"');
    }
    
    fclose($output);
    exit();
}

/**
 * Export data to Excel format (HTML table format for simplicity)
 */
function exportToExcel($data, $filename) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    // Add BOM for UTF-8
    echo "\xEF\xBB\xBF";
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
    echo '<meta name="ProgId" content="Excel.Sheet" />';
    echo '<meta name="Generator" content="Krua Thai Admin System" />';
    echo '<style>';
    echo 'table { border-collapse: collapse; font-family: Arial, sans-serif; }';
    echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
    echo 'th { background-color: #cf723a; color: white; font-weight: bold; }';
    echo 'tr:nth-child(even) { background-color: #f9f9f9; }';
    echo 'tr:hover { background-color: #f5f5f5; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo '<h2>Krua Thai - User Export Report</h2>';
    echo '<p>Export Date: ' . date('Y-m-d H:i:s') . '</p>';
    echo '<p>Total Users: ' . (count($data) - 1) . '</p>';
    echo '<table>';
    
    foreach ($data as $index => $row) {
        if ($index === 0) {
            echo '<thead><tr>';
            foreach ($row as $cell) {
                echo '<th>' . htmlspecialchars($cell) . '</th>';
            }
            echo '</tr></thead><tbody>';
        } else {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . htmlspecialchars($cell) . '</td>';
            }
            echo '</tr>';
        }
    }
    
    echo '</tbody></table>';
    echo '<br><p><small>Generated by Krua Thai Admin System</small></p>';
    echo '</body>';
    echo '</html>';
    exit();
}

/**
 * Log export activity
 */
function logExportActivity($action, $user_id, $details = []) {
    global $connection;
    
    $details_json = json_encode($details);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    // Check if activity_logs table exists, if not skip logging
    $table_check = mysqli_query($connection, "SHOW TABLES LIKE 'activity_logs'");
    if (mysqli_num_rows($table_check) == 0) {
        return; // Skip logging if table doesn't exist
    }
    
    $query = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
              VALUES (?, ?, ?, ?, ?, NOW())";
    
    try {
        $stmt = $connection->prepare($query);
        if ($stmt) {
            $stmt->bind_param('sssss', $user_id, $action, $details_json, $ip_address, $user_agent);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        // Log error but don't stop export
        error_log("Failed to log export activity: " . $e->getMessage());
    }
}

mysqli_close($connection);
?>