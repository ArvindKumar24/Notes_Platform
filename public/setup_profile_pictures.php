<?php
/**
 * Setup Script: Move old profile pictures and update database
 * This script:
 * 1. Moves all images from /uploads/profiles/ to /public/profile_pictures/
 * 2. Updates database to remove path prefixes (store filename only)
 * 3. Verifies all images are accessible
 */

require_once("../config/config.php");

$message = "";
$success = false;
$details = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'setup') {
    try {
        // Create new profile pictures directory if it doesn't exist
        $newDir = __DIR__ . '/profile_pictures/';
        if (!is_dir($newDir)) {
            mkdir($newDir, 0755, true);
            $details[] = "✓ Created /public/profile_pictures/ directory";
        } else {
            $details[] = "✓ /public/profile_pictures/ directory already exists";
        }

        // Old profile pictures directory
        $oldDir = __DIR__ . '/../uploads/profiles/';
        $movedCount = 0;
        $alreadyInNew = 0;

        // Move files from old location to new
        if (is_dir($oldDir)) {
            $files = scandir($oldDir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                
                $oldPath = $oldDir . $file;
                $newPath = $newDir . $file;

                // Skip if already in new location
                if (file_exists($newPath)) {
                    $alreadyInNew++;
                    continue;
                }

                if (is_file($oldPath) && copy($oldPath, $newPath)) {
                    $movedCount++;
                }
            }
            $details[] = "✓ Copied $movedCount files from /uploads/profiles/ to /public/profile_pictures/";
            if ($alreadyInNew > 0) {
                $details[] = "ℹ $alreadyInNew files already existed in new location";
            }
        }

        // Get all users with profile pictures and clean up database paths
        $stmt = $pdo->prepare("SELECT id, profile_picture FROM users WHERE profile_picture IS NOT NULL AND profile_picture != ''");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $updated = 0;
        $issues = [];

        foreach ($users as $user) {
            $oldPath = $user['profile_picture'];
            // Extract just the filename
            $fileName = basename($oldPath);

            // Only update if different
            if ($oldPath !== $fileName) {
                $updateStmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                if ($updateStmt->execute([$fileName, $user['id']])) {
                    $updated++;
                } else {
                    $issues[] = "Failed to update user ID {$user['id']}";
                }
            }

            // Verify file exists in new location
            if (!file_exists($newDir . $fileName)) {
                $issues[] = "File not found in new location: $fileName (User ID: {$user['id']})";
            }
        }

        $details[] = "✓ Updated $updated user records in database";

        if (!empty($issues)) {
            $details[] = "⚠ Issues found (" . count($issues) . "):";
            foreach ($issues as $issue) {
                $details[] = "  • $issue";
            }
        }

        $success = true;
        $message = "✅ Setup completed successfully!";

    } catch (Exception $e) {
        $message = "❌ Error: " . htmlspecialchars($e->getMessage());
        $details[] = "Error Details: " . $e->getMessage();
    }
}

$page_title = "Profile Pictures Setup";
include("../includes/header.php");
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Profile Pictures Directory Setup</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        This script will set up the new profile pictures directory structure and migrate existing images.
                    </p>

                    <div class="alert alert-info">
                        <strong>What this does:</strong>
                        <ul class="mb-0">
                            <li>Creates <code>/public/profile_pictures/</code> directory</li>
                            <li>Copies existing images from <code>/uploads/profiles/</code></li>
                            <li>Updates database to use filename-only format</li>
                            <li>Verifies all images are accessible</li>
                        </ul>
                    </div>

                    <div class="alert alert-warning">
                        <strong>Directory Structure After Setup:</strong>
                        <pre style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 4px;">
/public/
├── profile_pictures/        ← All profile pictures stored here
│   ├── profile_123.jpg
│   ├── profile_456.jpg
│   └── ...
├── register.php
├── edit_profile.php
├── student_dashboard.php
└── teacher_dashboard.php</pre>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>

                        <?php if (!empty($details)): ?>
                            <div class="card bg-light mt-3">
                                <div class="card-body">
                                    <strong>Setup Details:</strong>
                                    <ul class="mb-0 mt-2">
                                        <?php foreach ($details as $detail): ?>
                                            <li style="margin-bottom: 5px;">
                                                <?php echo htmlspecialchars($detail); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <form method="POST" class="mt-4">
                            <input type="hidden" name="action" value="setup">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-arrow-repeat"></i> Run Setup
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="mt-4 p-3 bg-light rounded">
                            <strong>Next Steps:</strong>
                            <ol>
                                <li>Test profile picture uploads by creating a new account or updating a profile</li>
                                <li>Verify profile pictures display on:
                                    <ul>
                                        <li>edit_profile.php</li>
                                        <li>student_dashboard.php</li>
                                        <li>teacher_dashboard.php</li>
                                    </ul>
                                </li>
                                <li>Once verified, you can optionally delete <code>/uploads/profiles/</code> folder</li>
                            </ol>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("../includes/footer.php"); ?>
