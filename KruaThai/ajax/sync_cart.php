<?php
// =================================================================
// วิธีที่ 3: เพิ่ม AJAX sync system
// =================================================================

// สร้างไฟล์ ajax/sync_cart.php:
?>

<?php
/**
 * ไฟล์: ajax/sync_cart.php
 * ส่งคืนสถานะ cart ปัจจุบัน
 */

header('Content-Type: application/json');
session_start();

// คำนวณ cart count จริง
$cart_count = 0;
$cart_total = 0.00;

if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
        $price = isset($item['base_price']) ? floatval($item['base_price']) : 0.00;
        
        $cart_count += $quantity;
        $cart_total += ($price * $quantity);
    }
}

echo json_encode([
    'success' => true,
    'count' => $cart_count,
    'total' => number_format($cart_total, 2),
    'items' => count($_SESSION['cart'] ?? []),
    'is_empty' => $cart_count === 0
]);
exit;
?>
