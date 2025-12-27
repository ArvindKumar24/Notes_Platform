<?php
require_once("../config/config.php");

// Fetch stats
$total_notes = $pdo->query("SELECT COUNT(*) FROM notes")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_downloads = $pdo->query("SELECT COALESCE(SUM(downloads_count),0) FROM notes")->fetchColumn();

// Recent notes
$recent_notes = $pdo->query("
    SELECT n.*, c.name AS category_name, u.name AS uploader_name
    FROM notes n
    LEFT JOIN categories c ON n.category_id = c.id
    LEFT JOIN users u ON n.user_id = u.id
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
    background:linear-gradient(135deg,#667eea,#764ba2);
}
.note-thumbnail.pdf { background:linear-gradient(135deg,#f093fb,#f5576c); }
.note-thumbnail.doc { background:linear-gradient(135deg,#4facfe,#00f2fe); }
.note-thumbnail.image { background:linear-gradient(135deg,#43e97b,#38f9d7); }

.note-thumbnail img { width:100%;height:100%;object-fit:cover; }

.file-type-badge {
    position:absolute;top:8px;left:8px;
    background:rgba(0,0,0,0.65);color:#fff;
    padding:4px 8px;border-radius:6px;
    font-size:.75rem;font-weight:600;
}

.note-content { padding:1.2rem;flex:1;display:flex;flex-direction:column; }

.note-type-badge {
    background:#fee2e2;color:#991b1b;
    padding:3px 8px;border-radius:6px;font-size:.75rem;font-weight:600;margin-bottom:.6rem;
}

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
    display:flex;align-items:center;justify-content:center;font-weight:700;color:#dc2626;
}

.note-meta { display:flex;gap:1rem;border-top:1px solid #e5e7eb;padding-top:.7rem;font-size:.8rem;color:#64748b; }

.note-footer { display:flex;gap:.5rem;margin-top:1rem; }
.note-footer-btn {
    flex:1;padding:.5rem;border-radius:6px;font-size:.85rem;font-weight:600;
    display:flex;align-items:center;justify-content:center;gap:.3rem;border:none;
}

.btn-preview { background:#f1f5f9;border:1px solid #e2e8f0;color:#475569; }
.btn-preview:hover { background:#e2e8f0; }

.btn-download { background:#dc2626;color:white; }
.btn-download:hover { background:#b91c1c; }

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

<div class="bg-danger text-white rounded-4 p-5 text-center shadow-sm">
    <h1 class="display-5 fw-bold mb-2">Share Knowledge, Grow Together</h1>
    <p class="lead mx-auto mb-4" style="max-width:640px">A platform for students and teachers to share academic notes.</p>
    <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
        <a href="upload_notes.php" class="btn btn-light btn-lg"><i class="bi bi-upload me-2"></i>Upload Notes</a>
        <a href="view_notes.php" class="btn btn-outline-light btn-lg"><i class="bi bi-search me-2"></i>Browse Notes</a>
    </div>
</div>

<section class="my-5">
    <div class="row g-3 text-center">
        <div class="col-md-4"><div class="card shadow-sm border-0"><div class="card-body py-4">
            <div class="display-5 fw-bold text-danger"><?= $total_notes ?></div><div class="text-muted">Study Materials</div>
        </div></div></div>

        <div class="col-md-4"><div class="card shadow-sm border-0"><div class="card-body py-4">
            <div class="display-5 fw-bold text-danger"><?= $total_users ?></div><div class="text-muted">Active Users</div>
        </div></div></div>

        <div class="col-md-4"><div class="card shadow-sm border-0"><div class="card-body py-4">
            <div class="display-5 fw-bold text-danger"><?= $total_downloads ?></div><div class="text-muted">Total Downloads</div>
        </div></div></div>
    </div>
</section>

<!-- Recent Notes -->
<section class="my-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h2 class="h4 mb-1">📚 Recent Notes</h2><p class="text-muted small mb-0">Latest study resources</p></div>
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
            $typeClass = $classes[$ext] ?? 'doc';
            $icon = $icons[$ext] ?? '📦';
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="note-card">

                <div class="note-thumbnail <?= $typeClass ?>">
                    <?= $icon ?>
                    <span class="file-type-badge"><?= strtoupper($ext) ?></span>
                </div>

                <div class="note-content">
                    <span class="note-type-badge"><?= ucfirst(str_replace('_',' ',$note['type'])) ?></span>

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
                        <button class="note-footer-btn btn-preview" onclick='openPreview(<?= json_encode($note) ?>)'>
                            <i class="bi bi-eye"></i> Preview
                        </button>
                        <a href="download.php?id=<?= $note["id"] ?>" class="note-footer-btn btn-download">
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
        <h5 class="mt-3">No notes yet</h5>
        <p class="text-muted">Be the first to upload.</p>
        <a href="upload_notes.php" class="btn btn-danger">Upload Note</a>
    </div>
    <?php endif; ?>
</section>

<!-- Modal -->
<div id="previewModal">
    <div class="modal-box">
        <div class="modal-header">
            <h5 id="previewTitle" class="mb-0"></h5>
            <button class="btn-close" onclick="closePreview()"></button>
        </div>

        <div class="modal-body">
            <div class="preview-info-grid">
                <div><strong>Category:</strong><br><span id="previewCategory"></span></div>
                <div><strong>Type:</strong><br><span id="previewType"></span></div>
                <div><strong>Uploader:</strong><br><span id="previewUploader"></span></div>
                <div><strong>Date:</strong><br><span id="previewDate"></span></div>
            </div>

            <p><strong>Description:</strong></p>
            <p id="previewDescription"></p>

            <div id="previewFileContainer" class="preview-file"></div>
        </div>

        <div class="modal-footer">
            <a id="previewDownloadLink" class="btn btn-danger">📥 Download</a>
            <button class="btn btn-secondary" onclick="closePreview()">Close</button>
        </div>
    </div>
</div>

<script>
function openPreview(note){
    const m = document.getElementById("previewModal");
    const file = "../" + note.file_path;
    const ext = file.split('.').pop().toLowerCase();

    previewTitle.textContent = note.title;
    previewCategory.textContent = note.category_name || "—";
    previewType.textContent = (note.type || "").replace(/_/g," ").toUpperCase();
    previewUploader.textContent = note.uploader_name || "Unknown";
    previewDate.textContent = new Date(note.uploaded_at).toLocaleDateString();
    previewDescription.textContent = note.description || "No description provided";

    previewDownloadLink.href = file;
    previewDownloadLink.download = note.title + "." + ext;

    const box = previewFileContainer;
    box.innerHTML = "";

    if(["jpg","jpeg","png","gif"].includes(ext)){
        let img = new Image();
        img.src = file;
        img.style.maxWidth = "100%";
        img.style.maxHeight = "420px";
        box.appendChild(img);
    } else if(ext === "pdf"){
        box.innerHTML = `<iframe src="${file}" style="width:100%;height:420px;border:0;border-radius:8px"></iframe>`;
    } else if(ext === "txt"){
        fetch(file).then(r=>r.text()).then(t=>{
            box.innerHTML = `<pre style="white-space:pre-wrap;max-height:420px;overflow:auto">${t}</pre>`;
        });
    } else {
        box.innerHTML = `<div class="text-muted text-center">Preview not available for ${ext.toUpperCase()}</div>`;
    }

    m.style.display = "flex";
}
function closePreview(){ previewModal.style.display="none"; }
window.addEventListener("keydown", e => { if(e.key==="Escape") closePreview(); });
</script>

<section class="my-5">
    <div class="bg-light border rounded-4 p-5 text-center shadow-sm">
        <h2 class="h4 mb-3">Ready to Share Your Knowledge?</h2>
        <p class="text-muted mb-4">Join our community of students and teachers.</p>
        <a href="register.php" class="btn btn-danger btn-lg">Get Started</a>
    </div>
</section>

<?php include("includes/footer.php"); ?>
