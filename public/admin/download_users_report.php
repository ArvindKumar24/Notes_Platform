<?php
require_once("../../config/config.php");

// Only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Fetch all users with comprehensive stats
$users = $pdo->query("
    SELECT 
        u.*,
        COUNT(DISTINCT n.id) as total_uploads,
        COALESCE(SUM(n.downloads_count), 0) as total_downloads_generated,
        COUNT(DISTINCT d.id) as total_downloaded_notes,
        (SELECT COUNT(*) FROM notes WHERE user_id = u.id AND type = 'note') as notes_count,
        (SELECT COUNT(*) FROM notes WHERE user_id = u.id AND type = 'question_paper') as papers_count,
        (SELECT COUNT(*) FROM notes WHERE user_id = u.id AND type = 'assessment') as assessments_count
    FROM users u
    LEFT JOIN notes n ON u.id = n.user_id
    LEFT JOIN downloads_log d ON u.id = d.user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=detailed_users_report_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

// Add CSV headers
fputcsv($output, [
    'User ID',
    'Full Name',
    'Email Address',
    'Role',
    'Total Uploads',
    'Notes Uploaded',
    'Question Papers',
    'Assessments',
    'Downloads Generated',
    'Notes Downloaded',
    'Profile Picture',
    'Registration Date',
    'Last Activity',
    'Account Status'
]);

// Add data rows
foreach ($users as $user) {
    fputcsv($output, [
        $user['id'],
        $user['name'],
        $user['email'],
        ucfirst($user['role']),
        $user['total_uploads'],
        $user['notes_count'],
        $user['papers_count'],
        $user['assessments_count'],
        $user['total_downloads_generated'],
        $user['total_downloaded_notes'],
        $user['profile_picture'] ? 'Yes' : 'No',
        $user['created_at'],
        $user['updated_at'] ?? 'Never',
        'Active'
    ]);
}

fclose($output);
exit;
?>