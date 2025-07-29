<?php
/**
 * Somdul Table - Updated Checkout System
 * Updated for Wednesday/Saturday date selection and meal details fix
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize variables with defaults
$order = null;
$plan = null;
$selected_meals = [];
$meal_details = [];
$errors = [];
$success = false;
$user = null;

// Early session and authentication checks
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Utility Functions
class CheckoutUtils {
    
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
        return 'TXN-' . date('Ymd-His') . '-' . substr(self::generateUUID(), 0, 6);
    }
    
    public static function getPlanName($plan) {
        return $plan['name'] ?? 'Selected Package';
    }
    
    public static function getMenuName($menu) {
        return $menu['name'] ?? 'Menu Item';
    }
    
    public static function sanitizeInput($input) {
        return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    }
}

// Database Connection Handler
class DatabaseConnection {
    private static $connection = null;
    
    public static function getInstance() {
        if (self::$connection === null) {
            try {
                require_once 'config/database.php';
                self::$connection = (new Database())->getConnection();
            } catch (Exception $e) {
                // Fallback connections
                $configs = [
                    ["mysql:host=localhost;dbname=somdul_table;charset=utf8mb4", "root", "root"],
                    ["mysql:host=localhost:8889;dbname=somdul_table;charset=utf8mb4", "root", "root"]
                ];
                
                foreach ($configs as $config) {
                    try {
                        self::$connection = new PDO($config[0], $config[1], $config[2]);
                        self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        break;
                    } catch (PDOException $e) {
                        continue;
                    }
                }
                
                if (self::$connection === null) {
                    throw new Exception("❌ Database connection failed: " . $e->getMessage());
                }
            }
        }
        return self::$connection;
    }
}

// Checkout Data Manager
class CheckoutDataManager {
    
    public static function createDemoCheckoutData($db) {
        try {
            $stmt = $db->query("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 1");
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($plan) {
                $meal_limit = $plan['meals_per_week'] ?? 3;
                $stmt = $db->query("SELECT id, name, base_price FROM menus WHERE is_available = 1 ORDER BY RAND() LIMIT $meal_limit");
                $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($menus)) {
                    return [
                        'plan' => $plan,
                        'selected_meals' => array_column($menus, 'id'),
                        'meal_details' => array_combine(array_column($menus, 'id'), $menus)
                    ];
                }
            }
        } catch (Exception $e) {
            throw new Exception("Error loading demo data: " . $e->getMessage());
        }
        
        return null;
    }
    
    public static function validateCheckoutData($order) {
        $errors = [];
        
        if (!$order || empty($order['plan']) || empty($order['selected_meals'])) {
            $errors[] = "Invalid checkout data";
        }
        
        if (!isset($order['plan']['id']) || !isset($order['plan']['final_price'])) {
            $errors[] = "Invalid plan data";
        }
        
        if (empty($order['selected_meals']) || !is_array($order['selected_meals'])) {
            $errors[] = "No meals selected";
        }
        
        return $errors;
    }
    
    /**
     * NEW FUNCTION: Populate meal details from database based on selected meal IDs
     */
    public static function populateMealDetails($db, $selected_meals) {
        if (empty($selected_meals) || !is_array($selected_meals)) {
            return [];
        }
        
        try {
            // Create placeholders for the IN clause
            $placeholders = str_repeat('?,', count($selected_meals) - 1) . '?';
            
            $stmt = $db->prepare("
                SELECT id, name, name_thai, base_price, description, main_image_url,
                       mc.name as category_name, mc.name_thai as category_name_thai
                FROM menus m 
                LEFT JOIN menu_categories mc ON m.category_id = mc.id 
                WHERE m.id IN ($placeholders) AND m.is_available = 1
            ");
            
            $stmt->execute($selected_meals);
            $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Create associative array with meal ID as key
            $meal_details = [];
            foreach ($menus as $menu) {
                $meal_details[$menu['id']] = $menu;
            }
            
            return $meal_details;
            
        } catch (Exception $e) {
            error_log("Error fetching meal details: " . $e->getMessage());
            return [];
        }
    }
}

// Order Processing Engine (Updated for single delivery date)
class OrderProcessor {
    
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function validateFormInput($postData) {
        $errors = [];
        
        $required_fields = [
            'delivery_address' => 'Delivery address',
            'city' => 'City/State',
            'zip_code' => 'ZIP code',
            'payment_method' => 'Payment method'
        ];
        
        foreach ($required_fields as $field => $label) {
            if (empty(trim($postData[$field] ?? ''))) {
                $errors[] = "Please enter {$label}";
            }
        }
        
        // Updated validation for delivery date
        if (empty($postData['delivery_day'])) {
            $errors[] = "Please select a delivery date";
        } else {
            $delivery_date = $postData['delivery_day'];
            $date_obj = DateTime::createFromFormat('Y-m-d', $delivery_date);
            
            if (!$date_obj) {
                $errors[] = "Invalid date format";
            } else {
                $day_of_week = $date_obj->format('N'); // 1=Monday, 3=Wednesday, 6=Saturday
                if ($day_of_week != 3 && $day_of_week != 6) {
                    $errors[] = "Please select a Wednesday or Saturday for delivery";
                }
                
                // Check if date is not in the past
                $today = new DateTime();
                if ($date_obj < $today) {
                    $errors[] = "Please select a future date for delivery";
                }
                
                // Check if date is not too far in the future (within 4 weeks)
                $max_date = new DateTime('+4 weeks');
                if ($date_obj > $max_date) {
                    $errors[] = "Please select a date within the next 4 weeks";
                }
            }
        }
        
        // ZIP code validation
        $zip_code = trim($postData['zip_code'] ?? '');
        if (!preg_match('/^\d{5}$/', $zip_code)) {
            $errors[] = "ZIP code must be 5 digits";
        }
        
        return $errors;
    }
    
