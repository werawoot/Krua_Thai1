<?php
/**
 * Somdul Table - AJAX Add Product to Cart
 * File: ajax/add_product_to_cart.php
 * Description: Handle AJAX requests to add products to cart
 */

session_start();
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

require_once '../config/database.php';

try {
    // Validate input
    $product_id = trim($input['product_id'] ?? '');
    $quantity = max(1, intval($input['quantity'] ?? 1));
    $type = $input['type'] ?? 'product';
    
    if (empty($product_id)) {
        throw new Exception('Product ID is required');
    }
    
    if ($quantity > 10) {
        throw new Exception('Maximum quantity per item is 10');
    }
    
    // Database connection with fallback
    try {
        $database = new Database();
        $pdo = $database->getConnection();
    } catch (Exception $e) {
        // Fallback connection for MAMP/XAMPP
        try {
            $pdo = new PDO("mysql:host=localhost;dbname=somdul_table;charset=utf8mb4", "root", "root");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $pdo = new PDO("mysql:host=localhost:8889;dbname=somdul_table;charset=utf8mb4", "root", "root");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    }
    
    // Fetch product details
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        // Fallback products
        $fallback_products = [
            'pad-thai-kit-pro' => [
                'id' => 'pad-thai-kit-pro',
                'name' => 'Premium Pad Thai Kit',
                'price' => 24.99,
                'category' => 'meal-kit',
                'stock_quantity' => 50
            ],
            'tom-yum-paste-authentic' => [
                'id' => 'tom-yum-paste-authentic',
                'name' => 'Authentic Tom Yum Paste',
                'price' => 12.99,
                'category' => 'sauce',
                'stock_quantity' => 100
            ],
            'thai-curry-kit-trio' => [
                'id' => 'thai-curry-kit-trio',
                'name' => 'Thai Curry Kit Trio',
                'price' => 34.99,
                'category' => 'meal-kit',
                'stock_quantity' => 30
            ],
            'fish-sauce-premium' => [
                'id' => 'fish-sauce-premium',
                'name' => 'Premium Fish Sauce',
                'price' => 18.99,
                'category' => 'sauce',
                'stock_quantity' => 75
            ],
            'thai-chili-oil-spicy' => [
                'id' => 'thai-chili-oil-spicy',
                'name' => 'Spicy Thai Chili Oil',
                'price' => 15.99,
                'category' => 'sauce',
                'stock_quantity' => 60
            ],
            'som-tam-kit-fresh' => [
                'id' => 'som-tam-kit-fresh',
                'name' => 'Fresh Som Tam Kit',
                'price' => 19.99,
                'category' => 'meal-kit',
                'stock_quantity' => 40
            ]
        ];
        
        $product = $fallback_products[$product_id] ?? null;
        
        if (!$product) {
            throw new Exception('Product not found');
        }
    }
    
    // Check stock
    if (isset($product['stock_quantity']) && $product['stock_quantity'] < $quantity) {
        throw new Exception('Insufficient stock. Only ' . $product['stock_quantity'] . ' available.');
    }
    
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Check if product already exists in cart
    $found_index = -1;
    foreach ($_SESSION['cart'] as $index => $item) {
        if ($item['id'] === $product_id && $item['type'] === $type) {
            $found_index = $index;
            break;
        }
    }
    
    if ($found_index >= 0) {
        // Update existing item quantity
        $new_quantity = $_SESSION['cart'][$found_index]['quantity'] + $quantity;
        
        // Check total quantity doesn't exceed stock or max limit
        if (isset($product['stock_quantity']) && $new_quantity > $product['stock_quantity']) {
            throw new Exception('Cannot add more. Only ' . $product['stock_quantity'] . ' in stock.');
        }
        
        if ($new_quantity > 10) {
            throw new Exception('Maximum 10 of each item allowed in cart.');
        }
        
        $_SESSION['cart'][$found_index]['quantity'] = $new_quantity;
        $action = 'updated';
        
    } else {
        // Add new item to cart
        $cart_item = [
            'id' => $product['id'],
            'name' => $product['name'],
            'base_price' => floatval($product['price']),
            'quantity' => $quantity,
            'type' => $type,
            'category' => $product['category'] ?? 'product',
            'image_url' => $product['image_url'] ?? '',
            'added_at' => time(),
            'customizations' => []
        ];
        
        $_SESSION['cart'][] = $cart_item;
        $action = 'added';
    }
    
    // Calculate cart summary
    $cart_count = 0;
    $cart_subtotal = 0;
    
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += intval($item['quantity']);
        $cart_subtotal += floatval($item['base_price']) * intval($item['quantity']);
        
        // Add customization costs if any
        if (isset($item['customizations'])) {
            if (isset($item['customizations']['extra_protein']) && $item['customizations']['extra_protein']) {
                $cart_subtotal += 2.99 * intval($item['quantity']);
            }
            if (isset($item['customizations']['extra_vegetables']) && $item['customizations']['extra_vegetables']) {
                $cart_subtotal += 1.99 * intval($item['quantity']);
            }
        }
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => $action === 'added' ? 'Product added to cart!' : 'Cart updated!',
        'action' => $action,
        'product' => [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => $product['price']
        ],
        'cart_count' => $cart_count,
        'cart_items' => count($_SESSION['cart']),
        'cart_subtotal' => $cart_subtotal,
        'quantity_in_cart' => $found_index >= 0 ? $_SESSION['cart'][$found_index]['quantity'] : $quantity
    ]);
    
} catch (Exception $e) {
    error_log("Add to cart error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>