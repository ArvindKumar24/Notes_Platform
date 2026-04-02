<?php
require_once("../config/config.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];
$role   = $_SESSION["role"];

$message      = "";
$message_type = "danger";

// ── Handle student submission ────────────────────────────────────────────────
if ($role === 'student' && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_assessment'])) {
    $assessment_id = (int)($_POST['assessment_id'] ?? 0);

    if ($assessment_id <= 0) {
        $message = "Invalid assessment.";
    } elseif (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] !== 0) {
        $message = "Please select a file to upload.";
    } else {
        $file    = $_FILES['submission_file'];
        $allowed = ["pdf", "docx"];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $message = "Only PDF and DOCX files are allowed.";
        } elseif ($file['size'] > 100 * 1024 * 1024) {
            $message = "File must not exceed 100 MB.";
        } else {
            // Check student is enrolled in this assessment's course
            $checkStmt = $pdo->prepare("
                SELECT n.id FROM notes n
                INNER JOIN course_enrollments ce ON ce.course_id = n.course_id
                WHERE n.id = ? AND ce.student_id = ? AND n.type = 'assessment' AND n.status = 'approved'
            ");
            $checkStmt->execute([$assessment_id, $userId]);

            if (!$checkStmt->fetch()) {
                $message = "You are not enrolled in this course or the assessment is not available.";
            } else {
                // Check for duplicate submission
                $dupStmt = $pdo->prepare("SELECT id FROM student_submissions WHERE assessment_id = ? AND student_id = ?");
                $dupStmt->execute([$assessment_id, $userId]);

                if ($dupStmt->fetch()) {
                    $message = "You have already submitted this assessment. Your teacher will review it.";
                    $message_type = "info";
                } else {
                    $newFile = uniqid() . "_sub_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
                    $dest    = "../uploads/" . $newFile;

                    if (!is_dir("../uploads")) @mkdir("../uploads", 0777, true);

                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        $insStmt = $pdo->prepare("
                            INSERT INTO student_submissions (assessment_id, student_id, file_path)
                            VALUES (?, ?, ?)
                        ");
                        $insStmt->execute([$assessment_id, $userId, $newFile]);
                        $message      = "Your assessment has been submitted successfully! Your teacher will review it shortly.";
                        $message_type = "success";
                    } else {
                        $message = "File upload failed. Please try again.";
                    }
                }
            }
        }
    }
}

// ── Fetch assessments ────────────────────────────────────────────────────────
$category_filter = $_GET['category'] ?? '';
$search          = $_GET['search']   ?? '';

if ($role === 'student') {
    // Students only see assessments for their enrolled courses
    $query = "
        SELECT n.*, u.name AS uploader, c.name AS category_name, co.name AS course_name
        FROM notes n
        LEFT JOIN users u ON n.user_id = u.id
        LEFT JOIN categories c ON n.category_id = c.id
        LEFT JOIN courses co ON co.id = n.course_id
        INNER JOIN course_enrollments ce ON ce.course_id = n.course_id AND ce.student_id = :uid
        WHERE n.type = 'assessment' AND n.status = 'approved'
    ";
    $params = [':uid' => $userId];
} else {
    // Teachers / admins see all assessments
    $query = "
        SELECT n.*, u.name AS uploader, c.name AS category_name, co.name AS course_name
        FROM notes n
        LEFT JOIN users u ON n.user_id = u.id
        LEFT JOIN categories c ON n.category_id = c.id
        LEFT JOIN courses co ON co.id = n.course_id
        WHERE n.type = 'assessment' AND n.status = 'approved'
    ";
    $params = [];
}

