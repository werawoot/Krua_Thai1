<?php
/**
 * Krua Thai - Customer Support Center
 * File: support-center.php
 * Description: Customer support page for submitting complaints, viewing tickets, and getting help
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'submit_complaint') {
            // Generate complaint number
            $complaint_number = 'COMP-' . date('Ymd') . '-' . substr(uniqid(), -6);
            
            // Insert complaint
            $stmt = $pdo->prepare("
                INSERT INTO complaints (id, complaint_number, user_id, subscription_id, category, priority, title, description, expected_resolution, status, created_at)
                VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, 'open', NOW())
            ");
            
            $stmt->execute([
                $complaint_number,
                $user_id,
                $_POST['subscription_id'] ?: null,
                $_POST['category'],
                $_POST['priority'],
                $_POST['title'],
                $_POST['description'],
                $_POST['expected_resolution'] ?: null
            ]);
            
            $success_message = "ส่งคำร้องเรียนเรียบร้อยแล้ว หมายเลขคำร้องเรียน: {$complaint_number}";
        }
        
    } catch (Exception $e) {
        $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        error_log("Support center error: " . $e->getMessage());
    }
}

// Get user's subscriptions
try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.status, sp.name as plan_name, s.start_date, s.end_date
        FROM subscriptions s
        JOIN subscription_plans sp ON s.plan_id = sp.id
        WHERE s.user_id = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $subscriptions = [];
    error_log("Error fetching subscriptions: " . $e->getMessage());
}

// Get user's complaints
try {
    $stmt = $pdo->prepare("
        SELECT c.*, s.status as subscription_status, sp.name as plan_name
        FROM complaints c
        LEFT JOIN subscriptions s ON c.subscription_id = s.id
        LEFT JOIN subscription_plans sp ON s.plan_id = sp.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $user_complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $user_complaints = [];
    error_log("Error fetching complaints: " . $e->getMessage());
}

// Get user info
try {
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $user_info = ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => ''];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ศูนย์บริการลูกค้า - Krua Thai</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
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

        .header-content {
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
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--curry);
            text-decoration: none;
        }

        .logo-image {
            height: 40px;
            width: auto;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-gray);
        }

        .btn-logout {
            background: var(--curry);
            color: var(--white);
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .btn-logout:hover {
            background: var(--brown);
            transform: translateY(-1px);
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Page Header */
        .page-header {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            text-align: center;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--text-gray);
            font-size: 1.1rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-sm);
            margin-bottom: 2rem;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.1);
            border-left-color: #27ae60;
            color: #27ae60;
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            border-left-color: #e74c3c;
            color: #e74c3c;
        }

        /* Tabs */
        .tabs {
            display: flex;
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .tab-button {
            flex: 1;
            padding: 1rem;
            background: transparent;
            border: none;
            cursor: pointer;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-gray);
            transition: var(--transition);
        }

        .tab-button.active {
            background: var(--curry);
            color: var(--white);
        }

        .tab-button:hover:not(.active) {
            background: var(--cream);
        }

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Cards */
        .card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
            border-bottom: 1px solid var(--border-light);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .form-control[readonly] {
            background: var(--cream);
            color: var(--text-gray);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), #e67e22);
            color: var(--white);
            box-shadow: var(--shadow-soft);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--text-dark);
            border: 1px solid var(--border-light);
        }

        .btn-secondary:hover {
            background: var(--cream);
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-open {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .status-in_progress {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .status-resolved {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .status-closed {
            background: rgba(149, 165, 166, 0.1);
            color: #95a5a6;
        }

        .priority-low {
            background: rgba(149, 165, 166, 0.1);
            color: #95a5a6;
        }

        .priority-medium {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .priority-high {
            background: rgba(230, 126, 34, 0.1);
            color: #e67e22;
        }

        .priority-critical {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        /* Tables */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }

        .table th {
            background: var(--cream);
            font-weight: 600;
            color: var(--text-dark);
        }

        .table tbody tr:hover {
            background: #fafafa;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--sage);
        }

        /* FAQ Section */
        .faq-item {
            border-bottom: 1px solid var(--border-light);
            margin-bottom: 1rem;
        }

        .faq-question {
            padding: 1rem;
            background: transparent;
            border: none;
            width: 100%;
            text-align: left;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-dark);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .faq-question:hover {
            background: var(--cream);
        }

        .faq-answer {
            padding: 0 1rem 1rem;
            color: var(--text-gray);
            display: none;
        }

        .faq-answer.show {
            display: block;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .quick-action {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            border: 1px solid var(--border-light);
        }

        .quick-action:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
        }

        .quick-action-icon {
            font-size: 2rem;
            color: var(--curry);
            margin-bottom: 1rem;
        }

        .quick-action-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .quick-action-desc {
            font-size: 0.9rem;
            color: var(--text-gray);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .header-content {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            .tabs {
                flex-direction: column;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <a href="index.php" class="logo">
                <img src="assets/image/LOGO_White Trans.png" alt="Krua Thai" class="logo-image">
                <span>Krua Thai</span>
            </a>
            <div class="user-menu">
                <div class="user-info">
                    <i class="fas fa-user"></i>
                    <span><?= htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']) ?></span>
                </div>
                <a href="logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    ออกจากระบบ
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">ศูนย์บริการลูกค้า</h1>
            <p class="page-subtitle">ส่งคำร้องเรียน ติดตามปัญหา และรับการช่วยเหลือ</p>
        </div>

        <!-- Alert Messages -->
        <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success_message) ?>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="quick-action" onclick="showTab('new-complaint')">
                <div class="quick-action-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="quick-action-title">ส่งคำร้องเรียนใหม่</div>
                <div class="quick-action-desc">รายงานปัญหาเกี่ยวกับการสั่งซื้อหรือบริการ</div>
            </div>
            
            <div class="quick-action" onclick="showTab('my-tickets')">
                <div class="quick-action-icon">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="quick-action-title">ตรวจสอบคำร้องเรียน</div>
                <div class="quick-action-desc">ดูสถานะและการตอบกลับคำร้องเรียนของคุณ</div>
            </div>
            
            <div class="quick-action" onclick="showTab('faq')">
                <div class="quick-action-icon">
                    <i class="fas fa-question-circle"></i>
                </div>
                <div class="quick-action-title">คำถามที่พบบ่อย</div>
                <div class="quick-action-desc">หาคำตอบสำหรับคำถามทั่วไป</div>
            </div>
            
            <div class="quick-action" onclick="showTab('contact')">
                <div class="quick-action-icon">
                    <i class="fas fa-phone"></i>
                </div>
                <div class="quick-action-title">ติดต่อเรา</div>
                <div class="quick-action-desc">ช่องทางการติดต่อและข้อมูลบริษัท</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-button active" onclick="showTab('new-complaint')">
                <i class="fas fa-plus"></i> ส่งคำร้องเรียนใหม่
            </button>
            <button class="tab-button" onclick="showTab('my-tickets')">
                <i class="fas fa-list"></i> คำร้องเรียนของฉัน
            </button>
            <button class="tab-button" onclick="showTab('faq')">
                <i class="fas fa-question"></i> คำถามที่พบบ่อย
            </button>
            <button class="tab-button" onclick="showTab('contact')">
                <i class="fas fa-phone"></i> ติดต่อเรา
            </button>
        </div>

        <!-- Tab Content: New Complaint -->
        <div id="new-complaint" class="tab-content active">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">ส่งคำร้องเรียนใหม่</h3>
                    <p>กรุณากรอกข้อมูลให้ครบถ้วนเพื่อให้เราสามารถช่วยเหลือคุณได้อย่างดีที่สุด</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="submit_complaint">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">ประเภทปัญหา *</label>
                                <select name="category" class="form-control" required>
                                    <option value="">เลือกประเภทปัญหา</option>
                                    <option value="food_quality">คุณภาพอาหาร</option>
                                    <option value="delivery_late">การจัดส่งล่าช้า</option>
                                    <option value="delivery_wrong">จัดส่งผิดที่อยู่</option>
                                    <option value="missing_items">อาหารไม่ครบ</option>
                                    <option value="damaged_package">บรรจุภัณฑ์เสียหาย</option>
                                    <option value="customer_service">บริการลูกค้า</option>
                                    <option value="billing">การเรียกเก็บเงิน</option>
                                    <option value="other">อื่นๆ</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">ระดับความเร่งด่วน *</label>
                                <select name="priority" class="form-control" required>
                                    <option value="">เลือกระดับความเร่งด่วน</option>
                                    <option value="low">ต่ำ</option>
                                    <option value="medium">ปานกลาง</option>
                                    <option value="high">สูง</option>
                                    <option value="critical">วิกฤต</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">แพ็กเกจการสมัครสมาชิก (ถ้ามี)</label>
                            <select name="subscription_id" class="form-control">
                                <option value="">ไม่เกี่ยวข้องกับการสมัครสมาชิก</option>
                                <?php foreach ($subscriptions as $subscription): ?>
                                <option value="<?= $subscription['id'] ?>">
                                    <?= htmlspecialchars($subscription['plan_name']) ?> 
                                    (<?= date('d/m/Y', strtotime($subscription['start_date'])) ?> - 
                                    <?= $subscription['end_date'] ? date('d/m/Y', strtotime($subscription['end_date'])) : 'ไม่มีกำหนด' ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">หัวข้อปัญหา *</label>
                            <input type="text" name="title" class="form-control" placeholder="สรุปปัญหาในหัวข้อ" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">รายละเอียดปัญหา *</label>
                            <textarea name="description" class="form-control" placeholder="กรุณาอธิบายปัญหาอย่างละเอียด เพื่อให้เราสามารถช่วยเหลือคุณได้อย่างเหมาะสม" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">ผลลัพธ์ที่คาดหวัง</label>
                            <textarea name="expected_resolution" class="form-control" placeholder="คุณต้องการให้เราแก้ไขปัญหานี้อย่างไร (ไม่บังคับ)"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            ส่งคำร้องเรียน
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tab Content: My Tickets -->
        <div id="my-tickets" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">คำร้องเรียนของฉัน</h3>
                    <p>ติดตามสถานะและดูการตอบกลับของคำร้องเรียน</p>
                </div>
                <div class="card-body">
                    <?php if (empty($user_complaints)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>ไม่มีคำร้องเรียน</h3>
                        <p>คุณยังไม่เคยส่งคำร้องเรียนมาก่อน</p>
                    </div>
                    <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>หมายเลข</th>
                                    <th>หัวข้อ</th>
                                    <th>ประเภท</th>
                                    <th>สถานะ</th>
                                    <th>ความเร่งด่วน</th>
                                    <th>วันที่ส่ง</th>
                                    <th>การกระทำ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_complaints as $complaint): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($complaint['complaint_number']) ?></strong></td>
                                    <td><?= htmlspecialchars($complaint['title']) ?></td>
                                    <td>
                                        <?php
                                        
                                        $categories = [
                                            'food_quality' => 'คุณภาพอาหาร',
                                            'delivery_late' => 'การจัดส่งล่าช้า',
                                            'delivery_wrong' => 'จัดส่งผิดที่อยู่',
                                            'missing_items' => 'อาหารไม่ครบ',
                                            'damaged_package' => 'บรรจุภัณฑ์เสียหาย',
                                            'customer_service' => 'บริการลูกค้า',
                                            'billing' => 'การเรียกเก็บเงิน',
                                            'other' => 'อื่นๆ'
                                        ];
                                        echo $categories[$complaint['category']] ?? $complaint['category'];
                                        ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $complaint['status'] ?>">
                                            <?php
                                            $statuses = [
                                                'open' => 'เปิด',
                                                'in_progress' => 'กำลังดำเนินการ',
                                                'resolved' => 'แก้ไขแล้ว',
                                                'closed' => 'ปิด',
                                                'escalated' => 'ส่งต่อ'
                                            ];
                                            echo $statuses[$complaint['status']] ?? $complaint['status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge priority-<?= $complaint['priority'] ?>">
                                            <?php
                                            $priorities = [
                                                'low' => 'ต่ำ',
                                                'medium' => 'ปานกลาง',
                                                'high' => 'สูง',
                                                'critical' => 'วิกฤต'
                                            ];
                                            echo $priorities[$complaint['priority']] ?? $complaint['priority'];
                                            ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($complaint['created_at'])) ?></td>
                                    <td>
                                        <button class="btn btn-secondary" onclick="viewComplaint('<?= $complaint['id'] ?>')">
                                            <i class="fas fa-eye"></i> ดูรายละเอียด
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab Content: FAQ -->
        <div id="faq" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">คำถามที่พบบ่อย</h3>
                    <p>คำตอบสำหรับคำถามที่ลูกค้าถามบ่อยที่สุด</p>
                </div>
                <div class="card-body">
                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span>วิธีการสั่งอาหารผ่านแพ็กเกจสมาชิก?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>คุณสามารถสั่งอาหารผ่านแพ็กเกจสมาชิกได้โดยการ:</p>
                            <ol>
                                <li>เข้าสู่ระบบในบัญชีของคุณ</li>
                                <li>เลือกแพ็กเกจที่ต้องการ</li>
                                <li>เลือกเมนูอาหารสำหรับแต่ละวัน</li>
                                <li>กำหนดวันและเวลาจัดส่ง</li>
                                <li>ชำระเงิน</li>
                            </ol>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span>สามารถเปลี่ยนเมนูอาหารหลังจากสั่งแล้วได้ไหม?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>คุณสามารถเปลี่ยนเมนูอาหารได้ก่อนวันจัดส่งอย่างน้อย 24 ชั่วโมง โดยเข้าไปที่หน้าการจัดการสมาชิกและแก้ไขการสั่งซื้อ</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span>ค่าจัดส่งเท่าไหร่?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>ค่าจัดส่งขึ้นอยู่กับพื้นที่การจัดส่ง:</p>
                            <ul>
                                <li>กรุงเทพฯ และปริมณฑล: ฟรีสำหรับออเดอร์ขั้นต่ำ 500 บาท</li>
                                <li>ต่างจังหวัด: 80-120 บาท ขึ้นอยู่กับระยะทาง</li>
                            </ul>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span>จะยกเลิกการสมัครสมาชิกได้อย่างไร?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>คุณสามารถยกเลิกการสมัครสมาชิกได้โดย:</p>
                            <ol>
                                <li>เข้าไปที่หน้าการจัดการสมาชิก</li>
                                <li>เลือก "ยกเลิกสมาชิก"</li>
                                <li>ระบุเหตุผลในการยกเลิก</li>
                                <li>ยืนยันการยกเลิก</li>
                            </ol>
                            <p>หมายเหตุ: การยกเลิกจะมีผลตั้งแต่รอบการเรียกเก็บเงินถัดไป</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span>ถ้าอาหารมีปัญหาควรทำอย่างไร?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>หากอาหารมีปัญหา กรุณา:</p>
                            <ol>
                                <li>ถ่ายรูปอาหารที่มีปัญหา</li>
                                <li>ส่งคำร้องเรียนผ่านระบบ หรือโทรหาเรา</li>
                                <li>ระบุรายละเอียดปัญหาให้ชัดเจน</li>
                                <li>เราจะดำเนินการแก้ไขภายใน 24 ชั่วโมง</li>
                            </ol>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span>รับประกันคุณภาพอาหารอย่างไร?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>เรามีการรับประกันคุณภาพอาหาร 100% โดย:</p>
                            <ul>
                                <li>ใช้วัตถุดิบคุณภาพสูงและสดใหม่</li>
                                <li>มีมาตรฐาน HACCP</li>
                                <li>ตรวจสอบคุณภาพทุกขั้นตอน</li>
                                <li>หากมีปัญหาเราจะเปลี่ยนให้ใหม่หรือคืนเงิน</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content: Contact -->
        <div id="contact" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">ติดต่อเรา</h3>
                    <p>ช่องทางการติดต่อและข้อมูลบริษัท</p>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="card">
                            <div class="card-body" style="text-align: center;">
                                <div style="font-size: 2rem; color: var(--curry); margin-bottom: 1rem;">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <h4>โทรศัพท์</h4>
                                <p style="font-size: 1.2rem; font-weight: 600; color: var(--curry);">02-123-4567</p>
                                <p style="color: var(--text-gray);">จันทร์-ศุกร์ 9:00-18:00<br>เสาร์-อาทิตย์ 9:00-17:00</p>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body" style="text-align: center;">
                                <div style="font-size: 2rem; color: var(--curry); margin-bottom: 1rem;">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <h4>อีเมล</h4>
                                <p style="font-size: 1.2rem; font-weight: 600; color: var(--curry);">support@kruathai.com</p>
                                <p style="color: var(--text-gray);">ตอบกลับภายใน 24 ชั่วโมง</p>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body" style="text-align: center;">
                                <div style="font-size: 2rem; color: var(--curry); margin-bottom: 1rem;">
                                    <i class="fab fa-line"></i>
                                </div>
                                <h4>LINE Official</h4>
                                <p style="font-size: 1.2rem; font-weight: 600; color: var(--curry);">@kruathai</p>
                                <p style="color: var(--text-gray);">แชทสดทุกวัน 9:00-21:00</p>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body" style="text-align: center;">
                                <div style="font-size: 2rem; color: var(--curry); margin-bottom: 1rem;">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <h4>ที่อยู่สำนักงาน</h4>
                                <p style="color: var(--text-gray);">123 ถนนสุขุมวิท<br>แขวงคลองเตย เขตคลองเตย<br>กรุงเทพฯ 10110</p>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 2rem; padding: 1.5rem; background: var(--cream); border-radius: var(--radius-md);">
                        <h4 style="margin-bottom: 1rem;">เวลาทำการ</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <div>
                                <strong>ศูนย์บริการลูกค้า</strong><br>
                                จันทร์-ศุกร์: 9:00-18:00<br>
                                เสาร์-อาทิตย์: 9:00-17:00
                            </div>
                            <div>
                                <strong>การจัดส่งอาหาร</strong><br>
                                ทุกวัน: 9:00-21:00<br>
                                (รวมวันหยุดนักขัตฤกษ์)
                            </div>
                            <div>
                                <strong>แชทสด LINE</strong><br>
                                ทุกวัน: 9:00-21:00<br>
                                ตอบกลับทันที
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Complaint Detail Modal -->
    <div id="complaintModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: var(--white); border-radius: var(--radius-lg); padding: 2rem; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3>รายละเอียดคำร้องเรียน</h3>
                <button onclick="closeComplaintModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="complaintDetails">
                <!-- Complaint details will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to corresponding button
            event.target.classList.add('active');
        }

        function toggleFAQ(element) {
            const answer = element.nextElementSibling;
            const icon = element.querySelector('i');
            
            if (answer.classList.contains('show')) {
                answer.classList.remove('show');
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                // Close all other FAQs
                document.querySelectorAll('.faq-answer').forEach(ans => {
                    ans.classList.remove('show');
                });
                document.querySelectorAll('.faq-question i').forEach(ic => {
                    ic.classList.remove('fa-chevron-up');
                    ic.classList.add('fa-chevron-down');
                });
                
                // Open clicked FAQ
                answer.classList.add('show');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        }

        function viewComplaint(complaintId) {
            // Here you would typically make an AJAX call to get complaint details
            // For now, we'll show a placeholder
            const modal = document.getElementById('complaintModal');
            const details = document.getElementById('complaintDetails');
            
            details.innerHTML = `
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--curry);"></i>
                    <p style="margin-top: 1rem;">กำลังโหลดข้อมูล...</p>
                </div>
            `;
            
            modal.style.display = 'block';
            
            // Simulate loading delay
            setTimeout(() => {
                details.innerHTML = `
                    <div style="border-bottom: 1px solid var(--border-light); padding-bottom: 1rem; margin-bottom: 1rem;">
                        <p><strong>หมายเลขคำร้องเรียน:</strong> COMP-20250721-ABC123</p>
                        <p><strong>สถานะ:</strong> <span class="status-badge status-in_progress">กำลังดำเนินการ</span></p>
                        <p><strong>ความเร่งด่วน:</strong> <span class="status-badge priority-medium">ปานกลาง</span></p>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <h4>รายละเอียดปัญหา</h4>
                        <p>ข้อมูลรายละเอียดของคำร้องเรียนจะแสดงที่นี่...</p>
                    </div>
                    <div>
                        <h4>การตอบกลับ</h4>
                        <p style="color: var(--text-gray);">ยังไม่มีการตอบกลับ</p>
                    </div>
                `;
            }, 1000);
        }

        function closeComplaintModal() {
            document.getElementById('complaintModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('complaintModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeComplaintModal();
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const category = document.querySelector('select[name="category"]').value;
            const priority = document.querySelector('select[name="priority"]').value;
            const title = document.querySelector('input[name="title"]').value.trim();
            const description = document.querySelector('textarea[name="description"]').value.trim();
            
            if (!category || !priority || !title || !description) {
                e.preventDefault();
                alert('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
                return false;
            }
            
            if (title.length < 5) {
                e.preventDefault();
                alert('หัวข้อปัญหาต้องมีความยาวอย่างน้อย 5 ตัวอักษร');
                return false;
            }
            
            if (description.length < 20) {
                e.preventDefault();
                alert('รายละเอียดปัญหาต้องมีความยาวอย่างน้อย 20 ตัวอักษร');
                return false;
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);

        console.log('Krua Thai Support Center loaded successfully');
    </script>
</body>
</html>