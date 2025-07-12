<?php
/**
 * Krua Thai - Menu Export System
 * File: admin/export-menus.php
 * Description: Export menu data to CSV/Excel format with filtering options
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php"); 
    exit();
}

try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get filter parameters (same as menus.php)
$category_filter = $_GET['category'] ?? '';
$availability_filter = $_GET['availability'] ?? '';
$featured_filter = $_GET['featured'] ?? '';
$search = $_GET['search'] ?? '';
$export_format = $_GET['format'] ?? 'csv';
$include_nutrition = $_GET['include_nutrition'] ?? '1';
$include_ingredients = $_GET['include_ingredients'] ?? '0';

// Build WHERE clause
$where_conditions = ['1=1'];
$params = [];

if ($category_filter) {
    $where_conditions[] = "m.category_id = ?";
    $params[] = $category_filter;
}

if ($availability_filter !== '') {
    $where_conditions[] = "m.is_available = ?";
    $params[] = $availability_filter === 'available' ? 1 : 0;
}

if ($featured_filter !== '') {
    $where_conditions[] = "m.is_featured = ?";
    $params[] = $featured_filter === 'featured' ? 1 : 0;
}

if ($search) {
    $where_conditions[] = "(m.name LIKE ? OR m.name_thai LIKE ? OR m.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = implode(' AND ', $where_conditions);

try {
    // Get menus with category information
    $menus_sql = "
        SELECT 
            m.id,
            m.name,
            m.name_thai,
            m.description,
            m.ingredients,
            m.cooking_method,
            mc.name as category_name,
            mc.name_thai as category_name_thai,
            m.base_price,
            m.portion_size,
            m.preparation_time,
            m.calories_per_serving,
            m.protein_g,
            m.carbs_g,
            m.fat_g,
            m.fiber_g,
            m.sodium_mg,
            m.sugar_g,
            m.spice_level,
            m.health_benefits,
            m.dietary_tags,
            m.is_available,
            m.is_featured,
            m.is_seasonal,
            m.availability_start,
            m.availability_end,
            m.created_at,
            m.updated_at
        FROM menus m 
        LEFT JOIN menu_categories mc ON m.category_id = mc.id
        WHERE $where_clause
        ORDER BY m.name ASC
    ";
    
    $stmt = $pdo->prepare($menus_sql);
    $stmt->execute($params);
    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare data for export
    $export_data = [];
    
    // Define headers based on options
    $headers = [
        'ID',
        'Menu Name',
        'Thai Name',
        'Description',
        'Category',
        'Price (THB)',
        'Portion Size',
        'Prep Time (min)',
        'Spice Level',
        'Available',
        'Featured',
        'Seasonal',
        'Created Date',
        'Updated Date'
    ];
    
    // Add nutrition headers if requested
    if ($include_nutrition) {
        $nutrition_headers = [
            'Calories',
            'Protein (g)',
            'Carbs (g)',
            'Fat (g)',
            'Fiber (g)',
            'Sodium (mg)',
            'Sugar (g)',
            'Health Benefits',
            'Dietary Tags'
        ];
        $headers = array_merge($headers, $nutrition_headers);
    }
    
    // Add ingredients headers if requested
    if ($include_ingredients) {
        $ingredient_headers = [
            'Ingredients',
            'Cooking Method',
            'Availability Start',
            'Availability End'
        ];
        $headers = array_merge($headers, $ingredient_headers);
    }
    
    $export_data[] = $headers;
    
    // Process each menu
    foreach ($menus as $menu) {
        $row = [
            $menu['id'],
            $menu['name'],
            $menu['name_thai'] ?: '',
            $menu['description'] ?: '',
            $menu['category_name'] ?: 'No Category',
            number_format($menu['base_price'], 2),
            $menu['portion_size'],
            $menu['preparation_time'],
            ucfirst($menu['spice_level']),
            $menu['is_available'] ? 'Available' : 'Unavailable',
            $menu['is_featured'] ? 'Featured' : 'Not Featured',
            $menu['is_seasonal'] ? 'Seasonal' : 'Regular',
            date('Y-m-d H:i:s', strtotime($menu['created_at'])),
            date('Y-m-d H:i:s', strtotime($menu['updated_at']))
        ];
        
        // Add nutrition data if requested
        if ($include_nutrition) {
            $health_benefits = '';
            $dietary_tags = '';
            
            if ($menu['health_benefits']) {
                $benefits = json_decode($menu['health_benefits'], true);
                $health_benefits = is_array($benefits) ? implode(', ', $benefits) : '';
            }
            
            if ($menu['dietary_tags']) {
                $tags = json_decode($menu['dietary_tags'], true);
                $dietary_tags = is_array($tags) ? implode(', ', $tags) : '';
            }
            
            $nutrition_data = [
                $menu['calories_per_serving'] ?: '',
                $menu['protein_g'] ?: '',
                $menu['carbs_g'] ?: '',
                $menu['fat_g'] ?: '',
                $menu['fiber_g'] ?: '',
                $menu['sodium_mg'] ?: '',
                $menu['sugar_g'] ?: '',
                $health_benefits,
                $dietary_tags
            ];
            $row = array_merge($row, $nutrition_data);
        }
        
        // Add ingredients data if requested
        if ($include_ingredients) {
            $ingredients_data = [
                $menu['ingredients'] ?: '',
                $menu['cooking_method'] ?: '',
                $menu['availability_start'] ?: '',
                $menu['availability_end'] ?: ''
            ];
            $row = array_merge($row, $ingredients_data);
        }
        
        $export_data[] = $row;
    }
    
    // Generate filename
    $timestamp = date('Y-m-d_H-i-s');
    $filter_suffix = '';
    if ($category_filter) $filter_suffix .= '_cat';
    if ($availability_filter) $filter_suffix .= '_' . $availability_filter;
    if ($featured_filter) $filter_suffix .= '_' . $featured_filter;
    if ($search) $filter_suffix .= '_search';
    
    $filename = 'krua_thai_menus_' . $timestamp . $filter_suffix;
    
    // Export based on format
    if ($export_format === 'excel') {
        exportToExcel($export_data, $filename);
    } else {
        exportToCSV($export_data, $filename);
    }
    
    // Log export activity
    logActivity('menu_export', $_SESSION['user_id'], getRealIPAddress(), [
        'format' => $export_format,
        'total_menus' => count($menus),
        'filters' => [
            'category' => $category_filter,
            'availability' => $availability_filter,
            'featured' => $featured_filter,
            'search' => $search
        ]
    ]);

} catch (Exception $e) {
    error_log("Menu export error: " . $e->getMessage());
    header("Location: menus.php?error=" . urlencode("Export failed: " . $e->getMessage()));
    exit();
}

/**
 * Export data to CSV format
 */
