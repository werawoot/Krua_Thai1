<?php
// subscription-status.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// เช็คว่าเข้าสู่ระบบหรือยัง
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// --- ฟังก์ชัน Update สถานะ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id = $_POST['id'];
    $action = $_POST['action'];
    $status_map = [
        'pause' => 'paused',
        'cancel' => 'cancelled',
        'renew' => 'active'
    ];
    if (isset($status_map[$action])) {
        $new_status = $status_map[$action];
        $stmt = $pdo->prepare("UPDATE subscriptions SET status = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$new_status, $id, $user_id]);
        header("Location: subscription-status.php");
        exit();
    }
}

// --- ดึงข้อมูล Subscription ---
$stmt = $pdo->prepare("
    SELECT s.*, sp.name AS plan_name, sp.meals_per_week, sp.final_price, sp.plan_type
    FROM subscriptions s 
    JOIN subscription_plans sp ON s.plan_id = sp.id
    WHERE s.user_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$user_id]);
$subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- ดึงข้อมูลเมนูที่เลือกสำหรับแต่ละ subscription ---
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

function getStatusText($status) {
    $map = [
        'active' => 'ใช้งาน',
        'paused' => 'พักชั่วคราว',
        'cancelled' => 'ยกเลิก',
        'expired' => 'หมดอายุ',
        'pending_payment' => 'รอชำระเงิน'
    ];
    return $map[$status] ?? $status;
}

