<?php
require_once("../config/config.php");

if (empty($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$userName = $_SESSION["name"] ?? $_SESSION["username"] ?? "Student";

// Get user profile information
$profileStmt = $pdo->prepare("SELECT id, name, email, role, created_at, profile_picture FROM users WHERE id = ?");
$profileStmt->execute([$userId]);
$userProfile = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: ['name' => 'Student', 'email' => '', 'role' => 'student', 'created_at' => date('Y-m-d'), 'profile_picture' => ''];

// Get user stats
$statsStmt = $pdo->prepare("
    SELECT COUNT(*) AS total_uploads, COALESCE(SUM(downloads_count), 0) AS total_downloads
    FROM notes
    WHERE user_id = ?
");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
if (!$stats) {
    $stats = ["total_uploads" => 0, "total_downloads" => 0];
}

// Get user's recent notes
$myNotesStmt = $pdo->prepare("
    SELECT n.id, n.title, n.uploaded_at, n.downloads_count, c.name AS category_name
    FROM notes n
    LEFT JOIN categories c ON n.category_id = c.id
    WHERE n.user_id = ?
    ORDER BY n.uploaded_at DESC
    LIMIT 10
");
$myNotesStmt->execute([$userId]);
$myNotes = $myNotesStmt->fetchAll(PDO::FETCH_ASSOC);
if (!$myNotes) {
    $myNotes = [];
}

// Get recent notes from other students - FIXED with proper error handling
$recentFromOthers = [];
try {
    $recentFromOthersStmt = $pdo->prepare("
        SELECT n.id, n.title, n.description, n.uploaded_at, c.name AS category_name, u.name AS uploader_name
        FROM notes n
        LEFT JOIN categories c ON n.category_id = c.id
        LEFT JOIN users u ON n.user_id = u.id
        WHERE n.user_id <> ?
        ORDER BY n.uploaded_at DESC
        LIMIT 4
    ");
    $recentFromOthersStmt->execute([$userId]);
    $recentFromOthers = $recentFromOthersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentFromOthers = [];
}

$page_title = "Student Dashboard";
include("includes/header.php");
?>

<!-- Back Button -->
<div class="mb-3">
    <a href="index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Home
    </a>
</div>

<!-- Welcome Section -->
<div class="mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
            <h1 class="h3 mb-1">Welcome back, <?php echo htmlspecialchars($userName); ?> 👋</h1>
            <p class="text-muted mb-0">Here's a quick snapshot of your contribution to the community.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="upload_notes.php" class="btn btn-danger">
                <i class="bi bi-upload me-1"></i>Upload Notes
            </a>
            <a href="view_notes.php" class="btn btn-outline-secondary">Browse Notes</a>
        </div>
    </div>
</div>

<!-- Profile Card Section -->
<div class="mb-4">
    <div class="row g-3">
        <div class="col-lg-4 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-person-circle me-1"></i>Your Profile
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <?php 
                        $profilePicPath = null;
                        if (!empty($userProfile['profile_picture'])) {
                            // Check if path is already relative or absolute
                            if (strpos($userProfile['profile_picture'], 'uploads/profiles/') === 0) {
                                $profilePicPath = '../' . $userProfile['profile_picture'];
                            } else {
                                $profilePicPath = $userProfile['profile_picture'];
                            }
                            
                            // Check if file exists
                            if (file_exists($profilePicPath)) {
                                echo '<img src="' . htmlspecialchars($profilePicPath) . '" 
                                     alt="Profile Picture" 
                                     class="rounded-circle"
                                     style="width: 100px; height: 100px; object-fit: cover; border: 3px solid #0d6efd;">';
                            } else {
                                // File doesn't exist, show default
                                echo '<div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px; font-size: 2rem;">
                                        <i class="bi bi-person-fill text-primary"></i>
                                      </div>';
                            }
                        } else {
                            // No profile picture set
                            echo '<div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px; font-size: 2rem;">
                                    <i class="bi bi-person-fill text-primary"></i>
                                  </div>';
                        }
                        ?>
                    </div>
                    <h5 class="card-title text-center"><?php echo htmlspecialchars($userProfile['name']); ?></h5>
                    <ul class="list-unstyled small">
                        <li class="mb-2">
                            <strong>Email:</strong><br>
                            <span class="text-muted text-break"><?php echo htmlspecialchars($userProfile['email']); ?></span>
                        </li>
                        <li class="mb-2">
                            <strong>Role:</strong><br>
                            <span class="badge bg-info"><?php echo ucfirst(htmlspecialchars($userProfile['role'])); ?></span>
                        </li>
                        <li class="mb-2">
                            <strong>Joined:</strong><br>
                            <span class="text-muted"><?php echo date('M d, Y', strtotime($userProfile['created_at'])); ?></span>
                        </li>
                    </ul>
                    <div class="mt-3 d-grid gap-2">
                        <a href="edit_profile.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-pencil me-1"></i>Edit Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <!-- ... rest of the code remains the same ... -->
        <div class="col-lg-8 col-xl-9">
            <div class="row g-3 h-100">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1">Total Uploads</p>
                            <h2 class="display-6 text-danger mb-0"><?php echo (int)$stats["total_uploads"]; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1">Total Downloads</p>
                            <h2 class="display-6 text-danger mb-0"><?php echo (int)$stats["total_downloads"]; ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



<div class="card shadow-sm mb-4">
    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-journal me-1"></i>My Recent Uploads</span>
        <a href="upload_notes.php" class="btn btn-light btn-sm">Upload New</a>
    </div>
    <div class="card-body p-0">
        <?php if (count($myNotes) > 0): ?>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Uploaded</th>
                            <th>Downloads</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myNotes as $note): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($note["title"]); ?></td>
                                <td><?php echo htmlspecialchars($note["category_name"] ?? "Uncategorized"); ?></td>
                                <td><?php echo date("M j, Y", strtotime($note["uploaded_at"])); ?></td>
                                <td><?php echo (int)$note["downloads_count"]; ?></td>
                                <td class="text-end">
                                    <a href="download.php?id=<?php echo $note["id"]; ?>" class="btn btn-outline-danger btn-sm">
                                        <i class="bi bi-download"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-4 text-center text-muted">
                <p class="mb-0">You haven't uploaded any notes yet. Start by sharing your first study resource!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <span><i class="bi bi-people me-1"></i>Newest from Fellow Students</span>
                <a href="view_notes.php" class="btn btn-outline-secondary btn-sm">View All</a>
            </div>
            <div class="card-body">
                <?php if (is_array($recentFromOthers) && count($recentFromOthers) > 0): ?>
                    <style>
                    .note-card-mini {
                        background: white;
                        border-radius: 10px;
                        overflow: hidden;
                        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
                        transition: all 0.3s ease;
                        margin-bottom: 1rem;
                        display: flex;
                        height: 140px;
                    }

                    .note-card-mini:last-child {
                        margin-bottom: 0;
                    }

                    .note-card-mini:hover {
                        transform: translateX(5px);
                        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
                    }

                    .note-thumb-mini {
                        width: 140px;
                        height: 140px;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: white;
                        font-size: 2.5rem;
                        flex-shrink: 0;
                        position: relative;
                    }

                    .note-thumb-mini.pdf {
                        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                    }

                    .note-thumb-mini.doc {
                        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                    }

                    .note-info-mini {
                        padding: 1rem;
                        display: flex;
                        flex-direction: column;
                        justify-content: space-between;
                        flex: 1;
                    }

                    .note-title-mini {
                        font-weight: 600;
                        color: #1e293b;
                        font-size: 0.95rem;
                        display: -webkit-box;
                        -webkit-line-clamp: 1;
                        -webkit-box-orient: vertical;
                        overflow: hidden;
                        margin-bottom: 0.35rem;
                    }

                    .note-desc-mini {
                        font-size: 0.8rem;
                        color: #64748b;
                        display: -webkit-box;
                        -webkit-line-clamp: 1;
                        -webkit-box-orient: vertical;
                        overflow: hidden;
                        margin-bottom: 0.5rem;
                    }

                    .note-meta-mini {
                        display: flex;
                        gap: 1rem;
                        font-size: 0.75rem;
                        color: #64748b;
                    }

                    .note-meta-mini i {
                        color: #dc2626;
                    }
                    </style>

                    <div>
                        <?php foreach ($recentFromOthers as $note): 
                            $ext = strtolower(pathinfo($note['file_path'] ?? '', PATHINFO_EXTENSION));
                            $typeClass = match($ext) {
                                'pdf' => 'pdf',
                                'txt', 'doc', 'docx' => 'doc',
                                default => 'doc'
                            };
                            $fileTypeIcon = match($ext) {
                                'pdf' => '📄',
                                'txt' => '📝',
                                'doc', 'docx' => '📋',
                                default => '📦'
                            };
                        ?>
                            <a href="javascript:void(0);" onclick="openPreview(<?php echo htmlspecialchars(json_encode($note)); ?>)" style="text-decoration: none; color: inherit;">
                                <div class="note-card-mini">
                                    <div class="note-thumb-mini <?php echo $typeClass; ?>">
                                        <?php echo $fileTypeIcon; ?>
                                    </div>
                                    <div class="note-info-mini">
                                        <div>
                                            <div class="note-title-mini"><?php echo htmlspecialchars($note["title"]); ?></div>
                                            <div class="note-desc-mini"><?php echo htmlspecialchars($note["description"] ?: "No description"); ?></div>
                                        </div>
                                        <div class="note-meta-mini">
                                            <div><i class="bi bi-person"></i> <?php echo htmlspecialchars($note["uploader_name"] ?? "Unknown"); ?></div>
                                            <div><i class="bi bi-calendar3"></i> <?php echo date("M d, Y", strtotime($note["uploaded_at"])); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No new uploads from other students yet. Check back soon!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Assessments Section -->
    <div class="col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-warning text-white">
                <i class="bi bi-clipboard-data me-1"></i>Assessments
            </div>
            <div class="card-body d-flex flex-column">
                <p class="text-muted flex-grow-1">Prepare for exams with the latest assessments shared by teachers.</p>
                <a href="view_assessments.php" class="btn btn-outline-warning">Browse Assessments</a>
            </div>
        </div>
    </div>
    
    <!-- Past Papers Section -->
    <div class="col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-danger text-white">
                <i class="bi bi-archive me-1"></i>Past Papers
            </div>
            <div class="card-body d-flex flex-column">
                <p class="text-muted flex-grow-1">Access previous year papers to practice before your tests.</p>
                <a href="view_papers.php" class="btn btn-outline-danger">Browse Papers</a>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="modal" style="display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5);">
    <div class="modal-dialog" style="position: relative; width: auto; margin: 1.75rem auto; max-width: 800px; max-height: 90vh; display: flex;">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; max-height: 90vh; display: flex; flex-direction: column;">
            <!-- Modal Header -->
            <div class="modal-header" style="padding: 1.5rem; border-bottom: 1px solid #e2e8f0; background: #f8fafc; flex-shrink: 0;">
                <h5 class="modal-title" id="previewTitle" style="margin: 0; color: #1e293b; font-weight: 600;"></h5>
                <button type="button" class="btn-close" onclick="closePreview()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body" style="padding: 1.5rem; overflow-y: auto; flex: 1;">
                <!-- Note Info -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 8px;">
                    <div>
                        <div style="font-weight: 600; color: #64748b; font-size: 0.85rem; text-transform: uppercase; margin-bottom: 0.25rem;">Category</div>
                        <div style="color: #1e293b;" id="previewCategory"></div>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: #64748b; font-size: 0.85rem; text-transform: uppercase; margin-bottom: 0.25rem;">Type</div>
                        <div style="color: #1e293b;" id="previewType"></div>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: #64748b; font-size: 0.85rem; text-transform: uppercase; margin-bottom: 0.25rem;">Uploader</div>
                        <div style="color: #1e293b;" id="previewUploader"></div>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: #64748b; font-size: 0.85rem; text-transform: uppercase; margin-bottom: 0.25rem;">Date</div>
                        <div style="color: #1e293b;" id="previewDate"></div>
                    </div>
                </div>

                <!-- Description -->
                <div style="margin-bottom: 1.5rem;">
                    <h6 style="color: #64748b; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; margin-bottom: 0.5rem;">Description</h6>
                    <p id="previewDescription" style="color: #475569; line-height: 1.6; margin: 0;"></p>
                </div>

                <!-- File Preview Container -->
                <div id="previewFileContainer" style="border: 2px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; min-height: 300px; background: #f8fafc; display: flex; align-items: center; justify-content: center; flex-direction: column; text-align: center; color: #64748b;"></div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer" style="padding: 1.5rem; border-top: 1px solid #e2e8f0; background: #f8fafc; display: flex; gap: 0.75rem; justify-content: flex-end; flex-shrink: 0;">
                <button type="button" class="btn btn-secondary" onclick="closePreview()" style="padding: 0.5rem 1rem; background: #e2e8f0; color: #334155; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">Close</button>
                <a id="previewDownloadLink" href="#" download class="btn btn-danger" style="padding: 0.5rem 1rem; background: #dc2626; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none;">📥 Download</a>
            </div>
        </div>
    </div>
</div>

<script>
function openPreview(note) {
    const modal = document.getElementById('previewModal');
    const filePath = '../' + note.file_path;
    const ext = getFileExtension(note.file_path);

    // Set header info
    document.getElementById('previewTitle').textContent = note.title;
    document.getElementById('previewCategory').textContent = note.category_name || 'Uncategorized';
    document.getElementById('previewType').textContent = (note.type || '').replace(/_/g, ' ').toUpperCase();
    document.getElementById('previewUploader').textContent = note.uploader_name || 'Unknown';
    document.getElementById('previewDate').textContent = new Date(note.uploaded_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    document.getElementById('previewDescription').textContent = note.description || 'No description provided';

    // Set download link
    const downloadLink = document.getElementById('previewDownloadLink');
    downloadLink.href = filePath;
    downloadLink.download = note.title + '.' + ext;

    const container = document.getElementById('previewFileContainer');
    container.innerHTML = '';

    // Handle different file types
    if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
        const img = document.createElement('img');
        img.src = filePath;
        img.style.maxWidth = '100%';
        img.style.maxHeight = '400px';
        img.style.borderRadius = '8px';
        img.onerror = () => {
            container.innerHTML = '<div style="text-align: center;"><div style="font-size: 2rem; margin-bottom: 1rem;">🖼️</div><p>Image could not be loaded</p><a href="' + filePath + '" download style="color: #dc2626; text-decoration: none; font-weight: 600;">Download image instead</a></div>';
        };
        container.appendChild(img);
        container.style.alignItems = 'center';
        container.style.justifyContent = 'center';
    } else if (ext === 'pdf') {
        const iframe = document.createElement('iframe');
        iframe.src = filePath;
        iframe.style.width = '100%';
        iframe.style.height = '400px';
        iframe.style.borderRadius = '8px';
        iframe.style.border = 'none';
        iframe.onerror = () => {
            container.innerHTML = '<div style="text-align: center;"><div style="font-size: 2rem; margin-bottom: 1rem;">📄</div><p>PDF preview not available</p><a href="' + filePath + '" download style="color: #dc2626; text-decoration: none; font-weight: 600;">Download PDF instead</a></div>';
        };
        container.appendChild(iframe);
    } else if (ext === 'txt') {
        fetch(filePath)
            .then(response => response.text())
            .then(text => {
                const pre = document.createElement('pre');
                pre.style.cssText = 'font-family: "Courier New", monospace; font-size: 0.9rem; line-height: 1.6; color: #334155; max-height: 400px; overflow-y: auto; margin: 0; white-space: pre-wrap; word-wrap: break-word; text-align: left;';
                pre.textContent = text.substring(0, 2000) + (text.length > 2000 ? '\n\n... (Preview truncated)' : '');
                container.appendChild(pre);
                container.style.alignItems = 'flex-start';
                container.style.justifyContent = 'flex-start';
            })
            .catch(() => {
                container.innerHTML = '<div style="text-align: center;"><div style="font-size: 2rem; margin-bottom: 1rem;">📝</div><p>Text file preview not available</p><a href="' + filePath + '" download style="color: #dc2626; text-decoration: none; font-weight: 600;">Download file instead</a></div>';
            });
    } else {
        container.innerHTML = '<div style="text-align: center;"><div style="font-size: 2rem; margin-bottom: 1rem;">📦</div><p>Preview not available for this file type</p><p style="font-size: 0.9rem; color: #64748b; margin-top: 0.5rem;">File format: ' + ext.toUpperCase() + '</p><a href="' + filePath + '" download style="display: inline-block; margin-top: 1rem; color: #dc2626; text-decoration: none; font-weight: 600;">Download file instead</a></div>';
    }

    modal.style.display = 'block';
}

function closePreview() {
    document.getElementById('previewModal').style.display = 'none';
}

function getFileExtension(filepath) {
    return filepath.split('.').pop().toLowerCase();
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('previewModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
});

// Close modal with Escape key
window.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        document.getElementById('previewModal').style.display = 'none';
    }
});
</script>

<?php include("includes/footer.php"); ?> 