function exportToCSV($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    // Add BOM for UTF-8 to ensure proper encoding in Excel
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    foreach ($data as $row) {
        // Clean data for CSV
        $cleaned_row = array_map(function($cell) {
            // Remove line breaks and extra spaces
            $cell = preg_replace('/\s+/', ' ', trim($cell));
            // Escape quotes
            $cell = str_replace('"', '""', $cell);
            return $cell;
        }, $row);
        
        fputcsv($output, $cleaned_row, ',', '"');
    }
    
    fclose($output);
    exit();
}

/**
 * Export data to Excel format (HTML table format for simplicity)
 */
function exportToExcel($data, $filename) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    echo '<html>';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
    echo 'th { background-color: #f2f2f2; font-weight: bold; }';
    echo 'tr:nth-child(even) { background-color: #f9f9f9; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo '<h2>Krua Thai - Menu Export</h2>';
    echo '<p>Export Date: ' . date('Y-m-d H:i:s') . '</p>';
    echo '<table>';
    
    $is_header = true;
    foreach ($data as $row) {
        if ($is_header) {
            echo '<thead><tr>';
            foreach ($row as $cell) {
                echo '<th>' . htmlspecialchars($cell) . '</th>';
            }
            echo '</tr></thead><tbody>';
            $is_header = false;
        } else {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . htmlspecialchars($cell) . '</td>';
            }
            echo '</tr>';
        }
    }
    
    echo '</tbody></table>';
    echo '<p>Total Records: ' . (count($data) - 1) . '</p>';
    echo '</body>';
    echo '</html>';
    exit();
}

/**
 * Alternative Excel export using simple XML format
 */
function exportToExcelXML($data, $filename) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xml"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<?mso-application progid="Excel.Sheet"?>';
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
    echo ' xmlns:o="urn:schemas-microsoft-com:office:office"';
    echo ' xmlns:x="urn:schemas-microsoft-com:office:excel"';
    echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"';
    echo ' xmlns:html="http://www.w3.org/TR/REC-html40">';
    
    echo '<Worksheet ss:Name="Menus">';
    echo '<Table>';
    
    $is_header = true;
    foreach ($data as $row) {
        echo '<Row>';
        foreach ($row as $cell) {
            if ($is_header) {
                echo '<Cell><Data ss:Type="String">' . htmlspecialchars($cell) . '</Data></Cell>';
            } else {
                // Detect data type
                if (is_numeric($cell)) {
                    echo '<Cell><Data ss:Type="Number">' . htmlspecialchars($cell) . '</Data></Cell>';
                } else {
                    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($cell) . '</Data></Cell>';
                }
            }
        }
        echo '</Row>';
        if ($is_header) $is_header = false;
    }
    
    echo '</Table>';
    echo '</Worksheet>';
    echo '</Workbook>';
    exit();
}
?>