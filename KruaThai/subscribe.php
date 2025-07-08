<?php
/**
 * Krua Thai - Subscribe Page
 * File: subscribe.php
 * Description: เลือกแพ็กเกจอาหารก่อนเลือกเมนู (Step 1)
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'config/database.php';

// ตรวจสอบว่าผู้ใช้ล็อกอินหรือยัง
if (!isset($_SESSION['user_id'])) {
    $redirect_url = 'subscribe.php';
    if (isset($_GET['menu'])) $redirect_url .= '?menu=' . urlencode($_GET['menu']);
    header('Location: login.php?redirect=' . urlencode($redirect_url));
    exit;
}

$highlight_menu_id = $_GET['menu'] ?? '';

// ดึงแพ็กเกจจากฐานข้อมูล (หรือ mock data หากไม่มี DB)
try {
    $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order ASC");
    $stmt->execute();
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $plans = [
        [
            'id' => 4, 'name_thai' => 'แพ็กเกจเริ่มต้น', 'meals_per_week' => 4, 'final_price' => 899, 'description' => 'เหมาะสำหรับผู้เริ่มต้น'
        ],
        [
            'id' => 8, 'name_thai' => 'กินดีทุกวัน', 'meals_per_week' => 8, 'final_price' => 1599, 'description' => 'เหมาะสำหรับดูแลสุขภาพประจำ'
        ],
        [
            'id' => 12, 'name_thai' => 'สุขภาพทั้งครอบครัว', 'meals_per_week' => 12, 'final_price' => 2199, 'description' => 'เหมาะสำหรับครอบครัว'
        ],
        [
            'id' => 15, 'name_thai' => 'พรีเมียมโปร', 'meals_per_week' => 15, 'final_price' => 2699, 'description' => 'สำหรับสาย Healthy จัดเต็ม'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เลือกแพ็กเกจอาหาร | Krua Thai</title>
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

        /* Main Container */
        .container {
            max-width: 1000px;
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
        }

        /* Hero Section */
        .hero-section {
            background: var(--white);
            border-radius: var(--radius-xl);
            padding: 3rem 2rem;
            text-align: center;
            margin-bottom: 3rem;
            box-shadow: var(--shadow-medium);
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--curry), var(--brown), var(--sage));
        }

        .hero-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }

        .hero-subtitle {
            font-size: 1.2rem;
            color: var(--text-gray);
            max-width: 600px;
            margin: 0 auto 2rem;
        }

        .hero-features {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .hero-feature {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            background: var(--cream);
            border-radius: var(--radius-lg);
            color: var(--curry);
            font-weight: 600;
        }

        .hero-feature i {
            font-size: 1.1rem;
        }

        /* Plans Grid */
        .plans-section {
            margin-bottom: 3rem;
        }

        .section-title {
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 1rem;
            color: var(--text-dark);
        }

        .section-subtitle {
            font-size: 1.1rem;
            color: var(--text-gray);
            text-align: center;
            margin-bottom: 2.5rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }

        .plan-card {
            background: var(--white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-soft);
            padding: 2.5rem 2rem;
            text-align: center;
            transition: var(--transition);
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .plan-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--sage);
            transform: scaleX(0);
            transition: var(--transition);
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-large);
            border-color: var(--curry);
        }

        .plan-card:hover::before {
            transform: scaleX(1);
            background: var(--curry);
        }

        .plan-card.selected {
            border-color: var(--curry);
            box-shadow: 0 8px 32px rgba(207, 114, 58, 0.2);
        }

        .plan-card.selected::before {
            transform: scaleX(1);
            background: var(--curry);
        }

        .plan-card.selected::after {
            content: '✓';
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: var(--success);
            color: var(--white);
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .plan-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--curry);
            margin-bottom: 0.5rem;
        }

        .plan-info {
            color: var(--text-gray);
            font-size: 1rem;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .plan-price {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .plan-price .currency {
            font-size: 1.5rem;
            color: var(--curry);
        }

        .plan-period {
            color: var(--text-gray);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        .plan-desc {
            font-size: 1rem;
            color: var(--text-gray);
            margin-bottom: 2rem;
            line-height: 1.5;
        }

        .plan-features {
            list-style: none;
            margin-bottom: 2rem;
            text-align: left;
        }

        .plan-features li {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.5rem 0;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        .plan-features i {
            color: var(--success);
            font-size: 1rem;
            width: 1rem;
        }

        /* Button */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            border-radius: var(--radius-xl);
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            font-weight: 700;
            font-size: 1.1rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(207, 114, 58, 0.3);
        }

        .btn:hover {
            background: linear-gradient(135deg, var(--brown), var(--sage));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(207, 114, 58, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem 1rem 3rem;
            }

            .hero-title {
                font-size: 2rem;
            }

            .hero-subtitle {
                font-size: 1rem;
            }

            .hero-features {
                gap: 1rem;
            }

            .hero-feature {
                font-size: 0.9rem;
                padding: 0.6rem 1rem;
            }

            .plans-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .plan-card {
                padding: 2rem 1.5rem;
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

            .hero-section {
                padding: 2rem 1.5rem;
            }

            .hero-features {
                flex-direction: column;
                align-items: center;
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
                <div class="progress-step active">
                    <i class="fas fa-check-circle"></i>
                    <span>เลือกแพ็กเกจ</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step">
                    <i class="fas fa-utensils"></i>
                    <span>เลือกเมนู</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step">
                    <i class="fas fa-credit-card"></i>
                    <span>ชำระเงิน</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step">
                    <i class="fas fa-check-double"></i>
                    <span>เสร็จสิ้น</span>
                </div>
            </div>
        </div>

        <!-- Hero Section -->
        <div class="hero-section">
            <h1 class="hero-title">เลือกแพ็กเกจอาหารของคุณ</h1>
            <p class="hero-subtitle">
                เลือกแพ็กเกจที่เหมาะกับไลฟ์สไตล์ของคุณ จากนั้นจะไปเลือกเมนูตามจำนวนที่กำหนด
                อาหารไทยเพื่อสุขภาพ ส่งถึงบ้านทุกวัน
            </p>
            <div class="hero-features">
                <div class="hero-feature">
                    <i class="fas fa-leaf"></i>
                    <span>วัตถุดิบสด</span>
                </div>
                <div class="hero-feature">
                    <i class="fas fa-truck"></i>
                    <span>ส่งฟรีทุกวัน</span>
                </div>
                <div class="hero-feature">
                    <i class="fas fa-heart"></i>
                    <span>เพื่อสุขภาพ</span>
                </div>
                <div class="hero-feature">
                    <i class="fas fa-star"></i>
                    <span>รสชาติดั้งเดิม</span>
                </div>
            </div>
        </div>

        <!-- Plans Section -->
        <div class="plans-section">
            <h2 class="section-title">แพ็กเกจอาหารของเรา</h2>
            <p class="section-subtitle">
                แพ็กเกจที่หลากหลาย เหมาะกับทุกไลฟ์สไตล์ พร้อมความยืดหยุ่นในการปรับเปลี่ยนตามต้องการ
            </p>
            
            <div class="plans-grid">
                <?php foreach($plans as $index => $plan): 
                    // ถ้า highlight menu id ส่ง query ไปหน้า meal-selection ต่อ
                    $query = "plan=" . urlencode($plan['id']);
                    if ($highlight_menu_id) $query .= "&menu=" . urlencode($highlight_menu_id);
                    
                    $isPopular = ($plan['meals_per_week'] == 8); // ทำให้แพ็กเกจ 8 มื้อเป็น popular
                ?>
                <div class="plan-card<?php echo $isPopular ? ' selected' : ''; ?>">
                    <div class="plan-name"><?php echo htmlspecialchars($plan['name_thai']); ?></div>
                    <div class="plan-info"><?php echo $plan['meals_per_week']; ?> มื้อต่อสัปดาห์</div>
                    <div class="plan-price">
                        <span class="currency">฿</span><?php echo number_format($plan['final_price'], 0); ?>
                    </div>
                    <div class="plan-period">ต่อสัปดาห์</div>
                    <div class="plan-desc"><?php echo htmlspecialchars($plan['description']); ?></div>
                    
                    <ul class="plan-features">
                        <li>
                            <i class="fas fa-utensils"></i>
                            <span><?php echo $plan['meals_per_week']; ?> มื้ออาหารสุขภาพ</span>
                        </li>
                        <li>
                            <i class="fas fa-truck"></i>
                            <span>ส่งฟรีถึงบ้าน</span>
                        </li>
                        <li>
                            <i class="fas fa-calendar-alt"></i>
                            <span>ยืดหยุ่นในการเปลี่ยนแปลง</span>
                        </li>
                        <li>
                            <i class="fas fa-leaf"></i>
                            <span>วัตถุดิบคุณภาพสูง</span>
                        </li>
                        <?php if ($isPopular): ?>
                        <li>
                            <i class="fas fa-crown"></i>
                            <span>แพ็กเกจยอดนิยม</span>
                        </li>
                        <?php endif; ?>
                    </ul>
                    
                    <a href="meal-selection.php?<?php echo $query; ?>" class="btn">
                        เลือกแพ็กเกจนี้
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        // Add smooth scrolling and enhanced interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading animation to plan cards
            const planCards = document.querySelectorAll('.plan-card');
            planCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Add click tracking for analytics
            planCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    if (!e.target.matches('.btn, .btn *')) {
                        const link = this.querySelector('.btn');
                        if (link) {
                            link.click();
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>