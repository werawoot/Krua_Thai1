<?php
/**
 * Krua Thai - Ajax Menu Details
 * File: ajax/get_menu_details.php
 * Description: Fetch menu details for modal display
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Include database connection
require_once '../config/database.php';

// Check if menu ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Menu ID is required'
    ]);
    exit;
}

$menu_id = $_GET['id'];
$is_logged_in = isset($_SESSION['user_id']);

try {
    // Get menu details with category information
    $stmt = $pdo->prepare("
        SELECT m.*, c.name as category_name, c.name_thai as category_name_thai
        FROM menus m 
        LEFT JOIN menu_categories c ON m.category_id = c.id 
        WHERE m.id = ? AND m.is_available = 1
    ");
    $stmt->execute([$menu_id]);
    $menu = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$menu) {
        echo json_encode([
            'success' => false,
            'message' => 'Menu not found or not available'
        ]);
        exit;
    }

    // Add logged in status to menu data
    $menu['is_logged_in'] = $is_logged_in;

    // Return success response
    echo json_encode([
        'success' => true,
        'menu' => $menu
    ]);

} catch (Exception $e) {
    error_log("Ajax Menu Details Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>