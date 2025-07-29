<?php
/**
 * Krua Thai - Payment Status & History Page (US ENGLISH VERSION)
 * File: payment_status.php
 * Description: Displays the logged-in user's payment history
 * Status: PRODUCTION READY ✅
 * Language: English (US)
 * Market: United States
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment History - Krua Thai</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- BaticaSans Font -->
    <link rel="preconnect" href="https://ydpschool.com">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
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
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
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
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
            line-height: 1.2;
            color: var(--text-dark);
        }

        /* Navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            z-index: 1000;
            box-shadow: var(--shadow-soft);
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
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

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-gray);
            font-weight: 500;
            transition: var(--transition);
        }

        .nav-links a:hover {
            color: var(--curry);
        }

        .nav-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            box-shadow: var(--shadow-soft);
        }

        .btn-primary:hover {
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

        /* Main Content */
        .main-content {
            margin-top: 80px;
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--sage) 0%, var(--curry) 100%);
            color: var(--white);
            padding: 3rem 0;
            margin-bottom: 3rem;
            border-radius: var(--radius-lg);
            text-align: center;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--white);
        }

        .page-title i {
            color: var(--white);
            margin-right: 1rem;
        }

        .page-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Table Container */
        .table-container {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-medium);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1.2rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }

        th {
            background: var(--curry);
            color: var(--white);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        tbody tr {
            transition: var(--transition);
        }

        tbody tr:hover {
            background-color: var(--cream);
        }

        tbody tr:last-child td {
            border-bottom: none;
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

        /* Payment Method */
        .payment-method {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        .payment-method::before {
            content: "💳";
            font-size: 1.2rem;
        }

        /* Price Display */
        .price {
            font-weight: 700;
            color: var(--curry);
            font-size: 1.1rem;
        }

        /* Transaction ID */
        .transaction-id {
            font-family: 'Courier New', monospace;
            background: var(--cream);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            color: var(--text-dark);
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
            color: var(--text-dark);
        }

        .empty-state p {
            margin-bottom: 2rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Error State */
        .error-state {
            text-align: center;
            padding: 2rem;
            background: #f8d7da;
            color: #721c24;
            border-radius: var(--radius-md);
            border: 1px solid #f5c6cb;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .page-header {
                margin-bottom: 2rem;
                padding: 2rem 0;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .nav-links {
                display: none;
            }
            
            th, td {
                padding: 0.8rem;
                font-size: 0.9rem;
            }
            
            .page-subtitle {
                font-size: 1rem;
            }
        }

        @media (max-width: 576px) {
            .table-wrapper {
                font-size: 0.85rem;
            }
            
            th, td {
                padding: 0.6rem 0.5rem;
            }
            
            .status-badge {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
        }

        /* Loading Animation */
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 3rem;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border-light);
            border-top: 4px solid var(--curry);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <a href="index.php" class="logo">
                <img src="./assets/image/LOGO_BG.png" alt="Krua Thai" style="height: 40px; width: auto;" onerror="this.style.display='none';">
                <span class="logo-text">Krua Thai</span>
            </a>
            
            <ul class="nav-links">
                <li><a href="./menus.php">Menu</a></li>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="notifications.php">Notifications</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            
            <div class="nav-actions">
                <a href="logout.php" class="btn btn-secondary">Sign Out</a>
                <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="container">
                <h1 class="page-title"><i class="fas fa-receipt"></i> Payment History</h1>
                <p class="page-subtitle">Complete record of all your payments for Krua Thai services</p>
            </div>
        </div>

        <div class="container">
            <div class="table-container">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fas fa-calendar-alt"></i> Date</th>
                                <th><i class="fas fa-hashtag"></i> Transaction ID</th>
                                <th><i class="fas fa-file-alt"></i> Description</th>
                                <th><i class="fas fa-credit-card"></i> Method</th>
                                <th><i class="fas fa-dollar-sign"></i> Amount</th>
                                <th><i class="fas fa-info-circle"></i> Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($page_error): ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="error-state">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <strong>Error:</strong> <?php echo htmlspecialchars($page_error); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php elseif (empty($payments)): ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class="fas fa-file-invoice-dollar"></i>
                                            <h3>No Payment History Yet</h3>
                                            <p>When you start subscribing to meal packages, your payment records will appear here</p>
                                            <a href="subscribe.php" class="btn btn-primary">Choose a Package</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $payment):
                                    $status_info = getStatusInfo($payment['status']);
                                ?>
                                <tr>
                                    <td><?php echo date('m/d/Y g:i A', strtotime($payment['payment_date'])); ?></td>
                                    <td>
                                        <span class="transaction-id"><?php echo htmlspecialchars($payment['transaction_id']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['description']); ?></td>
                                    <td>
                                        <span class="payment-method">
                                            <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="price">
                                            $<?php echo number_format($payment['amount'], 2); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <div class="status-badge <?php echo $status_info['class']; ?>">
                                            <i class="fas <?php echo $status_info['icon']; ?>"></i>
                                            <span><?php echo $status_info['text']; ?></span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payment Summary Card -->
            <?php if (!empty($payments)): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
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
                
                <div style="background: var(--white); padding: 2rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-soft); text-align: center; border-top: 4px solid var(--success);">
                    <div style="font-size: 2rem; font-weight: 700; color: var(--success); margin-bottom: 0.5rem;">
                        $<?php echo number_format($total_paid, 2); ?>
                    </div>
                    <div style="color: var(--text-gray); font-weight: 500;">Total Paid</div>
                </div>
                
                <div style="background: var(--white); padding: 2rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-soft); text-align: center; border-top: 4px solid var(--curry);">
                    <div style="font-size: 2rem; font-weight: 700; color: var(--curry); margin-bottom: 0.5rem;">
                        <?php echo $successful_payments; ?>
                    </div>
                    <div style="color: var(--text-gray); font-weight: 500;">Successful Payments</div>
                </div>
                
                <div style="background: var(--white); padding: 2rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-soft); text-align: center; border-top: 4px solid var(--warning);">
                    <div style="font-size: 2rem; font-weight: 700; color: #856404; margin-bottom: 0.5rem;">
                        <?php echo $pending_payments; ?>
                    </div>
                    <div style="color: var(--text-gray); font-weight: 500;">Pending Payments</div>
                </div>
                
                <div style="background: var(--white); padding: 2rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-soft); text-align: center; border-top: 4px solid var(--info);">
                    <div style="font-size: 2rem; font-weight: 700; color: var(--info); margin-bottom: 0.5rem;">
                        <?php echo count($payments); ?>
                    </div>
                    <div style="color: var(--text-gray); font-weight: 500;">Total Transactions</div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-refresh page every 5 minutes to check for payment updates
        setInterval(function() {
            window.location.reload();
        }, 300000);

        // Add loading state when navigating away
        document.addEventListener('DOMContentLoaded', function() {
            const links = document.querySelectorAll('a[href]');
            links.forEach(link => {
                link.addEventListener('click', function() {
                    // Add loading spinner for external navigation
                    if (!this.href.includes('#')) {
                        document.body.style.cursor = 'wait';
                    }
                });
            });
        });

        console.log('🍽️ Krua Thai - Payment History page loaded successfully!');
    </script>
</body>
</html>