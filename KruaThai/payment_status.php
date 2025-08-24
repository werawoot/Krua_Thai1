<?php
/**
 * Krua Thai - Payment Status & History Page (US ENGLISH VERSION)
 * File: payment_status.php
 * Description: Displays the logged-in user's payment history
 * Status: PRODUCTION READY ✅
 * Language: English (US)
 * Market: United States
 * UI Theme: Matches nutrition-tracking.php design system
 */
error_reporting(E_ALL);
ini_set('display_errors', 1); // Set to 0 in production

session_start();

// --- 1. Configuration and Login Check ---
require_once 'config/database.php';
require_once 'includes/functions.php'; // If you have global functions

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=payment_status.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$page_error = null;
$payments = [];

// --- 2. Fetch Payment History Data ---
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->prepare(
        "SELECT 
            p.transaction_id, 
            p.amount, 
            p.currency, 
            p.status, 
            p.payment_date, 
            p.description,
            p.payment_method
         FROM payments p
         WHERE p.user_id = ?
         ORDER BY p.payment_date DESC"
    );
    $stmt->execute([$user_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user info
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // logError('payment_status.php', $e->getMessage());
    $page_error = "Error retrieving payment information";
}

// --- Helper Function for Status Display ---
function getStatusInfo($status) {
    $map = [
        'completed' => ['text' => 'Completed', 'class' => 'completed', 'icon' => 'fa-check-circle'],
        'pending'   => ['text' => 'Pending', 'class' => 'pending', 'icon' => 'fa-clock'],
        'failed'    => ['text' => 'Failed', 'class' => 'failed', 'icon' => 'fa-times-circle'],
        'refunded'  => ['text' => 'Refunded', 'class' => 'refunded', 'icon' => 'fa-undo'],
        'partial_refund' => ['text' => 'Partial Refund', 'class' => 'refunded', 'icon' => 'fa-undo-alt']
    ];
    return $map[strtolower($status)] ?? ['text' => ucfirst($status), 'class' => 'unknown', 'icon' => 'fa-question-circle'];
}

// Include the header (same as nutrition-tracking.php)
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Krua Thai</title>
    <meta name="description" content="View your complete payment history for Krua Thai meal subscriptions">
    
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
    /* USING SAME DESIGN SYSTEM AS nutrition-tracking.php - Mobile Optimized */
    
    /* BaticaSans Font Import */
    @font-face {
        font-family: 'BaticaSans';
        src: url('https://ydpschool.com/fonts/BaticaSans-Regular.woff2') format('woff2');
        font-weight: 400;
        font-style: normal;
        font-display: swap;
    }
    @font-face {
        font-family: 'BaticaSans';
        src: url('https://ydpschool.com/fonts/BaticaSans-Bold.woff2') format('woff2');
        font-weight: 700;
        font-style: normal;
        font-display: swap;
    }
    @font-face {
        font-family: 'BaticaSans';
        src: url('https://ydpschool.com/fonts/BaticaSans-Medium.woff2') format('woff2');
        font-weight: 500;
        font-style: normal;
        font-display: swap;
    }

    /* CSS Custom Properties - Same as nutrition-tracking.php */
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
        --success: #27ae60;
        --warning: #f39c12;
        --danger: #e74c3c;
        --info: #3498db;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, sans-serif;
        line-height: 1.6;
        color: var(--text-dark);
        background-color: var(--cream);
        font-weight: 400;
        min-height: 100vh;
    }

    h1, h2, h3, h4, h5, h6 {
        font-family: 'BaticaSans', sans-serif;
        font-weight: 700;
        line-height: 1.2;
        color: var(--text-dark);
    }

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

    /* Main Content Card */
    .main-card {
        background: var(--white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-medium);
        overflow: hidden;
        position: relative;
        border: 1px solid var(--border-light);
        margin-bottom: 2rem;
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
        margin-bottom: 0.5rem;
    }

    .card-title i {
        color: var(--curry);
    }

    .card-subtitle {
        color: var(--text-gray);
        font-size: 1rem;
        font-family: 'BaticaSans', sans-serif;
    }

    /* Payment Cards Grid */
    .payments-grid {
        display: grid;
        gap: 1.5rem;
        padding: 2rem;
    }

    .payment-card {
        background: var(--white);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-light);
        box-shadow: var(--shadow-soft);
        transition: var(--transition);
        overflow: hidden;
        position: relative;
    }

    .payment-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-medium);
    }

    .payment-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-light);
        background: linear-gradient(135deg, rgba(173, 184, 157, 0.03), rgba(189, 147, 121, 0.03));
    }

    .payment-meta {
        flex: 1;
    }

    .payment-transaction-id {
        font-family: 'Courier New', monospace;
        background: var(--cream);
        padding: 0.3rem 0.6rem;
        border-radius: var(--radius-sm);
        font-size: 0.85rem;
        color: var(--text-dark);
        font-weight: 600;
        margin-bottom: 0.5rem;
        display: inline-block;
    }

    .payment-description {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 0.3rem;
        font-size: 1.05rem;
    }

    .payment-date {
        font-size: 0.9rem;
        color: var(--text-gray);
        font-family: 'BaticaSans', sans-serif;
    }

    .payment-amount {
        text-align: right;
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--curry);
        font-family: 'BaticaSans', sans-serif;
        margin-bottom: 0.5rem;
    }

    .payment-card-body {
        padding: 1.5rem;
    }

    .payment-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .payment-detail-item {
        text-align: center;
        padding: 1rem;
        background: var(--cream);
        border-radius: var(--radius-md);
        border: 1px solid var(--border-light);
    }

    .payment-detail-label {
        font-size: 0.8rem;
        color: var(--text-gray);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.3rem;
        font-weight: 600;
    }

    .payment-detail-value {
        font-weight: 700;
        color: var(--text-dark);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 25px;
        font-weight: 600;
        font-size: 0.85rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .status-badge.completed {
        background: #d4edda;
        color: var(--success);
        border: 1px solid #c3e6cb;
    }

    .status-badge.pending {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }

    .status-badge.failed {
        background: #f8d7da;
        color: var(--danger);
        border: 1px solid #f5c6cb;
    }

    .status-badge.refunded {
        background: #d1ecf1;
        color: var(--info);
        border: 1px solid #bee5eb;
    }

    .status-badge.unknown {
        background: #e2e3e5;
        color: #6c757d;
        border: 1px solid #d6d8db;
    }

    /* Payment Method Icons */
    .payment-method-icon {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
    }

    .payment-method-icon::before {
        content: "💳";
        font-size: 1.1rem;
    }

    /* Summary Stats */
    .summary-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        padding: 2rem;
    }

    .stat-card {
        background: var(--white);
        padding: 1.5rem;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-soft);
        text-align: center;
        border-top: 4px solid var(--curry);
        transition: var(--transition);
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-medium);
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--curry);
        margin-bottom: 0.5rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .stat-label {
        color: var(--text-gray);
        font-weight: 500;
        font-family: 'BaticaSans', sans-serif;
    }

    .stat-card.success {
        border-top-color: var(--success);
    }

    .stat-card.success .stat-value {
        color: var(--success);
    }

    .stat-card.warning {
        border-top-color: var(--warning);
    }

    .stat-card.warning .stat-value {
        color: #856404;
    }

    .stat-card.info {
        border-top-color: var(--info);
    }

    .stat-card.info .stat-value {
        color: var(--info);
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-gray);
    }

    .empty-state i {
        font-size: 4rem;
        color: var(--sage);
        opacity: 0.5;
        margin-bottom: 1.5rem;
        display: block;
    }

    .empty-state h3 {
        font-size: 1.5rem;
        margin-bottom: 1rem;
        color: var(--brown);
        font-family: 'BaticaSans', sans-serif;
        font-weight: 700;
    }

    .empty-state p {
        margin-bottom: 2rem;
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
        font-family: 'BaticaSans', sans-serif;
    }

    .empty-state .btn {
        background: var(--curry);
        color: var(--white);
        border: none;
        padding: 1rem 2rem;
        border-radius: var(--radius-lg);
        text-decoration: none;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: var(--transition);
        touch-action: manipulation;
    }

    .empty-state .btn:hover {
        background: var(--brown);
        transform: translateY(-1px);
        box-shadow: var(--shadow-soft);
    }

    /* Error State */
    .error-state {
        text-align: center;
        padding: 2rem;
        background: #f8d7da;
        color: #721c24;
        border-radius: var(--radius-md);
        border: 1px solid #f5c6cb;
        margin: 2rem;
    }

    /* Bottom Navigation */
    .bottom-nav {
        padding: 2rem;
        border-top: 1px solid var(--border-light);
        background: var(--cream);
    }

    .nav-links {
        display: flex;
        justify-content: center;
        gap: 2rem;
        flex-wrap: wrap;
    }

    .nav-link {
        color: var(--brown);
        text-decoration: none;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: var(--transition);
        padding: 0.5rem 1rem;
        border-radius: var(--radius-md);
        touch-action: manipulation;
        min-height: 44px;
    }

    .nav-link:hover {
        color: var(--curry);
        background: rgba(207, 114, 58, 0.1);
        transform: translateY(-1px);
    }

    /* Loading Animation */
    .loading {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 3rem;
        color: var(--text-gray);
        font-family: 'BaticaSans', sans-serif;
    }

    .spinner {
        width: 30px;
        height: 30px;
        border: 3px solid var(--border-light);
        border-top: 3px solid var(--curry);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-right: 1rem;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Mobile Responsive Design */
    @media (max-width: 768px) {
        .page-title {
            font-size: 2rem;
            margin-bottom: 1.5rem;
        }

        .payments-grid {
            padding: 1.5rem;
            gap: 1rem;
        }

        .payment-card-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
        }

        .payment-amount {
            text-align: left;
            font-size: 1.2rem;
        }

        .card-header {
            padding: 1.5rem;
        }

        .summary-stats {
            grid-template-columns: repeat(2, 1fr);
            padding: 1.5rem;
            gap: 1rem;
        }

        .payment-details-grid {
            grid-template-columns: 1fr;
            gap: 0.8rem;
        }

        .nav-links {
            flex-direction: column;
            gap: 1rem;
            align-items: center;
        }

        .payment-card-body {
            padding: 1rem;
        }
    }

    @media (max-width: 480px) {
        .container {
            padding: 0 15px;
        }

        .page-title {
            font-size: 1.8rem;
        }

        .card-title {
            font-size: 1.3rem;
        }

        .summary-stats {
            grid-template-columns: 1fr;
        }

        .stat-value {
            font-size: 1.5rem;
        }

        .payment-transaction-id {
            font-size: 0.75rem;
            padding: 0.2rem 0.5rem;
        }

        .payment-description {
            font-size: 1rem;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
    }

    /* Touch-friendly interactions */
    @media (hover: none) {
        .payment-card:hover,
        .stat-card:hover {
            transform: none;
        }
        
        .nav-link:hover {
            transform: none;
        }
    }

    /* Accessibility improvements */
    @media (prefers-reduced-motion: reduce) {
        .spinner {
            animation: none;
        }
        
        * {
            transition: none;
        }
    }

    /* High contrast mode */
    @media (prefers-contrast: high) {
        .payment-card,
        .stat-card {
            border: 2px solid var(--text-dark);
        }
    }

    /* Animation for cards loading */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .payment-card {
        animation: fadeInUp 0.5s ease forwards;
    }

    .payment-card:nth-child(2) { animation-delay: 0.1s; }
    .payment-card:nth-child(3) { animation-delay: 0.2s; }
    .payment-card:nth-child(4) { animation-delay: 0.3s; }
    </style>
</head>

<body class="has-header">
    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Page Title -->
            <h1 class="page-title">
                <i class="fas fa-receipt"></i>
                Payment History
            </h1>

            <!-- Main Payment History Card -->
            <div class="main-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-credit-card"></i>
                        Your Payment Records
                    </h2>
                    <p class="card-subtitle">
                        Hello <?= htmlspecialchars($user_info['first_name'] ?? 'there') ?>! Here's your complete payment history for Krua Thai services
                    </p>
                </div>

                <?php if ($page_error): ?>
                    <div class="error-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Error:</strong> <?php echo htmlspecialchars($page_error); ?>
                    </div>
                <?php elseif (empty($payments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <h3>No Payment History Yet</h3>
                        <p>When you start subscribing to meal packages, your payment records will appear here</p>
                        <a href="subscribe.php" class="btn">
                            <i class="fas fa-plus"></i>
                            Choose a Package
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Payments Grid -->
                    <div class="payments-grid">
                        <?php foreach ($payments as $index => $payment):
                            $status_info = getStatusInfo($payment['status']);
                        ?>
                            <div class="payment-card" style="animation-delay: <?= $index * 0.1 ?>s;">
                                <!-- Payment Card Header -->
                                <div class="payment-card-header">
                                    <div class="payment-meta">
                                        <div class="payment-transaction-id">
                                            <?= htmlspecialchars($payment['transaction_id']) ?>
                                        </div>
                                        <div class="payment-description">
                                            <?= htmlspecialchars($payment['description']) ?>
                                        </div>
                                        <div class="payment-date">
                                            <i class="fas fa-calendar"></i>
                                            <?= date('F j, Y g:i A', strtotime($payment['payment_date'])) ?>
                                        </div>
                                    </div>
                                    <div class="payment-amount">
                                        $<?= number_format($payment['amount'], 2) ?>
                                    </div>
                                </div>

                                <!-- Payment Card Body -->
                                <div class="payment-card-body">
                                    <div class="payment-details-grid">
                                        <!-- Payment Method -->
                                        <div class="payment-detail-item">
                                            <div class="payment-detail-label">Payment Method</div>
                                            <div class="payment-detail-value">
                                                <span class="payment-method-icon">
                                                    <?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Status -->
                                        <div class="payment-detail-item">
                                            <div class="payment-detail-label">Status</div>
                                            <div class="payment-detail-value">
                                                <div class="status-badge <?= $status_info['class'] ?>">
                                                    <i class="fas <?= $status_info['icon'] ?>"></i>
                                                    <span><?= $status_info['text'] ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Currency -->
                                        <div class="payment-detail-item">
                                            <div class="payment-detail-label">Currency</div>
                                            <div class="payment-detail-value">
                                                <i class="fas fa-dollar-sign" style="color: var(--curry);"></i>
                                                <?= strtoupper($payment['currency']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Payment Summary Stats -->
            <?php if (!empty($payments)): ?>
                <div class="main-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-chart-bar"></i>
                            Payment Summary
                        </h2>
                        <p class="card-subtitle">
                            Your payment statistics and totals
                        </p>
                    </div>

                    <div class="summary-stats">
                        <?php
                        $total_paid = 0;
                        $successful_payments = 0;
                        $pending_payments = 0;
                        
                        foreach ($payments as $payment) {
                            if ($payment['status'] === 'completed') {
                                $total_paid += $payment['amount'];
                                $successful_payments++;
                            } elseif ($payment['status'] === 'pending') {
                                $pending_payments++;
                            }
                        }
                        ?>
                        
                        <div class="stat-card success">
                            <div class="stat-value">$<?= number_format($total_paid, 2) ?></div>
                            <div class="stat-label">Total Paid</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value"><?= $successful_payments ?></div>
                            <div class="stat-label">Successful Payments</div>
                        </div>
                        
                        <div class="stat-card warning">
                            <div class="stat-value"><?= $pending_payments ?></div>
                            <div class="stat-label">Pending Payments</div>
                        </div>
                        
                        <div class="stat-card info">
                            <div class="stat-value"><?= count($payments) ?></div>
                            <div class="stat-label">Total Transactions</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Bottom Navigation -->
            <div class="main-card">
                <div class="bottom-nav">
                    <div class="nav-links">
                        <a href="dashboard.php" class="nav-link">
                            <i class="fas fa-home"></i>
                            Dashboard
                        </a>
                        <a href="subscription-status.php" class="nav-link">
                            <i class="fas fa-clipboard-list"></i>
                            Order Status
                        </a>
                        <a href="nutrition-tracking.php" class="nav-link">
                            <i class="fas fa-chart-pie"></i>
                            Nutrition
                        </a>
                        <a href="subscribe.php" class="nav-link">
                            <i class="fas fa-plus"></i>
                            Order Meals
                        </a>
                        <a href="help.php" class="nav-link">
                            <i class="fas fa-question-circle"></i>
                            Help
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // JavaScript for enhanced user experience - same approach as nutrition-tracking.php
        document.addEventListener('DOMContentLoaded', function() {
            console.log('💳 Payment History page loaded successfully!');
            
            // Initialize page
            initializePage();
            
            // Add touch-friendly interactions for mobile
            addMobileInteractions();
            
            // Auto-refresh functionality
            setupAutoRefresh();
        });

        function initializePage() {
            // Animate cards on load
            const cards = document.querySelectorAll('.payment-card, .stat-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Add click handlers for payment cards (for mobile interaction feedback)
            const paymentCards = document.querySelectorAll('.payment-card');
            paymentCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Show transaction details in mobile-friendly way
                    const transactionId = this.querySelector('.payment-transaction-id').textContent;
                    showTransactionDetails(transactionId);
                });
            });
        }

        function addMobileInteractions() {
            // Add touch feedback for interactive elements
            const interactiveElements = document.querySelectorAll('.nav-link, .payment-card, .stat-card');
            
            interactiveElements.forEach(element => {
                element.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.98)';
                }, { passive: true });
                
                element.addEventListener('touchend', function() {
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                }, { passive: true });
            });
        }

        function setupAutoRefresh() {
            // Auto-refresh page every 5 minutes to check for payment updates
            setInterval(function() {
                if (!document.hidden) {
                    console.log('🔄 Auto-refreshing payment data...');
                    window.location.reload();
                }
            }, 300000); // 5 minutes

            // Also refresh when page becomes visible again
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    // Check if we should refresh (if page was hidden for more than 2 minutes)
                    const lastRefresh = localStorage.getItem('lastPaymentRefresh');
                    const now = Date.now();
                    
                    if (!lastRefresh || (now - lastRefresh) > 120000) { // 2 minutes
                        localStorage.setItem('lastPaymentRefresh', now.toString());
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                }
            });
        }

        function showTransactionDetails(transactionId) {
            // Mobile-friendly transaction details modal
            const modalHTML = `
                <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 1rem;" onclick="this.remove()">
                    <div style="background: white; border-radius: 1rem; padding: 2rem; max-width: 400px; width: 100%; max-height: 80vh; overflow-y: auto;" onclick="event.stopPropagation()">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                            <h3 style="color: var(--brown); margin: 0; font-family: 'BaticaSans', sans-serif;">
                                <i class="fas fa-receipt" style="color: var(--curry);"></i>
                                Transaction Details
                            </h3>
                            <button onclick="this.closest('div[style*=\"position: fixed\"]').remove()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-gray); width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">&times;</button>
                        </div>
                        
                        <div style="background: var(--cream); padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                            <div style="font-family: 'Courier New', monospace; font-weight: 600; color: var(--text-dark);">${transactionId}</div>
                        </div>
                        
                        <div style="text-align: center; color: var(--text-gray); margin-top: 1rem;">
                            <p>For detailed transaction information, please contact our support team.</p>
                            <div style="margin-top: 1rem;">
                                <a href="help.php" style="background: var(--curry); color: white; padding: 0.8rem 1.5rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-headset"></i>
                                    Contact Support
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }

        // Utility functions for notifications
        function showNotification(message, type = 'info') {
            const colors = {
                success: '#27ae60',
                error: '#e74c3c',
                info: '#3498db',
                warning: '#f39c12'
            };
            
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 2rem;
                right: 2rem;
                background: ${colors[type]};
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 0.5rem;
                font-family: 'BaticaSans', sans-serif;
                font-weight: 600;
                z-index: 10000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                transform: translateX(100%);
                transition: transform 0.3s ease;
                max-width: 300px;
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Animate in
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            // Remove after delay
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 4000);
        }

        // Keyboard navigation support
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Close any open modals
                const modals = document.querySelectorAll('[style*="position: fixed"]');
                modals.forEach(modal => {
                    modal.remove();
                });
            }
        });

        // Copy transaction ID functionality
        function copyTransactionId(transactionId) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(transactionId).then(() => {
                    showNotification('Transaction ID copied to clipboard! 📋', 'success');
                }).catch(() => {
                    showNotification('Failed to copy transaction ID', 'error');
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = transactionId;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                try {
                    document.execCommand('copy');
                    showNotification('Transaction ID copied to clipboard! 📋', 'success');
                } catch (error) {
                    showNotification('Failed to copy transaction ID', 'error');
                }
                
                textArea.remove();
            }
        }

        // Add click-to-copy functionality to transaction IDs
        document.querySelectorAll('.payment-transaction-id').forEach(element => {
            element.style.cursor = 'pointer';
            element.title = 'Click to copy transaction ID';
            element.addEventListener('click', function(e) {
                e.stopPropagation();
                copyTransactionId(this.textContent.trim());
            });
        });

        // Swipe gesture support for mobile navigation
        let touchStartX = 0;
        let touchEndX = 0;

        document.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        document.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        }, { passive: true });

        function handleSwipe() {
            const swipeThreshold = 100;
            const swipeDistance = touchEndX - touchStartX;
            
            if (Math.abs(swipeDistance) > swipeThreshold) {
                if (swipeDistance > 0) {
                    // Swipe right - navigate back to dashboard
                    console.log('Swipe right detected - could navigate to dashboard');
                } else {
                    // Swipe left - navigate to nutrition tracking
                    console.log('Swipe left detected - could navigate to nutrition');
                }
            }
        }

        // Performance monitoring
        window.addEventListener('load', function() {
            // Log page load performance
            if (window.performance && window.performance.timing) {
                const loadTime = window.performance.timing.loadEventEnd - window.performance.timing.navigationStart;
                console.log(`💳 Payment page loaded in ${loadTime}ms`);
            }
        });

        // Error handling for images and external resources
        document.addEventListener('error', function(e) {
            if (e.target.tagName === 'IMG') {
                console.log('Image failed to load:', e.target.src);
                // Could implement fallback image logic here
            }
        }, true);

        // Initialize tooltips for status badges (simple implementation)
        document.querySelectorAll('.status-badge').forEach(badge => {
            const status = badge.textContent.trim();
            let tooltip = '';
            
            switch(status.toLowerCase()) {
                case 'completed':
                    tooltip = 'Payment was processed successfully';
                    break;
                case 'pending':
                    tooltip = 'Payment is being processed';
                    break;
                case 'failed':
                    tooltip = 'Payment could not be processed';
                    break;
                case 'refunded':
                    tooltip = 'Payment has been refunded';
                    break;
                default:
                    tooltip = 'Payment status: ' + status;
            }
            
            badge.title = tooltip;
        });

        // Set last refresh timestamp
        localStorage.setItem('lastPaymentRefresh', Date.now().toString());
        
        console.log('🍽️ Krua Thai Payment History page fully initialized!');
    </script>
</body>
</html>