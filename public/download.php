<?php
require_once("../config/config.php");

// Must be logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    http_response_code(400);
    die("Invalid request: Missing or invalid note ID.");
}

$note_id = (int)$_GET["id"];

try {
    // Fetch note details with error handling
    $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ?");
    $stmt->execute([$note_id]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$note) {
        http_response_code(404);
        die("Note not found.");
    }

    // Check if note is approved
    if ($note['status'] !== 'approved') {
        http_response_code(403);
        die("This note has not been approved yet and is not available for download.");
    }

    $file_path = $note["file_path"];
    $full_path = "../uploads/" . basename($file_path);

    // Verify file exists and is readable
    if (!file_exists($full_path) || !is_readable($full_path)) {
        http_response_code(404);
        die("File not found or cannot be accessed.");
    }

    // Verify file is within uploads directory (prevent directory traversal)
    $real_path = realpath($full_path);
    $uploads_dir = realpath("../uploads");
    if (strpos($real_path, $uploads_dir) !== 0) {
        http_response_code(403);
        die("Access denied: Invalid file path.");
    }

    // Get file size safely
    $file_size = filesize($full_path);
    if ($file_size === false) {
        http_response_code(500);
        die("Error reading file information.");
    }

    // Increment downloads count with error handling
    try {
        $updateStmt = $pdo->prepare("UPDATE notes SET downloads_count = downloads_count + 1 WHERE id = ?");
        $updateStmt->execute([$note_id]);
    } catch (PDOException $e) {
        // Log error but continue with download
        error_log("Download counter update failed: " . $e->getMessage());
    }

    // Force file download
    header("Content-Description: File Transfer");
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"" . basename($file_path) . "\"");
    header("Expires: 0");
    header("Cache-Control: must-revalidate");
    header("Pragma: public");
    header("Content-Length: " . $file_size);
    readfile($full_path);
    exit;

} catch (PDOException $e) {
    error_log("Database error in download.php: " . $e->getMessage());
    http_response_code(500);
    die("Database error. Please try again later.");
} catch (Exception $e) {
    error_log("Unexpected error in download.php: " . $e->getMessage());
    http_response_code(500);
    die("An unexpected error occurred. Please try again later.");
}