function getDayName($day) {
    $days = [
        'monday' => 'จันทร์',
        'tuesday' => 'อังคาร',
        'wednesday' => 'พุธ',
        'thursday' => 'พฤหัสบดี',
        'friday' => 'ศุกร์',
        'saturday' => 'เสาร์',
        'sunday' => 'อาทิตย์'
    ];
    return $days[$day] ?? $day;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สถานะการสมัครสมาชิก - Krua Thai</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            --shadow-large: 0 16px 48px rgba(0,0,0,0.15);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #3498db;
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
            max-width: 1100px;
            margin: 0 auto;
            padding: 2rem 2rem 4rem;
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
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
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
        }

        /* Page Title */
        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 2rem;
            color: var(--text-dark);
        }

        .page-title i {
            color: var(--curry);
            margin-right: 0.5rem;
        }

        /* Success Message */
        .success-message {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border: 2px solid #b8dabc;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
            color: var(--success);
            text-align: center;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .success-message i {
            font-size: 1.2rem;
        }

        /* Main Content Card */
        .main-card {
            background: var(--white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-medium);
            overflow: hidden;
            position: relative;
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
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-title i {
            color: var(--curry);
        }

        /* Table */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1.2rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }

        th {
            background: var(--cream);
            color: var(--text-dark);
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            color: var(--text-dark);
            font-weight: 500;
        }

        tbody tr:hover {
            background: rgba(207, 114, 58, 0.02);
        }

        /* Status Badges */
        .status {
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-xl);
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status.active {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }

        .status.paused {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
        }

        .status.cancelled {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
        }

        .status.expired {
            background: linear-gradient(135deg, #e2e3e5, #d6d8db);
            color: #495057;
        }

        .status.pending_payment {
            background: linear-gradient(135deg, #cce5ff, #b3d9ff);
            color: #004085;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 200px;
        }

        .btn-action {
            border: none;
            padding: 0.7rem 1.2rem;
            border-radius: var(--radius-lg);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-family: inherit;
            text-align: center;
            width: 100%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn-view {
            background: linear-gradient(135deg, var(--info), #74b9ff);
            color: var(--white);
            border: 1px solid #74b9ff;
        }

        .btn-view:hover {
            background: linear-gradient(135deg, #74b9ff, #0984e3);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(116, 185, 255, 0.4);
        }

        .btn-pause {
            background: linear-gradient(135deg, #fdcb6e, #f39c12);
            color: var(--white);
            border: 1px solid #f39c12;
        }

        .btn-pause:hover {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(243, 156, 18, 0.4);
        }

        .btn-cancel {
            background: linear-gradient(135deg, #fd7979, var(--danger));
            color: var(--white);
            border: 1px solid var(--danger);
        }

        .btn-cancel:hover {
            background: linear-gradient(135deg, var(--danger), #c0392b);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4);
        }

        .btn-renew {
            background: linear-gradient(135deg, #00b894, var(--success));
            color: var(--white);
            border: 1px solid var(--success);
        }

        .btn-renew:hover {
            background: linear-gradient(135deg, var(--success), #00a085);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
        }

        .btn-disabled {
            background: linear-gradient(135deg, #b2bec3, #636e72);
            color: var(--white);
            border: 1px solid #636e72;
            cursor: not-allowed;
        }

        .btn-action:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-action:disabled:hover {
            transform: none;
            box-shadow: none;
        }

        /* Modal */
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
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--radius-xl);
            padding: 0;
            width: 90%;
            max-width: 800px;
            max-height: 85vh;
            overflow: hidden;
            box-shadow: var(--shadow-large);
            position: relative;
            transform: scale(0.8);
            opacity: 0;
            transition: var(--transition);
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
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 700;
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
        }

        .modal-close:hover {
            background: rgba(255,255,255,0.2);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 2rem;
            max-height: 600px;
            overflow-y: auto;
        }

        .detail-section {
            margin-bottom: 2rem;
        }

        .detail-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--curry);
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
        }

        .detail-label {
            font-size: 0.85rem;
            color: var(--text-gray);
            margin-bottom: 0.3rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-weight: 600;
            color: var(--text-dark);
        }

        .delivery-days-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .delivery-day-tag {
            background: var(--curry);
            color: var(--white);
            padding: 0.3rem 0.8rem;
            border-radius: var(--radius-lg);
            font-size: 0.8rem;
            font-weight: 600;
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
            color: var(--text-dark);
            margin-bottom: 0.3rem;
            font-size: 1rem;
        }

        .menu-name-thai {
            color: var(--text-gray);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .menu-category {
            background: var(--sage);
            color: var(--white);
            padding: 0.2rem 0.6rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 0.5rem;
        }

        .menu-nutrition {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: var(--text-gray);
            margin-bottom: 0.5rem;
        }

        .menu-price {
            font-weight: 700;
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
            position: absolute;
            top: 0.5rem;
            left: 0.5rem;
        }

        .menu-card {
            position: relative;
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
            color: var(--text-dark);
        }

        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }

        .empty-state .btn {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            padding: 1rem 2rem;
            border-radius: var(--radius-xl);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }

        .empty-state .btn:hover {
            background: linear-gradient(135deg, var(--brown), var(--sage));
            transform: translateY(-2px);
        }

        /* Navigation */
        .bottom-nav {
            padding: 2rem;
            border-top: 1px solid var(--border-light);
            background: var(--cream);
        }

        .back-link {
            color: var(--curry);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }

        .back-link:hover {
            color: var(--brown);
            transform: translateX(-3px);
        }

        /* Price Display */
        .price {
            font-weight: 700;
            color: var(--curry);
            font-size: 1.1rem;
        }

        /* Plan Name */
        .plan-name {
            font-weight: 700;
            color: var(--text-dark);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 1rem 1rem 3rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .table-container {
                font-size: 0.9rem;
            }

            th, td {
                padding: 0.8rem 0.5rem;
            }

            .action-buttons {
                flex-direction: row;
                justify-content: flex-start;
                flex-wrap: wrap;
            }

            .btn-action {
                padding: 0.5rem 0.8rem;
                font-size: 0.75rem;
                margin-bottom: 0.3rem;
                min-width: auto;
                flex: 1;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

            .menus-grid {
                grid-template-columns: 1fr;
            }

            .progress-bar {
                gap: 0.5rem;
            }

            .progress-step {
                font-size: 0.8rem;
                padding: 0.6rem 1rem;
            }

            .progress-arrow {
                font-size: 1rem;
            }

            .modal-content {
                width: 95%;
                max-height: 90vh;
            }
        }

        @media (max-width: 480px) {
            .header-container {
                padding: 1rem;
            }

            .logo-text {
                font-size: 1.5rem;
            }

            .progress-step {
                font-size: 0.7rem;
                padding: 0.5rem 0.8rem;
            }

            .table-container {
                font-size: 0.8rem;
            }

            th, td {
                padding: 0.6rem 0.3rem;
            }

            .card-header {
                padding: 1.5rem 1rem;
            }

            .bottom-nav {
                padding: 1.5rem 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <div class="logo-text">Krua Thai</div>
            </a>
            <nav class="header-nav">
                <a href="menu.php" class="nav-link">เมนู</a>
                <a href="about.php" class="nav-link">เกี่ยวกับเรา</a>
                <a href="contact.php" class="nav-link">ติดต่อ</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="nav-link">แดชบอร์ด</a>
                    <a href="logout.php" class="nav-link">ออกจากระบบ</a>
                <?php else: ?>
                    <a href="login.php" class="nav-link">เข้าสู่ระบบ</a>
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
                    <span>เลือกแพ็กเกจ</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step completed">
                    <i class="fas fa-check-circle"></i>
                    <span>เลือกเมนู</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step completed">
                    <i class="fas fa-check-circle"></i>
                    <span>ชำระเงิน</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step completed">
                    <i class="fas fa-check-double"></i>
                    <span>เสร็จสิ้น</span>
                </div>
            </div>
        </div>

        <!-- Page Title -->
        <h1 class="page-title">
            <i class="fas fa-clipboard-list"></i>
            สถานะการสมัครสมาชิก
        </h1>

        <!-- Success Message -->
        <?php if (isset($_SESSION['flash_message']) && $_SESSION['flash_type'] === 'success'): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="main-card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-list-alt"></i>
                    รายการแพ็กเกจของคุณ
                </h2>
            </div>

            <?php if (empty($subs)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>ยังไม่มีแพ็กเกจการสมัครสมาชิก</h3>
                    <p>เริ่มต้นการเดินทางสุขภาพของคุณกับอาหารไทยเพื่อสุขภาพ</p>
                    <a href="subscribe.php" class="btn">
                        <i class="fas fa-plus"></i>
                        สมัครสมาชิกแพ็กเกจแรก
                    </a>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fas fa-box"></i> แพ็กเกจ</th>
                                <th><i class="fas fa-tag"></i> ประเภท</th>
                                <th><i class="fas fa-utensils"></i> จำนวนมื้อ</th>
                                <th><i class="fas fa-money-bill"></i> ราคา</th>
                                <th><i class="fas fa-info-circle"></i> สถานะ</th>
                                <th><i class="fas fa-calendar-alt"></i> วันเริ่ม</th>
                                <th><i class="fas fa-calendar-times"></i> วันหมดอายุ</th>
                                <th><i class="fas fa-cogs"></i> จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subs as $sub): ?>
                            <tr>
                                <td>
                                    <span class="plan-name"><?= htmlspecialchars($sub['plan_name']) ?></span>
                                </td>
                                <td><?= htmlspecialchars(ucfirst($sub['plan_type'])) ?></td>
                                <td><?= $sub['meals_per_week'] ?> มื้อ/สัปดาห์</td>
                                <td>
                                    <span class="price">฿<?= number_format($sub['final_price']) ?></span>
                                    <span style="color: var(--text-gray); font-size: 0.9rem;">
                                        /<?= $sub['plan_type'] === 'weekly' ? 'สัปดาห์' : 'เดือน' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status <?= $sub['status'] ?>"><?= getStatusText($sub['status']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($sub['start_date']) ?></td>
                                <td><?= htmlspecialchars($sub['end_date'] ?? '-') ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- View Details Button -->
                                        <button onclick="viewDetails('<?= htmlspecialchars($sub['id']) ?>')" class="btn-action btn-view">
                                            <i class="fas fa-eye"></i> ดูรายละเอียด
                                        </button>
                                        
                                        <!-- Management Buttons -->
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($sub['id']) ?>">
                                            <?php if ($sub['status'] === 'active'): ?>
                                                <button name="action" value="pause" class="btn-action btn-pause">
                                                    <i class="fas fa-pause"></i> พัก
                                                </button>
                                                <button name="action" value="cancel" class="btn-action btn-cancel">
                                                    <i class="fas fa-times"></i> ยกเลิก
                                                </button>
                                            <?php elseif ($sub['status'] === 'paused'): ?>
                                                <button name="action" value="renew" class="btn-action btn-renew">
                                                    <i class="fas fa-play"></i> เริ่มใหม่
                                                </button>
                                            <?php else: ?>
                                                <button disabled class="btn-action btn-disabled">
                                                    <i class="fas fa-ban"></i> ไม่สามารถจัดการได้
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
                    กลับแดชบอร์ด
                </a>
            </div>
        </div>
    </div>

    <!-- Modal for Subscription Details -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-clipboard-list"></i>
                    รายละเอียดแพ็กเกจ
                </h3>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Subscription data for modal
        const subscriptionData = <?= json_encode($subs) ?>;
        const menuData = <?= json_encode($subscription_menus) ?>;

        // Add animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate table rows
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

            // Confirm actions
            document.querySelectorAll('.btn-cancel').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    if (!confirm('คุณแน่ใจหรือไม่ที่จะยกเลิกแพ็กเกจนี้?')) {
                        e.preventDefault();
                    }
                });
            });

            document.querySelectorAll('.btn-pause').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    if (!confirm('คุณต้องการพักแพ็กเกจนี้ชั่วคราวหรือไม่?')) {
                        e.preventDefault();
                    }
                });
            });

            // Modal close on backdrop click
            document.getElementById('detailsModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });

            // Keyboard shortcut for modal
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal();
                }
            });
        });

        function viewDetails(subscriptionId) {
            const subscription = subscriptionData.find(sub => sub.id === subscriptionId);
            const menus = menuData[subscriptionId] || [];
            
            if (!subscription) return;

            const deliveryDays = JSON.parse(subscription.delivery_days || '[]');
            const dayNames = {
                'monday': 'จันทร์',
                'tuesday': 'อังคาร', 
                'wednesday': 'พุธ',
                'thursday': 'พฤหัสบดี',
                'friday': 'ศุกร์',
                'saturday': 'เสาร์',
                'sunday': 'อาทิตย์'
            };

            const statusColors = {
                'active': '#27ae60',
                'paused': '#f39c12',
                'cancelled': '#e74c3c',
                'expired': '#636e72',
                'pending_payment': '#3498db'
            };

            // Group menus by day
            const menusByDay = {};
            menus.forEach(menu => {
                // Use delivery_date instead of day_of_week for grouping
                const deliveryDate = menu.delivery_date;
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
                        ข้อมูลพื้นฐาน
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">ชื่อแพ็กเกจ</div>
                            <div class="detail-value">${subscription.plan_name}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">ประเภท</div>
                            <div class="detail-value">${subscription.plan_type === 'weekly' ? 'รายสัปดาห์' : 'รายเดือน'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">จำนวนมื้อต่อสัปดาห์</div>
                            <div class="detail-value">${subscription.meals_per_week} มื้อ</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">ราคา</div>
                            <div class="detail-value" style="color: var(--curry); font-weight: 700;">
                                ฿${Number(subscription.final_price).toLocaleString()}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <div class="detail-title">
                        <i class="fas fa-calendar-alt"></i>
                        ระยะเวลาและการจัดส่ง
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">วันที่เริ่มใช้งาน</div>
                            <div class="detail-value">${subscription.start_date}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">วันที่หมดอายุ</div>
                            <div class="detail-value">${subscription.end_date || 'ไม่มีกำหนด'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">วันที่เรียกเก็บเงินครั้งถัดไป</div>
                            <div class="detail-value">${subscription.next_billing_date || '-'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">เวลาจัดส่งที่ต้องการ</div>
                            <div class="detail-value">
                                ${subscription.preferred_delivery_time === 'morning' ? 'เช้า (8:00-12:00)' :
                                  subscription.preferred_delivery_time === 'afternoon' ? 'บ่าย (12:00-16:00)' :
                                  subscription.preferred_delivery_time === 'evening' ? 'เย็น (16:00-20:00)' : 'ยืดหยุ่น'}
                            </div>
                        </div>
                    </div>
                    
                    ${deliveryDays.length > 0 ? `
                    <div style="margin-top: 1rem;">
                        <div class="detail-label">วันที่จัดส่ง</div>
                        <div class="delivery-days-list">
                            ${deliveryDays.map(day => `<span class="delivery-day-tag">${dayNames[day] || day}</span>`).join('')}
                        </div>
                    </div>
                    ` : ''}
                </div>

                <div class="detail-section">
                    <div class="detail-title">
                        <i class="fas fa-chart-line"></i>
                        สถานะและการตั้งค่า
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">สถานะปัจจุบัน</div>
                            <div class="detail-value">
                                <span style="color: ${statusColors[subscription.status] || '#636e72'}; font-weight: 700;">
                                    ${getStatusText(subscription.status)}
                                </span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">การต่ออายุอัตโนมัติ</div>
                            <div class="detail-value">${subscription.auto_renew == 1 ? '✅ เปิดใช้งาน' : '❌ ปิดใช้งาน'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">วันที่สมัคร</div>
                            <div class="detail-value">${subscription.created_at}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">อัพเดทล่าสุด</div>
                            <div class="detail-value">${subscription.updated_at}</div>
                        </div>
                    </div>
                </div>

                ${menus.length > 0 ? `
                <div class="detail-section">
                    <div class="detail-title">
                        <i class="fas fa-utensils"></i>
                        เมนูที่เลือก (${menus.length} รายการ)
                    </div>
                    ${Object.keys(menusByDay).length > 0 ? 
                        Object.keys(menusByDay).sort().map(date => `
                            <div style="margin-bottom: 1.5rem;">
                                <h4 style="color: var(--curry); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-calendar-day"></i>
                                    วันที่ ${date}
                                </h4>
                                <div class="menus-grid" style="grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));">
                                    ${menusByDay[date].map(menu => `
                                        <div class="menu-card">
                                            ${menu.main_image_url ? 
                                                `<img src="${menu.main_image_url}" alt="${menu.menu_name}" class="menu-image" onerror="this.style.display='none'">` :
                                                `<div class="menu-image" style="display: flex; align-items: center; justify-content: center; color: var(--text-gray);">
                                                    <i class="fas fa-utensils" style="font-size: 2rem;"></i>
                                                </div>`
                                            }
                                            <div class="menu-content">
                                                <div class="menu-name">${menu.menu_name}</div>
                                                ${menu.name_thai ? `<div class="menu-name-thai">${menu.name_thai}</div>` : ''}
                                                ${menu.category_name ? `<div class="menu-category">${menu.category_name}</div>` : ''}
                                                
                                                <div class="menu-nutrition">
                                                    ${menu.calories_per_serving ? `<span><i class="fas fa-fire"></i> ${menu.calories_per_serving} แคล</span>` : ''}
                                                    ${menu.protein_g ? `<span><i class="fas fa-drumstick-bite"></i> ${menu.protein_g}g</span>` : ''}
                                                </div>
                                                
                                                <div class="menu-price">฿${Number(menu.base_price).toLocaleString()}</div>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `).join('') :
                        `<div style="text-align: center; padding: 2rem; color: var(--text-gray);">
                            <i class="fas fa-utensils" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                            <p>ยังไม่มีเมนูที่เลือก</p>
                        </div>`
                    }
                </div>
                ` : ''}

                ${subscription.special_instructions ? `
                <div class="detail-section">
                    <div class="detail-title">
                        <i class="fas fa-sticky-note"></i>
                        คำแนะนำพิเศษ
                    </div>
                    <div class="detail-item">
                        <div class="detail-value">${subscription.special_instructions}</div>
                    </div>
                </div>
                ` : ''}
            `;

            document.getElementById('detailsModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('detailsModal').classList.remove('show');
        }

        function getStatusText(status) {
            const statusMap = {
                'active': 'ใช้งาน',
                'paused': 'พักชั่วคราว',
                'cancelled': 'ยกเลิก',
                'expired': 'หมดอายุ',
                'pending_payment': 'รอชำระเงิน'
            };
            return statusMap[status] || status;
        }
    </script>
</body>
</html>