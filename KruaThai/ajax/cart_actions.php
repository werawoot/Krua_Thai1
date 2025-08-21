<?php
/**
 * Somdul Table - AJAX Cart Actions
 * File: ajax/cart_actions.php
 * Description: Handle AJAX requests for cart operations (update, remove, clear)
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

try {
    $action = $input['action'] ?? '';
    
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    switch ($action) {
        case 'update_quantity':
            $item_index = intval($input['item_index'] ?? -1);
            $new_quantity = max(1, min(10, intval($input['quantity'] ?? 1)));
            
            if ($item_index < 0 || !isset($_SESSION['cart'][$item_index])) {
                throw new Exception('Invalid item index');
            }
            
            $old_quantity = $_SESSION['cart'][$item_index]['quantity'];
            $_SESSION['cart'][$item_index]['quantity'] = $new_quantity;
            
            $response = [
                'success' => true,
                'message' => 'Quantity updated successfully',
                'item_index' => $item_index,
                'old_quantity' => $old_quantity,
                'new_quantity' => $new_quantity
            ];
            break;
            
        case 'remove_item':
            $item_index = intval($input['item_index'] ?? -1);
            
            if ($item_index < 0 || !isset($_SESSION['cart'][$item_index])) {
                throw new Exception('Invalid item index');
            }
            
            $item_name = $_SESSION['cart'][$item_index]['name'];
            unset($_SESSION['cart'][$item_index]);
            $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index array
            
            $response = [
                'success' => true,
                'message' => $item_name . ' removed from cart',
                'item_name' => $item_name,
                'item_index' => $item_index
            ];
            break;
            
        case 'clear_cart':
            $items_count = count($_SESSION['cart']);
            $_SESSION['cart'] = [];
            
            $response = [
                'success' => true,
                'message' => 'Cart cleared successfully',
                'items_removed' => $items_count
            ];
            break;
            
        case 'get_cart_summary':
            // Just return current cart summary without making changes
            $response = [
                'success' => true,
                'message' => 'Cart summary retrieved'
            ];
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    // Calculate updated cart totals
    $subtotal = 0;
    $total_items = 0;
    $cart_count = 0;
    
    foreach ($_SESSION['cart'] as $item) {
        $quantity = intval($item['quantity']);
        $price = floatval($item['base_price']);
        $item_total = $price * $quantity;
        
        // Add customization costs
        if (isset($item['customizations'])) {
            if (isset($item['customizations']['extra_protein']) && $item['customizations']['extra_protein']) {
                $item_total += 2.99 * $quantity;
            }
            if (isset($item['customizations']['extra_vegetables']) && $item['customizations']['extra_vegetables']) {
                $item_total += 1.99 * $quantity;
            }
        }
        
        $subtotal += $item_total;
        $total_items += $quantity;
        $cart_count += $quantity;
    }
    
    $delivery_fee = $subtotal >= 25 ? 0 : 3.99;
    $tax_rate = 0.0825; // 8.25%
    $tax_amount = $subtotal * $tax_rate;
    $total = $subtotal + $delivery_fee + $tax_amount;
    
    // Add cart summary to response
    $response['cart_summary'] = [
        'items_count' => count($_SESSION['cart']),
        'total_items' => $total_items,
        'cart_count' => $cart_count,
        'subtotal' => $subtotal,
        'delivery_fee' => $delivery_fee,
        'tax_amount' => $tax_amount,
        'total' => $total,
        'free_shipping_qualified' => $delivery_fee == 0,
        'amount_for_free_shipping' => $delivery_fee > 0 ? (25 - $subtotal) : 0
    ];
    
    // Format prices for display
    $response['cart_summary_formatted'] = [
        'subtotal' => '$' . number_format($subtotal, 2),
        'delivery_fee' => $delivery_fee == 0 ? 'FREE' : '$' . number_format($delivery_fee, 2),
        'tax_amount' => '$' . number_format($tax_amount, 2),
        'total' => '$' . number_format($total, 2),
        'amount_for_free_shipping' => $delivery_fee > 0 ? '$' . number_format(25 - $subtotal, 2) : '$0.00'
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Cart action error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>