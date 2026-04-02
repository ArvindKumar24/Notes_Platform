<?php
session_start();
require_once("../config/config.php");

// Check if user is logged in (teacher or student can view files)
if (!isset($_SESSION["user_id"])) {
    header("HTTP/1.0 403 Forbidden");
    die("Access denied");
}

$file = $_GET['file'] ?? '';
if (empty($file)) {
    header("HTTP/1.0 400 Bad Request");
    die("No file specified");
}

// Security: Prevent directory traversal
$file = basename($file);

// The uploads folder is ONE level above public (sibling directory)
$uploadPath = __DIR__ . '/../uploads/';
$filePath = $uploadPath . $file;

// Also check if file is in submissions subfolder
if (!file_exists($filePath)) {
    $filePath = $uploadPath . 'submissions/' . $file;
}

if (!file_exists($filePath)) {
    header("HTTP/1.0 404 Not Found");
    die("File not found: " . htmlspecialchars($file));
}

// Get file extension
$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

// Check if this is a download request or preview request
$isDownload = isset($_GET['download']) && $_GET['download'] == '1';

// Set appropriate headers based on file type and request
if ($isDownload) {
    // Force download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file . '"');
} else {
    // For preview - set appropriate content type
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'md' => 'text/markdown',
        'html' => 'text/html',
        'htm' => 'text/html',
        'json' => 'application/json',
        'xml' => 'application/xml'
    ];
    
    $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $contentType);
    
    // For PDF, set inline disposition to show in browser
    if ($ext === 'pdf') {
        header('Content-Disposition: inline; filename="' . $file . '"');
    } else if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'txt', 'csv', 'md'])) {
        // For images and text files, also show inline
        header('Content-Disposition: inline; filename="' . $file . '"');
    } else {
        // For other file types, force download
        header('Content-Disposition: attachment; filename="' . $file . '"');
    }
}

// Disable caching for dynamic content
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output the file
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
?>