<?php
require_once("../../config/config.php");

// Only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Fetch all notes with detailed information
$notes = $pdo->query("
    SELECT 
        n.id,
        n.title,
        n.description,
        n.file_path,
        n.type,
        n.status,
        n.downloads_count,
        n.uploaded_at,
        u.name AS uploader_name,
        u.email AS uploader_email,
        u.role AS uploader_role,
        c.name AS category_name,
        (SELECT COUNT(*) FROM downloads_log WHERE note_id = n.id) as unique_downloads
    FROM notes n
    LEFT JOIN users u ON n.user_id = u.id
    LEFT JOIN categories c ON n.category_id = c.id
    ORDER BY n.downloads_count DESC, n.uploaded_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=detailed_notes_report_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

// Add CSV headers
fputcsv($output, [
    'Note ID',
    'Title',
    'Description',
    'Category',
    'Type',
    'Status',
    'Total Downloads',
    'Unique Downloads',
    'Uploader Name',
    'Uploader Email',
    'Uploader Role',
    'File Name',
    'Upload Date',
    'File Status'
]);

// Add data rows
foreach ($notes as $note) {
    $fileStatus = 'Missing';
    if (!empty($note['file_path'])) {
        $fullPath = "../../" . $note['file_path'];
        $fileStatus = file_exists($fullPath) ? 'Available' : 'Missing';
    }
    
    fputcsv($output, [
        $note['id'],
        $note['title'],
        $note['description'],
        $note['category_name'],
        ucfirst(str_replace('_', ' ', $note['type'])),
        ucfirst($note['status']),
        $note['downloads_count'],
        $note['unique_downloads'],
        $note['uploader_name'],
        $note['uploader_email'],
        ucfirst($note['uploader_role']),
        basename($note['file_path']),
        $note['uploaded_at'],
        $fileStatus
    ]);
}

fclose($output);
exit;
?>