if ($category_filter && is_numeric($category_filter)) {
    $query .= " AND n.category_id = :cat";
    $params[':cat'] = $category_filter;
}
if ($search) {
    $query .= " AND (n.title LIKE :s1 OR n.description LIKE :s2 OR c.name LIKE :s3)";
    $params[':s1'] = $params[':s2'] = $params[':s3'] = "%$search%";
}
$query .= " ORDER BY n.uploaded_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pre-load student's existing submissions (for button state)
$mySubmissions = [];
if ($role === 'student') {
    $subStmt = $pdo->prepare("SELECT assessment_id, status, teacher_remarks FROM student_submissions WHERE student_id = ?");
    $subStmt->execute([$userId]);
    foreach ($subStmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $mySubmissions[$s['assessment_id']] = $s;
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$page_title = "Browse Assessments - Notes Platform";
include("includes/header.php");
?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h4 mb-1">Browse Assessments</h2>
        <p class="text-muted mb-3">
            <?= $role === 'student' ? 'Assessments from your enrolled courses' : 'All assessments across all courses' ?>
        </p>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="GET">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search"
                           placeholder="Search by title, description, or category..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <select class="form-select" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-warning w-100">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (count($assessments) > 0): ?>
    <div class="row g-3">
        <?php foreach ($assessments as $a):
            $submission = $mySubmissions[$a['id']] ?? null;
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm border-warning">
                <div class="card-header bg-warning text-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clipboard-data me-1"></i>Assessment</span>
                    <?php if ($a['course_name']): ?>
                        <span class="badge bg-white text-warning" style="font-size:.7rem;"><?= htmlspecialchars($a['course_name']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body d-flex flex-column">
                    <h3 class="h6 mb-2"><?= htmlspecialchars($a['title']) ?></h3>
                    <p class="text-muted small mb-3"><?= htmlspecialchars($a['description'] ?: 'No description provided') ?></p>
                    <ul class="list-unstyled small text-muted mb-3">
                        <li><strong>Category:</strong> <?= htmlspecialchars($a['category_name'] ?? '—') ?></li>
                        <li><strong>Teacher:</strong>  <?= htmlspecialchars($a['uploader']      ?? '—') ?></li>
                        <li><strong>Uploaded:</strong> <?= date('M j, Y', strtotime($a['uploaded_at'])) ?></li>
                        <li><strong>Downloads:</strong><?= (int)$a['downloads_count'] ?></li>
                    </ul>

                    <!-- Submission status badge (students) -->
                    <?php if ($role === 'student' && $submission): ?>
                        <div class="mb-2">
                            <?php
                            $badge = match($submission['status']) {
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default    => 'secondary',
                            };
                            ?>
                            <span class="badge bg-<?= $badge ?>">
                                Submitted – <?= ucfirst($submission['status']) ?>
                            </span>
                            <?php if ($submission['teacher_remarks']): ?>
                                <div class="mt-1 small text-muted">
                                    <strong>Remarks:</strong> <?= htmlspecialchars($submission['teacher_remarks']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-auto d-grid gap-2">
                        <!-- Download assessment -->
                        <a href="download.php?id=<?= $a['id'] ?>" class="btn btn-warning btn-sm">
                            <i class="bi bi-download me-1"></i>Download Assessment
                        </a>

                        <!-- Submit work (students only, not yet submitted) -->
                        <?php if ($role === 'student' && !$submission): ?>
                            <button type="button" class="btn btn-sm btn-outline-success"
                                    data-bs-toggle="modal"
                                    data-bs-target="#submitModal"
                                    data-id="<?= $a['id'] ?>"
                                    data-title="<?= htmlspecialchars($a['title']) ?>">
                                <i class="bi bi-upload me-1"></i>Submit My Work
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card text-center shadow-sm">
        <div class="card-body py-5">
            <i class="bi bi-clipboard-x text-warning" style="font-size:3rem;"></i>
            <h3 class="h5 mt-3">No assessments available</h3>
            <p class="text-muted">
                <?= $role === 'student'
                    ? 'No assessments have been posted for your enrolled courses yet.'
                    : 'No assessments have been uploaded yet.' ?>
            </p>
        </div>
    </div>
<?php endif; ?>

<div class="text-center my-4">
    <a href="<?= $role === 'teacher' ? 'teacher_dashboard.php' : 'student_dashboard.php' ?>"
       class="btn btn-outline-secondary">← Back to Dashboard</a>
</div>

<!-- ── Submit Work Modal ────────────────────────────────────────────────────── -->
<?php if ($role === 'student'): ?>
<div class="modal fade" id="submitModal" tabindex="-1" aria-labelledby="submitModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:#14B8A6;color:white;">
                <h5 class="modal-title" id="submitModalLabel">
                    <i class="bi bi-upload me-2"></i>Submit Your Work
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <p class="text-muted mb-3">
                        Submitting for: <strong id="assessmentTitle"></strong>
                    </p>
                    <input type="hidden" name="assessment_id" id="assessmentIdInput">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Upload Your Answer File <span class="text-danger">*</span></label>
                        <input type="file" name="submission_file" class="form-control" accept=".pdf,.docx" required>
                        <div class="form-text">PDF or DOCX only. Max 100 MB.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_assessment" class="btn" style="background:#14B8A6;color:white;">
                        <i class="bi bi-send me-1"></i>Submit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const submitModal = document.getElementById('submitModal');
submitModal.addEventListener('show.bs.modal', function(event) {
    const btn = event.relatedTarget;
    document.getElementById('assessmentIdInput').value = btn.dataset.id;
    document.getElementById('assessmentTitle').textContent = btn.dataset.title;
});
</script>
<?php endif; ?>

<?php include("includes/footer.php"); ?>