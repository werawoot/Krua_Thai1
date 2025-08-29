<?php
/**
 * Somdul Table - Subscription Status Page
 * File: subscription-status.php
 * Description: Track order status and manage subscriptions
 * UPDATED: Removed duplicate status column, keeping only user status
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'includes/email_functions.php'; // ←← เพิ่มบรรทัดนี้
require_once 'NotificationManager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// ===== ORDER STATUS TRACKING FUNCTIONS =====

function getOrderStatusForSubscription($subscription_id, $pdo) {
    // หาสถานะจาก orders table ก่อน
    $stmt = $pdo->prepare("
        SELECT o.status, o.kitchen_status, o.delivery_date
        FROM orders o
        WHERE o.subscription_id = ? 
        AND o.delivery_date >= CURDATE()
        ORDER BY o.delivery_date ASC
        LIMIT 1
    ");
    $stmt->execute([$subscription_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        return mapOrderStatusToCustomer($order['status']);
    } else {
        return getStatusFromSubscriptionMenus($subscription_id, $pdo);
    }
}

function mapOrderStatusToCustomer($order_status) {
    $mapping = [
        'pending' => 'order_received',
        'confirmed' => 'order_received',
        'preparing' => 'in_kitchen', 
        'ready' => 'in_kitchen',
        'out_for_delivery' => 'delivering',
        'delivered' => 'completed',
        'cancelled' => 'cancelled'
    ];
    return $mapping[$order_status] ?? 'order_received';
}

function getStatusFromSubscriptionMenus($subscription_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered,
            COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled
        FROM subscription_menus 
        WHERE subscription_id = ? AND delivery_date >= CURDATE()
    ");
    $stmt->execute([$subscription_id]);
    $result = $stmt->fetch();
    
    if (!$result || $result['total'] == 0) {
        return 'order_received';
    }
    
    if ($result['delivered'] == $result['total'] && $result['total'] > 0) {
        return 'completed';
    } elseif ($result['delivered'] > 0) {
        return 'delivering';
    } elseif ($result['scheduled'] < $result['total']) {
        return 'in_kitchen';
    } else {
        return 'order_received';
    }
}

function getOrderStatusDisplay($status) {
    $displays = [
        'order_received' => [
            'icon' => '📋',
            'label' => 'Order Received',
            'color' => '#3498db',
            'description' => 'Your order has been received and confirmed'
        ],
        'in_kitchen' => [
            'icon' => '👨‍🍳',
            'label' => 'In the Kitchen', 
            'color' => '#f39c12',
            'description' => 'Our chefs are preparing your meals'
        ],
        'delivering' => [
            'icon' => '🚚',
            'label' => 'Delivering',
            'color' => '#9b59b6', 
            'description' => 'Your order is on the way'
        ],
        'completed' => [
            'icon' => '✅',
            'label' => 'Completed',
            'color' => '#27ae60',
            'description' => 'Order delivered successfully'
        ],
        'cancelled' => [
            'icon' => '❌',
            'label' => 'Cancelled',
            'color' => '#e74c3c',
            'description' => 'Order has been cancelled'
        ]
    ];
    return $displays[$status] ?? $displays['order_received'];
}

// เพิ่มฟังก์ชันตรวจสอบรีวิวที่มีอยู่แล้ว
function hasExistingReview($pdo, $user_id, $subscription_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM reviews r
        JOIN orders o ON r.order_id = o.id
        WHERE r.user_id = ? AND o.subscription_id = ?
    ");
    $stmt->execute([$user_id, $subscription_id]);
    return $stmt->fetchColumn() > 0;
}

// ✅ NEW: Function to get delivery photo for subscription
function getDeliveryPhoto($pdo, $subscription_id) {
    // 🔧 FIX: Cast both IDs as CHAR to ensure proper UUID matching
    $stmt = $pdo->prepare("
        SELECT photo_path, notes, updated_at, rider_id
        FROM delivery_status 
        WHERE CAST(subscription_id as CHAR) = CAST(? as CHAR) 
        AND photo_path IS NOT NULL 
        AND photo_path != ''
        ORDER BY updated_at DESC
        LIMIT 1
    ");
    $stmt->execute([$subscription_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// --- Review Submission Function (เวอร์ชันสมบูรณ์) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    $subscription_id = $_POST['subscription_id'];
    $rating = $_POST['rating'];
    $title = $_POST['title'];
    $comment = $_POST['comment'];
    
    try {
        // ✅ เพิ่มการตรวจสอบว่ามีรีวิวแล้วหรือไม่ (เพิ่มตรงนี้)
        if (hasExistingReview($pdo, $user_id, $subscription_id)) {
            $_SESSION['flash_message'] = "คุณได้ทำการรีวิวแพ็คเกจนี้แล้ว";
            $_SESSION['flash_type'] = 'error';
            header("Location: subscription-status.php");
            exit();
        }
        // หา order_id จาก subscription_id
        $stmt = $pdo->prepare("
            SELECT o.id as order_id 
            FROM orders o
            WHERE o.subscription_id = ? 
            ORDER BY o.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$subscription_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            // ดึงข้อมูลผู้ใช้สำหรับที่อยู่
            $stmt = $pdo->prepare("
                SELECT delivery_address, city, zip_code, phone
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // ถ้าไม่มี order ให้สร้างใหม่พร้อมข้อมูลครบถ้วน
            $order_id = bin2hex(random_bytes(16));
            
            // สร้าง order_number (format: ORD-YYYYMMDD-XXXX)
            $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(md5($order_id), 0, 4));
            
            // กำหนด delivery_date (วันถัดไป)
            $delivery_date = date('Y-m-d', strtotime('+1 day'));
            
            // จัดการที่อยู่
            $delivery_address = $user['delivery_address'] ?? 'No address provided';
            $delivery_city = $user['city'] ?? 'Bangkok';
            $delivery_zip = $user['zip_code'] ?? '10100';
            $customer_phone = $user['phone'] ?? '';
            
            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    id, subscription_id, user_id, order_number, 
                    delivery_date, delivery_address, status, 
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'confirmed', NOW(), NOW())
            ");
            $stmt->execute([
                $order_id, 
                $subscription_id, 
                $user_id, 
                $order_number,
                $delivery_date,
                $delivery_address
            ]);
        } else {
            $order_id = $order['order_id'];
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO reviews (
                id, user_id, order_id, overall_rating, title, comment, 
                moderation_status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
        ");
        
        $review_id = bin2hex(random_bytes(16));
        $result = $stmt->execute([
            $review_id, 
            $user_id, 
            $order_id,
            $rating, 
            $title, 
            $comment
        ]);
        
        if ($result) {
            $_SESSION['flash_message'] = "Thank you for your review! It will be published after approval.";
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = "Failed to submit review. Please try again.";
            $_SESSION['flash_type'] = 'error';
        }
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = "Error submitting review: " . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
    
    header("Location: subscription-status.php");
    exit();
}

// --- Complaint Submission Function ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complain') {
    $subscription_id = $_POST['subscription_id'];
    $category = $_POST['category'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $priority = $_POST['priority'] ?? 'medium';
    
    // Generate unique complaint ID and number
    $complaint_id = bin2hex(random_bytes(16));
    $complaint_number = 'CMP-' . date('Ymd') . '-' . strtoupper(substr(md5($complaint_id), 0, 4));
    
    try {
        // Insert complaint with subscription_id (column was renamed from order_id)
        $stmt = $pdo->prepare("
            INSERT INTO complaints (
                id, complaint_number, user_id, subscription_id, category, 
                priority, title, description, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $result = $stmt->execute([
            $complaint_id, 
            $complaint_number, 
            $user_id, 
            $subscription_id, 
            $category, 
            $priority, 
            $title, 
            $description
        ]);
        
       if ($result) {
    // ส่ง email ยืนยันให้ลูกค้า
    sendComplaintConfirmationEmail($pdo, $user_id, $complaint_number, $_POST);
    
    // ส่ง email แจ้งเตือน admin
    sendAdminNotificationEmail($pdo, $user_id, $complaint_number, $_POST);
    
    $_SESSION['flash_message'] = "
        <div style='background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; padding: 2rem; border-radius: 15px; text-align: center; margin-bottom: 2rem; box-shadow: 0 8px 25px rgba(39, 174, 96, 0.3);'>
            <i class='fas fa-check-circle' style='font-size: 3rem; margin-bottom: 1rem;'></i>
            <h3 style='margin: 0 0 1rem 0; font-size: 1.5rem;'>แจ้งปัญหาเรียบร้อยแล้ว!</h3>
            <div style='background: rgba(255,255,255,0.2); padding: 1rem; border-radius: 10px; margin: 1rem 0;'>
                <p style='margin: 0; font-size: 1.1rem; font-weight: bold;'>รหัสอ้างอิง: #{$complaint_number}</p>
            </div>
            <div style='text-align: left; margin-top: 1.5rem;'>
                <p style='margin: 0.5rem 0;'><i class='fas fa-envelope'></i> เราจะติดต่อกลับท่านผ่านทางอีเมล ภายใน 4 ชั่วโมง</p>
                <p style='margin: 0.5rem 0;'><i class='fas fa-lightbulb'></i> ท่านสามารถตอบกลับอีเมลจากเราได้โดยตรง ไม่ต้องเข้าเว็บไซต์อีกครั้ง</p>
                <p style='margin: 0.5rem 0;'><i class='fas fa-phone'></i> กรณีเร่งด่วน: โทร (02) 123-4567</p>
            </div>
        </div>
    ";
    $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = "Failed to submit complaint. Please try again.";
            $_SESSION['flash_type'] = 'error';
        }
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = "Error submitting complaint: " . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
    
    header("Location: subscription-status.php");
    exit();
}

// --- Status Update Function (Remove pause, keep cancel and renew) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id = $_POST['id'];
    $action = $_POST['action'];
    $status_map = [
        'cancel' => 'cancelled',
        'renew' => 'active'
    ];
    
    if (isset($status_map[$action])) {
        $new_status = $status_map[$action];
        
        // Get subscription details before updating for notification
        $subscriptionDetails = null;
        if ($action === 'cancel') {
            $stmt = $pdo->prepare("
                SELECT s.*, sp.name AS plan_name, sp.meals_per_week 
                FROM subscriptions s 
                JOIN subscription_plans sp ON s.plan_id = sp.id
                WHERE s.id = ? AND s.user_id = ?
            ");
            $stmt->execute([$id, $user_id]);
            $subscriptionDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Update subscription status
        $stmt = $pdo->prepare("UPDATE subscriptions SET status = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $result = $stmt->execute([$new_status, $id, $user_id]);
        
        // CREATE CANCELLATION NOTIFICATION
        if ($result && $action === 'cancel' && $subscriptionDetails) {
            try {
                $notificationManager = new NotificationManager($pdo);
                
                // Create system notification for cancellation
                $notificationManager->createSystemNotification(
                    $user_id,
                    'Order Cancelled',
                    "Your order for '{$subscriptionDetails['plan_name']}' has been cancelled successfully. " .
                    "",
                    'medium' // Medium priority for cancellations
                );
                
                error_log("Cancellation notification created for user: $user_id, subscription: $id");
                
            } catch (Exception $e) {
                error_log("Failed to create cancellation notification: " . $e->getMessage());
                // Don't stop the cancellation process if notification fails
            }
        }
        
        header("Location: subscription-status.php");
        exit();
    }
}

// --- Fetch Subscription Data ---
$stmt = $pdo->prepare("
    SELECT s.*, sp.name AS plan_name, sp.meals_per_week, sp.final_price, sp.plan_type
    FROM subscriptions s 
    JOIN subscription_plans sp ON s.plan_id = sp.id
    WHERE s.user_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$user_id]);
$subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// เพิ่มการตรวจสอบรีวิวและ order status สำหรับแต่ละ subscription
foreach ($subs as &$sub) {
    $sub['has_review'] = hasExistingReview($pdo, $user_id, $sub['id']);
    $sub['order_status'] = getOrderStatusForSubscription($sub['id'], $pdo);
    $sub['status_display'] = getOrderStatusDisplay($sub['order_status']);
    
    // ✅ NEW: Get delivery photo if subscription is delivered
    if ($sub['status'] === 'delivered') {
        $sub['delivery_photo'] = getDeliveryPhoto($pdo, $sub['id']);
    }
}

// --- Fetch selected menus for each subscription ---
$subscription_menus = [];
foreach ($subs as $sub) {
    $stmt = $pdo->prepare("
        SELECT sm.*, m.name as menu_name, m.name_thai, m.base_price, m.main_image_url, 
               m.calories_per_serving, m.protein_g, m.carbs_g, m.fat_g,
               mc.name as category_name
        FROM subscription_menus sm
        JOIN menus m ON sm.menu_id = m.id
        LEFT JOIN menu_categories mc ON m.category_id = mc.id
        WHERE sm.subscription_id = ?
        ORDER BY sm.delivery_date, sm.created_at
    ");
    $stmt->execute([$sub['id']]);
    $subscription_menus[$sub['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ✅ UPDATED: Enhanced getStatusText function with delivered status
function getStatusText($status) {
    $map = [
        'active' => 'Active',
        'paused' => 'Paused',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
        'pending_payment' => 'Pending Payment',
        'delivered' => 'Delivered' // ✅ NEW: Added delivered status
    ];
    return $map[$status] ?? $status;
}

function getDayName($day) {
    $days = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday'
    ];
    return $days[$day] ?? $day;
}

// Email Functions
require_once 'includes/email_functions.php';

function sendComplaintConfirmationEmail($pdo, $userId, $complaintNumber, $complaintData) {
    // Get user info
    $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) return false;
    
    $customerName = $user['first_name'] . ' ' . $user['last_name'];
    $customerEmail = $user['email'];
    
    $subject = "[Somdul Table] รับแจ้งปัญหา #{$complaintNumber}";
    
    $emailBody = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; background: #fff; }
            .header { background: linear-gradient(135deg, #cf723a, #e67e22); color: white; padding: 30px 20px; text-align: center; }
            .content { padding: 30px 20px; }
            .complaint-box { background: #f8f9fa; border-left: 5px solid #cf723a; padding: 20px; margin: 20px 0; border-radius: 5px; }
            .info-box { background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 20px; border-radius: 10px; margin: 20px 0; }
            .footer { background: #ece8e1; padding: 20px; text-align: center; color: #666; }
            .highlight { color: #cf723a; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin: 0; font-size: 2rem;'>🍽️ Somdul Table</h1>
                <p style='margin: 10px 0 0 0; font-size: 1.2rem;'>ยืนยันการรับแจ้งปัญหา</p>
            </div>
            
            <div class='content'>
                <h2>เรียน คุณ {$customerName}</h2>
                
                <p>ขอบคุณที่แจ้งปัญหาให้เราทราบ เราได้รับเรื่องของท่านแล้วและจะดำเนินการแก้ไขโดยเร็วที่สุด</p>
                
                <div class='complaint-box'>
                    <h3 style='margin-top: 0; color: #cf723a;'>📋 รายละเอียดการแจ้งปัญหา</h3>
                    <p><strong>รหัสอ้างอิง:</strong> <span class='highlight'>#{$complaintNumber}</span></p>
                    <p><strong>หัวข้อ:</strong> {$complaintData['title']}</p>
                    <p><strong>ประเภท:</strong> " . ucfirst(str_replace('_', ' ', $complaintData['category'])) . "</p>
                    <p><strong>ระดับความสำคัญ:</strong> " . ucfirst($complaintData['priority']) . "</p>
                    <p><strong>วันที่แจ้ง:</strong> " . date('d/m/Y H:i น.') . "</p>
                </div>
                
                <div class='info-box'>
                    <h3 style='margin-top: 0;'>⏰ ขั้นตอนต่อไป</h3>
                    <p style='margin: 10px 0;'>✅ ทีมงานจะตรวจสอบและติดต่อกลับภายใน <strong>4 ชั่วโมง</strong></p>
                    <p style='margin: 10px 0;'>✅ ท่านสามารถตอบกลับอีเมลนี้ได้โดยตรง ไม่ต้องเข้าเว็บไซต์อีกครั้ง</p>
                    <p style='margin: 10px 0;'>✅ กรณีเร่งด่วน: โทร <strong>(02) 123-4567</strong></p>
                </div>
                
                <p>เราขออภัยในความไม่สะดวก และขอบคุณที่ให้โอกาสเราปรับปรุงการบริการให้ดียิ่งขึ้น</p>
                
                <p>ด้วยความห่วงใย<br>
                <strong>Somdul Table Support Team</strong></p>
            </div>
            
            <div class='footer'>
                <p><strong>Somdul Table - Authentic Thai Meals, Made Healthy</strong></p>
                <p>📧 support@somdultable.com | 📞 (02) 123-4567</p>
                <p style='font-size: 0.9em; margin-top: 15px;'>
                    อีเมลนี้ส่งเกี่ยวกับการแจ้งปัญหา #{$complaintNumber}
                </p>
            </div>
        </div>
    </body>
    </html>";
    
    return sendRealEmail($customerEmail, $subject, $emailBody);
}

function sendAdminNotificationEmail($pdo, $userId, $complaintNumber, $complaintData) {
    // Get customer info
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) return false;
    
    $customerName = $customer['first_name'] . ' ' . $customer['last_name'];
    $adminEmail = 'admin@kruathai.com';
    
    $subject = "[🚨 URGENT] New Complaint #{$complaintNumber} - " . ucfirst(str_replace('_', ' ', $complaintData['category']));
    
    $priorityColor = $complaintData['priority'] === 'critical' ? '#c0392b' : 
                    ($complaintData['priority'] === 'high' ? '#e74c3c' : '#f39c12');
    
    $emailBody = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 700px; margin: 0 auto; background: #fff; }
            .header { background: linear-gradient(135deg, #cf723a, #e67e22); color: white; padding: 25px; text-align: center; }
            .priority-{$complaintData['priority']} { border-left: 5px solid {$priorityColor}; padding: 15px; margin: 20px 0; }
            .customer-info { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 15px 0; }
            .complaint-details { background: #e8f4f8; padding: 20px; border-radius: 8px; margin: 15px 0; }
            .action-button { background: #cf723a; color: white; padding: 12px 25px; text-decoration: none; border-radius: 6px; display: inline-block; margin: 10px 0; }
            .footer { background: #f1f1f1; padding: 20px; text-align: center; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin: 0;'>🚨 New Customer Complaint Alert</h1>
                <p style='margin: 10px 0 0 0; font-size: 1.1rem;'>Requires Immediate Attention</p>
            </div>
            
            <div style='padding: 25px;'>
                <div class='priority-{$complaintData['priority']}'>
                    <h2 style='margin: 0 0 10px 0; color: {$priorityColor};'>Complaint #{$complaintNumber}</h2>
                    <p style='margin: 0; font-size: 1.1rem;'><strong>Priority:</strong> " . strtoupper($complaintData['priority']) . "</p>
                </div>
                
                <div class='customer-info'>
                    <h3 style='margin: 0 0 15px 0; color: #cf723a;'>👤 Customer Information</h3>
                    <p><strong>Name:</strong> {$customerName}</p>
                    <p><strong>Email:</strong> {$customer['email']}</p>
                    <p><strong>Phone:</strong> " . ($customer['phone'] ?: 'Not provided') . "</p>
                </div>
                
                <div class='complaint-details'>
                    <h3 style='margin: 0 0 15px 0; color: #2980b9;'>📋 Complaint Details</h3>
                    <p><strong>Category:</strong> " . ucfirst(str_replace('_', ' ', $complaintData['category'])) . "</p>
                    <p><strong>Subject:</strong> {$complaintData['title']}</p>
                    <p><strong>Description:</strong></p>
                    <div style='background: white; padding: 15px; border-radius: 5px; margin-top: 10px;'>
                        " . nl2br(htmlspecialchars($complaintData['description'])) . "
                    </div>
                    <p><strong>Submitted:</strong> " . date('d/m/Y H:i น.') . "</p>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='https://somdultable.com/admin/complaints.php' class='action-button'>
                        📝 Respond to Complaint Now
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>";
    
    return sendRealEmail($adminEmail, $subject, $emailBody);
}

// Include the header (contains navbar, promo banner, fonts, and base styles)
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status - Somdul Table</title>
    <meta name="description" content="Track your Thai meal orders with Somdul Table">
    
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
    /* PAGE-SPECIFIC STYLES ONLY - header styles come from header.php */
    
    /* Container */
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .main-content {
        padding-top: 2rem;
        min-height: calc(100vh - 200px);
    }

    /* Page Title */
    .page-title {
        font-size: 2.5rem;
        font-weight: 700;
        font-family: 'BaticaSans', sans-serif;
        text-align: center;
        margin-bottom: 2rem;
        color: var(--brown);
    }

    .page-title i {
        color: var(--curry);
        margin-right: 0.5rem;
    }

    /* Flash Messages */
    .flash-message {
        padding: 1.5rem;
        margin-bottom: 2rem;
        border-radius: var(--radius-lg);
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        border: 2px solid;
        animation: slideIn 0.3s ease-out;
    }

    .flash-message i {
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .flash-message.success {
        background: linear-gradient(135deg, rgba(39, 174, 96, 0.1), rgba(39, 174, 96, 0.05));
        border-color: #27ae60;
        color: #27ae60;
    }

    .flash-message.error {
        background: linear-gradient(135deg, rgba(231, 76, 60, 0.1), rgba(231, 76, 60, 0.05));
        border-color: #e74c3c;
        color: #e74c3c;
    }

    /* Main Content Card */
    .main-card {
        background: var(--white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-medium);
        overflow: hidden;
        position: relative;
        border: 1px solid var(--border-light);
    }

    .main-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--curry), var(--brown), var(--sage));
    }

    .card-header {
        padding: 2rem;
        border-bottom: 1px solid var(--border-light);
        background: linear-gradient(135deg, rgba(207, 114, 58, 0.05), rgba(189, 147, 121, 0.05));
    }

    .card-title {
        font-size: 1.5rem;
        font-weight: 700;
        font-family: 'BaticaSans', sans-serif;
        color: var(--brown);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .card-title i {
        color: var(--curry);
    }

    /* Table Styles */
    .table-container {
        overflow-x: auto;
        position: relative;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-family: 'BaticaSans', sans-serif;
        min-width: 800px;
    }

    th, td {
        padding: 1.2rem;
        text-align: left;
        border-bottom: 1px solid var(--border-light);
        vertical-align: middle;
    }

    th {
        background: var(--cream);
        color: var(--brown);
        font-weight: 700;
        font-size: 0.9rem;
        font-family: 'BaticaSans', sans-serif;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    td {
        color: var(--text-dark);
        font-weight: 500;
        font-family: 'BaticaSans', sans-serif;
    }

    tbody tr {
        transition: var(--transition);
    }

    tbody tr:hover {
        background: rgba(189, 147, 121, 0.02);
    }

    /* Status Badges */
    .status {
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        padding: 0.5rem 1rem;
        border-radius: var(--radius-lg);
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }

    .status.active {
        background: linear-gradient(135deg, rgba(39, 174, 96, 0.1), rgba(39, 174, 96, 0.05));
        color: #27ae60;
        border: 1px solid #27ae60;
    }

    .status.paused {
        background: linear-gradient(135deg, rgba(243, 156, 18, 0.1), rgba(243, 156, 18, 0.05));
        color: #f39c12;
        border: 1px solid #f39c12;
    }

    .status.cancelled {
        background: linear-gradient(135deg, rgba(231, 76, 60, 0.1), rgba(231, 76, 60, 0.05));
        color: #e74c3c;
        border: 1px solid #e74c3c;
    }

    .status.expired {
        background: linear-gradient(135deg, rgba(127, 140, 141, 0.1), rgba(127, 140, 141, 0.05));
        color: var(--text-gray);
        border: 1px solid var(--text-gray);
    }

    .status.pending_payment {
        background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(52, 152, 219, 0.05));
        color: #3498db;
        border: 1px solid #3498db;
    }

    /* ✅ NEW: Delivered status styling */
    .status.delivered {
        background: linear-gradient(135deg, rgba(111, 66, 193, 0.1), rgba(111, 66, 193, 0.05));
        color: #6f42c1;
        border: 1px solid #6f42c1;
    }

    /* Additional user_status values that might exist */
    .status.completed {
        background: linear-gradient(135deg, rgba(39, 174, 96, 0.1), rgba(39, 174, 96, 0.05));
        color: #27ae60;
        border: 1px solid #27ae60;
    }

    .status.processing {
        background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(52, 152, 219, 0.05));
        color: #3498db;
        border: 1px solid #3498db;
    }

    .status.on_hold {
        background: linear-gradient(135deg, rgba(243, 156, 18, 0.1), rgba(243, 156, 18, 0.05));
        color: #f39c12;
        border: 1px solid #f39c12;
    }

    /* Action Buttons */
    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        min-width: 200px;
    }

    .btn-action {
        background: var(--white);
        border: 1px solid var(--border-light);
        padding: 0.7rem 1.2rem;
        border-radius: var(--radius-md);
        font-size: 0.85rem;
        font-weight: 500;
        font-family: 'BaticaSans', sans-serif;
        cursor: pointer;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        text-align: center;
        width: 100%;
        color: var(--text-dark);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        touch-action: manipulation;
        min-height: 44px;
        text-decoration: none;
    }

    .btn-action:hover {
        background: var(--cream);
        border-color: var(--brown);
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    }

    .btn-action:active {
        transform: translateY(0) scale(0.98);
    }

    .btn-view {
        border-color: var(--sage);
        color: var(--sage);
    }

    .btn-view:hover {
        background: var(--sage);
        color: var(--white);
        border-color: var(--sage);
    }

    .btn-complain {
        border-color: #f39c12;
        color: #f39c12;
    }

    .btn-complain:hover {
        background: #f39c12;
        color: var(--white);
        border-color: #f39c12;
    }

    .btn-cancel {
        border-color: var(--brown);
        color: var(--brown);
    }

    .btn-cancel:hover {
        background: var(--brown);
        color: var(--white);
        border-color: var(--brown);
    }

    .btn-review {
        background: linear-gradient(135deg, #f39c12, #e67e22);
        color: white;
        border-color: #f39c12;
    }

    .btn-review:hover {
        background: linear-gradient(135deg, #e67e22, #d35400);
        transform: translateY(-1px);
        border-color: #e67e22;
    }

    /* ✅ NEW: View Photo button styling */
    .btn-view-photo {
        background: linear-gradient(135deg, #6f42c1, #5a32a3);
        color: white;
        border-color: #6f42c1;
    }

    .btn-view-photo:hover {
        background: linear-gradient(135deg, #5a32a3, #4c2a91);
        transform: translateY(-1px);
        border-color: #5a32a3;
    }

    .btn-disabled {
        background: var(--cream);
        border-color: var(--border-light);
        color: var(--text-gray);
        cursor: not-allowed;
        opacity: 0.6;
    }

    .btn-action:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 2000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        backdrop-filter: blur(5px);
    }

    .modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .modal-content {
        background: var(--white);
        border-radius: var(--radius-lg);
        padding: 0;
        width: 90%;
        max-width: 800px;
        max-height: 85vh;
        overflow: hidden;
        box-shadow: var(--shadow-medium);
        position: relative;
        transform: scale(0.8);
        opacity: 0;
        transition: var(--transition);
        border: 1px solid var(--border-light);
    }

    .modal.show .modal-content {
        transform: scale(1);
        opacity: 1;
    }

    .modal-header {
        background: linear-gradient(135deg, var(--curry), var(--brown));
        color: var(--white);
        padding: 1.5rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
    }

    .modal-title {
        font-size: 1.3rem;
        font-weight: 700;
        font-family: 'BaticaSans', sans-serif;
        margin: 0;
    }

    .modal-close {
        background: none;
        border: none;
        color: var(--white);
        font-size: 1.5rem;
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 50%;
        transition: var(--transition);
        min-width: 44px;
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        touch-action: manipulation;
    }

    .modal-close:hover {
        background: rgba(255,255,255,0.2);
        transform: rotate(90deg);
    }

    .modal-body {
        padding: 2rem;
        max-height: 600px;
        overflow-y: auto;
        font-family: 'BaticaSans', sans-serif;
    }

    /* ✅ NEW: Photo modal styles */
    .photo-modal-content {
        max-width: 90%;
        max-height: 90%;
        background: var(--white);
        border-radius: var(--radius-lg);
        overflow: hidden;
        position: relative;
    }

    .photo-display {
        text-align: center;
        padding: 2rem;
    }

    .delivery-photo-large {
        max-width: 100%;
        max-height: 70vh;
        object-fit: contain;
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-medium);
    }

    .photo-info {
        margin-top: 1rem;
        padding: 1rem;
        background: var(--cream);
        border-radius: var(--radius-md);
        text-align: left;
    }

    .photo-info-item {
        margin-bottom: 0.5rem;
        color: var(--text-gray);
        font-size: 0.9rem;
    }

    .photo-info-item strong {
        color: var(--text-dark);
    }

    /* Order Progress Bar */
    .order-progress-bar {
        margin: 1rem 0;
    }

    .progress-steps {
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        max-width: 600px;
        margin: 0 auto;
    }

    .progress-step-modal {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        position: relative;
        z-index: 2;
        background: var(--white);
        padding: 0.5rem;
        border-radius: var(--radius-md);
        min-width: 80px;
    }

    .step-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
        border: 3px solid var(--border-light);
        background: var(--cream);
        color: var(--text-gray);
        transition: var(--transition);
    }

    .step-label {
        font-size: 0.8rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        color: var(--text-gray);
        white-space: nowrap;
    }

    .progress-step-modal.completed .step-icon {
        background: #27ae60;
        border-color: #27ae60;
        color: var(--white);
    }

    .progress-step-modal.completed .step-label {
        color: #27ae60;
    }

    .progress-step-modal.active .step-icon {
        background: var(--curry);
        border-color: var(--curry);
        color: var(--white);
        animation: pulse 2s infinite;
    }

    .progress-step-modal.active .step-label {
        color: var(--curry);
    }

    .progress-line {
        position: absolute;
        top: 35px;
        left: 25%;
        right: 25%;
        height: 3px;
        background: var(--border-light);
        z-index: 1;
    }

    .progress-line.completed {
        background: #27ae60;
    }

    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }

    /* Form Styles */
    .complaint-form, .review-form {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-label {
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        color: var(--text-dark);
        font-size: 0.9rem;
    }

    .form-control {
        padding: 0.8rem;
        border: 1px solid var(--border-light);
        border-radius: var(--radius-md);
        font-family: 'BaticaSans', sans-serif;
        font-size: 0.9rem;
        transition: var(--transition);
        background: var(--white);
        color: var(--text-dark);
        touch-action: manipulation;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--brown);
        box-shadow: 0 0 0 2px rgba(189, 147, 121, 0.1);
    }

    .form-control-textarea {
        resize: vertical;
        min-height: 100px;
    }

    .form-buttons {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 1rem;
    }

    .btn-submit {
        background: var(--brown);
        color: var(--white);
        border: none;
        padding: 0.8rem 1.5rem;
        border-radius: var(--radius-md);
        font-family: 'BaticaSans', sans-serif;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        touch-action: manipulation;
        min-height: 48px;
    }

    .btn-submit:hover {
        background: #a8855f;
        transform: translateY(-1px);
    }

    .btn-cancel-form {
        background: var(--cream);
        color: var(--text-dark);
        border: 1px solid var(--border-light);
        padding: 0.8rem 1.5rem;
        border-radius: var(--radius-md);
        font-family: 'BaticaSans', sans-serif;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        touch-action: manipulation;
        min-height: 48px;
    }

    .btn-cancel-form:hover {
        background: var(--border-light);
    }

    /* Star Rating */
    .rating-section {
        text-align: center;
        padding: 1.5rem;
        background: linear-gradient(135deg, rgba(189, 147, 121, 0.05), rgba(173, 184, 157, 0.05));
        border-radius: var(--radius-md);
        border: 1px solid var(--border-light);
    }

    .rating-title {
        font-size: 1.2rem;
        font-weight: 700;
        font-family: 'BaticaSans', sans-serif;
        color: var(--brown);
        margin-bottom: 1rem;
    }

    .star-rating {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .star {
        font-size: 2rem;
        color: #ddd;
        cursor: pointer;
        transition: all 0.3s ease;
        padding: 0.3rem;
        border-radius: 50%;
        touch-action: manipulation;
        min-width: 44px;
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .star:hover,
    .star.active {
        color: #ffd700;
        transform: scale(1.1);
    }

    .star:hover {
        background: rgba(255, 215, 0, 0.1);
    }

    .rating-text {
        font-size: 1rem;
        font-weight: 600;
        color: var(--brown);
        margin-top: 0.5rem;
        min-height: 1.5rem;
    }

    .review-tips {
        background: var(--cream);
        padding: 1rem;
        border-radius: var(--radius-sm);
        border-left: 4px solid var(--brown);
        font-size: 0.85rem;
        color: var(--text-gray);
        line-height: 1.5;
    }

    .review-tips strong {
        color: var(--text-dark);
        display: block;
        margin-bottom: 0.5rem;
    }

    /* Detail Sections */
    .detail-section {
        margin-bottom: 2rem;
    }

    .detail-title {
        font-size: 1.1rem;
        font-weight: 700;
        font-family: 'BaticaSans', sans-serif;
        color: var(--brown);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .detail-item {
        background: var(--cream);
        padding: 1rem;
        border-radius: var(--radius-md);
        border: 1px solid var(--border-light);
    }

    .detail-label {
        font-size: 0.85rem;
        color: var(--text-gray);
        margin-bottom: 0.3rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-family: 'BaticaSans', sans-serif;
        font-weight: 600;
    }

    .detail-value {
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        color: var(--text-dark);
        line-height: 1.4;
    }

    .delivery-days-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }

    .delivery-day-tag {
        background: var(--brown);
        color: var(--white);
        padding: 0.3rem 0.8rem;
        border-radius: var(--radius-lg);
        font-size: 0.8rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
    }

    /* Menu Cards */
    .menus-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-top: 1rem;
    }

    .menu-card {
        background: var(--white);
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-soft);
        border: 1px solid var(--border-light);
        transition: var(--transition);
        position: relative;
    }

    .menu-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-medium);
    }

    .menu-image {
        width: 100%;
        height: 150px;
        object-fit: cover;
        background: linear-gradient(135deg, var(--cream), var(--sage));
    }

    .menu-content {
        padding: 1rem;
    }

    .menu-name {
        font-weight: 700;
        font-family: 'BaticaSans', sans-serif;
        color: var(--text-dark);
        margin-bottom: 0.3rem;
        font-size: 1rem;
    }

    .menu-name-thai {
        color: var(--text-gray);
        font-size: 0.9rem;
        font-family: 'BaticaSans', sans-serif;
        margin-bottom: 0.5rem;
    }

    .menu-category {
        background: var(--sage);
        color: var(--white);
        padding: 0.2rem 0.6rem;
        border-radius: var(--radius-sm);
        font-size: 0.75rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        display: inline-block;
        margin-bottom: 0.5rem;
    }

    .menu-nutrition {
        display: flex;
        justify-content: space-between;
        font-size: 0.8rem;
        color: var(--text-gray);
        margin-bottom: 0.5rem;
        font-family: 'BaticaSans', sans-serif;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .menu-price {
        font-weight: 700;
        font-family: 'BaticaSans', sans-serif;
        color: var(--curry);
        font-size: 1.1rem;
    }

    .menu-day {
        background: var(--curry);
        color: var(--white);
        padding: 0.3rem 0.8rem;
        border-radius: var(--radius-lg);
        font-size: 0.8rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        position: absolute;
        top: 0.5rem;
        left: 0.5rem;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-gray);
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        opacity: 0.3;
        color: var(--sage);
    }

    .empty-state h3 {
        font-size: 1.5rem;
        margin-bottom: 1rem;
        color: var(--brown);
        font-family: 'BaticaSans', sans-serif;
        font-weight: 700;
    }

    .empty-state p {
        font-size: 1.1rem;
        margin-bottom: 2rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .empty-state .btn {
        background: var(--white);
        color: var(--brown);
        border: 2px solid var(--brown);
        padding: 1rem 2rem;
        border-radius: var(--radius-lg);
        text-decoration: none;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: var(--transition);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        touch-action: manipulation;
        min-height: 56px;
    }

    .empty-state .btn:hover {
        background: var(--brown);
        color: var(--white);
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(189, 147, 121, 0.2);
    }

    /* Navigation */
    .bottom-nav {
        padding: 2rem;
        border-top: 1px solid var(--border-light);
        background: var(--cream);
    }

    .back-link {
        color: var(--brown);
        text-decoration: none;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: var(--transition);
        touch-action: manipulation;
        padding: 0.5rem 1rem;
        border-radius: var(--radius-md);
    }

    .back-link:hover {
        color: var(--curry);
        transform: translateX(-3px);
        background: rgba(189, 147, 121, 0.1);
    }

    /* Utility Classes */
    .price {
        font-weight: 700;
        font-family: 'BaticaSans', sans-serif;
        color: var(--curry);
        font-size: 1.1rem;
    }

    .plan-name {
        font-weight: 700;
        font-family: 'BaticaSans', sans-serif;
        color: var(--brown);
    }

    /* Animations */
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

    /* Quantity Badge for Menu Cards */
    .menu-quantity {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background: var(--curry);
        color: var(--white);
        border-radius: 50%;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        z-index: 10;
        font-family: 'BaticaSans', sans-serif;
    }

    /* Quantity text in menu name */
    .menu-name .quantity-text {
        color: var(--curry);
        font-weight: 700;
        margin-left: 0.5rem;
        font-size: 0.95rem;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .page-title {
            font-size: 2rem;
            margin-bottom: 1.5rem;
        }

        .card-header {
            padding: 1.5rem 1rem;
        }

        .card-title {
            font-size: 1.3rem;
        }

        .table-container {
            font-size: 0.9rem;
            margin: 0 -1rem;
            padding: 0 1rem;
        }

        table {
            min-width: 650px;
        }

        th, td {
            padding: 0.8rem 0.5rem;
            font-size: 0.85rem;
        }

        .action-buttons {
            min-width: 180px;
            gap: 0.3rem;
        }

        .btn-action {
            padding: 0.6rem 0.8rem;
            font-size: 0.8rem;
            min-height: 44px;
        }

        .modal-content {
            width: 95%;
            max-height: 90vh;
            margin: 0.5rem;
        }

        .modal-header {
            padding: 1rem 1.5rem;
        }

        .modal-body {
            padding: 1.5rem 1rem;
            max-height: 70vh;
        }

        .detail-grid {
            grid-template-columns: 1fr;
            gap: 0.8rem;
        }

        .menus-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .form-buttons {
            flex-direction: column;
            gap: 0.8rem;
        }

        .btn-submit,
        .btn-cancel-form {
            width: 100%;
            padding: 1rem;
            min-height: 48px;
        }
    }

    @media (max-width: 480px) {
        .page-title {
            font-size: 1.6rem;
            margin-bottom: 1rem;
        }

        .card-header {
            padding: 1.2rem 0.8rem;
        }

        table {
            min-width: 580px;
        }

        th, td {
            padding: 0.6rem 0.3rem;
            font-size: 0.8rem;
        }

        .action-buttons {
            min-width: 160px;
        }

        .btn-action {
            padding: 0.5rem 0.6rem;
            font-size: 0.75rem;
        }

        .star {
            font-size: 1.6rem;
            min-width: 40px;
            min-height: 40px;
        }
    }
    </style>
</head>

<!-- IMPORTANT: Add has-header class for proper spacing -->
<body class="has-header">
    <!-- The header (promo banner + navbar) is already included from header.php -->

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Page Title -->
            <h1 class="page-title">
                <i class="fas fa-clipboard-list"></i>
                Order Status
            </h1>

            <!-- Flash Messages -->
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="flash-message <?php echo $_SESSION['flash_type']; ?>">
                    <i class="fas fa-<?php echo $_SESSION['flash_type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
                </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            <?php endif; ?>

            <!-- Main Content -->
            <div class="main-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-list-alt"></i>
                        Your Order Plans
                    </h2>
                </div>

                <?php if (empty($subs)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Order Plans Yet</h3>
                        <p>Start your healthy eating journey with authentic Thai food</p>
                        <a href="subscribe.php" class="btn">
                            <i class="fas fa-plus"></i>
                            Order Your First Plan
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th><i class="fas fa-box"></i> Plan</th>
                                    <th><i class="fas fa-utensils"></i> Meals</th>
                                    <th><i class="fas fa-money-bill"></i> Price</th>
                                    <th><i class="fas fa-info-circle"></i> Status</th>
                                    <th><i class="fas fa-calendar-alt"></i> Delivery Date</th>
                                    <th><i class="fas fa-cogs"></i> Manage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subs as $sub): ?>
                                <tr>
                                    <td>
                                        <span class="plan-name"><?= htmlspecialchars($sub['plan_name']) ?></span>
                                    </td>
                                    <td><?= $sub['meals_per_week'] ?> meals/week</td>
                                    <td>
                                        <span class="price">$<?= number_format($sub['final_price'], 2) ?></span>
                                        <span style="color: var(--text-gray); font-size: 0.9rem;">
                                            /<?= $sub['plan_type'] === 'weekly' ? 'week' : 'month' ?>
                                        </span>
                                    </td>
                                    <td>
    <span class="status <?= ($sub['user_status'] ?? $sub['status']) ?>"><?= getStatusText($sub['user_status'] ?? $sub['status']) ?></span>
                                        
                                        <!-- ✅ Show delivered timestamp if available - still checks status for actual delivery -->
                                        <?php if ($sub['status'] === 'delivered' && $sub['delivered_at']): ?>
                                            <small style="display: block; margin-top: 0.3rem; color: var(--text-gray);">
                                                <i class="fas fa-check-circle"></i> 
                                                <?= date('M j, Y \a\t g:i A', strtotime($sub['delivered_at'])) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($sub['start_date']) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <!-- View Details Button -->
                                            <button onclick="viewDetails('<?= htmlspecialchars($sub['id']) ?>')" class="btn-action btn-view">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>

                                            <!-- ✅ View Photo button for delivered orders - checks status = 'delivered' -->
                                            <?php if ($sub['status'] === 'delivered' && isset($sub['delivery_photo']) && $sub['delivery_photo']): ?>
                                                <button onclick="viewDeliveryPhoto('<?= htmlspecialchars($sub['delivery_photo']['photo_path']) ?>', '<?= htmlspecialchars($sub['plan_name']) ?>', '<?= htmlspecialchars($sub['delivery_photo']['notes'] ?? '') ?>', '<?= $sub['delivery_photo']['updated_at'] ?>')" class="btn-action btn-view-photo">
                                                    <i class="fas fa-camera"></i> View Photo
                                                </button>
                                            <?php endif; ?>

<?php if ((($sub['user_status'] ?? $sub['status']) === 'active' || ($sub['user_status'] ?? $sub['status']) === 'completed' || $sub['status'] === 'delivered') && !$sub['has_review']): ?>
                                            <button onclick="openReviewModal('<?= htmlspecialchars($sub['id']) ?>', '<?= htmlspecialchars($sub['plan_name']) ?>')" class="btn-action btn-review">
                                                <i class="fas fa-star"></i> Review
                                            </button>
                                            <?php elseif ($sub['has_review']): ?>
                                            <button disabled class="btn-action btn-disabled">
                                                <i class="fas fa-check"></i> Reviewed
                                            </button>
                                            <?php endif; ?>
                                            
                                            <!-- Complain Button -->
                                            <button onclick="openComplaintModal('<?= htmlspecialchars($sub['id']) ?>', '<?= htmlspecialchars($sub['plan_name']) ?>')" class="btn-action btn-complain">
                                                <i class="fas fa-exclamation-triangle"></i> Complain
                                            </button>
                                            
                                            <!-- Management Buttons -->
                                            <form method="post" style="display: contents;">
                                                <input type="hidden" name="id" value="<?= htmlspecialchars($sub['id']) ?>">
<?php if (($sub['user_status'] ?? $sub['status']) === 'active'): ?>
                                                    <?php
                                                    // Check if subscription can be cancelled
                                                    $canCancelSubscription = true;
                                                    $blockingReason = '';
                                                    
                                                    // Check delivery_date from subscription_menus
                                                    $stmt = $pdo->prepare("
                                                        SELECT delivery_date,
                                                               CASE 
                                                                   WHEN DAYOFWEEK(delivery_date) = 4 THEN 
                                                                       DATE_FORMAT(DATE_SUB(delivery_date, INTERVAL 2 DAY), '%Y-%m-%d 08:00:00')
                                                                   WHEN DAYOFWEEK(delivery_date) = 7 THEN 
                                                                       DATE_FORMAT(DATE_SUB(delivery_date, INTERVAL 2 DAY), '%Y-%m-%d 08:00:00')
                                                                   ELSE NULL 
                                                               END as cutoff_time
                                                        FROM subscription_menus 
                                                        WHERE subscription_id = ? 
                                                        AND delivery_date >= CURDATE() 
                                                        ORDER BY delivery_date ASC 
                                                        LIMIT 1
                                                    ");
                                                    $stmt->execute([$sub['id']]);
                                                    $nextDelivery = $stmt->fetch(PDO::FETCH_ASSOC);
                                                    
                                                    if ($nextDelivery && $nextDelivery['cutoff_time']) {
                                                        $now = new DateTime('now', new DateTimeZone('Asia/Bangkok'));
                                                        $cutoff = new DateTime($nextDelivery['cutoff_time'], new DateTimeZone('Asia/Bangkok'));
                                                        
                                                        if ($now > $cutoff) {
                                                            $canCancelSubscription = false;
                                                            $blockingReason = 'Next delivery (' . date('M j', strtotime($nextDelivery['delivery_date'])) . ') past cutoff time';
                                                        }
                                                    }
                                                    ?>
                                                    
                                                    <?php if ($canCancelSubscription): ?>
                                                        <button name="action" value="cancel" class="btn-action btn-cancel" 
                                                                onclick="return confirm('Are you sure you want to cancel this subscription?')">
                                                            <i class="fas fa-times"></i> Cancel
                                                        </button>
                                                    <?php else: ?>
                                                        <button disabled class="btn-action btn-disabled" 
                                                                title="<?= $blockingReason ?>">
                                                            <i class="fas fa-ban"></i> Cannot Cancel
                                                        </button>
                                                        <small style="color: #999; font-size: 0.8em; display: block; margin-top: 0.3rem;">
                                                            <?= $blockingReason ?>
                                                        </small>
                                                    <?php endif; ?>
                                                
                                                    
                                                <?php else: ?>
                                                    <button disabled class="btn-action btn-disabled">
                                                        <i class="fas fa-ban"></i> Cannot Manage
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <div class="bottom-nav">
                    <a href="dashboard.php" class="back-link">
                        <i class="fas fa-arrow-left"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal for Subscription Details -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-clipboard-list"></i>
                    Order Details
                </h3>
                <button class="modal-close" onclick="closeModal('detailsModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- ✅ Modal for Delivery Photo Viewing -->
    <div id="photoModal" class="modal">
        <div class="modal-content photo-modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-camera"></i>
                    Delivery Photo
                </h3>
                <button class="modal-close" onclick="closeModal('photoModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="photo-display">
                    <img id="deliveryPhotoImg" src="" alt="Delivery Photo" class="delivery-photo-large">
                    <div id="photoInfoSection" class="photo-info">
                        <!-- Photo info will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Complaint Form -->
    <div id="complaintModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Submit Complaint
                </h3>
                <button class="modal-close" onclick="closeModal('complaintModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" class="complaint-form">
                    <input type="hidden" name="action" value="complain">
                    <input type="hidden" name="subscription_id" id="complaint_subscription_id">
                    
                    <div class="form-group">
                        <label class="form-label">Order Plan</label>
                        <input type="text" id="complaint_plan_name" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Complaint Category *</label>
                        <select name="category" class="form-control" required>
                            <option value="">Select a category</option>
                            <option value="food_quality">Food Quality</option>
                            <option value="delivery_late">Late Delivery</option>
                            <option value="delivery_wrong">Wrong Delivery</option>
                            <option value="missing_items">Missing Items</option>
                            <option value="damaged_package">Damaged Package</option>
                            <option value="customer_service">Customer Service</option>
                            <option value="billing">Billing Issue</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Complaint Title *</label>
                        <input type="text" name="title" class="form-control" placeholder="Brief description of the issue" required maxlength="200">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Detailed Description *</label>
                        <textarea name="description" class="form-control form-control-textarea" placeholder="Please provide detailed information about your complaint..." required></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn-cancel-form" onclick="closeModal('complaintModal')">Cancel</button>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Submit Complaint
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal for Review Form -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-star"></i>
                    Leave a Review
                </h3>
                <button class="modal-close" onclick="closeModal('reviewModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" class="review-form" id="reviewForm">
                    <input type="hidden" name="action" value="submit_review">
                    <input type="hidden" name="subscription_id" id="review_subscription_id">
                    
                    <div class="form-group">
                        <label class="form-label">Order Plan</label>
                        <input type="text" id="review_plan_name" class="form-control" readonly>
                    </div>
                    
                    <!-- Rating Section -->
                    <div class="rating-section">
                        <div class="rating-title">How would you rate your experience?</div>
                        <div class="star-rating" id="starRating">
                            <i class="fas fa-star star" data-rating="1"></i>
                            <i class="fas fa-star star" data-rating="2"></i>
                            <i class="fas fa-star star" data-rating="3"></i>
                            <i class="fas fa-star star" data-rating="4"></i>
                            <i class="fas fa-star star" data-rating="5"></i>
                        </div>
                        <div class="rating-text" id="ratingText">Click a star to rate</div>
                        <input type="hidden" name="rating" id="selectedRating" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Review Title *</label>
                        <input type="text" name="title" class="form-control" 
                               placeholder="Brief title for your review" required maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Your Review *</label>
                        <textarea name="comment" class="form-control form-control-textarea" 
                                  placeholder="Share your experience with this meal plan..." required></textarea>
                    </div>
                    
                    <div class="review-tips">
                        <strong>Tips for writing a helpful review:</strong>
                        • Comment on food quality, delivery time, and packaging<br>
                        • Mention your favorite dishes<br>
                        • Be honest and constructive
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn-cancel-form" onclick="closeModal('reviewModal')">Cancel</button>
                        <button type="submit" class="btn-submit" id="submitReviewBtn" disabled>
                            <i class="fas fa-paper-plane"></i> Submit Review
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Page-specific JavaScript for subscription-status.php
        document.addEventListener('DOMContentLoaded', function() {
            console.log('📋 Subscription Status page loaded');
            
            // Initialize star rating events
            const stars = document.querySelectorAll('.star');
            const ratingTexts = ['Terrible', 'Poor', 'Average', 'Good', 'Excellent'];

            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.dataset.rating);
                    document.getElementById('selectedRating').value = rating;
                    document.getElementById('ratingText').textContent = ratingTexts[rating - 1];
                    document.getElementById('submitReviewBtn').disabled = false;

                    // Update star display
                    stars.forEach((s, index) => {
                        if (index < rating) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                });

                star.addEventListener('mouseenter', function() {
                    const rating = parseInt(this.dataset.rating);
                    stars.forEach((s, index) => {
                        if (index < rating) {
                            s.style.color = '#ffd700';
                        } else {
                            s.style.color = '#ddd';
                        }
                    });
                });
            });

            // Reset on mouse leave
            const starRating = document.querySelector('.star-rating');
            if (starRating) {
                starRating.addEventListener('mouseleave', function() {
                    const selectedRating = document.getElementById('selectedRating').value;
                    stars.forEach((s, index) => {
                        if (selectedRating && index < parseInt(selectedRating)) {
                            s.style.color = '#ffd700';
                        } else {
                            s.style.color = '#ddd';
                        }
                    });
                });
            }

            // Modal close on backdrop click
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal(this.id);
                    }
                });
            });

            // Keyboard shortcut for modal
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal('detailsModal');
                    closeModal('complaintModal');
                    closeModal('reviewModal');
                    closeModal('photoModal');
                }
            });

            // Animate table rows on load
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    row.style.transition = 'all 0.6s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Subscription data for modal
        const subscriptionData = <?= json_encode($subs) ?>;
        const menuData = <?= json_encode($subscription_menus) ?>;

        // ✅ Function to view delivery photo
        function viewDeliveryPhoto(photoPath, planName, notes, timestamp) {
            const photoModal = document.getElementById('photoModal');
            const photoImg = document.getElementById('deliveryPhotoImg');
            const photoInfo = document.getElementById('photoInfoSection');
            
            // Set the photo source
            photoImg.src = photoPath;
            
            // Build photo info content
            let infoContent = `
                <div class="photo-info-item">
                    <strong><i class="fas fa-box"></i> Order Plan:</strong> ${planName}
                </div>
                <div class="photo-info-item">
                    <strong><i class="fas fa-clock"></i> Photo Taken:</strong> ${new Date(timestamp).toLocaleString()}
                </div>
            `;
            
            if (notes && notes.trim()) {
                infoContent += `
                    <div class="photo-info-item">
                        <strong><i class="fas fa-sticky-note"></i> Delivery Notes:</strong> ${notes}
                    </div>
                `;
            }
            
            photoInfo.innerHTML = infoContent;
            
            // Show modal
            photoModal.classList.add('show');
        }

        // Create Order Progress Bar
        function createOrderProgressBar(orderStatus) {
            const steps = [
                { key: 'order_received', label: 'Order Received', icon: '📋' },
                { key: 'in_kitchen', label: 'In the Kitchen', icon: '👨‍🍳' },
                { key: 'delivering', label: 'Delivering', icon: '🚚' },
                { key: 'completed', label: 'Completed', icon: '✅' }
            ];
            
            const currentIndex = steps.findIndex(step => step.key === orderStatus);
            
            let progressHTML = '<div class="progress-steps">';
            
            steps.forEach((step, index) => {
                const isCompleted = index <= currentIndex;
                const isActive = index === currentIndex;
                
                progressHTML += `
                    <div class="progress-step-modal ${isCompleted ? 'completed' : ''} ${isActive ? 'active' : ''}">
                        <div class="step-icon">${step.icon}</div>
                        <div class="step-label">${step.label}</div>
                    </div>
                `;
                
                if (index < steps.length - 1) {
                    progressHTML += `<div class="progress-line ${index < currentIndex ? 'completed' : ''}"></div>`;
                }
            });
            
            progressHTML += '</div>';
            return progressHTML;
        }

        function openComplaintModal(subscriptionId, planName) {
            document.getElementById('complaint_subscription_id').value = subscriptionId;
            document.getElementById('complaint_plan_name').value = planName;
            document.getElementById('complaintModal').classList.add('show');
        }

        function openReviewModal(subscriptionId, planName) {
            document.getElementById('review_subscription_id').value = subscriptionId;
            document.getElementById('review_plan_name').value = planName;
            
            // Reset form
            document.getElementById('reviewForm').reset();
            document.getElementById('review_subscription_id').value = subscriptionId;
            document.getElementById('review_plan_name').value = planName;
            resetStarRating();
            
            document.getElementById('reviewModal').classList.add('show');
        }

        function viewDetails(subscriptionId) {
            const subscription = subscriptionData.find(sub => sub.id === subscriptionId);
            const menus = menuData[subscriptionId] || [];
            
            if (!subscription) return;

            const deliveryDays = JSON.parse(subscription.delivery_days || '[]');
            const dayNames = {
                'monday': 'Monday',
                'tuesday': 'Tuesday', 
                'wednesday': 'Wednesday',
                'thursday': 'Thursday',
                'friday': 'Friday',
                'saturday': 'Saturday',
                'sunday': 'Sunday'
            };

            const statusColors = {
                'active': '#27ae60',
                'paused': '#f39c12',
                'cancelled': '#e74c3c',
                'expired': '#636e72',
                'pending_payment': '#3498db',
                'delivered': '#6f42c1'
            };

            // Group menus by day
            const menusByDay = {};
            let totalMeals = 0; // Track total meals with quantities
            
            menus.forEach(menu => {
                const deliveryDate = menu.delivery_date;
                const quantity = parseInt(menu.quantity) || 1; // Get quantity from database
                totalMeals += quantity; // Add to total count
                
                if (!menusByDay[deliveryDate]) {
                    menusByDay[deliveryDate] = [];
                }
                menusByDay[deliveryDate].push(menu);
            });

            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = `
                <div class="detail-section">
                    <div class="detail-title">
                        <i class="fas fa-info-circle"></i>
                        Basic Information
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Plan Name</div>
                            <div class="detail-value">${subscription.plan_name}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Type</div>
                            <div class="detail-value">${subscription.plan_type === 'weekly' ? 'Weekly' : 'Monthly'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Meals per Week</div>
                            <div class="detail-value">${subscription.meals_per_week} meals</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Price</div>
                            <div class="detail-value" style="color: var(--curry); font-weight: 700;">
                                $${Number(subscription.final_price).toLocaleString()}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <div class="detail-title">
                        <i class="fas fa-truck"></i>
                        Order Progress
                    </div>
                    <div class="order-progress-bar">
                        ${createOrderProgressBar(subscription.order_status || 'order_received')}
                    </div>
                </div>

                <div class="detail-section">
                    <div class="detail-title">
                        <i class="fas fa-calendar-alt"></i>
                        Duration and Delivery
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Delivery Date</div>
                            <div class="detail-value">${subscription.start_date}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Next Billing Date</div>
                            <div class="detail-value">${subscription.next_billing_date || '-'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Preferred Delivery Time</div>
                            <div class="detail-value">
                                ${subscription.preferred_delivery_time === 'morning' ? 'Morning (8:00-12:00)' :
                                subscription.preferred_delivery_time === 'afternoon' ? 'Afternoon (12:00-16:00)' :
                                subscription.preferred_delivery_time === 'evening' ? 'Evening (16:00-20:00)' : 'Flexible'}
                            </div>
                        </div>
                        
                        ${subscription.status === 'delivered' && subscription.delivered_at ? `
                        <div class="detail-item">
                            <div class="detail-label">Delivered At</div>
                            <div class="detail-value" style="color: #6f42c1; font-weight: 700;">
                                <i class="fas fa-check-circle"></i> ${new Date(subscription.delivered_at).toLocaleString()}
                            </div>
                        </div>
                        ` : ''}
                    </div>
                    
                    ${deliveryDays.length > 0 ? `
                    <div style="margin-top: 1rem;">
                        <div class="detail-label">Delivery Days</div>
                        <div class="delivery-days-list">
                            ${deliveryDays.map(day => `<span class="delivery-day-tag">${dayNames[day] || day}</span>`).join('')}
                        </div>
                    </div>
                    ` : ''}
                </div>

                <div class="detail-section">
                    <div class="detail-title">
                        <i class="fas fa-chart-line"></i>
                        Status and Settings
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Current Status</div>
                            <div class="detail-value">
                                <span style="color: ${statusColors[subscription.user_status] || statusColors[subscription.status] || '#636e72'}; font-weight: 700;">
                                    ${getStatusText(subscription.user_status)}
                                </span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Auto Renewal</div>
                            <div class="detail-value">${subscription.auto_renew == 1 ? '✅ Enabled' : '❌ Disabled'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Order Date</div>
                            <div class="detail-value">${subscription.created_at}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Last Updated</div>
                            <div class="detail-value">${subscription.updated_at}</div>
                        </div>
                    </div>
                </div>

                ${menus.length > 0 ? `
                <div class="detail-section">
                    <div class="detail-title">
                        <i class="fas fa-utensils"></i>
                        Selected Meals (${totalMeals} meals total)
                    </div>
                    ${Object.keys(menusByDay).length > 0 ? 
                        Object.keys(menusByDay).sort().map(date => {
                            // Calculate total meals for this date
                            const dailyTotal = menusByDay[date].reduce((sum, menu) => sum + (parseInt(menu.quantity) || 1), 0);
                            
                            return `
                                <div style="margin-bottom: 1.5rem;">
                                    <h4 style="color: var(--curry); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-calendar-day"></i>
                                        Delivery Date: ${date} (${dailyTotal} meals)
                                    </h4>
                                    <div class="menus-grid" style="grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));">
                                        ${menusByDay[date].map(menu => {
                                            const quantity = parseInt(menu.quantity) || 1;
                                            return `
                                                <div class="menu-card">
                                                    ${menu.main_image_url ? 
                                                        `<img src="${menu.main_image_url}" alt="${menu.menu_name}" class="menu-image" onerror="this.style.display='none'">` :
                                                        `<div class="menu-image" style="display: flex; align-items: center; justify-content: center; color: var(--text-gray);">
                                                            <i class="fas fa-utensils" style="font-size: 2rem;"></i>
                                                        </div>`
                                                    }
                                                    ${quantity > 1 ? `<div class="menu-quantity" style="
                                                        position: absolute; 
                                                        top: 0.5rem; 
                                                        right: 0.5rem; 
                                                        background: var(--curry); 
                                                        color: var(--white); 
                                                        border-radius: 50%; 
                                                        width: 30px; 
                                                        height: 30px; 
                                                        display: flex; 
                                                        align-items: center; 
                                                        justify-content: center; 
                                                        font-weight: 700; 
                                                        font-size: 0.9rem;
                                                        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                                                    ">×${quantity}</div>` : ''}
                                                    <div class="menu-content">
                                                        <div class="menu-name">
                                                            ${menu.menu_name}
                                                            ${quantity > 1 ? `<span style="color: var(--curry); font-weight: 700; margin-left: 0.5rem;">(×${quantity})</span>` : ''}
                                                        </div>
                                                        ${menu.name_thai ? `<div class="menu-name-thai">${menu.name_thai}</div>` : ''}
                                                        ${menu.category_name ? `<div class="menu-category">${menu.category_name}</div>` : ''}
                                                        
                                                        <div class="menu-nutrition">
                                                            ${menu.calories_per_serving ? `<span><i class="fas fa-fire"></i> ${menu.calories_per_serving} cal</span>` : ''}
                                                            ${menu.protein_g ? `<span><i class="fas fa-drumstick-bite"></i> ${menu.protein_g}g</span>` : ''}
                                                        </div>
                                                    </div>
                                                </div>
                                            `;
                                        }).join('')}
                                    </div>
                                </div>
                            `;
                        }).join('') :
                        `<div style="text-align: center; padding: 2rem; color: var(--text-gray);">
                            <i class="fas fa-utensils" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                            <p>No meals selected yet</p>
                        </div>`
                    }
                </div>
                ` : ''}

                ${subscription.special_instructions ? `
                <div class="detail-section">
                    <div class="detail-title">
                        <i class="fas fa-sticky-note"></i>
                        Special Instructions
                    </div>
                    <div class="detail-item">
                        <div class="detail-value">${subscription.special_instructions}</div>
                    </div>
                </div>
                ` : ''}
            `;

            document.getElementById('detailsModal').classList.add('show');
        }

        function closeModal(modalId) {
            if (modalId === 'reviewModal') {
                resetStarRating();
            }
            document.getElementById(modalId).classList.remove('show');
        }

        function getStatusText(status) {
            const statusMap = {
                'active': 'Active',
                'paused': 'Paused',
                'cancelled': 'Cancelled',
                'expired': 'Expired',
                'pending_payment': 'Pending Payment',
                'delivered': 'Delivered'
            };
            return statusMap[status] || status;
        }

        function resetStarRating() {
            const stars = document.querySelectorAll('.star');
            stars.forEach(star => star.classList.remove('active'));
            document.getElementById('selectedRating').value = '';
            document.getElementById('ratingText').textContent = 'Click a star to rate';
            document.getElementById('submitReviewBtn').disabled = true;
        }
    </script>
</body>
</html>