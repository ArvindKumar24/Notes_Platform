<?php
require_once("../../config/config.php");

// Only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Fetch all categories with note counts
$categories = $pdo->query("
    SELECT c.*, 
           COUNT(n.id) as total_notes,
           COALESCE(SUM(n.downloads_count), 0) as total_downloads
    FROM categories c
    LEFT JOIN notes n ON c.id = n.category_id
    GROUP BY c.id
    ORDER BY c.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=categories_report_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'Category ID',
    'Category Name',
    'Total Notes',
    'Total Downloads',
    'Created Date'
]);

// Add data rows
foreach ($categories as $category) {
    fputcsv($output, [
        $category['id'],
        $category['name'],
        $category['total_notes'],
        $category['total_downloads'],
        $category['created_at'] ?? 'N/A'
    ]);
}

fclose($output);
exit;
?>