    public function createSubscription($data) {
        $subscription_id = CheckoutUtils::generateUUID();
        
        $stmt = $this->db->prepare("INSERT INTO subscriptions (
            id, user_id, plan_id, status, start_date, next_billing_date,
            billing_cycle, total_amount, delivery_days, preferred_delivery_time,
            special_instructions, auto_renew, created_at, updated_at
        ) VALUES (?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
        
        $result = $stmt->execute([
            $subscription_id,
            $data['user_id'],
            $data['plan_id'],
            $data['start_date'],
            $data['next_billing_date'],
            $data['billing_cycle'],
            $data['total_amount'],
            $data['delivery_days'],
            $data['preferred_time'],
            $data['delivery_instructions']
        ]);
        
        if (!$result) {
            throw new Exception("Failed to create subscription");
        }
        
        return $subscription_id;
    }
    
    public function createPayment($data) {
        $payment_id = CheckoutUtils::generateUUID();
        $transaction_id = CheckoutUtils::generateOrderNumber();
        
        $payment_map = [
            'credit' => 'credit_card',
            'promptpay' => 'bank_transfer',
            'paypal' => 'paypal',
            'apple_pay' => 'apple_pay',
            'google_pay' => 'google_pay'
        ];
        
        $db_payment_method = $payment_map[$data['payment_method']] ?? 'credit_card';
        
        $stmt = $this->db->prepare("INSERT INTO payments (
            id, subscription_id, user_id, payment_method, transaction_id,
            amount, currency, net_amount, status, payment_date,
            billing_period_start, billing_period_end, description, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'USD', ?, 'completed', NOW(), ?, ?, ?, NOW(), NOW())");
        
        $result = $stmt->execute([
            $payment_id,
            $data['subscription_id'],
            $data['user_id'],
            $db_payment_method,
            $transaction_id,
            $data['amount'],
            $data['amount'],
            $data['start_date'],
            $data['next_billing_date'],
            $data['description']
        ]);
        
        if (!$result) {
            throw new Exception("Failed to create payment");
        }
        
        return ['payment_id' => $payment_id, 'transaction_id' => $transaction_id];
    }
    
    public function createSubscriptionMenus($subscription_id, $selected_meals, $delivery_date) {
        $stmt = $this->db->prepare("INSERT INTO subscription_menus
            (id, subscription_id, menu_id, delivery_date, quantity, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, 1, 'scheduled', NOW(), NOW())");
        
        foreach ($selected_meals as $meal_id) {
            $menu_uuid = CheckoutUtils::generateUUID();
            $result = $stmt->execute([$menu_uuid, $subscription_id, $meal_id, $delivery_date]);
            
            if (!$result) {
                throw new Exception("Failed to create subscription menu");
            }
        }
    }
    
    public function updateUserProfile($user_id, $data) {
        $stmt = $this->db->prepare("UPDATE users 
            SET delivery_address=?, city=?, zip_code=?, delivery_instructions=?, updated_at=NOW() 
            WHERE id=?");
        
        return $stmt->execute([
            $data['delivery_address'],
            $data['city'],
            $data['zip_code'],
            $data['delivery_instructions'],
            $user_id
        ]);
    }
    
    public function processFullOrder($user_id, $order, $postData) {
        $this->db->beginTransaction();
        
        try {
            $plan = $order['plan'];
            $selected_meals = $order['selected_meals'];
            $delivery_date = $postData['delivery_day']; // Direct date from form (Y-m-d format)
            
            // Calculate dates
            $start_date = $delivery_date; // Already in Y-m-d format
            
            $billing_cycle = ($plan['plan_type'] ?? 'weekly') === 'monthly' ? 'monthly' : 'weekly';
            $next_billing_date = $billing_cycle === 'monthly'
                ? date('Y-m-d', strtotime('+1 month', strtotime($start_date)))
                : date('Y-m-d', strtotime('+1 week', strtotime($start_date)));
            
            // Create subscription
            $subscription_data = [
                'user_id' => $user_id,
                'plan_id' => $plan['id'],
                'start_date' => $start_date,
                'next_billing_date' => $next_billing_date,
                'billing_cycle' => $billing_cycle,
                'total_amount' => $plan['final_price'],
                'delivery_days' => json_encode([$delivery_date]), // Array with the selected date
                'preferred_time' => $postData['preferred_time'] ?? 'afternoon',
                'delivery_instructions' => CheckoutUtils::sanitizeInput($postData['delivery_instructions'] ?? '')
            ];
            
            $subscription_id = $this->createSubscription($subscription_data);
            
            // Create payment
            $payment_data = [
                'subscription_id' => $subscription_id,
                'user_id' => $user_id,
                'payment_method' => $postData['payment_method'],
                'amount' => $plan['final_price'],
                'start_date' => $start_date,
                'next_billing_date' => $next_billing_date,
                'description' => "Subscription " . CheckoutUtils::getPlanName($plan)
            ];
            
            $payment_result = $this->createPayment($payment_data);
            
            // Create subscription menus for delivery date
            $this->createSubscriptionMenus($subscription_id, $selected_meals, $start_date);
            
            // Update user profile
            $user_data = [
                'delivery_address' => CheckoutUtils::sanitizeInput($postData['delivery_address']),
                'city' => CheckoutUtils::sanitizeInput($postData['city']),
                'zip_code' => CheckoutUtils::sanitizeInput($postData['zip_code']),
                'delivery_instructions' => CheckoutUtils::sanitizeInput($postData['delivery_instructions'] ?? '')
            ];
            
            $this->updateUserProfile($user_id, $user_data);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'subscription_id' => $subscription_id,
                'transaction_id' => $payment_result['transaction_id']
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}

// Main execution starts here
try {
    // Get database connection
    $db = DatabaseConnection::getInstance();
    
    // Get user data
    $user_id = $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get or create checkout data
    $order = $_SESSION['checkout_data'] ?? null;
    
    // Validate existing checkout data or create demo data
    $validation_errors = CheckoutDataManager::validateCheckoutData($order);
    if (!empty($validation_errors)) {
        $order = CheckoutDataManager::createDemoCheckoutData($db);
        if ($order) {
            $_SESSION['checkout_data'] = $order;
        } else {
            throw new Exception("Unable to create checkout data. Please ensure plans and menus are available.");
        }
    }
    
    // **FIX: Populate meal details if missing (coming from meal-selection.php)**
    if (isset($order['selected_meals']) && !empty($order['selected_meals']) && 
        (!isset($order['meal_details']) || empty($order['meal_details']))) {
        
        $meal_details = CheckoutDataManager::populateMealDetails($db, $order['selected_meals']);
        $order['meal_details'] = $meal_details;
        
        // Update session with the populated meal details
        $_SESSION['checkout_data'] = $order;
    }
    
    // Extract order components
    $plan = $order['plan'];
    $selected_meals = $order['selected_meals'] ?? [];
    $meal_details = $order['meal_details'] ?? [];
    $total_price = $plan['final_price'];
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['submit_order']) || !empty($_POST['payment_method']))) {
        
        $processor = new OrderProcessor($db);
        
        // Validate form input
        $errors = $processor->validateFormInput($_POST);
        
        if (empty($errors)) {
            try {
                $result = $processor->processFullOrder($user_id, $order, $_POST);
                
                if ($result['success']) {
                    $success = true;
                    unset($_SESSION['checkout_data']);
                    $_SESSION['flash_message'] = "Order placed successfully! Thank you for choosing Somdul Table";
                    $_SESSION['flash_type'] = 'success';
                    $_SESSION['last_order_id'] = $result['subscription_id'];
                    $_SESSION['prevent_double_submit'] = time();
                    
                    header("Location: subscription-status.php?order=" . $result['subscription_id']);
                    exit;
                }
                
            } catch (Exception $e) {
                $errors[] = "An error occurred: " . $e->getMessage();
            }
        }
    }
    
} catch (Exception $e) {
    die("❌ Application Error: " . $e->getMessage());
}

// Check for successful completion - early return to prevent HTML output
if ($success) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Confirm Order | Somdul Table</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://ydpschool.com/fonts/BaticaSans.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --cream: #ece8e1;
            --sage: #adb89d;
            --brown: #bd9379;
            --curry: #cf723a;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-gray: #7f8c8d;
            --border-light: #e8e8e8;
            --shadow-soft: 0 4px 12px rgba(0,0,0,0.05);
            --shadow-medium: 0 8px 24px rgba(0,0,0,0.1);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'BaticaSans', 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--cream) 0%, #f8f6f3 100%);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: var(--white);
            box-shadow: var(--shadow-soft);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            color: inherit;
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--curry);
        }

        .header-nav {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .nav-link {
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 500;
            transition: var(--transition);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-sm);
        }

        .nav-link:hover {
            background: var(--cream);
            color: var(--curry);
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 20px 4rem;
        }

        /* Progress Bar */
        .progress-container {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 3rem;
            box-shadow: var(--shadow-soft);
        }

        .progress-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            max-width: 800px;
            margin: 0 auto;
        }

        .progress-step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            border-radius: var(--radius-xl);
            font-weight: 600;
            font-size: 0.95rem;
            background: var(--cream);
            color: var(--text-gray);
            border: 2px solid var(--cream);
            transition: var(--transition);
            white-space: nowrap;
            flex: 1;
            justify-content: center;
        }

        .progress-step.active {
            background: var(--curry);
            color: var(--white);
            border-color: var(--curry);
            box-shadow: 0 4px 12px rgba(207, 114, 58, 0.3);
        }

        .progress-step.completed {
            background: var(--success);
            color: var(--white);
            border-color: var(--success);
        }

        .progress-arrow {
            color: var(--sage);
            font-size: 1.2rem;
            font-weight: 600;
            flex-shrink: 0;
        }

        .title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 2rem;
            text-align: center;
            color: var(--text-dark);
        }

        .title i {
            color: var(--curry);
            margin-right: 0.5rem;
        }

        .section {
            background: var(--white);
            border-radius: var(--radius-xl);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-soft);
            padding: 2rem;
            border: 1px solid var(--border-light);
            position: relative;
            overflow: hidden;
        }

        .section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--curry), var(--brown), var(--sage));
        }

        .label {
            font-weight: 700;
            color: var(--curry);
            margin-bottom: 1rem;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .plan-title {
            font-size: 1.2rem;
            color: var(--text-dark);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .plan-price {
            color: var(--curry);
            font-size: 1.4rem;
            font-weight: 700;
        }

        .meal-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .meal-list li {
            border-bottom: 1px solid var(--border-light);
            padding: 1rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
        }

        .meal-list li:hover {
            background: var(--cream);
            margin: 0 -1rem;
            padding: 1rem;
            border-radius: var(--radius-md);
        }

        .meal-list li:last-child {
            border-bottom: none;
        }

        .meal-name {
            flex: 1;
            font-weight: 600;
            color: var(--text-dark);
        }

        .meal-price {
            color: var(--curry);
            font-weight: 600;
        }

        /* Empty meals state */
        .no-meals-message {
            text-align: center;
            padding: 2rem;
            color: var(--text-gray);
            font-style: italic;
        }

        .total {
            font-size: 1.5rem;
            color: var(--curry);
            font-weight: 700;
            margin: 2rem 0;
            text-align: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--cream), #f5f3f0);
            border-radius: var(--radius-lg);
        }

        .address-input, .input {
            width: 100%;
            padding: 1rem 1.2rem;
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-light);
            margin-bottom: 1.2rem;
            font-size: 1rem;
            font-family: inherit;
            transition: var(--transition);
            background: var(--white);
        }

        .input:focus, .address-input:focus {
            border-color: var(--curry);
            outline: none;
            box-shadow: 0 0 15px rgba(207, 114, 58, 0.2);
        }

        .btn {
            width: 100%;
            padding: 1.2rem 2rem;
            border-radius: var(--radius-xl);
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            font-size: 1.1rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            box-shadow: 0 4px 12px rgba(207, 114, 58, 0.3);
        }

        .btn:hover {
            background: linear-gradient(135deg, var(--brown), var(--sage));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(207, 114, 58, 0.4);
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .payment-methods label {
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            padding: 1.2rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            transition: var(--transition);
            background: var(--white);
            font-weight: 600;
            position: relative;
            overflow: hidden;
        }

        .payment-methods label::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(207, 114, 58, 0.05), rgba(189, 147, 121, 0.05));
            opacity: 0;
            transition: var(--transition);
        }

        .payment-methods label:hover::before {
            opacity: 1;
        }

        .payment-methods label:hover {
            border-color: var(--curry);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(207, 114, 58, 0.2);
        }

        .payment-methods input:checked + i {
            color: var(--curry);
        }

        .payment-methods input {
            accent-color: var(--curry);
            margin-right: 0.5rem;
        }

        .payment-methods i {
            font-size: 1.3rem;
            color: var(--curry);
            z-index: 1;
        }

        .payment-methods span {
            z-index: 1;
        }

        .error {
            background: linear-gradient(135deg, #ffebee, #fce4ec);
            color: var(--danger);
            border: 2px solid #ffcdd2;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
        }

        .error ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .error li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .error li:before {
            content: "⚠️";
        }

        .weekend-info {
            background: linear-gradient(135deg, #e8f5e8, #f0f8f0);
            border: 2px solid var(--sage);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
            color: var(--text-dark);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .weekend-info i {
            color: var(--sage);
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .weekend-info-content {
            flex: 1;
        }

        .weekend-info strong {
            color: var(--curry);
        }

        /* Date Selection Styles - Custom Calendar */
        .date-selection-container {
            position: relative;
        }

        .custom-calendar {
            background: var(--white);
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-soft);
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--cream);
        }

        .calendar-nav {
            background: var(--cream);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            color: var(--curry);
            font-size: 1rem;
        }

        .calendar-nav:hover {
            background: var(--curry);
            color: var(--white);
            transform: scale(1.1);
        }

        .calendar-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-dark);
            text-align: center;
            flex: 1;
        }

        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .weekday {
            text-align: center;
            font-weight: 600;
            color: var(--text-gray);
            padding: 0.5rem;
            font-size: 0.9rem;
        }

        .weekday.highlight {
            color: var(--curry);
            font-weight: 700;
        }

        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
        }

        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
            position: relative;
            background: var(--white);
            border: 1px solid transparent;
            min-height: 40px;
            user-select: none;
        }

        .calendar-day.other-month {
            color: var(--text-gray);
            opacity: 0.3;
            cursor: not-allowed;
        }

        .calendar-day.disabled {
            color: var(--text-gray);
            opacity: 0.4;
            cursor: not-allowed;
            background: #f8f8f8;
        }

        .calendar-day.available {
            color: var(--curry);
            background: var(--cream);
            border-color: var(--curry);
            font-weight: 700;
        }

        .calendar-day.available:hover {
            background: var(--curry);
            color: var(--white);
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(207, 114, 58, 0.3);
        }

        .calendar-day.selected {
            background: var(--curry);
            color: var(--white);
            transform: scale(1.05);
            box-shadow: 0 4px 16px rgba(207, 114, 58, 0.4);
            border-color: var(--brown);
        }

        .calendar-day.today {
            position: relative;
        }

        .calendar-day.today::after {
            content: '';
            position: absolute;
            bottom: 2px;
            left: 50%;
            transform: translateX(-50%);
            width: 4px;
            height: 4px;
            background: var(--sage);
            border-radius: 50%;
        }

        .calendar-day.available.today::after {
            background: var(--curry);
        }

        .calendar-day.selected.today::after {
            background: var(--white);
        }

        /* Calendar animations */
        .custom-calendar {
            animation: calendarFadeIn 0.3s ease-out;
        }

        @keyframes calendarFadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .calendar-day {
            animation: dayFadeIn 0.2s ease-out;
        }

        @keyframes dayFadeIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .date-error-message, .date-success-message {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            transition: var(--transition);
            animation: slideIn 0.3s ease-out;
        }

        .date-error-message {
            background: linear-gradient(135deg, #ffebee, #fce4ec);
            color: var(--danger);
            border: 2px solid #ffcdd2;
        }

        .date-success-message {
            background: linear-gradient(135deg, #e8f5e8, #f0f8f0);
            color: var(--success);
            border: 2px solid var(--sage);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-message {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: var(--success);
            border: 2px solid #c3e6cb;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            text-align: center;
        }

        .success-message h2 {
            margin-bottom: 1rem;
            color: var(--success);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem 15px 3rem;
            }

            .section {
                padding: 1.5rem;
            }

            .progress-bar {
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .progress-step {
                font-size: 0.8rem;
                padding: 0.6rem 1rem;
                flex: none;
                min-width: 120px;
            }

            .progress-arrow {
                font-size: 1rem;
            }

            .payment-methods {
                grid-template-columns: 1fr;
            }

            .custom-calendar {
                padding: 1rem;
                max-width: 100%;
            }

            .calendar-title {
                font-size: 1.1rem;
            }

            .calendar-nav {
                width: 35px;
                height: 35px;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .header-container {
                padding: 1rem;
            }

            .logo-text {
                font-size: 1.5rem;
            }

            .title {
                font-size: 1.8rem;
            }

            .progress-step {
                font-size: 0.7rem;
                padding: 0.5rem 0.8rem;
                min-width: 100px;
            }

            .meal-list li {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .custom-calendar {
                padding: 0.8rem;
            }

            .calendar-title {
                font-size: 1rem;
            }

            .calendar-nav {
                width: 30px;
                height: 30px;
                font-size: 0.8rem;
            }

            .weekday {
                font-size: 0.8rem;
                padding: 0.3rem;
            }

            .calendar-day {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <div class="logo-text">Somdul Table</div>
            </a>
            <nav class="header-nav">
                <a href="menu.php" class="nav-link">Menu</a>
                <a href="about.php" class="nav-link">About Us</a>
                <a href="contact.php" class="nav-link">Contact</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="nav-link">Dashboard</a>
                    <a href="logout.php" class="nav-link">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="nav-link">Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <div class="container">
        <!-- Progress Bar -->
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-step completed">
                    <i class="fas fa-check-circle"></i>
                    <span>Choose Package</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step completed">
                    <i class="fas fa-check-circle"></i>
                    <span>Select Menu</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step active">
                    <i class="fas fa-credit-card"></i>
                    <span>Payment</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step">
                    <i class="fas fa-check-double"></i>
                    <span>Complete</span>
                </div>
            </div>
        </div>

        <div class="title"><i class="fas fa-wallet"></i> Review and Pay</div>

        <?php if (!empty($errors)): ?>
            <div class="error">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Plan Summary -->
        <div class="section plan-summary">
            <div class="label"><i class="fas fa-box"></i> Selected Package</div>
            <div class="plan-title"><?php echo htmlspecialchars(CheckoutUtils::getPlanName($plan)); ?> (<?php echo $plan['meals_per_week']; ?> meals per week)</div>
            <div class="plan-price">$<?php echo number_format($plan['final_price']/100, 2); ?> /week</div>
        </div>

        <!-- Meals Summary -->
        <div class="section meals-summary">
            <div class="label"><i class="fas fa-utensils"></i> Selected Meals</div>
            <?php if (!empty($selected_meals) && !empty($meal_details)): ?>
                <ul class="meal-list">
                    <?php foreach ($selected_meals as $meal_id): ?>
                        <?php $meal = $meal_details[$meal_id] ?? null; if (!$meal) continue; ?>
                        <li>
                            <div class="meal-name"><?php echo htmlspecialchars(CheckoutUtils::getMenuName($meal)); ?></div>
                            <div class="meal-price">$<?php echo number_format($meal['base_price']/100, 2); ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="no-meals-message">
                    <i class="fas fa-exclamation-triangle" style="color: var(--warning); margin-right: 0.5rem;"></i>
                    No meals selected or meal details unavailable. Please go back and select your meals.
                    <br><br>
                    <a href="meal-selection.php?plan=<?php echo urlencode($plan['id'] ?? ''); ?>" 
                       style="color: var(--curry); text-decoration: none; font-weight: 600;">
                        ← Back to Meal Selection
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Order Form -->
        <form method="POST" action="" id="checkout-form">
            <div class="section">
                <div class="label"><i class="fas fa-map-marker-alt"></i> Delivery Address</div>
                <input type="text" class="address-input" name="delivery_address" required
                       value="<?php echo htmlspecialchars($user['delivery_address'] ?? ''); ?>" placeholder="Enter your address">
                <input type="text" class="address-input" name="city" required
                       value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" placeholder="City, State">
                <input type="text" class="address-input" name="zip_code" required maxlength="5" pattern="[0-9]{5}"
                       value="<?php echo htmlspecialchars($user['zip_code'] ?? ''); ?>" placeholder="ZIP Code">
                <textarea name="delivery_instructions" class="address-input" rows="2" placeholder="Special delivery instructions (optional)"><?php echo htmlspecialchars($user['delivery_instructions'] ?? ''); ?></textarea>
            </div>

            <div class="section">
                <div class="label"><i class="fas fa-calendar-weekend"></i> Select Your Delivery Day</div>
                
                <div class="weekend-info">
                    <i class="fas fa-info-circle"></i>
                    <div class="weekend-info-content">
                        <strong>Delivery Schedule:</strong> We deliver fresh Thai meals on <strong>Wednesdays and Saturdays only</strong>. Please select your preferred delivery date below.
                    </div>
                </div>
                
                <div class="date-selection-container">
                    <!-- Custom Calendar -->
                    <div class="custom-calendar">
                        <div class="calendar-header">
                            <button type="button" class="calendar-nav" id="prev-month">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div class="calendar-title" id="calendar-title">
                                <!-- Month Year will be populated by JavaScript -->
                            </div>
                            <button type="button" class="calendar-nav" id="next-month">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                        
                        <div class="calendar-weekdays">
                            <div class="weekday">Sun</div>
                            <div class="weekday">Mon</div>
                            <div class="weekday">Tue</div>
                            <div class="weekday highlight">Wed</div>
                            <div class="weekday">Thu</div>
                            <div class="weekday">Fri</div>
                            <div class="weekday highlight">Sat</div>
                        </div>
                        
                        <div class="calendar-days" id="calendar-days">
                            <!-- Days will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Hidden input to store the selected date -->
                    <input type="hidden" name="delivery_day" id="delivery_date" required>
                    
                    <div id="date-error" class="date-error-message" style="display: none;">
                        <i class="fas fa-exclamation-triangle"></i>
                        Please select a Wednesday or Saturday for delivery.
                    </div>
                    
                    <div id="date-success" class="date-success-message" style="display: none;">
                        <i class="fas fa-check-circle"></i>
                        <span id="selected-day-name"></span> delivery selected!
                    </div>
                </div>
                
                <div class="label" style="margin-top:2rem;"><i class="fas fa-clock"></i> Preferred Delivery Time</div>
                <select name="preferred_time" class="address-input" required>
                    <option value="09:00-12:00">Morning (9:00 AM - 12:00 PM)</option>
                    <option value="12:00-15:00">Lunch (12:00 PM - 3:00 PM)</option>
                    <option value="15:00-18:00" selected>Afternoon (3:00 PM - 6:00 PM)</option>
                    <option value="18:00-21:00">Evening (6:00 PM - 9:00 PM)</option>
                </select>
            </div>

            <!-- Payment Method -->
            <div class="section">
                <div class="label"><i class="fas fa-credit-card"></i> Choose Payment Method</div>
                <div class="payment-methods">
                    <label>
                        <input type="radio" name="payment_method" value="credit" required>
                        <i class="fas fa-credit-card"></i>
                        <span>Credit/Debit Card</span>
                    </label>
                    <label>
                        <input type="radio" name="payment_method" value="paypal">
                        <i class="fab fa-paypal"></i>
                        <span>PayPal</span>
                    </label>
                    <label>
                        <input type="radio" name="payment_method" value="apple_pay">
                        <i class="fab fa-apple-pay"></i>
                        <span>Apple Pay</span>
                    </label>
                    <label>
                        <input type="radio" name="payment_method" value="google_pay">
                        <i class="fab fa-google-pay"></i>
                        <span>Google Pay</span>
                    </label>
                    <label>
                        <input type="radio" name="payment_method" value="promptpay">
                        <i class="fas fa-university"></i>
                        <span>Bank Transfer</span>
                    </label>
                </div>
                
                <div class="total">Total: $<?php echo number_format($plan['final_price']/100, 2); ?></div>
                
                <button class="btn" type="submit" name="submit_order" value="1" id="main-submit-btn">
                    <i class="fas fa-lock"></i> Confirm and Pay
                </button>
                
                <!-- Hidden fields for form security -->
                <input type="hidden" name="form_token" value="<?php echo hash('sha256', session_id() . time()); ?>">
                <input type="hidden" name="form_submitted" value="1">
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ Updated checkout page loaded');
            
            // Calendar functionality
            class DeliveryCalendar {
                constructor() {
                    this.currentDate = new Date();
                    this.selectedDate = null;
                    this.calendarTitle = document.getElementById('calendar-title');
                    this.calendarDays = document.getElementById('calendar-days');
                    this.deliveryDateInput = document.getElementById('delivery_date');
                    this.dateError = document.getElementById('date-error');
                    this.dateSuccess = document.getElementById('date-success');
                    this.selectedDayName = document.getElementById('selected-day-name');
                    
                    this.init();
                }
                
                init() {
                    this.renderCalendar();
                    this.attachEventListeners();
                }
                
                attachEventListeners() {
                    document.getElementById('prev-month')?.addEventListener('click', () => {
                        this.currentDate.setMonth(this.currentDate.getMonth() - 1);
                        this.renderCalendar();
                    });
                    
                    document.getElementById('next-month')?.addEventListener('click', () => {
                        this.currentDate.setMonth(this.currentDate.getMonth() + 1);
                        this.renderCalendar();
                    });
                }
                
                renderCalendar() {
                    const year = this.currentDate.getFullYear();
                    const month = this.currentDate.getMonth();
                    
                    // Update calendar title
                    const monthNames = [
                        'January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'
                    ];
                    this.calendarTitle.textContent = `${monthNames[month]} ${year}`;
                    
                    // Clear previous days
                    this.calendarDays.innerHTML = '';
                    
                    // Get first day of month and number of days
                    const firstDay = new Date(year, month, 1);
                    const lastDay = new Date(year, month + 1, 0);
                    const firstDayOfWeek = firstDay.getDay();
                    const daysInMonth = lastDay.getDate();
                    
                    // Get previous month's last days to fill the first week
                    const prevMonth = new Date(year, month - 1, 0);
                    const daysInPrevMonth = prevMonth.getDate();
                    
                    const today = new Date();
                    const maxDate = new Date();
                    maxDate.setDate(maxDate.getDate() + 28); // 4 weeks from today
                    
                    // Add previous month's trailing days
                    for (let i = firstDayOfWeek - 1; i >= 0; i--) {
                        const dayNum = daysInPrevMonth - i;
                        const dayElement = this.createDayElement(dayNum, 'other-month');
                        this.calendarDays.appendChild(dayElement);
                    }
                    
                    // Add current month's days
                    for (let day = 1; day <= daysInMonth; day++) {
                        const currentDayDate = new Date(year, month, day);
                        const dayOfWeek = currentDayDate.getDay(); // 0=Sunday, 3=Wednesday, 6=Saturday
                        const isToday = this.isSameDate(currentDayDate, today);
                        
                        let dayClass = '';
                        let isClickable = false;
                        
                        // Check if it's Wednesday (3) or Saturday (6)
                        if (dayOfWeek === 3 || dayOfWeek === 6) {
                            // Check if it's not in the past and within 4 weeks
                            if (currentDayDate >= today && currentDayDate <= maxDate) {
                                dayClass = 'available';
                                isClickable = true;
                            } else if (currentDayDate < today) {
                                dayClass = 'disabled';
                            } else {
                                dayClass = 'disabled';
                            }
                        } else {
                            dayClass = 'disabled';
                        }
                        
                        if (isToday) {
                            dayClass += ' today';
                        }
                        
                        // Check if this day is selected
                        if (this.selectedDate && this.isSameDate(currentDayDate, this.selectedDate)) {
                            dayClass += ' selected';
                        }
                        
                        const dayElement = this.createDayElement(day, dayClass, isClickable, currentDayDate);
                        this.calendarDays.appendChild(dayElement);
                    }
                    
                    // Add next month's leading days to complete the grid
                    const totalCells = this.calendarDays.children.length;
                    const remainingCells = 42 - totalCells; // 6 rows × 7 days = 42
                    
                    for (let day = 1; day <= remainingCells && day <= 14; day++) {
                        const dayElement = this.createDayElement(day, 'other-month');
                        this.calendarDays.appendChild(dayElement);
                    }
                }
                
                createDayElement(dayNum, className = '', isClickable = false, date = null) {
                    const dayElement = document.createElement('div');
                    dayElement.className = `calendar-day ${className}`;
                    dayElement.textContent = dayNum;
                    
                    if (isClickable && date) {
                        dayElement.style.cursor = 'pointer';
                        dayElement.addEventListener('click', () => {
                            this.selectDate(date, dayElement);
                        });
                    }
                    
                    return dayElement;
                }
                
                selectDate(date, element) {
                    // Remove previous selection
                    const previousSelected = this.calendarDays.querySelector('.calendar-day.selected');
                    if (previousSelected) {
                        previousSelected.classList.remove('selected');
                    }
                    
                    // Add selection to clicked element
                    element.classList.add('selected');
                    
                    // Update selected date
                    this.selectedDate = new Date(date);
                    
                    // Format date for form input (YYYY-MM-DD)
                    const formattedDate = this.formatDateForInput(date);
                    this.deliveryDateInput.value = formattedDate;
                    
                    // Show success message
                    const dayOfWeek = date.getDay();
                    const dayName = dayOfWeek === 3 ? 'Wednesday' : 'Saturday';
                    const formattedDisplay = this.formatDateForDisplay(date);
                    
                    this.selectedDayName.textContent = `${dayName}, ${formattedDisplay}`;
                    this.dateError.style.display = 'none';
                    this.dateSuccess.style.display = 'flex';
                    
                    console.log('✅ Date selected:', formattedDate, dayName);
                }
                
                formatDateForInput(date) {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                }
                
                formatDateForDisplay(date) {
                    const monthNames = [
                        'January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'
                    ];
                    return `${monthNames[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
                }
                
                isSameDate(date1, date2) {
                    return date1.getFullYear() === date2.getFullYear() &&
                           date1.getMonth() === date2.getMonth() &&
                           date1.getDate() === date2.getDate();
                }
            }
            
            // Initialize calendar
            const calendar = new DeliveryCalendar();
            
            // Form validation and submission
            const form = document.getElementById('checkout-form');
            const submitBtn = document.getElementById('main-submit-btn');
            
            if (form && submitBtn) {
                // Enhanced form validation
                function validateForm() {
                    const errors = [];
                    
                    // Required fields validation
                    const requiredFields = [
                        { name: 'delivery_address', label: 'Delivery address' },
                        { name: 'city', label: 'City' },
                        { name: 'zip_code', label: 'ZIP code' }
                    ];
                    
                    requiredFields.forEach(field => {
                        const input = document.querySelector(`input[name="${field.name}"]`);
                        if (!input || !input.value.trim()) {
                            errors.push(`Please enter ${field.label}`);
                        }
                    });
                    
                    // ZIP code format validation
                    const zipCode = document.querySelector('input[name="zip_code"]');
                    if (zipCode && zipCode.value && !/^\d{5}$/.test(zipCode.value.trim())) {
                        errors.push('ZIP code must be 5 digits');
                    }
                    
                    // Payment method validation
                    const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
                    if (!paymentMethod) {
                        errors.push('Please select a payment method');
                    }
                    
                    // Delivery date validation
                    const deliveryDate = document.querySelector('input[name="delivery_day"]');
                    if (!deliveryDate || !deliveryDate.value) {
                        errors.push('Please select a delivery date');
                    } else {
                        const selectedDate = new Date(deliveryDate.value);
                        const dayOfWeek = selectedDate.getDay();
                        if (dayOfWeek !== 3 && dayOfWeek !== 6) {
                            errors.push('Please select a Wednesday or Saturday for delivery');
                        }
                        
                        // Check if date is not in the past
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        selectedDate.setHours(0, 0, 0, 0);
                        if (selectedDate < today) {
                            errors.push('Please select a future date for delivery');
                        }
                    }
                    
                    return errors;
                }
                
                // Form submission handler
                form.addEventListener('submit', function(e) {
                    console.log('🚀 Form submission started');
                    
                    const errors = validateForm();
                    
                    if (errors.length > 0) {
                        console.log('❌ Validation failed:', errors);
                        alert('Please fix the following issues:\n\n' + errors.join('\n'));
                        e.preventDefault();
                        return false;
                    }
                    
                    // Prevent double submission
                    if (submitBtn.disabled) {
                        console.log('⚠️ Double submission prevented');
                        e.preventDefault();
                        return false;
                    }
                    
                    console.log('✅ Validation passed, submitting...');
                    
                    // Update button state
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Payment...';
                    submitBtn.disabled = true;
                    
                    // Add timestamp to prevent double submission
                    const timestamp = document.createElement('input');
                    timestamp.type = 'hidden';
                    timestamp.name = 'submit_timestamp';
                    timestamp.value = Date.now();
                    form.appendChild(timestamp);
                    
                    return true;
                });
                
                // Payment method selection enhancement
                const paymentOptions = document.querySelectorAll('.payment-methods label');
                paymentOptions.forEach(label => {
                    label.addEventListener('click', function() {
                        const radio = this.querySelector('input[type="radio"]');
                        console.log('💳 Payment method selected:', radio.value);
                    });
                });
                
                // Auto-fill demo data for testing (remove in production)
                if (window.location.search.includes('demo=1')) {
                    setTimeout(() => {
                        // Auto-select first payment method
                        const firstPayment = document.querySelector('input[name="payment_method"]');
                        if (firstPayment) firstPayment.checked = true;
                        
                        // Auto-select the first available delivery date
                        const firstAvailableDay = document.querySelector('.calendar-day.available');
                        if (firstAvailableDay) {
                            firstAvailableDay.click();
                        }
                        
                        console.log('🧪 Demo data auto-filled');
                    }, 1000);
                }
                
                // Real-time validation feedback
                const inputs = form.querySelectorAll('input[required], select[required]');
                inputs.forEach(input => {
                    input.addEventListener('blur', function() {
                        if (this.value.trim()) {
                            this.style.borderColor = 'var(--success)';
                        } else {
                            this.style.borderColor = 'var(--danger)';
                        }
                    });
                    
                    input.addEventListener('input', function() {
                        if (this.style.borderColor === 'rgb(231, 76, 60)') {
                            this.style.borderColor = 'var(--border-light)';
                        }
                    });
                });
                
                console.log('✅ Enhanced form handlers attached');
                
            } else {
                console.error('❌ Form or submit button not found');
            }
        });
        
        // Global error handler
        window.addEventListener('error', function(e) {
            console.error('❌ JavaScript error:', e.error);
            
            // Re-enable submit button if there's an error
            const submitBtn = document.getElementById('main-submit-btn');
            if (submitBtn && submitBtn.disabled) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-lock"></i> Confirm and Pay';
            }
        });
        
        // Handle page unload during form submission
        let formSubmitting = false;
        document.getElementById('checkout-form')?.addEventListener('submit', () => {
            formSubmitting = true;
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (formSubmitting) {
                e.preventDefault();
                e.returnValue = '';
                return 'Your order is being processed. Please wait...';
            }
        });
    </script>
</body>
</html>