<?php
/**
 * Migration Script: Convert profile picture paths from full paths to filenames only
 * This script fixes old profile_picture records that contain paths like '../uploads/profiles/filename.jpg'
 * and converts them to just 'filename.jpg'
 * 
 * Usage: Run this once from the browser by visiting: admin/migrate_profile_paths.php
 * Then delete this file
 */

require_once("../config/config.php");

// Simple authentication check - modify as needed for your admin verification
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$message = "";
$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Get all users with profile pictures
        $stmt = $pdo->prepare("SELECT id, profile_picture FROM users WHERE profile_picture IS NOT NULL AND profile_picture != ''");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updated = 0;
        
        foreach ($users as $user) {
            $oldPath = $user['profile_picture'];
            
            // Extract just the filename from paths like '../uploads/profiles/filename.jpg' or 'uploads/profiles/filename.jpg'
            $fileName = basename($oldPath);
            
            // Only update if the path was different
            if ($oldPath !== $fileName) {
                $updateStmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                if ($updateStmt->execute([$fileName, $user['id']])) {
                    $updated++;
                }
            }
        }
        
        $success = true;
        $message = "✅ Migration completed! Updated $updated user records.";
        
    } catch (PDOException $e) {
        $message = "❌ Error: " . htmlspecialchars($e->getMessage());
    }
}

$page_title = "Profile Path Migration";
include("../includes/header.php");
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">Profile Picture Path Migration</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        This script converts profile picture paths from the old format to the new format.
                    </p>
                    
                    <div class="alert alert-info">
                        <strong>What this does:</strong>
                        <ul class="mb-0">
                            <li>Finds all users with profile pictures</li>
                            <li>Converts paths like <code>../uploads/profiles/filename.jpg</code> to just <code>filename.jpg</code></li>
                            <li>Updates the database with the new format</li>
                        </ul>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$success): ?>
                        <form method="POST">
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-arrow-repeat"></i> Run Migration
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="mt-4">
                            <strong>Next steps:</strong>
                            <ol>
                                <li>Verify that profile pictures are displaying correctly on profile pages</li>
                                <li>Delete this file: <code>public/admin/migrate_profile_paths.php</code></li>
                            </ol>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("../includes/footer.php"); ?>
