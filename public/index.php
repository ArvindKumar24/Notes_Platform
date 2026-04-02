<?php
require_once("../config/config.php");

// Fetch stats - only approved notes and papers (not assessments)
$total_notes = $pdo->query("SELECT COUNT(*) FROM notes WHERE status = 'approved' AND type IN ('note', 'question_paper')")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_downloads = $pdo->query("SELECT COALESCE(SUM(downloads_count),0) FROM notes WHERE status = 'approved' AND type IN ('note', 'question_paper')")->fetchColumn();

// Recent notes and papers (not assessments)
$recent_notes = $pdo->query("
    SELECT n.*, c.name AS category_name, u.name AS uploader_name
    FROM notes n
    LEFT JOIN categories c ON n.category_id = c.id
    LEFT JOIN users u ON n.user_id = u.id
    WHERE n.status = 'approved' AND n.type IN ('note', 'question_paper')
    ORDER BY n.uploaded_at DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Notes Sharing Platform";
include("includes/header.php");
?>

<style>
/* --- Clean, merged, compact styling --- */
.note-card {
    background:#fff;border-radius:12px;overflow:hidden;
    box-shadow:0 2px 10px rgba(0,0,0,0.08);
    display:flex;flex-direction:column;height:100%;
    transition:.25s;
}
.note-card:hover { transform:translateY(-4px);box-shadow:0 6px 18px rgba(0,0,0,0.15); }

.note-thumbnail {
    height:170px;display:flex;align-items:center;justify-content:center;
    color:#fff;font-size:2.7rem;position:relative;
    background:linear-gradient(135deg,#14B8A6,#0d9488);
}
.note-thumbnail.pdf { background:linear-gradient(135deg,#38BDF8,#0284C7); }
.note-thumbnail.doc { background:linear-gradient(135deg,#38BDF8,#06B6D4); }
.note-thumbnail.image { background:linear-gradient(135deg,#43e97b,#38f9d7); }
.note-thumbnail.paper { background:linear-gradient(135deg,#F59E0B,#D97706); }

.note-thumbnail img { width:100%;height:100%;object-fit:cover; }

.file-type-badge {
    position:absolute;top:8px;left:8px;
    background:rgba(0,0,0,0.65);color:#fff;
    padding:4px 8px;border-radius:6px;
    font-size:.75rem;font-weight:600;
}

.note-content { padding:1.2rem;flex:1;display:flex;flex-direction:column; }

.note-type-badge {
    padding:3px 8px;border-radius:6px;font-size:.75rem;font-weight:600;margin-bottom:.6rem;
    display: inline-block;
    width: fit-content;
}
.note-type-badge.note { background:#DBEAFE;color:#1E40AF; }
.note-type-badge.paper { background:#FEF3C7;color:#D97706; }

.note-title {
    font-size:1rem;font-weight:600;margin-bottom:.4rem;
    color:#1e293b;line-height:1.35;
    display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
}

.note-description {
    font-size:.85rem;color:#64748b;line-height:1.4;
    display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
    margin-bottom:.8rem;flex:1;
}

.note-author { display:flex;align-items:center;gap:.5rem;margin-bottom:.7rem;font-size:.85rem;color:#475569; }
.author-avatar {
    width:28px;height:28px;border-radius:50%;background:#f1f5f9;
    display:flex;align-items:center;justify-content:center;font-weight:700;color:#14B8A6;
}

.note-meta { display:flex;gap:1rem;border-top:1px solid #e5e7eb;padding-top:.7rem;font-size:.8rem;color:#64748b; }

.note-footer { display:flex;gap:.5rem;margin-top:1rem; }
.note-footer-btn {
    flex:1;padding:.5rem;border-radius:6px;font-size:.85rem;font-weight:600;
    display:flex;align-items:center;justify-content:center;gap:.3rem;border:none;
}

.btn-preview { background:#f1f5f9;border:1px solid #e2e8f0;color:#475569; }
.btn-preview:hover { background:#e2e8f0; }

.btn-download { background:#14B8A6;color:white; }
.btn-download:hover { background:#0d9488; }

/* Modal */
#previewModal {
    display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
    align-items:center;justify-content:center;z-index:1050;padding:1rem;
}
.modal-box {
    background:white;border-radius:12px;max-width:850px;width:100%;
    max-height:90vh;overflow:hidden;display:flex;flex-direction:column;
}
.modal-header, .modal-footer {
    padding:1rem 1.5rem;background:#f8fafc;
    border-bottom:1px solid #e5e7eb;
}
.modal-body { padding:1.5rem;overflow:auto; }
.preview-info-grid {
    display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
    gap:1rem;background:#f8fafc;padding:1rem;border-radius:8px;margin-bottom:1rem;
}
.preview-file {
    border:2px solid #e2e8f0;border-radius:8px;padding:1rem;min-height:300px;
    display:flex;align-items:center;justify-content:center;background:#f8fafc;
}
</style>

<div class="rounded-4 p-5 text-center shadow-sm" style="background: #14B8A6; color: white;">
    <h1 class="display-5 fw-bold mb-2">Share Knowledge, Grow Together</h1>
    <p class="lead mx-auto mb-4" style="max-width:640px">A platform for students and teachers to share academic notes and past papers.</p>
    <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
        <a href="upload_notes.php" class="btn btn-light btn-lg"><i class="bi bi-upload me-2"></i>Upload Notes</a>
        <a href="view_notes.php" class="btn btn-outline-light btn-lg"><i class="bi bi-search me-2"></i>Browse Notes</a>
    </div>
</div>

<section class="my-5">
    <div class="row g-3 text-center">
        <div class="col-md-4"><div class="card shadow-sm border-0"><div class="card-body py-4">
            <div class="display-5 fw-bold" style="color: #14B8A6;"><?= $total_notes ?></div><div class="text-muted">Study Materials</div>
        </div></div></div>

        <div class="col-md-4"><div class="card shadow-sm border-0"><div class="card-body py-4">
            <div class="display-5 fw-bold" style="color: #14B8A6;"><?= $total_users ?></div><div class="text-muted">Active Users</div>
        </div></div></div>

        <div class="col-md-4"><div class="card shadow-sm border-0"><div class="card-body py-4">
            <div class="display-5 fw-bold" style="color: #14B8A6;"><?= $total_downloads ?></div><div class="text-muted">Total Downloads</div>
        </div></div></div>
    </div>
</section>

<!-- Recent Notes & Papers Section -->
<section class="my-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h2 class="h4 mb-1">📚 Recent Study Materials</h2><p class="text-muted small mb-0">Latest notes and past papers from the community</p></div>
        <a href="view_notes.php" class="btn btn-outline-danger btn-sm">View All →</a>
    </div>

    <?php if ($recent_notes): ?>
    <div class="row g-4">
        <?php foreach ($recent_notes as $note):
            $ext = strtolower(pathinfo($note["file_path"], PATHINFO_EXTENSION));
            $classes = [
                'pdf'=>'pdf','txt'=>'doc','doc'=>'doc','docx'=>'doc',
                'jpg'=>'image','jpeg'=>'image','png'=>'image','gif'=>'image'
            ];
            $icons = [
                'pdf'=>'📄','txt'=>'📝','doc'=>'📋','docx'=>'📋',
                'jpg'=>'🖼️','jpeg'=>'🖼️','png'=>'🖼️','gif'=>'🖼️'
            ];
            $typeClass = $classes[$ext] ?? ($note['type'] === 'question_paper' ? 'paper' : 'doc');
            $icon = $icons[$ext] ?? ($note['type'] === 'question_paper' ? '📜' : '📦');
            
            // Set display type name
            $displayType = $note['type'] === 'question_paper' ? 'Past Paper' : 'Note';
            $typeBadgeClass = $note['type'] === 'question_paper' ? 'paper' : 'note';
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="note-card">

                <div class="note-thumbnail <?= $typeClass ?>">
                    <?= $icon ?>
                    <span class="file-type-badge"><?= strtoupper($ext) ?></span>
                </div>

                <div class="note-content">
                    <span class="note-type-badge <?= $typeBadgeClass ?>"><?= $displayType ?></span>

                    <h3 class="note-title"><?= htmlspecialchars($note["title"]) ?></h3>

                    <p class="note-description"><?= htmlspecialchars($note["description"] ?: "No description provided") ?></p>

                    <div class="note-author">
                        <div class="author-avatar"><?= strtoupper($note["uploader_name"][0] ?? 'U') ?></div>
                        <div>
                            <strong><?= htmlspecialchars($note["uploader_name"] ?: "Unknown") ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($note["category_name"] ?: "Uncategorized") ?></small>
                        </div>
                    </div>

                    <div class="note-meta">
                        <span><i class="bi bi-calendar3 me-1"></i><?= date("M d, Y", strtotime($note["uploaded_at"])) ?></span>
                        <span><i class="bi bi-download me-1"></i><?= $note["downloads_count"] ?> downloads</span>
                    </div>

                    <div class="note-footer">
                        <a href="download.php?id=<?= $note["id"] ?>" class="note-footer-btn btn-download" style="flex: 1;">
                            <i class="bi bi-download"></i> Download
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="card text-center shadow-sm border-0 py-5">
        <div style="font-size:3rem;">📭</div>
        <h5 class="mt-3">No study materials yet</h5>
        <p class="text-muted">Be the first to share notes or past papers.</p>
        <a href="upload_notes.php" class="btn" style="background: #14B8A6; color: white;">Upload Study Material</a>
    </div>
    <?php endif; ?>
</section>

<!-- Modal -->
<div id="previewModal" style="display:none;">
</div>

<script>
</script>

<section class="my-5">
    <div class="bg-light border rounded-4 p-5 text-center shadow-sm">
        <h2 class="h4 mb-3">Ready to Share Your Knowledge?</h2>
        <p class="text-muted mb-4">Join our community of students and teachers.</p>
        <a href="register.php" class="btn btn-lg" style="background: #14B8A6; color: white;">Get Started</a>
    </div>
</section>

<?php include("includes/footer.php"); ?>