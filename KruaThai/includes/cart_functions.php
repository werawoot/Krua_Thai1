<?php
/**
 * ไฟล์: includes/cart_functions.php
 * ฟังก์ชันสำหรับจัดการ cart ที่ใช้ร่วมกันทุกหน้า
 */

class SharedCartUtils {
    
    /**
     * คำนวณจำนวนสินค้าทั้งหมดใน cart
     */
    public static function getCartItemCount() {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            return 0;
        }
        
        $total_count = 0;
        foreach ($_SESSION['cart'] as $item) {
            $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
            $total_count += $quantity;
        }
        
        return $total_count;
    }
    
    /**
     * คำนวณยอดรวมของ cart
     */
    public static function getCartTotal() {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            return 0.00;
        }
        
        $total = 0.00;
        foreach ($_SESSION['cart'] as $item) {
            $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
            $price = isset($item['base_price']) ? floatval($item['base_price']) : 0.00;
            
            $item_total = $price * $quantity;
            
            // เพิ่มค่า extras
            if (isset($item['customizations'])) {
                if (isset($item['customizations']['extra_protein']) && $item['customizations']['extra_protein']) {
                    $item_total += 2.99 * $quantity;
                }
                if (isset($item['customizations']['extra_vegetables']) && $item['customizations']['extra_vegetables']) {
                    $item_total += 1.99 * $quantity;
                }
            }
            
            $total += $item_total;
        }
        
        return $total;
    }
    
    /**
     * รับข้อมูลสรุป cart
     */
    public static function getCartSummary() {
        return [
            'count' => self::getCartItemCount(),
            'total' => self::getCartTotal(),
            'items' => count($_SESSION['cart'] ?? [])
        ];
    }
    
    /**
     * Generate HTML สำหรับ cart counter
     */
    public static function renderCartCounter($additional_classes = '') {
        $count = self::getCartItemCount();
        $display = $count > 0 ? 'inline-block' : 'none';
        
        return sprintf(
            '<span class="cart-counter %s" style="background: var(--curry); color: var(--white); border-radius: 50%%; padding: 2px 6px; font-size: 0.8rem; margin-left: 5px; display: %s;">%d</span>',
            $additional_classes,
            $display,
            $count
        );
    }
}
