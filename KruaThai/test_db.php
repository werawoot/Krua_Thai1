<?php
/**
 * Database Connection Test
 * File: test_connection.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Database Connection Test</h2>";

// Test configurations
$configs = [
    [
        'name' => 'XAMPP Default',
        'host' => 'localhost',
        'port' => '3306',
        'username' => 'root',
        'password' => ''
    ],
    [
        'name' => 'MAMP Default',
        'host' => 'localhost',
        'port' => '8889',
        'username' => 'root',
        'password' => 'root'
    ],
    [
        'name' => 'Standard MySQL',
        'host' => 'localhost',
        'port' => '3306',
        'username' => 'root',
        'password' => 'root'
    ]
];

echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; background: #d4edda; padding: 10px; margin: 5px 0; border-radius: 5px; }
    .error { color: red; background: #f8d7da; padding: 10px; margin: 5px 0; border-radius: 5px; }
    .info { color: blue; background: #cce7ff; padding: 10px; margin: 5px 0; border-radius: 5px; }
    .config-box { border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; }
</style>";

$working_config = null;

foreach ($configs as $config) {
    echo "<div class='config-box'>";
    echo "<h3>Testing: {$config['name']}</h3>";
    
    try {
        echo "<div class='info'>";
        echo "Host: {$config['host']}<br>";
        echo "Port: {$config['port']}<br>";
        echo "Username: {$config['username']}<br>";
        echo "Password: " . (empty($config['password']) ? '(empty)' : '***');
        echo "</div>";
        
        // Test basic connection
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        echo "<div class='success'>✅ MySQL Connection: SUCCESS</div>";
        
        // Check if krua_thai database exists
        $stmt = $pdo->query("SHOW DATABASES LIKE 'krua_thai'");
        if ($stmt->rowCount() > 0) {
            echo "<div class='success'>✅ Database 'krua_thai': EXISTS</div>";
            
            // Test connection to krua_thai database
            try {
                $dsn_with_db = "mysql:host={$config['host']};port={$config['port']};dbname=krua_thai;charset=utf8mb4";
                $pdo_db = new PDO($dsn_with_db, $config['username'], $config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                
                echo "<div class='success'>✅ Connection to krua_thai: SUCCESS</div>";
                
                // Check users table
                try {
                    $stmt = $pdo_db->query("SELECT COUNT(*) as count FROM users");
                    $result = $stmt->fetch();
                    echo "<div class='success'>✅ Users table: EXISTS ({$result['count']} records)</div>";
                    
                    $working_config = $config;
                    
                } catch (PDOException $e) {
                    echo "<div class='error'>❌ Users table: " . $e->getMessage() . "</div>";
                }
                
            } catch (PDOException $e) {
                echo "<div class='error'>❌ krua_thai connection failed: " . $e->getMessage() . "</div>";
            }
            
        } else {
            echo "<div class='error'>❌ Database 'krua_thai': NOT FOUND</div>";
            
            // Show available databases
            $stmt = $pdo->query("SHOW DATABASES");
            $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "<div class='info'><strong>Available databases:</strong><br>";
            foreach ($databases as $db) {
                echo "- $db<br>";
            }
            echo "</div>";
        }
        
    } catch (PDOException $e) {
        echo "<div class='error'>❌ Connection failed: " . $e->getMessage() . "</div>";
    }
    
    echo "</div>";
}

// Show working configuration
if ($working_config) {
    echo "<div style='background: #d4edda; color: #155724; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "<h2>🎉 Working Configuration Found!</h2>";
    echo "<p><strong>Update your config/database.php with these settings:</strong></p>";
    echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
    echo "define('DB_HOST', '{$working_config['host']}" . ($working_config['port'] != '3306' ? ":{$working_config['port']}" : "") . "');\n";
    echo "define('DB_NAME', 'krua_thai');\n";
    echo "define('DB_USER', '{$working_config['username']}');\n";
    echo "define('DB_PASS', '{$working_config['password']}');\n";
    echo "</pre>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "<h2>❌ No Working Configuration Found</h2>";
    echo "<p><strong>Steps to fix:</strong></p>";
    echo "<ol>";
    echo "<li>Make sure XAMPP/MAMP is running</li>";
    echo "<li>Create 'krua_thai' database in phpMyAdmin</li>";
    echo "<li>Import krua_thai.sql file</li>";
    echo "<li>Try different username/password combinations</li>";
    echo "</ol>";
    echo "</div>";
}

// Quick database creation script
echo "<div style='background: #cce7ff; color: #004085; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
echo "<h3>📝 Quick Database Setup</h3>";
echo "<p>If 'krua_thai' database doesn't exist, run this SQL in phpMyAdmin:</p>";
echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
echo "CREATE DATABASE krua_thai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
echo "</pre>";
echo "<p>Then import your krua_thai.sql file.</p>";
echo "</div>";

echo "<hr>";
echo "<p><strong>🔄 Next steps:</strong></p>";
echo "<ol>";
echo "<li>Use the working configuration above</li>";
echo "<li>Create krua_thai database if needed</li>";
echo "<li>Import krua_thai.sql</li>";
echo "<li>Test login again</li>";
echo "<li>Delete this test file</li>";
echo "</ol>";
?>