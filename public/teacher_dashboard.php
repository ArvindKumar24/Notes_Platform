<?php
require_once("../config/config.php");

if (empty($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$userName = $_SESSION["name"] ?? $_SESSION["username"] ?? "Teacher";

// Get user profile information
$profileStmt = $pdo->prepare("SELECT id, name, email, role, created_at, profile_picture FROM users WHERE id = ?");
$profileStmt->execute([$userId]);
$userProfile = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: ['name' => 'Teacher', 'email' => '', 'role' => 'teacher', 'created_at' => date('Y-m-d'), 'profile_picture' => null];

$statsStmt = $pdo->prepare("
    SELECT COUNT(*) AS total_uploads,
           SUM(CASE WHEN type = 'assessment' THEN 1 ELSE 0 END) AS total_assessments,
           SUM(CASE WHEN type = 'past_paper' THEN 1 ELSE 0 END) AS total_papers
    FROM notes
    WHERE user_id = ?
");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [
    "total_uploads" => 0,
    "total_assessments" => 0,
    "total_papers" => 0
];

$myNotesStmt = $pdo->prepare("
    SELECT n.id, n.title, n.type, n.uploaded_at, n.downloads_count, c.name AS category_name
    FROM notes n
    LEFT JOIN categories c ON n.category_id = c.id
    WHERE n.user_id = ?
    ORDER BY n.uploaded_at DESC
    LIMIT 10
");
$myNotesStmt->execute([$userId]);
$myNotes = $myNotesStmt->fetchAll(PDO::FETCH_ASSOC);

$recentStudentNotes = $pdo->query("
    SELECT n.id, n.title, n.uploaded_at, u.name AS uploader_name
    FROM notes n
    JOIN users u ON n.user_id = u.id
    WHERE u.role = 'student'
    ORDER BY n.uploaded_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Teacher Dashboard";
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
            <p class="text-muted mb-0">Plan your uploads and keep the community engaged.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="upload_notes.php" class="btn btn-danger">
                <i class="bi bi-upload me-1"></i>Upload Notes
            </a>
            <a href="upload_assessments.php" class="btn btn-outline-warning">Upload Assessment</a>
            <a href="upload_papers.php" class="btn btn-outline-secondary">Upload Past Paper</a>
        </div>
    </div>
</div>

<!-- Profile Card Section -->
<div class="mb-4">
    <div class="row g-3">
        <div class="col-lg-4 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-success text-white">
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
                                     class="rounded-circle"
                                     style="width: 100px; height: 100px; object-fit: cover; border: 3px solid #198754;">';
                            } else {
                                // File doesn't exist, show default
                                echo '<div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px; font-size: 2rem;">
                                        <i class="bi bi-person-fill text-success"></i>
                                      </div>';
                            }
                        } else {
                            // No profile picture set
                            echo '<div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px; font-size: 2rem;">
                                    <i class="bi bi-person-fill text-success"></i>
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
                            <span class="badge bg-success"><?php echo ucfirst(htmlspecialchars($userProfile['role'])); ?></span>
                        </li>
                        <li class="mb-2">
                            <strong>Joined:</strong><br>
                            <span class="text-muted"><?php echo date('M d, Y', strtotime($userProfile['created_at'])); ?></span>
                        </li>
                    </ul>
                    <div class="mt-3 d-grid gap-2">
                        <a href="edit_profile.php" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-pencil me-1"></i>Edit Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <!-- ... rest of the code remains the same ... -->
        <div class="col-lg-8 col-xl-9">
            <div class="row g-3 h-100">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1">Total Uploads</p>
                            <h2 class="display-6 text-danger mb-0"><?php echo (int)$stats["total_uploads"]; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1">Assessments Published</p>
                            <h2 class="display-6 text-danger mb-0"><?php echo (int)$stats["total_assessments"]; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1">Past Papers Shared</p>
                            <h2 class="display-6 text-danger mb-0"><?php echo (int)$stats["total_papers"]; ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



<div class="card shadow-sm mb-4">
    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-journal me-1"></i>Your Recent Uploads</span>
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
                            <th>Type</th>
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
                                <td><?php echo ucfirst(str_replace("_", " ", $note["type"])); ?></td>
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
                <p class="mb-0">You haven’t uploaded any content yet. Share your first notes or assessments to help students prepare!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-light">
                <i class="bi bi-people me-1"></i>Latest Student Uploads
            </div>
            <div class="card-body">
                <?php if (count($recentStudentNotes) > 0): ?>
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
                        <?php foreach ($recentStudentNotes as $note): 
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
                            <div class="note-card-mini">
                                <div class="note-thumb-mini <?php echo $typeClass; ?>">
                                    <?php echo $fileTypeIcon; ?>
                                </div>
                                <div class="note-info-mini">
                                    <div>
                                        <div class="note-title-mini"><?php echo htmlspecialchars($note["title"]); ?></div>
                                    </div>
                                    <div class="note-meta-mini">
                                        <div><i class="bi bi-person"></i> <?php echo htmlspecialchars($note["uploader_name"]); ?></div>
                                        <div><i class="bi bi-calendar3"></i> <?php echo date("M d, Y", strtotime($note["uploaded_at"])); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No recent uploads from students yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-warning text-white">
                <i class="bi bi-clipboard-data"></i> Manage Assessments
            </div>
            <div class="card-body d-flex flex-column">
                <p class="text-muted flex-grow-1">Create, update, or remove assessments for your classes.</p>
                <div class="d-grid gap-2">
                    <a href="upload_assessments.php" class="btn btn-outline-warning">Upload Assessment</a>
                    <a href="view_assessments.php" class="btn btn-warning text-white">View Assessments</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-danger text-white">
                <i class="bi bi-archive"></i> Share Past Papers
            </div>
            <div class="card-body d-flex flex-column">
                <p class="text-muted flex-grow-1">Help students practice with previous exam papers.</p>
                <div class="d-grid gap-2">
                    <a href="upload_papers.php" class="btn btn-outline-danger">Upload Paper</a>
                    <a href="view_papers.php" class="btn btn-danger">View Papers</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("includes/footer.php"); ?>
