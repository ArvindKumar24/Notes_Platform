<?php
require_once("../config/config.php");

$stmt = $pdo->query("SELECT id, name, profile_picture FROM users WHERE profile_picture IS NOT NULL LIMIT 10");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Current Database Profile Picture Paths:\n";
echo "======================================\n\n";

foreach($results as $row) {
    echo "User ID: {$row['id']}\n";
    echo "Name: {$row['name']}\n";
    echo "DB Value: {$row['profile_picture']}\n";
    
    // Try different path constructions
    $paths_to_check = [
        "Full path (../uploads/profiles/)" => __DIR__ . '/../uploads/profiles/' . $row['profile_picture'],
        "Full path (uploads/profiles/)" => __DIR__ . '/uploads/profiles/' . $row['profile_picture'],
        "Full path (public/uploads/profiles/)" => __DIR__ . '/..\\uploads\\profiles\\' . $row['profile_picture'],
    ];
    
    echo "File Exists Check:\n";
    foreach($paths_to_check as $desc => $path) {
        $exists = file_exists($path) ? "✓ YES" : "✗ NO";
        echo "  $desc: $exists\n  Path: $path\n";
    }
    echo "\n";
}
?>
