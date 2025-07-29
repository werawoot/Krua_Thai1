<?php
/**
 * Unified Database Configuration
 * File: config/database.php
 * Support both PDO and MySQLi connections
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'kruathai');
define('DB_USER', 'root');
define('DB_PASS', 'root'); // Change to 'root' for MAMP or your password

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    public $conn;

    /**
     * Get PDO connection
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            error_log("PDO Connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
        
        return $this->conn;
    }
}

/**
 * Get MySQLi connection for legacy code
 */
function getMySQLiConnection() {
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($connection->connect_error) {
        error_log("MySQLi Connection failed: " . $connection->connect_error);
        throw new Exception("Database connection failed");
    }
    
    $connection->set_charset("utf8mb4");
    return $connection;
}

// Create global instances
try {
    // PDO instance (for login.php and new code)
    $database = new Database();
    $pdo = $database->getConnection();
    
    // MySQLi instance (for dashboard.php and legacy code)
    $connection = getMySQLiConnection();
    
} catch (Exception $e) {
    // Show user-friendly error
    if (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost') {
        die("
        <div style='background: #f8d7da; color: #721c24; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px; font-family: Arial, sans-serif;'>
            <h3>🚨 Database Connection Error</h3>
            <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <hr>
            <h4>🔧 Troubleshooting Steps:</h4>
            <ol>
                <li><strong>Check if MySQL is running</strong> (XAMPP/MAMP)</li>
                <li><strong>Verify database exists:</strong> 'krua_thai'</li>
                <li><strong>Check credentials in config/database.php:</strong>
                    <ul>
                        <li>Host: " . DB_HOST . "</li>
                        <li>Database: " . DB_NAME . "</li>
                        <li>Username: " . DB_USER . "</li>
                        <li>Password: " . (DB_PASS ? '***' : '(empty)') . "</li>
                    </ul>
                </li>
                <li><strong>Import krua_thai.sql if needed</strong></li>
            </ol>
            <p><a href='test_db.php' style='background: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>🔍 Test Database Connection</a></p>
        </div>
        ");
    } else {
        die("Service temporarily unavailable. Please try again later.");
    }
}
?>