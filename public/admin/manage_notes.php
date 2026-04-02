<?php
require_once("../../config/config.php");
require_once __DIR__ . "/../../public/includes/EmailSender.php";

// only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$page_title = "Manage Content - Admin";
$mailer = new EmailSender();

/* =========================
   DELETE NOTE
========================= */
if (isset($_POST['delete_note'])) {
    $noteId = $_POST['note_id'];
    
    // Check type
    $checkStmt = $pdo->prepare("SELECT type FROM notes WHERE id = ?");
    $checkStmt->execute([$noteId]);
    $noteType = $checkStmt->fetchColumn();
    
    if ($noteType === 'assessment') {
        $message = "❌ Assessments cannot be deleted by admin.";
        $message_type = "danger";
    } else {

        // Fetch note + user info
        $stmt = $pdo->prepare("
            SELECT n.title, n.type, u.email, u.name
            FROM notes n
            JOIN users u ON n.user_id = u.id
            WHERE n.id = ?
        ");
        $stmt->execute([$noteId]);
        $info = $stmt->fetch();

        // Delete file
        $stmtFile = $pdo->prepare("SELECT file_path FROM notes WHERE id = ?");
        $stmtFile->execute([$noteId]);
        $note = $stmtFile->fetch();

        if ($note) {
            $filePath = UPLOAD_PATH . basename($note['file_path']);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        // Delete DB
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ?");
        $stmt->execute([$noteId]);

        // Reset AUTO_INCREMENT
        $count = $pdo->query("SELECT COUNT(*) FROM notes")->fetchColumn();
        if ($count == 0) {
            $pdo->query("ALTER TABLE notes AUTO_INCREMENT = 1");
        }

        // Send email
        if ($info && $info['type'] !== 'assessment') {
            $mailer->sendAdminDeletionEmail(
                $info['email'],
                $info['name'],
                $info['title'],
                $info['type']
            );
        }

        $message = "Deleted successfully!";
        $message_type = "success";
    }
}

/* =========================
   APPROVE NOTE
========================= */
if (isset($_POST['approve_note'])) {
    $noteId = $_POST['note_id'];
    
    // Get type + status
    $checkStmt = $pdo->prepare("SELECT type, status FROM notes WHERE id = ?");
    $checkStmt->execute([$noteId]);
    $noteData = $checkStmt->fetch();

    if ($noteData['type'] === 'assessment') {
        $message = "❌ Assessments are auto-managed by teachers.";
        $message_type = "danger";

    } elseif ($noteData['status'] === 'approved') {
        $message = "Already approved!";
        $message_type = "warning";

    } else {

        // Update
        $stmt = $pdo->prepare("UPDATE notes SET status = 'approved' WHERE id = ?");
        $stmt->execute([$noteId]);

        if ($stmt->rowCount() > 0) {

            // Fetch user info
            $stmt2 = $pdo->prepare("
                SELECT n.title, n.type, u.email, u.name
                FROM notes n
                JOIN users u ON n.user_id = u.id
                WHERE n.id = ?
            ");
            $stmt2->execute([$noteId]);
            $info = $stmt2->fetch();

            if ($info && $info['type'] !== 'assessment') {
                $mailer->sendAdminApprovalEmail(
                    $info['email'],
                    $info['name'],
                    $info['title'],
                    $info['type']
                );
            }
        }

        $message = "Approved successfully!";
        $message_type = "success";
    }
}

/* =========================
   REJECT NOTE
========================= */
if (isset($_POST['reject_note'])) {
    $noteId = $_POST['note_id'];
    
    // Get type + status
    $checkStmt = $pdo->prepare("SELECT type, status FROM notes WHERE id = ?");
    $checkStmt->execute([$noteId]);
    $noteData = $checkStmt->fetch();

    if ($noteData['type'] === 'assessment') {
        $message = "❌ Assessments cannot be rejected.";
        $message_type = "danger";

    } elseif ($noteData['status'] === 'rejected') {
        $message = "Already rejected!";
        $message_type = "warning";

    } else {

        $stmt = $pdo->prepare("UPDATE notes SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$noteId]);

        if ($stmt->rowCount() > 0) {

            $stmt2 = $pdo->prepare("
                SELECT n.title, n.type, u.email, u.name
                FROM notes n
                JOIN users u ON n.user_id = u.id
                WHERE n.id = ?
            ");
            $stmt2->execute([$noteId]);
            $info = $stmt2->fetch();

            if ($info && $info['type'] !== 'assessment') {
                $mailer->sendAdminRejectionEmail(
                    $info['email'],
                    $info['name'],
                    $info['title'],
                    $info['type']
                );
            }
        }

        $message = "Rejected successfully!";
        $message_type = "success";
    }
}

/* =========================
   FETCH DATA
========================= */
$notes = $pdo->query("
    SELECT n.id, n.title, n.description, n.file_path, n.type, n.status, n.uploaded_at,
           u.name AS uploader_name, c.name AS category_name
    FROM notes n
    LEFT JOIN users u ON n.user_id = u.id
    LEFT JOIN categories c ON n.category_id = c.id
    ORDER BY n.uploaded_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

include("./header.php");
?>

<!-- UI SAME RAHEGA (NO CHANGE NEEDED BELOW) -->

<div class="container mt-4">
    <!-- Display Message -->
    <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>
            <i class="bi bi-journal-bookmark-fill me-2" style="color: #14B8A6;"></i>
            Manage Notes & Uploads
        </h2>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <!-- Notes Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Uploaded By</th>
                            <th>Status</th>
                            <th>Uploaded At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notes as $n): 
                            $isAssessment = ($n['type'] === 'assessment');
                        ?>
                            <tr>
                                <td><?= $n['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($n['title']) ?></strong>
                                    <?php if ($isAssessment): ?>
                                        <span class="badge bg-warning ms-2" style="font-size: 0.7rem;">Teacher Managed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $isAssessment ? 'bg-warning' : 'bg-info' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $n['type'])) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($n['category_name'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($n['uploader_name'] ?? 'Unknown') ?></td>
                                <td>
                                    <span class="badge <?= $n['status'] === 'approved' ? 'bg-success' : ($n['status'] === 'rejected' ? 'bg-danger' : 'bg-warning') ?>">
                                        <?= ucfirst($n['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($n['uploaded_at'])) ?></td>
                                <td>
                                    <?php if (!$isAssessment): ?>
                                        <!-- Only show Approve/Reject for non-assessments -->
                                        <?php if ($n['status'] != 'approved'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="note_id" value="<?= $n['id'] ?>">
                                                <button type="submit" name="approve_note" class="btn btn-sm btn-success">
                                                    <i class="bi bi-check-lg"></i> Approve
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($n['status'] != 'rejected'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="note_id" value="<?= $n['id'] ?>">
                                                <button type="submit" name="reject_note" class="btn btn-sm btn-warning">
                                                    <i class="bi bi-x-lg"></i> Reject
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this note? This cannot be undone.');">
                                            <input type="hidden" name="note_id" value="<?= $n['id'] ?>">
                                            <button type="submit" name="delete_note" class="btn btn-sm btn-danger">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <!-- Assessments - Show message that teacher manages these -->
                                        <span class="text-muted small">
                                            <i class="bi bi-shield-lock"></i> Teacher managed
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (count($notes) == 0): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                    <p class="mt-2 mb-0">No notes or uploads found.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Report Download Button -->
    <div class="mt-4 text-end">
        <a href="download_notes_report.php" class="btn" style="background: #14B8A6; color: white;">
            <i class="bi bi-download me-1"></i> Download Notes Report
        </a>
    </div>
</div>

<style>
/* Additional styles */
.badge {
    font-size: 0.75rem;
    padding: 0.35rem 0.65rem;
}
.table th, .table td {
    vertical-align: middle;
}
.btn-sm {
    margin: 0 2px;
}
</style>

<?php include("../includes/footer.php"); ?>