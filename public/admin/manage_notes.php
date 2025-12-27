<?php
require_once("../../config/config.php");
require_once __DIR__ . "/../../public/includes/EmailSender.php"; // <-- ADD THIS LINE

// only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$page_title = "Manage Content - Admin";
$mailer = new EmailSender(); // <-- Email handler

// Handle delete
if (isset($_POST['delete_note'])) {
    $noteId = $_POST['note_id'];

    // Fetch note + user info for email
    $stmt = $pdo->prepare("
        SELECT n.title, u.email, u.name
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

    if ($note && file_exists("../../" . $note['file_path'])) {
        unlink("../../" . $note['file_path']);
    }

    // Delete DB record
    $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ?");
    $stmt->execute([$noteId]);

    // Send delete email
    if ($info) {
        $mailer->sendAdminDeletionEmail(
            $info['email'],
            $info['name'],
            $info['title']
        );
    }
}

// Handle approval
if (isset($_POST['approve_note'])) {
    $noteId = $_POST['note_id'];

    // Update status
    $stmt = $pdo->prepare("UPDATE notes SET status = 'approved' WHERE id = ?");
    $stmt->execute([$noteId]);

    // Fetch user & title to send email
    $stmt2 = $pdo->prepare("
        SELECT n.title, u.email, u.name
        FROM notes n
        JOIN users u ON n.user_id = u.id
        WHERE n.id = ?
    ");
    $stmt2->execute([$noteId]);
    $info = $stmt2->fetch();

    if ($info) {
        $mailer->sendAdminApprovalEmail(
            $info['email'],
            $info['name'],
            $info['title']
        );
    }
}

// Handle rejection
if (isset($_POST['reject_note'])) {
    $noteId = $_POST['note_id'];

    // Update status
    $stmt = $pdo->prepare("UPDATE notes SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$noteId]);

    // Fetch user & title to send email
    $stmt2 = $pdo->prepare("
        SELECT n.title, u.email, u.name
        FROM notes n
        JOIN users u ON n.user_id = u.id
        WHERE n.id = ?
    ");
    $stmt2->execute([$noteId]);
    $info = $stmt2->fetch();

    if ($info) {
        $mailer->sendAdminRejectionEmail(
            $info['email'],
            $info['name'],
            $info['title']
        );
    }
}

// Fetch notes list (unchanged)
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

<div class="manage-container">
    <div class="container">
        <div class="manage-header">
            <h2>📚 Manage Notes & Uploads</h2>
            <div class="mt-3">
                <a href="download_notes_report.php" class="btn btn-success">
                    <i class="bi bi-download me-1"></i> Download Notes Report
                </a>
            </div>
        </div>

        <!-- Rest of your existing code -->

<!-- Back Button -->
<div style="margin-bottom: 1.5rem; margin-top: 1rem;">
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
    </a>
</div>

<style>
.manage-container {
    background: #f8fafc;
    padding: 2rem 0;
}

.manage-header {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
}

.manage-header h2 {
    margin: 0;
    font-size: 1.8rem;
}

.notes-table-wrapper {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

table {
    width: 100%;
    border-collapse: collapse;
}

table thead {
    background: #f1f5f9;
    border-bottom: 2px solid #e2e8f0;
}

table th {
    padding: 1rem;
    text-align: left;
    font-weight: 700;
    color: #334155;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

table td {
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;
    color: #475569;
}

table tbody tr:hover {
    background: #f8fafc;
}

.status-badge {
    display: inline-block;
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-approved {
    background: #dbeafe;
    color: #1e40af;
}

.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.action-btn {
    padding: 0.5rem 0.75rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 600;
    margin-right: 0.25rem;
    transition: all 0.2s;
    white-space: nowrap;
}

.btn-approve {
    background: #10b981;
    color: white;
}

.btn-approve:hover {
    background: #059669;
}

.btn-reject {
    background: #f59e0b;
    color: white;
}

.btn-reject:hover {
    background: #d97706;
}

.btn-delete {
    background: #ef4444;
    color: white;
}

.btn-delete:hover {
    background: #dc2626;
}

.btn-view {
    background: #3b82f6;
    color: white;
}

.btn-view:hover {
    background: #2563eb;
}

@media (max-width: 768px) {
    table {
        font-size: 0.9rem;
    }

    table th, table td {
        padding: 0.75rem;
    }

    .action-btn {
        display: block;
        width: 100%;
        margin-bottom: 0.25rem;
    }
}
</style>

<div class="manage-container">
    <div class="container">
        <div class="manage-header">
            <h2>📚 Manage Notes & Uploads</h2>
        </div>

        <div class="notes-table-wrapper">
            <table>
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Description</th>
            <th>Category</th>
            <th>Type</th>
            <th>Uploaded By</th>
            <th>File</th>
            <th>Status</th>
            <th>Uploaded At</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($notes as $n): ?>
        <tr>
            <td><?= $n['id'] ?></td>
            <td><?= htmlspecialchars($n['title']) ?></td>
            <td><?= htmlspecialchars($n['description']) ?></td>
            <td><?= htmlspecialchars($n['category_name']) ?></td>
            <td><?= ucfirst(str_replace('_', ' ', $n['type'])) ?></td>
            <td><?= htmlspecialchars($n['uploader_name'] ?? 'Unknown') ?></td>
            <td>
                <?php if (!empty($n['file_path']) && file_exists("../../" . $n['file_path'])): ?>
                    <a href="../../<?= $n['file_path'] ?>" target="_blank" class="action-btn btn-view">View</a>
                <?php else: ?>
                    <span style="color: #ef4444; font-weight: 600;">Missing</span>
                <?php endif; ?>
            </td>
            <td>
                <span class="status-badge status-<?= $n['status'] ?>">
                    <?= ucfirst($n['status']) ?>
                </span>
            </td>
            <td><?= date('M d, Y', strtotime($n['uploaded_at'])) ?></td>
            <td style="display: flex; gap: 0.25rem; flex-wrap: wrap;">
                <?php if ($n['status'] != 'approved'): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="note_id" value="<?= $n['id'] ?>">
                    <button type="submit" name="approve_note" class="action-btn btn-approve">✓ Approve</button>
                </form>
                <?php endif; ?>

                <?php if ($n['status'] != 'rejected'): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="note_id" value="<?= $n['id'] ?>">
                    <button type="submit" name="reject_note" class="action-btn btn-reject">✕ Reject</button>
                </form>
                <?php endif; ?>

                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this note? This cannot be undone.');">
                    <input type="hidden" name="note_id" value="<?= $n['id'] ?>">
                    <button type="submit" name="delete_note" class="action-btn btn-delete">🗑 Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?> 