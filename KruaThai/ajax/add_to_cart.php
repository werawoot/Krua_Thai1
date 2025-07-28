<?php
/**
 * Krua Thai - AJAX Add to Cart
 * File: ajax/add_to_cart.php
 * Description: Handle adding meal kits to cart via AJAX for logged-in users
 */

// Set content type for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response

// Start session
session_start();

// Include database connection
require_once '../config/database.php';

// Utility class for cart operations
class CartUtils {
    
    public static function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    public static function sanitizeInput($input) {
        return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    }
    
    public static function validateQuantity($quantity) {
        $qty = intval($quantity);
        return max(1, min(10, $qty)); // Between 1-10
    }
    
    public static function calculateItemTotal($base_price, $quantity, $extras = []) {
        $total = $base_price * $quantity;
        
        // Add extra charges if any
        if (isset($extras['extra_protein']) && $extras['extra_protein']) {
            $total += 2.99 * $quantity; // $2.99 per extra protein
        }
        
        if (isset($extras['extra_vegetables']) && $extras['extra_vegetables']) {
            $total += 1.99 * $quantity; // $1.99 per extra vegetables
        }
        
        return $total;
    }
}

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'cart_count' => 0,
    'cart_total' => 0.00,
    'item_added' => null
];

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Please log in to add items to cart');
    }
    
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }
    
    // Validate required fields
    if (!isset($data['kit_id']) || empty($data['kit_id'])) {
        throw new Exception('Meal kit ID is required');
    }
    
    $kit_id = CartUtils::sanitizeInput($data['kit_id']);
    $quantity = CartUtils::validateQuantity($data['quantity'] ?? 1);
    $type = CartUtils::sanitizeInput($data['type'] ?? 'meal_kit');
    $customizations = $data['customizations'] ?? [];
    $special_requests = CartUtils::sanitizeInput($data['special_requests'] ?? '');
    
    // Get database connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Fetch meal kit details
    $stmt = $pdo->prepare("
        SELECT m.*, c.name AS category_name, c.name_thai AS category_name_thai
        FROM menus m 
        LEFT JOIN menu_categories c ON m.category_id = c.id 
        WHERE m.id = ? AND m.is_available = 1
    ");
    $stmt->execute([$kit_id]);
    $meal_kit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If not found in database, check fallback meal kits
    if (!$meal_kit) {
        $fallback_kits = [
            'green-curry-kit' => [
                'id' => 'green-curry-kit',
                'name' => 'Green Curry Kit',
                'base_price' => 18.99,
                'category_name' => 'Meal Kits',
                'is_available' => 1
            ],
            'panang-kit' => [
                'id' => 'panang-kit',
                'name' => 'Panang Curry Kit',
                'base_price' => 21.99,
                'category_name' => 'Meal Kits',
                'is_available' => 1
            ],
            'pad-thai-kit' => [
                'id' => 'pad-thai-kit',
                'name' => 'Pad Thai Kit',
                'base_price' => 16.99,
                'category_name' => 'Meal Kits',
                'is_available' => 1
            ],
            'tom-yum-kit' => [
                'id' => 'tom-yum-kit',
                'name' => 'Tom Yum Soup Kit',
                'base_price' => 17.99,
                'category_name' => 'Meal Kits',
                'is_available' => 1
            ]
        ];
        
        $meal_kit = $fallback_kits[$kit_id] ?? null;
    }
    
    if (!$meal_kit) {
        throw new Exception('Meal kit not found or not available');
    }
    
    // Verify it's a meal kit
    if ($type === 'meal_kit' && $meal_kit['category_name'] !== 'Meal Kits') {
        // For regular meals that aren't meal kits, still allow but change type
        $type = 'regular_meal';
    }
    
    // Initialize cart if it doesn't exist
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Check if item already exists in cart
    $item_exists = false;
    $existing_index = -1;
    
    foreach ($_SESSION['cart'] as $index => $cart_item) {
        if ($cart_item['kit_id'] === $kit_id && 
            $cart_item['type'] === $type &&
            json_encode($cart_item['customizations']) === json_encode($customizations)) {
            $item_exists = true;
            $existing_index = $index;
            break;
        }
    }
    
    // Calculate item total
    $item_total = CartUtils::calculateItemTotal($meal_kit['base_price'], $quantity, $customizations);
    
    // Create cart item
    $cart_item = [
        'id' => CartUtils::generateUUID(),
        'kit_id' => $kit_id,
        'name' => $meal_kit['name'],
        'type' => $type,
        'base_price' => $meal_kit['base_price'],
        'quantity' => $quantity,
        'item_total' => $item_total,
        'customizations' => $customizations,
        'special_requests' => $special_requests,
        'category' => $meal_kit['category_name'],
        'added_at' => date('Y-m-d H:i:s')
    ];
    
    if ($item_exists) {
        // Update existing item quantity
        $_SESSION['cart'][$existing_index]['quantity'] += $quantity;
        $_SESSION['cart'][$existing_index]['item_total'] = CartUtils::calculateItemTotal(
            $meal_kit['base_price'], 
            $_SESSION['cart'][$existing_index]['quantity'], 
            $customizations
        );
        $cart_item = $_SESSION['cart'][$existing_index];
    } else {
        // Add new item to cart
        $_SESSION['cart'][] = $cart_item;
    }
    
    // Calculate cart totals
    $cart_count = 0;
    $cart_total = 0.00;
    
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['quantity'];
        $cart_total += $item['item_total'];
    }
    
    // Store cart info in session for easy access
    $_SESSION['cart_summary'] = [
        'count' => $cart_count,
        'total' => $cart_total,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Log cart activity (optional)
    try {
        $user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("
            INSERT INTO cart_activities (id, user_id, action, item_id, item_name, quantity, created_at) 
            VALUES (?, ?, 'add', ?, ?, ?, NOW())
        ");
        $stmt->execute([
            CartUtils::generateUUID(),
            $user_id,
            $kit_id,
            $meal_kit['name'],
            $quantity
        ]);
    } catch (Exception $e) {
        // Log error but don't fail the main operation
        error_log("Cart activity logging failed: " . $e->getMessage());
    }
    
    // Prepare success response
    $response = [
        'success' => true,
        'message' => $item_exists ? 'Item quantity updated in cart' : 'Item added to cart successfully',
        'cart_count' => $cart_count,
        'cart_total' => number_format($cart_total, 2),
        'item_added' => [
            'id' => $cart_item['id'],
            'name' => $cart_item['name'],
            'quantity' => $cart_item['quantity'],
            'total' => number_format($cart_item['item_total'], 2)
        ],
        'cart_url' => '../cart.php'
    ];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'cart_count' => isset($_SESSION['cart_summary']['count']) ? $_SESSION['cart_summary']['count'] : 0,
        'cart_total' => isset($_SESSION['cart_summary']['total']) ? number_format($_SESSION['cart_summary']['total'], 2) : '0.00'
    ];
    
    // Log error for debugging
    error_log("Add to cart error: " . $e->getMessage() . " | Request: " . print_r($_POST, true));
}

// Output JSON response
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
?>