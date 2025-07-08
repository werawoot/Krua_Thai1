<?php
/**
 * 🎯 FINAL WORKING CHECKOUT - แก้ปัญหา subscription_menus constraint
 * แทนที่ STEP 7 ในไฟล์ debug_checkout.php
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    echo "<div style='background: #d4edda; padding: 20px; margin: 10px; border: 3px solid #28a745; border-radius: 8px;'>";
    echo "<h2>🚀 STEP 7: FINAL WORKING VERSION</h2>";
    
    // Get form data
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');
    $payment_method = $_POST['payment_method'] ?? '';
    $delivery_days = $_POST['delivery_days'] ?? [];
    
    echo "Form data received:<br>";
    echo "- Address: " . ($delivery_address ? '✅' : '❌') . " " . htmlspecialchars($delivery_address) . "<br>";
    echo "- City: " . ($city ? '✅' : '❌') . " " . htmlspecialchars($city) . "<br>";
    echo "- Zip: " . ($zip_code ? '✅' : '❌') . " " . htmlspecialchars($zip_code) . "<br>";
    echo "- Payment: " . ($payment_method ? '✅' : '❌') . " " . htmlspecialchars($payment_method) . "<br>";
    echo "- Delivery days: " . (count($delivery_days) > 0 ? '✅' : '❌') . " " . count($delivery_days) . " days<br>";
    
    // Validation
    $errors = [];
    if (empty($delivery_address)) $errors[] = "Missing address";
    if (empty($city)) $errors[] = "Missing city";
    if (empty($zip_code)) $errors[] = "Missing zip";
    if (empty($payment_method)) $errors[] = "Missing payment method";
    if (empty($delivery_days)) $errors[] = "Missing delivery days";
    
    if (!empty($errors)) {
        echo "<strong>❌ VALIDATION ERRORS:</strong><br>";
        foreach ($errors as $error) {
            echo "- $error<br>";
        }
    } else {
        echo "<strong>✅ VALIDATION PASSED</strong><br>";
        
        try {
            echo "<br><strong>🔍 Getting Real Data from Database...</strong><br>";
            
            // 1. Get REAL plan from database
            $stmt = $db->query("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 1");
            $real_plan = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$real_plan) {
                throw new Exception("No active subscription plans found in database");
            }
            
            echo "✅ Found plan: " . htmlspecialchars($real_plan['name_thai'] ?? $real_plan['name']) . "<br>";
            echo "Plan ID: " . $real_plan['id'] . "<br>";
            echo "Plan Price: ฿" . number_format($real_plan['final_price'], 2) . "<br>";
            
            // 2. Get REAL menus from database
            $stmt = $db->query("SELECT id, name, name_thai, base_price FROM menus WHERE is_available = 1 ORDER BY RAND() LIMIT 3");
            $real_menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($real_menus)) {
                echo "⚠️ No available menus found, will skip menu insertion<br>";
            } else {
                echo "✅ Found " . count($real_menus) . " available menus<br>";
                foreach ($real_menus as $menu) {
                    echo "- " . htmlspecialchars($menu['name_thai'] ?? $menu['name']) . " (฿" . $menu['base_price'] . ")<br>";
                }
            }
            
            // Start transaction
            echo "<br><strong>🔄 Starting Database Transaction...</strong><br>";
            $db->beginTransaction();
            echo "✅ Transaction started<br>";
            
            // Generate IDs
            $subscription_id = generateUUID();
            $payment_id = generateUUID();
            $user_id = $_SESSION['user_id'];
            $start_date = date('Y-m-d', strtotime('+1 day'));
            $next_billing_date = date('Y-m-d', strtotime('+1 week', strtotime($start_date)));
            
            echo "Generated IDs:<br>";
            echo "- Subscription ID: $subscription_id<br>";
            echo "- Payment ID: $payment_id<br>";
            echo "- User ID: $user_id<br>";
            echo "- Start Date: $start_date<br>";
            
            // 3. Insert subscription with REAL plan_id
            echo "<br><strong>📝 Inserting Subscription...</strong><br>";
            $stmt = $db->prepare("
                INSERT INTO subscriptions (
                    id, user_id, plan_id, status, start_date, next_billing_date, 
                    billing_cycle, total_amount, delivery_days, preferred_delivery_time, 
                    special_instructions, auto_renew, created_at, updated_at
                ) VALUES (?, ?, ?, 'active', ?, ?, 'weekly', ?, ?, 'afternoon', ?, 1, NOW(), NOW())
            ");
            
            $result1 = $stmt->execute([
                $subscription_id,
                $user_id,
                $real_plan['id'], // ✅ ใช้ plan ID จริง
                $start_date,
                $next_billing_date,
                $real_plan['final_price'], // ✅ ใช้ราคาจริง
                json_encode($delivery_days),
                'Test subscription from debug - ' . date('Y-m-d H:i:s')
            ]);
            
            echo $result1 ? "✅ Subscription inserted successfully<br>" : "❌ Subscription insert failed<br>";
            
            // 4. Insert payment
            echo "<br><strong>💳 Inserting Payment...</strong><br>";
            $stmt = $db->prepare("
                INSERT INTO payments (
                    id, subscription_id, user_id, payment_method, transaction_id, 
                    amount, currency, net_amount, status, payment_date, 
                    billing_period_start, billing_period_end, description, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'THB', ?, 'completed', NOW(), ?, ?, ?, NOW(), NOW())
            ");
            
            $transaction_id = 'DEBUG-' . date('Ymd-His') . '-' . substr($subscription_id, 0, 6);
            $description = 'Debug payment for ' . ($real_plan['name_thai'] ?? $real_plan['name']);
            $billing_end = date('Y-m-d', strtotime('+1 week', strtotime($start_date)));
            
            $result2 = $stmt->execute([
                $payment_id,
                $subscription_id,
                $user_id,
                'credit_card',
                $transaction_id,
                $real_plan['final_price'],
                $real_plan['final_price'],
                $start_date,
                $billing_end,
                $description
            ]);
            
            echo $result2 ? "✅ Payment inserted successfully<br>" : "❌ Payment insert failed<br>";
            
            // 5. Insert subscription menus (only if we have menus)
            echo "<br><strong>🍛 Inserting Subscription Menus...</strong><br>";
            
            if (!empty($real_menus)) {
                // First, let's check the constraint
                echo "Checking foreign key constraint...<br>";
                
                // Test if subscription exists
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM subscriptions WHERE id = ?");
                $stmt->execute([$subscription_id]);
                $sub_exists = $stmt->fetch()['count'];
                echo "- Subscription exists: " . ($sub_exists > 0 ? "✅ Yes" : "❌ No") . "<br>";
                
                if ($sub_exists > 0) {
                    $stmt_menu = $db->prepare("
                        INSERT INTO subscription_menus (id, subscription_id, menu_id, delivery_date, quantity, status, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, 1, 'scheduled', NOW(), NOW())
                    ");
                    
                    $menu_count = 0;
                    foreach ($real_menus as $menu) {
                        try {
                            $menu_record_id = generateUUID();
                            
                            // Test if menu exists
                            $stmt_test = $db->prepare("SELECT COUNT(*) as count FROM menus WHERE id = ?");
                            $stmt_test->execute([$menu['id']]);
                            $menu_exists = $stmt_test->fetch()['count'];
                            
                            if ($menu_exists > 0) {
                                echo "Inserting menu: " . htmlspecialchars($menu['name_thai'] ?? $menu['name']) . "<br>";
                                
                                $result3 = $stmt_menu->execute([
                                    $menu_record_id, 
                                    $subscription_id, 
                                    $menu['id'], 
                                    $start_date
                                ]);
                                
                                if ($result3) {
                                    $menu_count++;
                                    echo "✅ Menu inserted successfully<br>";
                                } else {
                                    echo "❌ Menu insert failed<br>";
                                }
                            } else {
                                echo "❌ Menu " . $menu['id'] . " does not exist<br>";
                            }
                            
                        } catch (Exception $menu_error) {
                            echo "❌ Menu insert error: " . $menu_error->getMessage() . "<br>";
                        }
                    }
                    echo "✅ Successfully inserted $menu_count subscription menus<br>";
                } else {
                    echo "❌ Cannot insert menus - subscription not found<br>";
                }
            } else {
                echo "⚠️ No menus available, skipping menu insertion<br>";
            }
            
            // 6. Update user address
            echo "<br><strong>👤 Updating User Address...</strong><br>";
            $stmt = $db->prepare("
                UPDATE users SET 
                    delivery_address = ?, city = ?, zip_code = ?, 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $result4 = $stmt->execute([$delivery_address, $city, $zip_code, $user_id]);
            echo $result4 ? "✅ User address updated<br>" : "❌ User address update failed<br>";
            
            // 7. Commit transaction
            echo "<br><strong>💾 Committing Transaction...</strong><br>";
            $db->commit();
            echo "✅ ALL DATA COMMITTED TO DATABASE!<br>";
            
            // 8. Final verification
            echo "<br><strong>🔍 Final Verification...</strong><br>";
            
            // Count totals
            $stmt = $db->query("SELECT COUNT(*) as count FROM subscriptions");
            $total_subs = $stmt->fetch()['count'];
            echo "- Total subscriptions in DB: $total_subs<br>";
            
            $stmt = $db->query("SELECT COUNT(*) as count FROM payments");
            $total_payments = $stmt->fetch()['count'];
            echo "- Total payments in DB: $total_payments<br>";
            
            $stmt = $db->query("SELECT COUNT(*) as count FROM subscription_menus");
            $total_menus = $stmt->fetch()['count'];
            echo "- Total subscription menus in DB: $total_menus<br>";
            
            // Verify our specific records
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM subscriptions WHERE id = ?");
            $stmt->execute([$subscription_id]);
            $our_sub = $stmt->fetch()['count'];
            
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM payments WHERE id = ?");
            $stmt->execute([$payment_id]);
            $our_payment = $stmt->fetch()['count'];
            
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM subscription_menus WHERE subscription_id = ?");
            $stmt->execute([$subscription_id]);
            $our_menus = $stmt->fetch()['count'];
            
            echo "<br><strong>🎯 Our Records:</strong><br>";
            echo "- Our subscription: " . ($our_sub > 0 ? "✅ Found" : "❌ Not found") . "<br>";
            echo "- Our payment: " . ($our_payment > 0 ? "✅ Found" : "❌ Not found") . "<br>";
            echo "- Our menus: " . ($our_menus > 0 ? "✅ Found ($our_menus)" : "❌ Not found") . "<br>";
            
            if ($our_sub > 0 && $our_payment > 0) {
                echo "<br><div style='background: #28a745; color: white; padding: 20px; border-radius: 10px; text-align: center;'>";
                echo "<h3>🎉 COMPLETE SUCCESS!</h3>";
                echo "<h4>All data successfully saved to database!</h4>";
                echo "<p><strong>Subscription ID:</strong> $subscription_id</p>";
                echo "<p><strong>Payment ID:</strong> $payment_id</p>";
                echo "<p><strong>Transaction ID:</strong> $transaction_id</p>";
                echo "<p><strong>Total Amount:</strong> ฿" . number_format($real_plan['final_price'], 2) . "</p>";
                echo "<p><strong>Menus Added:</strong> $our_menus</p>";
                echo "</div>";
            } else {
                echo "<br><div style='background: #dc3545; color: white; padding: 20px; border-radius: 10px; text-align: center;'>";
                echo "<h3>❌ PARTIAL FAILURE</h3>";
                echo "<p>Some records were not created properly</p>";
                echo "</div>";
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            echo "<br><strong>❌ DATABASE ERROR:</strong><br>";
            echo "Error: " . $e->getMessage() . "<br>";
            echo "File: " . $e->getFile() . "<br>";
            echo "Line: " . $e->getLine() . "<br>";
            echo "<br><strong>Full Trace:</strong><br>";
            echo "<pre style='background: #f8f9fa; padding: 10px; font-size: 12px; max-height: 300px; overflow: auto;'>";
            echo $e->getTraceAsString();
            echo "</pre>";
        }
    }
    echo "</div>";
}

// ============================================================================
// Test Queries to run in phpMyAdmin
// ============================================================================
echo "<div style='background: #e9ecef; padding: 20px; margin: 10px; border: 2px solid #6c757d; border-radius: 8px;'>";
echo "<h2>📊 SQL Commands to Test Results</h2>";
echo "<p><strong>Copy and run these in phpMyAdmin after testing:</strong></p>";
echo "<textarea style='width: 100%; height: 200px; font-family: monospace; font-size: 12px; padding: 10px;' readonly>";
echo "-- Check latest 5 subscriptions\n";
echo "SELECT s.id, s.status, s.total_amount, u.first_name, sp.name as plan_name\n";
echo "FROM subscriptions s\n";
echo "JOIN users u ON s.user_id = u.id\n";
echo "JOIN subscription_plans sp ON s.plan_id = sp.id\n";
echo "ORDER BY s.created_at DESC LIMIT 5;\n\n";

echo "-- Check latest 5 payments\n";
echo "SELECT p.id, p.amount, p.status, p.transaction_id, u.first_name\n";
echo "FROM payments p\n";
echo "JOIN users u ON p.user_id = u.id\n";
echo "ORDER BY p.created_at DESC LIMIT 5;\n\n";

echo "-- Check latest subscription menus\n";
echo "SELECT sm.id, m.name_thai, sm.delivery_date, sm.status, s.id as sub_id\n";
echo "FROM subscription_menus sm\n";
echo "JOIN menus m ON sm.menu_id = m.id\n";
echo "JOIN subscriptions s ON sm.subscription_id = s.id\n";
echo "ORDER BY sm.created_at DESC LIMIT 10;\n\n";

echo "-- Check complete order with all details\n";
echo "SELECT \n";
echo "    s.id as subscription_id,\n";
echo "    u.first_name,\n";
echo "    sp.name_thai as plan_name,\n";
echo "    s.total_amount,\n";
echo "    p.status as payment_status,\n";
echo "    COUNT(sm.id) as menu_count\n";
echo "FROM subscriptions s\n";
echo "JOIN users u ON s.user_id = u.id\n";
echo "JOIN subscription_plans sp ON s.plan_id = sp.id\n";
echo "LEFT JOIN payments p ON s.id = p.subscription_id\n";
echo "LEFT JOIN subscription_menus sm ON s.id = sm.subscription_id\n";
echo "GROUP BY s.id\n";
echo "ORDER BY s.created_at DESC LIMIT 5;";
echo "</textarea>";
echo "</div>";
?>