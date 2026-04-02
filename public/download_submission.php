<?php
session_start();
require_once("../config/config.php");

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit;
}

$fileName = $_GET['file'] ?? '';
$studentName = $_GET['student'] ?? 'Student';
$assessmentTitle = $_GET['assessment'] ?? 'Submission';

if (empty($fileName)) {
    die("Invalid file request");
}

$fileName = basename($fileName);

// Uploads folder is outside public (sibling directory)
$uploadPath = __DIR__ . '/../uploads/';
$filePath = $uploadPath . $fileName;

if (!file_exists($filePath)) {
    $filePath = $uploadPath . 'submissions/' . $fileName;
}

if (!file_exists($filePath)) {
    die("File not found: " . htmlspecialchars($fileName));
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$safeStudentName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentName);
$safeAssessmentTitle = preg_replace('/[^a-zA-Z0-9_-]/', '_', $assessmentTitle);
$downloadFileName = $safeStudentName . '_' . $safeAssessmentTitle . '.' . $ext;

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $downloadFileName . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
?>