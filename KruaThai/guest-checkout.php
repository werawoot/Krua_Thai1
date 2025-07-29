<?php
session_start();

// 🔥 Debug Form Submission 🔥
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<div style='background: #fff3cd; padding: 15px; margin: 10px; border-radius: 5px; position: fixed; top: 10px; left: 10px; z-index: 9999; width: 400px;'>";
    echo "<h4>📝 Form Submitted!</h4>";
    echo "<p><strong>Method:</strong> " . $_SERVER['REQUEST_METHOD'] . "</p>";
    echo "<p><strong>place_order:</strong> " . (isset($_POST['place_order']) ? 'YES' : 'NO') . "</p>";
    echo "<p><strong>POST keys:</strong> " . implode(', ', array_keys($_POST)) . "</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 10px; margin: 10px; border-radius: 5px; position: fixed; top: 10px; right: 10px; z-index: 9999; width: 200px;'>";
    echo "<p><strong>Method:</strong> " . $_SERVER['REQUEST_METHOD'] . "</p>";
    echo "<p>No POST data</p>";
    echo "</div>";
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';


// Utility Functions
class GuestCheckoutUtils {
    public static function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    public static function generateOrderNumber() {
        return 'GUEST-' . date('Ymd-His') . '-' . substr(self::generateUUID(), 0, 6);
    }
    
    public static function sanitizeInput($input) {
        return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    }
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validatePhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return strlen($phone) === 10 || strlen($phone) === 11;
    }
    
    public static function formatPrice($price) {
        return '$' . number_format($price, 2);
    }
    
    public static function calculateCartTotals($cart_items) {
        $subtotal = 0;
        $total_items = 0;
        
        foreach ($cart_items as $item) {
            $item_total = ($item['base_price'] ?? 0) * ($item['quantity'] ?? 1);
            
            // Add extra charges
            if (isset($item['customizations'])) {
                if (isset($item['customizations']['extra_protein']) && $item['customizations']['extra_protein']) {
                    $item_total += 2.99 * $item['quantity'];
                }
                if (isset($item['customizations']['extra_vegetables']) && $item['customizations']['extra_vegetables']) {
                    $item_total += 1.99 * $item['quantity'];
                }
            }
            
            $subtotal += $item_total;
            $total_items += $item['quantity'];
        }
        
        $delivery_fee = $subtotal >= 25 ? 0 : 3.99;
        $tax_rate = 0.0825; // 8.25%
        $tax_amount = $subtotal * $tax_rate;
        $total = $subtotal + $delivery_fee + $tax_amount;
        
        return [
            'subtotal' => $subtotal,
            'delivery_fee' => $delivery_fee,
            'tax_amount' => $tax_amount,
            'total' => $total,
            'total_items' => $total_items
        ];
    }
}

// Initialize variables
$cart_items = [];
$selected_kit = null;
$checkout_source = '';
$checkout_action = '';
$errors = [];
$order_id = null;
$order_number = '';

// แทนที่ส่วน try-catch block ในไฟล์ guest-checkout.php ด้วยโค้ดนี้

