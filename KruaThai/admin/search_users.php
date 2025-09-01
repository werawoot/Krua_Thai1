<?php
/**
 * Somdul Table - AJAX User Search Endpoint
 * File: admin/ajax/search_users.php
 * Description: Search users for notification targeting
 */

require_once '../../config/database.php';

header('Content-Type: application/json');

// Simple check - we'll add proper authentication later if needed

// Get search query
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($query) || strlen($query) < 2) {
    echo json_encode(['success' => false, 'error' => 'Search query too short']);
    exit();
}

try {
    // Search users by name or email (exclude admins, only active users)
    $sql = "SELECT id, name, email, phone, created_at 
            FROM users 
            WHERE (name LIKE ? OR email LIKE ?) 
            AND role != 'admin'
            AND status = 'active'
            ORDER BY name ASC 
            LIMIT 20";
    
    $searchTerm = '%' . $query . '%';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$searchTerm, $searchTerm]);
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format users for display
    $formatted_users = array_map(function($user) {
        return [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'] ?? '',
            'member_since' => date('M Y', strtotime($user['created_at']))
        ];
    }, $users);
    
    echo json_encode([
        'success' => true,
        'users' => $formatted_users,
        'total' => count($formatted_users)
    ]);
    
} catch (PDOException $e) {
    error_log("User search error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
    
} catch (Exception $e) {
    error_log("General search error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'An unexpected error occurred'
    ]);
}