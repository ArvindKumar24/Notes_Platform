<?php
require_once("../config/config.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];
$role   = $_SESSION["role"];

// Get student's course if they are a student
$studentCourse = '';
if ($role === 'student') {
    $userStmt = $pdo->prepare("SELECT course FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
    $studentCourse = $userData['course'] ?? '';
}

$message = "";
$message_type = "danger";

// Handle student submission
if ($role === 'student' && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_assessment'])) {
    $assessment_id = (int)($_POST['assessment_id'] ?? 0);

    if ($assessment_id <= 0) {
        $message = "Invalid assessment.";
    } elseif (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] !== 0) {
        $message = "Please select a file to upload.";
    } else {
        $file = $_FILES['submission_file'];
        $allowed = ["pdf", "docx"];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $message = "Only PDF and DOCX files are allowed.";
        } elseif ($file['size'] > 100 * 1024 * 1024) {
            $message = "File must not exceed 100 MB.";
        } else {
            // Check student is enrolled in this assessment's course
            $checkStmt = $pdo->prepare("
                SELECT id FROM notes 
                WHERE id = ? AND type = 'assessment' AND status = 'approved' AND course = ?
            ");
            $checkStmt->execute([$assessment_id, $studentCourse]);

            if (!$checkStmt->fetch()) {
                $message = "You are not enrolled in this course or the assessment is not available.";
            } else {
                // Check for duplicate submission
                $dupStmt = $pdo->prepare("SELECT id FROM student_submissions WHERE assessment_id = ? AND student_id = ?");
                $dupStmt->execute([$assessment_id, $userId]);

                if ($dupStmt->fetch()) {
                    $message = "You have already submitted this assessment.";
                    $message_type = "info";
                } else {
                    $newFile = uniqid() . "_sub_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
                    $dest = "../uploads/" . $newFile;

                    if (!is_dir("../uploads")) @mkdir("../uploads", 0777, true);

                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        $insStmt = $pdo->prepare("
                            INSERT INTO student_submissions (assessment_id, student_id, file_path)
                            VALUES (?, ?, ?)
                        ");
                        $insStmt->execute([$assessment_id, $userId, $newFile]);
                        $message = "Assessment submitted successfully!";
                        $message_type = "success";
                    } else {
                        $message = "File upload failed.";
                    }
                }
            }
        }
    }
}

// Fetch assessments
$category_filter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

if ($role === 'student') {
    // Students only see assessments for their course
    $query = "
        SELECT n.*, u.name AS uploader, c.name AS category_name
        FROM notes n
        LEFT JOIN users u ON n.user_id = u.id
        LEFT JOIN categories c ON n.category_id = c.id
        WHERE n.type = 'assessment' AND n.status = 'approved' AND n.course = ?
    ";
    $params = [$studentCourse];
} else {
    // Teachers see all assessments they created
    $query = "
        SELECT n.*, u.name AS uploader, c.name AS category_name
        FROM notes n
        LEFT JOIN users u ON n.user_id = u.id
        LEFT JOIN categories c ON n.category_id = c.id
        WHERE n.type = 'assessment' AND n.status = 'approved' AND n.user_id = ?
    ";
    $params = [$userId];
}

if ($category_filter && is_numeric($category_filter)) {
    $query .= " AND n.category_id = ?";
    $params[] = $category_filter;
}
if ($search) {
    $query .= " AND (n.title LIKE ? OR n.description LIKE ? OR c.name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}
$query .= " ORDER BY n.uploaded_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pre-load student's existing submissions
$mySubmissions = [];
if ($role === 'student') {
    $subStmt = $pdo->prepare("SELECT assessment_id, status, teacher_remarks FROM student_submissions WHERE student_id = ?");
    $subStmt->execute([$userId]);
    foreach ($subStmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $mySubmissions[$s['assessment_id']] = $s;
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$page_title = "Browse Assessments";
include("includes/header.php");
?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h4 mb-1">Browse Assessments</h2>
        <p class="text-muted mb-3">
            <?= $role === 'student' ? "Assessments for your course: $studentCourse" : 'Your uploaded assessments' ?>
        </p>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="GET">
            <div class="row g-3">
                <div class="col-md-6">
                    <input type="text" class="form-control" name="search"
                           placeholder="Search by title, description, or category..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-4">
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
                    <button type="submit" class="btn btn-warning w-100">Filter</button>
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
                <div class="card-header bg-warning text-white">
                    <i class="bi bi-clipboard-data me-1"></i>Assessment
                    <?php if ($a['course']): ?>
                        <span class="badge bg-white text-warning float-end"><?= htmlspecialchars($a['course']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body d-flex flex-column">
                    <h3 class="h6 mb-2"><?= htmlspecialchars($a['title']) ?></h3>
                    <p class="text-muted small mb-3"><?= htmlspecialchars($a['description'] ?: 'No description') ?></p>
                    <ul class="list-unstyled small text-muted mb-3">
                        <li><strong>Category:</strong> <?= htmlspecialchars($a['category_name'] ?? '—') ?></li>
                        <li><strong>Teacher:</strong> <?= htmlspecialchars($a['uploader'] ?? '—') ?></li>
                        <li><strong>Uploaded:</strong> <?= date('M j, Y', strtotime($a['uploaded_at'])) ?></li>
                    </ul>

                    <?php if ($role === 'student' && $submission): ?>
                        <div class="mb-2">
                            <span class="badge bg-<?= $submission['status'] === 'approved' ? 'success' : ($submission['status'] === 'rejected' ? 'danger' : 'secondary') ?>">
                                Submitted – <?= ucfirst($submission['status']) ?>
                            </span>
                            <?php if ($submission['teacher_remarks']): ?>
                                <div class="mt-1 small"><strong>Remarks:</strong> <?= htmlspecialchars($submission['teacher_remarks']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-auto d-grid gap-2">
                        <a href="download.php?id=<?= $a['id'] ?>" class="btn btn-warning btn-sm">
                            <i class="bi bi-download me-1"></i>Download
                        </a>

                        <?php if ($role === 'student' && !$submission): ?>
                            <button type="button" class="btn btn-sm btn-outline-success"
                                    data-bs-toggle="modal" data-bs-target="#submitModal"
                                    data-id="<?= $a['id'] ?>" data-title="<?= htmlspecialchars($a['title']) ?>">
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
    <div class="card text-center">
        <div class="card-body py-5">
            <i class="bi bi-clipboard-x text-warning" style="font-size:3rem;"></i>
            <h3 class="h5 mt-3">No assessments available</h3>
            <p class="text-muted">
                <?= $role === 'student' ? 'No assessments have been posted for your course yet.' : 'You haven\'t uploaded any assessments yet.' ?>
            </p>
        </div>
    </div>
<?php endif; ?>

<div class="text-center my-4">
    <a href="<?= $role === 'teacher' ? 'teacher_dashboard.php' : 'student_dashboard.php' ?>" class="btn btn-outline-secondary">
        ← Back to Dashboard
    </a>
</div>

<!-- Submit Modal -->
<?php if ($role === 'student'): ?>
<div class="modal fade" id="submitModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:#14B8A6;color:white;">
                <h5 class="modal-title">Submit Your Work</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <p>Submitting for: <strong id="assessmentTitle"></strong></p>
                    <input type="hidden" name="assessment_id" id="assessmentIdInput">
                    <div class="mb-3">
                        <label class="form-label">Upload Answer File <span class="text-danger">*</span></label>
                        <input type="file" name="submission_file" class="form-control" accept=".pdf,.docx" required>
                        <div class="form-text">PDF or DOCX only. Max 100 MB.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_assessment" class="btn" style="background:#14B8A6;color:white;">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('submitModal').addEventListener('show.bs.modal', function(event) {
    const btn = event.relatedTarget;
    document.getElementById('assessmentIdInput').value = btn.dataset.id;
    document.getElementById('assessmentTitle').textContent = btn.dataset.title;
});
</script>
<?php endif; ?>

<?php include("includes/footer.php"); ?>