try {
    // Get database connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Determine checkout source - IMPROVED LOGIC
    $checkout_source = $_GET['source'] ?? 'auto';
    
    // Auto-detect source if not specified
    if ($checkout_source === 'auto') {
        if (!empty($_SESSION['cart'])) {
            $checkout_source = 'cart';
        } elseif (!empty($_GET['kit']) || !empty($_SESSION['selected_kit'])) {
            $checkout_source = 'single';
        } else {
            $checkout_source = 'single'; // Default fallback
        }
    }
    
    if ($checkout_source === 'cart') {
        // ======================================
        // CART CHECKOUT: Multiple items from cart
        // ======================================
        
        // Get cart items from PHP session
        $cart_items = $_SESSION['cart'] ?? [];
        
        if (empty($cart_items)) {
            throw new Exception("Your cart is empty. Please add items to cart first.");
        }
        
        // Calculate totals
        $cart_totals = GuestCheckoutUtils::calculateCartTotals($cart_items);
        $subtotal = $cart_totals['subtotal'];
        $delivery_fee = $cart_totals['delivery_fee'];
        $tax_amount = $cart_totals['tax_amount'];
        $total = $cart_totals['total'];
        
    } else {
        // ======================================
        // SINGLE KIT CHECKOUT: One meal kit
        // ======================================
        
        // Get selected meal kit from URL or session
        $kit_id = $_GET['kit'] ?? $_SESSION['selected_kit'] ?? '';
        $checkout_action = $_GET['action'] ?? $_SESSION['checkout_action'] ?? 'add_to_cart';
        
        // If no kit_id, try to get from cart (take first item)
        if (!$kit_id && !empty($_SESSION['cart'])) {
            $first_cart_item = $_SESSION['cart'][0];
            $kit_id = $first_cart_item['id'];
        }
        
        if (!$kit_id) {
            // Use fallback kit
            $kit_id = 'green-curry-kit';
        }
        
        // Store in session for persistence
        $_SESSION['selected_kit'] = $kit_id;
        $_SESSION['checkout_action'] = $checkout_action;
        
        // Try to fetch from database first
        $stmt = $pdo->prepare("
            SELECT m.*, c.name AS category_name, c.name_thai AS category_name_thai
            FROM menus m 
            LEFT JOIN menu_categories c ON m.category_id = c.id 
            WHERE m.id = ? AND m.is_available = 1
        ");
        $stmt->execute([$kit_id]);
        $selected_kit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If not found, use fallback data
        if (!$selected_kit) {
            $fallback_kits = [
                'green-curry-kit' => [
                    'id' => 'green-curry-kit',
                    'name' => 'Green Curry Kit',
                    'description' => 'Everything you need to make authentic Thai Green Curry at home.',
                    'base_price' => 18.99,
                    'prep_time' => 25,
                    'serves' => '2-3 people',
                    'spice_level' => 'Medium'
                ],
                'panang-kit' => [
                    'id' => 'panang-kit',
                    'name' => 'Panang Curry Kit',
                    'description' => 'Rich and creamy Panang curry with tender beef.',
                    'base_price' => 21.99,
                    'prep_time' => 30,
                    'serves' => '2-3 people',
                    'spice_level' => 'Mild'
                ],
                'pad-thai-kit' => [
                    'id' => 'pad-thai-kit',
                    'name' => 'Pad Thai Kit',
                    'description' => 'The iconic Thai noodle dish made simple.',
                    'base_price' => 16.99,
                    'prep_time' => 20,
                    'serves' => '2 people',
                    'spice_level' => 'Mild'
                ],
                'tom-yum-kit' => [
                    'id' => 'tom-yum-kit',
                    'name' => 'Tom Yum Soup Kit',
                    'description' => 'Hot and sour soup with fresh shrimp and mushrooms.',
                    'base_price' => 17.99,
                    'prep_time' => 15,
                    'serves' => '2-3 people',
                    'spice_level' => 'Hot'
                ]
            ];
            
            $selected_kit = $fallback_kits[$kit_id] ?? $fallback_kits['green-curry-kit'];
        }
        
        // Convert single kit to cart format for processing
        $cart_items = [[
            'id' => $selected_kit['id'],
            'name' => $selected_kit['name'],
            'base_price' => $selected_kit['base_price'],
            'quantity' => 1,
            'type' => 'meal_kit',
            'customizations' => []
        ]];
        
        // Calculate totals
        $cart_totals = GuestCheckoutUtils::calculateCartTotals($cart_items);
        $subtotal = $cart_totals['subtotal'];
        $delivery_fee = $cart_totals['delivery_fee'];
        $tax_amount = $cart_totals['tax_amount'];
        $total = $cart_totals['total'];
    }
    
    // Update debug variables after processing
    $kit_id = $kit_id ?? 'auto-detected';
    
} catch (Exception $e) {
    $errors[] = $e->getMessage();
    // Set default values if there's an error
    $subtotal = 0;
    $delivery_fee = 3.99;
    $tax_amount = 0;
    $total = 0;
}
// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['place_order'])) {
    
    // Validate form data
    $first_name = GuestCheckoutUtils::sanitizeInput($_POST['first_name'] ?? '');
    $last_name = GuestCheckoutUtils::sanitizeInput($_POST['last_name'] ?? '');
    $email = GuestCheckoutUtils::sanitizeInput($_POST['email'] ?? '');
    $phone = GuestCheckoutUtils::sanitizeInput($_POST['phone'] ?? '');
    $delivery_address = GuestCheckoutUtils::sanitizeInput($_POST['delivery_address'] ?? '');
    $city = GuestCheckoutUtils::sanitizeInput($_POST['city'] ?? '');
    $state = GuestCheckoutUtils::sanitizeInput($_POST['state'] ?? '');
    $zip_code = GuestCheckoutUtils::sanitizeInput($_POST['zip_code'] ?? '');
    $delivery_instructions = GuestCheckoutUtils::sanitizeInput($_POST['delivery_instructions'] ?? '');
    $payment_method = $_POST['payment_method'] ?? '';
    $delivery_date = $_POST['delivery_date'] ?? '';
    
    // Validation
    if (empty($first_name)) $errors[] = "First name is required.";
    if (empty($last_name)) $errors[] = "Last name is required.";
    if (!GuestCheckoutUtils::validateEmail($email)) $errors[] = "Valid email address is required.";
    if (!GuestCheckoutUtils::validatePhone($phone)) $errors[] = "Valid phone number is required.";
    if (empty($delivery_address)) $errors[] = "Delivery address is required.";
    if (empty($city)) $errors[] = "City is required.";
    if (empty($state)) $errors[] = "State is required.";
    if (empty($zip_code)) $errors[] = "ZIP code is required.";
    if (empty($payment_method)) $errors[] = "Payment method is required.";
    if (empty($delivery_date)) $errors[] = "Delivery date is required.";
    
    // Check if delivery date is valid
    if ($delivery_date && strtotime($delivery_date) <= strtotime('today')) {
        $errors[] = "Delivery date must be at least tomorrow.";
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Create or find guest user
            $user_id = GuestCheckoutUtils::generateUUID();
            
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_user) {
                $user_id = $existing_user['id'];
                
                // Update existing user's information
                $stmt = $pdo->prepare("
                    UPDATE users SET 
                        first_name = ?, last_name = ?, phone = ?,
                        delivery_address = ?, city = ?, state = ?, zip_code = ?,
                        delivery_instructions = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $first_name, $last_name, $phone,
                    $delivery_address, $city, $state, $zip_code,
                    $delivery_instructions, $user_id
                ]);
            } else {
                // Create new guest user
                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        id, first_name, last_name, email, phone, 
                        delivery_address, city, state, zip_code, delivery_instructions,
                        role, is_guest, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'customer', 1, NOW(), NOW())
                ");
                $stmt->execute([
                    $user_id, $first_name, $last_name, $email, $phone,
                    $delivery_address, $city, $state, $zip_code, $delivery_instructions
                ]);
            }
            
            // Create order
            $order_id = GuestCheckoutUtils::generateUUID();
            $order_number = GuestCheckoutUtils::generateOrderNumber();
            
            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    id, user_id, order_number, status, total_amount, 
                    delivery_date, delivery_address, delivery_instructions,
                    payment_method, created_at, updated_at
                ) VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $order_id, $user_id, $order_number, $total,
                $delivery_date, $delivery_address, $delivery_instructions, $payment_method
            ]);
            
            // Create order items for all cart items
            foreach ($cart_items as $item) {
                $order_item_id = GuestCheckoutUtils::generateUUID();
                
                // Calculate item total with customizations
                $item_total = $item['base_price'] * $item['quantity'];
                if (isset($item['customizations'])) {
                    if (isset($item['customizations']['extra_protein']) && $item['customizations']['extra_protein']) {
                        $item_total += 2.99 * $item['quantity'];
                    }
                    if (isset($item['customizations']['extra_vegetables']) && $item['customizations']['extra_vegetables']) {
                        $item_total += 1.99 * $item['quantity'];
                    }
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO order_items (
                        id, order_id, menu_id, quantity, unit_price, total_price,
                        customizations, special_requests, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $order_item_id, $order_id, $item['id'], 
                    $item['quantity'], $item['base_price'], $item_total,
                    json_encode($item['customizations'] ?? []),
                    $delivery_instructions
                ]);
            }
            
            // Create payment record
            $payment_id = GuestCheckoutUtils::generateUUID();
            $transaction_id = 'TXN-' . date('Ymd-His') . '-' . substr($order_id, 0, 6);
            
            $stmt = $pdo->prepare("
                INSERT INTO payments (
                    id, user_id, transaction_id, amount, currency, status,
                    payment_method, payment_date, description, created_at, updated_at
                ) VALUES (?, ?, ?, ?, 'USD', 'pending', ?, NOW(), ?, NOW(), NOW())
            ");
            
            $description = $checkout_source === 'cart' 
                ? 'Guest cart order: ' . count($cart_items) . ' items'
                : 'Guest meal kit order: ' . ($selected_kit['name'] ?? 'Unknown');
                
            $stmt->execute([
                $payment_id, $user_id, $transaction_id, $total, $payment_method, $description
            ]);
            
   $pdo->commit();


// 🔥 เพิ่ม Debug Messages ชั่วคราว 🔥
echo "<div style='background: #d4edda; padding: 15px; margin: 10px; border-radius: 5px; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999; width: 80%; max-width: 500px;'>";
echo "<h3>✅ Order Processing Complete!</h3>";
echo "<p><strong>Order ID:</strong> " . $order_id . "</p>";
echo "<p><strong>Order Number:</strong> " . $order_number . "</p>";
echo "<p><strong>Total:</strong> $" . number_format($total, 2) . "</p>";
echo "<p><strong>Redirecting to:</strong> order-confirmation.php?order=" . $order_id . "</p>";
echo "<p>Click <a href='order-confirmation.php?order=" . $order_id . "'>here</a> if not redirected automatically.</p>";
echo "</div>";


// Store success data in session
$_SESSION['order_success'] = [
    'order_number' => $order_number,
    'total' => $total,
    'items' => $cart_items,
    'email' => $email,
    'delivery_date' => $delivery_date,
    'source' => $checkout_source
];

// Clear cart data
if ($checkout_source === 'cart') {
    $_SESSION['cart'] = [];
} else {
    unset($_SESSION['selected_kit']);
    unset($_SESSION['checkout_action']);
}

// JavaScript redirect with delay to see the message
echo "<script>
setTimeout(function() {
    window.location.href = 'order-confirmation.php?order=" . $order_id . "';
}, 3000);
</script>";

// Stop execution here to prevent further processing
exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Failed to place order: " . $e->getMessage();
        }
    }
}

// Generate delivery date options
$delivery_date_options = [];
for ($i = 1; $i <= 7; $i++) {
    $date = date('Y-m-d', strtotime("+$i days"));
    $day_name = date('l, M j', strtotime($date));
    $delivery_date_options[$date] = $day_name;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Order - Krua Thai Meal Kits</title>
    <meta name="description" content="Complete your Thai meal kit order - Enter delivery details and payment information">
    
    <!-- BaticaSans Font Import -->
    <link rel="preconnect" href="https://ydpschool.com">
    <style>
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Regular.woff2') format('woff2'),
                 url('https://ydpschool.com/fonts/BaticaSans-Regular.woff') format('woff'),
                 url('https://ydpschool.com/fonts/BaticaSans-Regular.ttf') format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Bold.woff2') format('woff2'),
                 url('https://ydpschool.com/fonts/BaticaSans-Bold.woff') format('woff'),
                 url('https://ydpschool.com/fonts/BaticaSans-Bold.ttf') format('truetype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Medium.woff2') format('woff2'),
                 url('https://ydpschool.com/fonts/BaticaSans-Medium.woff') format('woff'),
                 url('https://ydpschool.com/fonts/BaticaSans-Medium.ttf') format('truetype');
            font-weight: 500;
            font-style: normal;
            font-display: swap;
        }

        /* CSS Custom Properties */
        :root {
            --brown: #bd9379;
            --cream: #ece8e1;
            --sage: #adb89d;
            --curry: #cf723a;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-gray: #7f8c8d;
            --border-light: #e8e8e8;
            --shadow-soft: 0 4px 12px rgba(189, 147, 121, 0.15);
            --shadow-medium: 0 8px 24px rgba(189, 147, 121, 0.25);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --error-color: #e74c3c;
            --success-color: #27ae60;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background-color: #f8f9fa;
            font-weight: 400;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
            line-height: 1.2;
            color: var(--text-dark);
        }

        /* Navigation */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            z-index: 1000;
            box-shadow: var(--shadow-soft);
            padding: 1rem 0;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            text-decoration: none;
            color: var(--text-dark);
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--curry);
            font-family: 'BaticaSans', sans-serif;
        }

        .back-link {
            color: var(--curry);
            text-decoration: none;
            font-weight: 500;
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
        }

        .back-link:hover {
            color: var(--brown);
        }

        /* Main Content */
        .main-container {
            padding-top: 100px;
            min-height: 100vh;
            background: #f8f9fa;
        }

        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 3rem;
        }

        .checkout-form {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            height: fit-content;
        }

        .order-summary {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            position: sticky;
            top: 120px;
            height: fit-content;
        }

        .section-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section-title {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--cream);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-family: 'BaticaSans', sans-serif;
            background: var(--white);
            transition: var(--transition);
        }

        .form-select:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-family: 'BaticaSans', sans-serif;
            resize: vertical;
            min-height: 80px;
            transition: var(--transition);
        }

        .form-textarea:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        /* Payment Methods */
        .payment-methods {
            display: grid;
            gap: 1rem;
        }

        .payment-option {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
        }

        .payment-option:hover {
            border-color: var(--curry);
        }

        .payment-option.selected {
            border-color: var(--curry);
            background: rgba(207, 114, 58, 0.05);
        }

        .payment-radio {
            margin-right: 1rem;
        }

        .payment-label {
            font-weight: 500;
            font-family: 'BaticaSans', sans-serif;
        }

        .payment-description {
            font-size: 0.9rem;
            color: var(--text-gray);
            margin-top: 0.25rem;
        }

        /* Order Summary */
        .kit-summary {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-light);
        }

        .kit-image {
            width: 80px;
            height: 80px;
            background: linear-gradient(45deg, var(--curry), var(--brown));
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.5rem;
        }

        .kit-details h3 {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .kit-meta {
            font-size: 0.9rem;
            color: var(--text-gray);
            margin-bottom: 0.25rem;
        }

        .kit-price {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--curry);
        }

        /* Price Breakdown */
        .price-breakdown {
            margin-bottom: 1.5rem;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .price-row.total {
            border-top: 2px solid var(--border-light);
            padding-top: 1rem;
            margin-top: 1rem;
            font-weight: 700;
            font-size: 1.1rem;
        }

        /* Buttons */
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--curry);
            color: var(--white);
            width: 100%;
        }

        .btn-primary:hover {
            background: var(--brown);
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: transparent;
            color: var(--curry);
            border: 2px solid var(--curry);
        }

        .btn-secondary:hover {
            background: var(--curry);
            color: var(--white);
        }

        /* Messages */
        .message {
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .message.error {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid var(--error-color);
            color: var(--error-color);
        }

        .message.success {
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }

        .error-list {
            margin: 0;
            padding-left: 1.5rem;
        }

        /* Success Page */
        .success-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 3rem 2rem;
            text-align: center;
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: var(--white);
            font-size: 3rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .checkout-container {
                grid-template-columns: 1fr;
                gap: 2rem;
                padding: 1rem;
            }

            .order-summary {
                position: static;
                order: -1;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .nav-container {
                padding: 0 1rem;
            }

            .logo-text {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .checkout-form,
            .order-summary {
                padding: 1.5rem;
            }

            .section-title {
                font-size: 1.3rem;
            }
        }

        /* Loading state */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .loading .btn-primary {
            background: var(--text-gray);
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="home2.php" class="logo">
                <img src="./assets/image/LOGO_BG.png" alt="Krua Thai" style="height: 40px; width: auto;">
                <span class="logo-text">Krua Thai</span>
            </a>
            <a href="<?php echo $checkout_source === 'cart' ? 'cart.php' : 'meal-kit.php'; ?>" class="back-link">
                ← Back to <?php echo $checkout_source === 'cart' ? 'Cart' : 'Meal Kits'; ?>
            </a>
        </div>
    </nav>

    <div class="main-container">
    
            <!-- Checkout Form -->
            <div class="checkout-container">
                <div class="checkout-form">
                    <h1 class="section-title">Complete Your Order</h1>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="message error">
                            <strong>Please fix the following errors:</strong>
                            <ul class="error-list">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="checkoutForm">
                        <!-- Contact Information -->
                        <div class="form-section">
                            <h2 class="form-section-title">Contact Information</h2>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" id="first_name" name="first_name" class="form-input" 
                                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" id="last_name" name="last_name" class="form-input" 
                                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" id="email" name="email" class="form-input" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="phone" class="form-label">Phone Number *</label>
                                    <input type="tel" id="phone" name="phone" class="form-input" 
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                                           placeholder="(555) 123-4567" required>
                                </div>
                            </div>
                        </div>

                        <!-- Delivery Information -->
                        <div class="form-section">
                            <h2 class="form-section-title">Delivery Information</h2>
                            <div class="form-group">
                                <label for="delivery_address" class="form-label">Street Address *</label>
                                <input type="text" id="delivery_address" name="delivery_address" class="form-input" 
                                       value="<?php echo htmlspecialchars($_POST['delivery_address'] ?? ''); ?>" 
                                       placeholder="123 Main Street, Apt 4B" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="city" class="form-label">City *</label>
                                    <input type="text" id="city" name="city" class="form-input" 
                                           value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="state" class="form-label">State *</label>
                                    <select id="state" name="state" class="form-select" required>
                                        <option value="">Select State</option>
                                        <option value="TX" <?php echo ($_POST['state'] ?? '') === 'TX' ? 'selected' : ''; ?>>Texas</option>
                                        <option value="CA" <?php echo ($_POST['state'] ?? '') === 'CA' ? 'selected' : ''; ?>>California</option>
                                        <option value="NY" <?php echo ($_POST['state'] ?? '') === 'NY' ? 'selected' : ''; ?>>New York</option>
                                        <option value="FL" <?php echo ($_POST['state'] ?? '') === 'FL' ? 'selected' : ''; ?>>Florida</option>
                                        <option value="IL" <?php echo ($_POST['state'] ?? '') === 'IL' ? 'selected' : ''; ?>>Illinois</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="zip_code" class="form-label">ZIP Code *</label>
                                    <input type="text" id="zip_code" name="zip_code" class="form-input" 
                                           value="<?php echo htmlspecialchars($_POST['zip_code'] ?? ''); ?>" 
                                           placeholder="12345" pattern="[0-9]{5}" required>
                                </div>
                                <div class="form-group">
                                    <label for="delivery_date" class="form-label">Delivery Date *</label>
                                    <select id="delivery_date" name="delivery_date" class="form-select" required>
                                        <option value="">Select Date</option>
                                        <?php foreach ($delivery_date_options as $date => $label): ?>
                                            <option value="<?php echo $date; ?>" 
                                                    <?php echo ($_POST['delivery_date'] ?? '') === $date ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="delivery_instructions" class="form-label">Delivery Instructions (Optional)</label>
                                <textarea id="delivery_instructions" name="delivery_instructions" class="form-textarea" 
                                          placeholder="e.g., Leave at front door, Ring doorbell, etc."><?php echo htmlspecialchars($_POST['delivery_instructions'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <!-- Payment Information -->
                        <div class="form-section">
                            <h2 class="form-section-title">Payment Method</h2>
                            <div class="payment-methods">
                                <label class="payment-option" for="credit_card">
                                    <input type="radio" id="credit_card" name="payment_method" value="credit_card" 
                                           class="payment-radio" <?php echo ($_POST['payment_method'] ?? '') === 'credit_card' ? 'checked' : ''; ?>>
                                    <div>
                                        <div class="payment-label">💳 Credit/Debit Card</div>
                                        <div class="payment-description">Visa, Mastercard, American Express</div>
                                    </div>
                                </label>
                                
                                <label class="payment-option" for="apple_pay">
                                    <input type="radio" id="apple_pay" name="payment_method" value="apple_pay" 
                                           class="payment-radio" <?php echo ($_POST['payment_method'] ?? '') === 'apple_pay' ? 'checked' : ''; ?>>
                                    <div>
                                        <div class="payment-label">🍎 Apple Pay</div>
                                        <div class="payment-description">Fast and secure payment with Touch ID</div>
                                    </div>
                                </label>
                                
                                <label class="payment-option" for="google_pay">
                                    <input type="radio" id="google_pay" name="payment_method" value="google_pay" 
                                           class="payment-radio" <?php echo ($_POST['payment_method'] ?? '') === 'google_pay' ? 'checked' : ''; ?>>
                                    <div>
                                        <div class="payment-label">🅖 Google Pay</div>
                                        <div class="payment-description">Quick checkout with your Google account</div>
                                    </div>
                                </label>
                                
                                <label class="payment-option" for="paypal">
                                    <input type="radio" id="paypal" name="payment_method" value="paypal" 
                                           class="payment-radio" <?php echo ($_POST['payment_method'] ?? '') === 'paypal' ? 'checked' : ''; ?>>
                                    <div>
                                        <div class="payment-label">🅿️ PayPal</div>
                                        <div class="payment-description">Pay with your PayPal account</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" name="place_order" class="btn btn-primary" id="submitBtn">
                            Place Order - <?php echo GuestCheckoutUtils::formatPrice($total); ?>
                        </button>
                    </form>
                </div>

                <!-- Order Summary Sidebar - FIXED VERSION -->
                <div class="order-summary">
                    <h2 class="section-title">Order Summary</h2>
                    
                    <?php if ($checkout_source === 'cart' && count($cart_items) > 1): ?>
                        <!-- CART CHECKOUT: Multiple Items -->
                        <div style="margin-bottom: 1.5rem;">
                            <h4 style="margin-bottom: 1rem; color: var(--text-dark);">🛒 Cart Items (<?php echo count($cart_items); ?>)</h4>
                            
                            <?php foreach ($cart_items as $item): ?>
                                <div style="display: flex; gap: 0.75rem; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-light);">
                                    <div style="width: 50px; height: 50px; background: linear-gradient(45deg, var(--curry), var(--brown)); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; color: var(--white); font-size: 1rem;">
                                        🍛
                                    </div>
                                    <div style="flex: 1;">
                                        <h5 style="font-size: 0.95rem; margin-bottom: 0.25rem; font-family: 'BaticaSans', sans-serif;">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </h5>
                                        <div style="font-size: 0.8rem; color: var(--text-gray);">
                                            Qty: <?php echo $item['quantity']; ?> × <?php echo GuestCheckoutUtils::formatPrice($item['base_price']); ?>
                                        </div>
                                        <div style="font-size: 0.9rem; font-weight: 600; color: var(--curry);">
                                            <?php 
                                            $item_total = $item['base_price'] * $item['quantity'];
                                            // Add customization costs if any
                                            if (isset($item['customizations'])) {
                                                if (isset($item['customizations']['extra_protein']) && $item['customizations']['extra_protein']) {
                                                    $item_total += 2.99 * $item['quantity'];
                                                }
                                                if (isset($item['customizations']['extra_vegetables']) && $item['customizations']['extra_vegetables']) {
                                                    $item_total += 1.99 * $item['quantity'];
                                                }
                                            }
                                            echo GuestCheckoutUtils::formatPrice($item_total);
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                    <?php else: ?>
                        <!-- SINGLE KIT CHECKOUT: One Item -->
                        <?php 
                        $display_item = $checkout_source === 'cart' ? $cart_items[0] : $selected_kit;
                        if ($display_item): ?>
                            <div class="kit-summary">
                                <div class="kit-image">🍛</div>
                                <div class="kit-details">
                                    <h3><?php echo htmlspecialchars($display_item['name']); ?></h3>
                                    <div class="kit-meta">
                                        <?php if (isset($display_item['prep_time'])): ?>
                                            ⏱️ <?php echo $display_item['prep_time']; ?> mins
                                        <?php endif; ?>
                                        <?php if (isset($display_item['serves'])): ?>
                                            • 👥 <?php echo htmlspecialchars($display_item['serves']); ?>
                                        <?php endif; ?>
                                        <?php if (isset($display_item['spice_level'])): ?>
                                            • 🌶️ <?php echo htmlspecialchars($display_item['spice_level']); ?>
                                        <?php endif; ?>
                                        <?php if ($checkout_source === 'cart' && isset($display_item['quantity']) && $display_item['quantity'] > 1): ?>
                                            • Qty: <?php echo $display_item['quantity']; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="kit-price">
                                        <?php echo GuestCheckoutUtils::formatPrice($display_item['base_price']); ?>
                                        <?php if ($checkout_source === 'cart' && isset($display_item['quantity']) && $display_item['quantity'] > 1): ?>
                                            <span style="font-size: 0.9rem; color: var(--text-gray);"> each</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Price Breakdown -->
                    <div class="price-breakdown">
                        <div class="price-row">
                            <span>Subtotal<?php if ($checkout_source === 'cart'): ?> (<?php echo array_sum(array_column($cart_items, 'quantity')); ?> items)<?php endif; ?>:</span>
                            <span><?php echo GuestCheckoutUtils::formatPrice($subtotal); ?></span>
                        </div>
                        <div class="price-row">
                            <span>Delivery Fee:</span>
                            <span style="<?php echo $delivery_fee == 0 ? 'color: var(--success-color); font-weight: 600;' : ''; ?>">
                                <?php echo $delivery_fee == 0 ? 'FREE' : GuestCheckoutUtils::formatPrice($delivery_fee); ?>
                            </span>
                        </div>
                        <?php if ($subtotal < 25 && $delivery_fee > 0): ?>
                            <div style="font-size: 0.9rem; color: var(--text-gray); margin-bottom: 0.5rem; font-style: italic;">
                                💡 Add <?php echo GuestCheckoutUtils::formatPrice(25 - $subtotal); ?> more for free delivery!
                            </div>
                        <?php endif; ?>
                        <div class="price-row">
                            <span>Tax (8.25%):</span>
                            <span><?php echo GuestCheckoutUtils::formatPrice($tax_amount); ?></span>
                        </div>
                        <div class="price-row total">
                            <span>Total:</span>
                            <span><?php echo GuestCheckoutUtils::formatPrice($total); ?></span>
                        </div>
                    </div>

                    <!-- What's Included Section -->
                    <div style="background: var(--cream); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1rem;">
                        <?php if ($checkout_source === 'cart'): ?>
                            <h4 style="margin-bottom: 0.5rem; color: var(--text-dark);">📦 What's Included:</h4>
                            <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.9rem; color: var(--text-gray);">
                                <li>✓ All selected meal kits</li>
                                <li>✓ Fresh ingredients & proteins</li>
                                <li>✓ Step-by-step recipe cards</li>
                                <li>✓ Authentic Thai curry pastes</li>
                                <li>✓ Traditional garnishes & rice</li>
                            </ul>
                        <?php else: ?>
                            <h4 style="margin-bottom: 0.5rem; color: var(--text-dark);">📦 What's Included:</h4>
                            <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.9rem; color: var(--text-gray);">
                                <li>✓ Pre-made authentic curry paste</li>
                                <li>✓ Fresh ingredients & proteins</li>
                                <li>✓ Step-by-step recipe card</li>
                                <li>✓ Jasmine rice (where applicable)</li>
                                <li>✓ Traditional garnishes</li>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <!-- Additional Info -->
                    <div style="font-size: 0.85rem; color: var(--text-gray); text-align: center; line-height: 1.4;">
                        <p>🚚 <strong>Free delivery</strong> on orders over $25</p>
                        <p>❄️ All ingredients arrive fresh & chilled</p>
                        <p>📞 24/7 customer support</p>
                        <?php if ($checkout_source === 'cart'): ?>
                            <p style="margin-top: 0.5rem; font-weight: 500;">🎉 Bundle discount applied!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
    
    </div>

    <script>
        // Form validation and interaction
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('checkoutForm');
            const submitBtn = document.getElementById('submitBtn');
// Debug button click
    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            console.log('🔥 Submit button clicked!');
            console.log('Form element:', form);
            console.log('Form method:', form ? form.method : 'No form');
            console.log('Form action:', form ? form.action : 'No action');
            
            // Check if form is valid
            if (form) {
                console.log('Form valid:', form.checkValidity());
                const requiredFields = form.querySelectorAll('[required]');
                console.log('Required fields count:', requiredFields.length);
                
                let emptyFields = [];
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        emptyFields.push(field.name);
                    }
                });
                console.log('Empty required fields:', emptyFields);
            }
        });
    } else {
        console.log('❌ Submit button not found!');
    }
    
    // Debug form submission
    if (form) {
        form.addEventListener('submit', function(e) {
            console.log('🚀 Form submitting!');
            console.log('Event:', e);
        });
    } else {
        console.log('❌ Form not found!');
    }


            const paymentOptions = document.querySelectorAll('.payment-option');
            const phoneInput = document.getElementById('phone');
            const zipInput = document.getElementById('zip_code');

            // Payment option selection
            paymentOptions.forEach(option => {
                option.addEventListener('click', function() {
                    paymentOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                });
            });

            // Phone number formatting
            if (phoneInput) {
                phoneInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length >= 6) {
                        value = value.replace(/(\d{3})(\d{3})(\d{0,4})/, '($1) $2-$3');
                    } else if (value.length >= 3) {
                        value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
                    }
                    e.target.value = value;
                });
            }

            // ZIP code validation
            if (zipInput) {
                zipInput.addEventListener('input', function(e) {
                    e.target.value = e.target.value.replace(/\D/g, '').substring(0, 5);
                });
            }

            // Form submission
            if (form) {
                form.addEventListener('submit', function(e) {
                    // Show loading state
                    submitBtn.textContent = 'Processing Order...';
                    submitBtn.disabled = true;
                    form.classList.add('loading');
                    
                    // Validate required fields
                    const requiredFields = form.querySelectorAll('[required]');
                    let isValid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            isValid = false;
                            field.style.borderColor = 'var(--error-color)';
                        } else {
                            field.style.borderColor = 'var(--border-light)';
                        }
                    });
                    
                    // Validate email
                    const emailField = document.getElementById('email');
                    if (emailField && emailField.value) {
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(emailField.value)) {
                            isValid = false;
                            emailField.style.borderColor = 'var(--error-color)';
                        }
                    }
                    
                    // Validate phone
                    if (phoneInput && phoneInput.value) {
                        const phoneRegex = /^\(\d{3}\) \d{3}-\d{4}$/;
                        if (!phoneRegex.test(phoneInput.value)) {
                            isValid = false;
                            phoneInput.style.borderColor = 'var(--error-color)';
                        }
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                        submitBtn.textContent = 'Place Order - <?php echo GuestCheckoutUtils::formatPrice($total); ?>';
                        submitBtn.disabled = false;
                        form.classList.remove('loading');
                        
                        // Scroll to first error
                        const firstError = form.querySelector('[style*="border-color: var(--error-color)"]');
                        if (firstError) {
                            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            firstError.focus();
                        }
                    }
                });
            }

            // Auto-scroll to errors
            const errorMessage = document.querySelector('.message.error');
            if (errorMessage) {
                errorMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        // Prevent double submission
        let isSubmitting = false;
        document.getElementById('checkoutForm')?.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            isSubmitting = true;
        });
    </script>
</body>
</html>