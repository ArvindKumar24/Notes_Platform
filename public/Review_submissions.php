<?php
require_once("../config/config.php");
require_once("includes/EmailSender.php");

// Only teachers can access this page
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit;
}

$teacherId = (int)$_SESSION["user_id"];
$teacherName = $_SESSION["name"] ?? "Teacher";
$message = "";
$message_type = "danger";

// Handle approve/reject actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submission_id = (int)($_POST['submission_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');

    if ($submission_id > 0 && in_array($action, ['approve', 'reject'])) {
        try {
            $verifyStmt = $pdo->prepare("
                SELECT ss.id, ss.student_id, ss.file_path, ss.submitted_at, 
                       n.id as assessment_id, n.title AS assessment_title, n.course,
                       u.name AS student_name, u.email AS student_email
                FROM student_submissions ss
                INNER JOIN notes n ON n.id = ss.assessment_id
                INNER JOIN users u ON u.id = ss.student_id
                WHERE ss.id = ? AND n.user_id = ?
            ");
            $verifyStmt->execute([$submission_id, $teacherId]);
            $submission = $verifyStmt->fetch(PDO::FETCH_ASSOC);

            if ($submission) {
                $newStatus = $action === 'approve' ? 'approved' : 'rejected';
                
                $updateStmt = $pdo->prepare("
                    UPDATE student_submissions
                    SET status = ?, teacher_remarks = ?, reviewed_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$newStatus, $remarks ?: null, $submission_id]);

                $message = "Submission " . ($newStatus === 'approved' ? 'approved' : 'rejected') . " successfully!";
                $message_type = "success";

                try {
                    $mailer = new EmailSender();
                    $mailer->sendSubmissionReviewedEmail(
                        $submission['student_email'],
                        $submission['student_name'],
                        $submission['assessment_title'],
                        $newStatus,
                        $remarks
                    );
                    error_log("Review email sent to: " . $submission['student_email']);
                } catch (Exception $e) {
                    error_log("Email error in review_submissions: " . $e->getMessage());
                }
            } else {
                $message = "Submission not found or you don't have permission to review it.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $message_type = "danger";
            error_log("Review error: " . $e->getMessage());
        }
    }
}

// Filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_course = $_GET['course'] ?? '';
$filter_assessment = $_GET['assessment'] ?? '';

// Fetch all assessments created by this teacher
$teacherAssessments = $pdo->prepare("
    SELECT id, title, course 
    FROM notes 
    WHERE user_id = ? AND type = 'assessment' 
    ORDER BY uploaded_at DESC
");
$teacherAssessments->execute([$teacherId]);
$teacherAssessments = $teacherAssessments->fetchAll(PDO::FETCH_ASSOC);

// Get unique courses
$teacherCourses = array_unique(array_column($teacherAssessments, 'course'));
$teacherCourses = array_filter($teacherCourses);

// Build query for submissions
$query = "
    SELECT 
        ss.id AS submission_id,
        ss.file_path AS submission_file,
        ss.submitted_at,
        ss.status,
        ss.teacher_remarks,
        ss.reviewed_at,
        u.id AS student_id,
        u.name AS student_name,
        u.email AS student_email,
        u.course AS student_course,
        n.id AS assessment_id,
        n.title AS assessment_title,
        n.course AS assessment_course,
        n.uploaded_at AS assessment_uploaded_at
    FROM student_submissions ss
    INNER JOIN users u ON u.id = ss.student_id
    INNER JOIN notes n ON n.id = ss.assessment_id
    WHERE n.user_id = :teacher_id
";

$params = [':teacher_id' => $teacherId];

if ($filter_status && in_array($filter_status, ['pending', 'approved', 'rejected'])) {
    $query .= " AND ss.status = :status";
    $params[':status'] = $filter_status;
}

if ($filter_course && !empty($filter_course)) {
    $query .= " AND n.course = :course";
    $params[':course'] = $filter_course;
}

if ($filter_assessment && is_numeric($filter_assessment)) {
    $query .= " AND n.id = :assessment_id";
    $params[':assessment_id'] = $filter_assessment;
}

$query .= " ORDER BY ss.submitted_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count submissions by status
$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($submissions as $s) {
    if (isset($counts[$s['status']])) {
        $counts[$s['status']]++;
    }
}

$page_title = "Review Submissions - Teacher Dashboard";
include("includes/header.php");
?>

<style>
/* Your existing styles here (same as before) */
.review-header {
    background: linear-gradient(135deg, #14B8A6 0%, #0d9488 100%);
    color: white;
    padding: 1.5rem 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
}
.review-header h2 { margin: 0; font-size: 1.5rem; }
.review-header p { margin: 0.5rem 0 0 0; opacity: 0.9; }
.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
.stat-card { background: white; border-radius: 12px; padding: 1.25rem; text-align: center; border-left: 4px solid; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.stat-card.pending { border-left-color: #F59E0B; }
.stat-card.approved { border-left-color: #10B981; }
.stat-card.rejected { border-left-color: #EF4444; }
.stat-number { font-size: 2rem; font-weight: 700; line-height: 1; }
.stat-card.pending .stat-number { color: #F59E0B; }
.stat-card.approved .stat-number { color: #10B981; }
.stat-card.rejected .stat-number { color: #EF4444; }
.stat-label { font-size: 0.85rem; color: #64748b; margin-top: 0.5rem; }
.filter-card { background: white; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.submission-card { background: white; border-radius: 12px; margin-bottom: 1rem; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.3s ease; }
.submission-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.15); transform: translateY(-2px); }
.submission-header { background: #f8fafc; padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; }
.student-info { display: flex; align-items: center; gap: 0.75rem; }
.student-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #14B8A6, #0d9488); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 1rem; }
.student-details h4 { margin: 0; font-size: 1rem; font-weight: 600; color: #1e293b; }
.student-details p { margin: 0; font-size: 0.8rem; color: #64748b; }
.status-badge { padding: 0.35rem 0.9rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
.status-pending { background: #fef3c7; color: #92400e; }
.status-approved { background: #dcfce7; color: #166534; }
.status-rejected { background: #fee2e2; color: #991b1b; }
.submission-body { padding: 1.25rem; }
.assessment-info { background: #f1f5f9; border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem; }
.assessment-title { font-weight: 600; color: #1e293b; margin-bottom: 0.25rem; }
.assessment-meta { display: flex; gap: 1rem; font-size: 0.8rem; color: #64748b; flex-wrap: wrap; }
.assessment-meta i { margin-right: 0.25rem; }
.submission-meta { display: flex; gap: 1rem; margin-bottom: 1rem; font-size: 0.85rem; color: #64748b; flex-wrap: wrap; }
.submission-meta i { margin-right: 0.35rem; color: #14B8A6; }
.action-buttons { display: flex; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap; }
.btn-preview, .btn-download { padding: 0.5rem 1rem; border: none; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; }
.btn-preview { background: #3B82F6; color: white; }
.btn-preview:hover { background: #2563EB; transform: translateY(-1px); color: white; text-decoration: none; }
.btn-download { background: #64748B; color: white; }
.btn-download:hover { background: #475569; transform: translateY(-1px); color: white; text-decoration: none; }
.remarks-box { background: #fef3c7; border-left: 3px solid #F59E0B; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
.remarks-box p { margin: 0; font-size: 0.9rem; }
.remarks-label { font-weight: 600; color: #92400e; display: block; margin-bottom: 0.25rem; }
.review-form { background: #f8fafc; border-radius: 8px; padding: 1rem; margin-top: 0.5rem; }
.review-form textarea { width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.75rem; font-size: 0.9rem; resize: vertical; margin-bottom: 0.75rem; }
.review-form textarea:focus { outline: none; border-color: #14B8A6; box-shadow: 0 0 0 2px rgba(20, 184, 166, 0.1); }
.review-actions { display: flex; gap: 0.75rem; }
.btn-approve, .btn-reject { padding: 0.5rem 1rem; border: none; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 0.5rem; }
.btn-approve { background: #10B981; color: white; }
.btn-approve:hover { background: #059669; transform: translateY(-1px); }
.btn-reject { background: #EF4444; color: white; }
.btn-reject:hover { background: #DC2626; transform: translateY(-1px); }
.preview-modal { display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); animation: fadeIn 0.3s ease; }
.preview-modal.active { display: flex !important; align-items: center; justify-content: center; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.preview-modal-dialog { position: relative; width: 90%; max-width: 900px; max-height: 90vh; animation: slideIn 0.3s ease; }
.preview-modal-content { border-radius: 12px; overflow: hidden; max-height: 90vh; display: flex; flex-direction: column; background: white; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); }
.preview-modal-header { padding: 1.5rem; border-bottom: 1px solid #e2e8f0; background: #f8fafc; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
.preview-modal-title { margin: 0; color: #1e293b; font-weight: 600; font-size: 1.25rem; }
.preview-modal-close { background: none; border: none; font-size: 1.75rem; cursor: pointer; color: #64748b; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; border-radius: 6px; }
.preview-modal-close:hover { background: #e2e8f0; color: #1e293b; }
.preview-modal-body { padding: 1.5rem; overflow-y: auto; flex: 1; }
.preview-modal-footer { padding: 1rem 1.5rem; border-top: 1px solid #e2e8f0; background: #f8fafc; display: flex; gap: 0.75rem; justify-content: flex-end; flex-shrink: 0; }
.preview-file-container { border: 2px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; min-height: 400px; background: #f8fafc; display: flex; align-items: center; justify-content: center; flex-direction: column; text-align: center; color: #64748b; }
.preview-file-container img { max-width: 100%; max-height: 500px; border-radius: 8px; object-fit: contain; }
.preview-file-container iframe, .preview-file-container embed { width: 100%; height: 500px; border-radius: 8px; border: none; }
.preview-file-container pre { font-family: "Courier New", monospace; font-size: 0.85rem; line-height: 1.5; color: #334155; max-height: 500px; overflow-y: auto; margin: 0; white-space: pre-wrap; word-wrap: break-word; text-align: left; width: 100%; padding: 1rem; background: white; border-radius: 6px; border: 1px solid #e2e8f0; }
.empty-state { text-align: center; padding: 3rem; background: white; border-radius: 12px; }
.empty-state-icon { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
.empty-state h4 { color: #1e293b; margin-bottom: 0.5rem; }
.empty-state p { color: #64748b; }
@media (max-width: 768px) {
    .stats-grid { grid-template-columns: 1fr; gap: 0.75rem; }
    .submission-header { flex-direction: column; align-items: flex-start; }
    .review-actions { flex-direction: column; }
    .btn-approve, .btn-reject { width: 100%; justify-content: center; }
    .action-buttons { flex-direction: column; }
    .btn-preview, .btn-download { width: 100%; justify-content: center; }
    .assessment-meta { flex-direction: column; gap: 0.25rem; }
    .preview-modal-dialog { width: 95%; }
    .preview-file-container iframe, .preview-file-container embed { height: 300px; }
    .preview-file-container img { max-height: 300px; }
}
</style>

<div class="container">
    <div class="review-header">
        <h2><i class="bi bi-journal-check me-2"></i>Review Student Submissions</h2>
        <p>Review and provide feedback on assessments submitted by your students</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card pending">
            <div class="stat-number"><?= $counts['pending'] ?></div>
            <div class="stat-label">Pending Review</div>
        </div>
        <div class="stat-card approved">
            <div class="stat-number"><?= $counts['approved'] ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card rejected">
            <div class="stat-number"><?= $counts['rejected'] ?></div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="filter-card">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Assessment</label>
                <select name="assessment" class="form-select">
                    <option value="">All Assessments</option>
                    <?php foreach ($teacherAssessments as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $filter_assessment == $a['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['title']) ?>
                            <?= $a['course'] ? "({$a['course']})" : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Course</label>
                <select name="course" class="form-select">
                    <option value="">All Courses</option>
                    <?php foreach ($teacherCourses as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= $filter_course == $c ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-3">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn" style="background:#14B8A6; color:white; flex:1;">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="review_submissions.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-repeat me-1"></i>Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <?php if (count($submissions) > 0): ?>
        <?php foreach ($submissions as $sub): ?>
            <div class="submission-card">
                <div class="submission-header">
                    <div class="student-info">
                        <div class="student-avatar">
                            <?= strtoupper(substr($sub['student_name'], 0, 1)) ?>
                        </div>
                        <div class="student-details">
                            <h4><?= htmlspecialchars($sub['student_name']) ?></h4>
                            <p><?= htmlspecialchars($sub['student_email']) ?></p>
                        </div>
                    </div>
                    <span class="status-badge status-<?= $sub['status'] ?>">
                        <?= ucfirst($sub['status']) ?>
                    </span>
                </div>
                
                <div class="submission-body">
                    <div class="assessment-info">
                        <div class="assessment-title">
                            <i class="bi bi-clipboard-data me-1" style="color:#14B8A6;"></i>
                            <?= htmlspecialchars($sub['assessment_title']) ?>
                        </div>
                        <div class="assessment-meta">
                            <span><i class="bi bi-book"></i> Course: <?= htmlspecialchars($sub['assessment_course'] ?: 'Not specified') ?></span>
                            <span><i class="bi bi-calendar3"></i> Uploaded: <?= date('M j, Y', strtotime($sub['assessment_uploaded_at'])) ?></span>
                        </div>
                    </div>

                    <div class="submission-meta">
                        <span><i class="bi bi-clock-history"></i> Submitted: <?= date('M j, Y g:i A', strtotime($sub['submitted_at'])) ?></span>
                        <?php if ($sub['reviewed_at']): ?>
                            <span><i class="bi bi-check2-circle"></i> Reviewed: <?= date('M j, Y g:i A', strtotime($sub['reviewed_at'])) ?></span>
                        <?php endif; ?>
                    </div>
                            <div class="action-buttons">
                                <button type="button" class="btn-preview" onclick="openPreview('<?= htmlspecialchars($sub['submission_file']) ?>', '<?= htmlspecialchars($sub['student_name']) ?>', '<?= htmlspecialchars($sub['assessment_title']) ?>')">
                                    <i class="bi bi-eye"></i> Preview Submission
                                </button>
                                <a href="download_submission.php?file=<?= urlencode($sub['submission_file']) ?>&student=<?= urlencode($sub['student_name']) ?>&assessment=<?= urlencode($sub['assessment_title']) ?>" class="btn-download">
                                    <i class="bi bi-download"></i> Download
                                </a>
                            </div>

                    <?php if ($sub['teacher_remarks']): ?>
                        <div class="remarks-box">
                            <span class="remarks-label"><i class="bi bi-chat-dots"></i> Your Feedback:</span>
                            <p><?= nl2br(htmlspecialchars($sub['teacher_remarks'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($sub['status'] === 'pending'): ?>
                        <div class="review-form">
                            <form method="POST">
                                <input type="hidden" name="submission_id" value="<?= $sub['submission_id'] ?>">
                                <textarea name="remarks" rows="3" placeholder="Provide feedback to the student... (optional)"></textarea>
                                <div class="review-actions">
                                    <button type="submit" name="action" value="approve" class="btn-approve" onclick="return confirm('Approve this submission? The student will be notified via email.');">
                                        <i class="bi bi-check-circle"></i> Approve
                                    </button>
                                    <button type="submit" name="action" value="reject" class="btn-reject" onclick="return confirm('Reject this submission? The student will be notified via email.');">
                                        <i class="bi bi-x-circle"></i> Reject
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-inbox"></i></div>
            <h4>No Submissions Found</h4>
            <p>
                <?php if ($filter_status || $filter_course || $filter_assessment): ?>
                    No submissions match your filters. Try clearing the filters.
                <?php else: ?>
                    You haven't received any student submissions yet.
                <?php endif; ?>
            </p>
            <?php if ($filter_status || $filter_course || $filter_assessment): ?>
                <a href="review_submissions.php" class="btn btn-outline-secondary mt-2">
                    <i class="bi bi-arrow-repeat me-1"></i> Clear Filters
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="mt-4 text-center">
        <a href="teacher_dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="preview-modal">
    <div class="preview-modal-dialog">
        <div class="preview-modal-content">
            <div class="preview-modal-header">
                <h5 class="preview-modal-title" id="previewModalTitle">Submission Preview</h5>
                <button type="button" class="preview-modal-close" onclick="closePreview()">&times;</button>
            </div>
            <div class="preview-modal-body">
                <div id="previewFileContainer" class="preview-file-container">
                    <div style="text-align: center;">
                        <div style="font-size: 2rem; margin-bottom: 1rem;">📄</div>
                        <p>Loading preview...</p>
                    </div>
                </div>
            </div>
            <div class="preview-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePreview()">Close</button>
                <a id="previewDownloadLink" href="#" class="btn" style="background: #14B8A6; color: white;">Download File</a>
            </div>
        </div>
    </div>
</div>
<script>
function openPreview(fileName, studentName, assessmentTitle) {
    const modal = document.getElementById('previewModal');
    const container = document.getElementById('previewFileContainer');
    const downloadLink = document.getElementById('previewDownloadLink');
    const modalTitle = document.getElementById('previewModalTitle');
    
    modalTitle.textContent = `${studentName}'s Submission - ${assessmentTitle}`;
    
    const ext = getFileExtension(fileName);
    
    // Use serve_file.php for preview (no download parameter)
    const previewUrl = `serve_file.php?file=${encodeURIComponent(fileName)}&t=${Date.now()}`;
    
    // Use download_submission.php for download
    const downloadUrl = `download_submission.php?file=${encodeURIComponent(fileName)}&student=${encodeURIComponent(studentName)}&assessment=${encodeURIComponent(assessmentTitle)}`;
    
    container.innerHTML = `
        <div style="text-align: center;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading preview...</p>
            <p class="text-muted small">File: ${fileName}</p>
        </div>
    `;
    
    downloadLink.href = downloadUrl;
    downloadLink.download = fileName;
    
    // Show preview based on file type
    showPreview(previewUrl, ext, fileName, downloadUrl);
    
    modal.classList.add('active');
}

function showPreview(fileUrl, ext, fileName, downloadUrl) {
    const container = document.getElementById('previewFileContainer');
    container.innerHTML = '';
    
    // For images
    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext)) {
        const img = document.createElement('img');
        img.src = fileUrl;
        img.style.maxWidth = '100%';
        img.style.maxHeight = '500px';
        img.style.objectFit = 'contain';
        img.style.borderRadius = '8px';
        img.onerror = function() {
            container.innerHTML = `
                <div style="text-align: center;">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">🖼️</div>
                    <p>Image could not be loaded</p>
                    <a href="${downloadUrl}" class="btn btn-primary mt-2">Download File</a>
                </div>
            `;
        };
        container.appendChild(img);
    } 
    // For PDF files
    else if (ext === 'pdf') {
        // Use embed tag for PDF preview
        const embedHtml = `
            <div style="width: 100%;">
                <embed src="${fileUrl}" type="application/pdf" width="100%" height="500px" style="border-radius: 8px; border: 1px solid #e2e8f0;">
                <p class="text-muted small text-center mt-2">
                    <a href="${fileUrl}" target="_blank">Open in new tab</a> | 
                    <a href="${downloadUrl}">Download PDF</a>
                </p>
            </div>
        `;
        container.innerHTML = embedHtml;
    } 
    // For text files
    else if (ext === 'txt' || ext === 'csv' || ext === 'md') {
        fetch(fileUrl)
            .then(response => {
                if (!response.ok) throw new Error('File not found');
                return response.text();
            })
            .then(text => {
                const pre = document.createElement('pre');
                pre.textContent = text.substring(0, 10000) + (text.length > 10000 ? '\n\n... (Preview truncated to first 10,000 characters)' : '');
                pre.style.cssText = 'font-family: "Courier New", monospace; font-size: 12px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word; background: #f8fafc; padding: 1rem; border-radius: 8px; max-height: 500px; overflow: auto;';
                container.appendChild(pre);
            })
            .catch((error) => {
                container.innerHTML = `
                    <div style="text-align: center;">
                        <div style="font-size: 2rem; margin-bottom: 1rem;">📝</div>
                        <p>Text file could not be loaded</p>
                        <a href="${downloadUrl}" class="btn btn-primary mt-2">Download File</a>
                    </div>
                `;
            });
    } 
    // For DOC/DOCX files - use Google Docs Viewer
    else if (ext === 'docx' || ext === 'doc') {
        // Get the absolute URL for the file
        const absoluteUrl = window.location.origin + window.location.pathname.replace('review_submissions.php', 'serve_file.php?file=' + encodeURIComponent(fileName));
        const googleViewerUrl = `https://docs.google.com/gview?url=${encodeURIComponent(absoluteUrl)}&embedded=true`;
        
        container.innerHTML = `
            <div style="text-align: center; width: 100%;">
                <div style="font-size: 2rem; margin-bottom: 1rem;">📋</div>
                <div class="mb-3">
                    <button onclick="loadGoogleViewer()" class="btn btn-sm btn-outline-primary">Preview with Google Docs</button>
                </div>
                <div id="docxViewer">
                    <p class="text-muted">Click the button above to preview this document.</p>
                    <a href="${downloadUrl}" class="btn btn-primary mt-2">Download File</a>
                </div>
            </div>
            <script>
                function loadGoogleViewer() {
                    const viewerUrl = '${googleViewerUrl}';
                    document.getElementById('docxViewer').innerHTML = '<iframe src="' + viewerUrl + '" style="width: 100%; height: 450px; border: 1px solid #e2e8f0; border-radius: 8px;" frameborder="0"></iframe><p class="text-muted small mt-2">If preview doesn\'t load, <a href="${downloadUrl}">download the file</a> instead.</p>';
                }
            <\/script>
        `;
    } 
    // For other file types
    else {
        container.innerHTML = `
            <div style="text-align: center;">
                <div style="font-size: 2rem; margin-bottom: 1rem;">📦</div>
                <p>Preview not available for ${ext.toUpperCase()} files</p>
                <p class="text-muted small">File: ${fileName}</p>
                <a href="${downloadUrl}" class="btn btn-primary mt-2">
                    <i class="bi bi-download"></i> Download File
                </a>
            </div>
        `;
    }
}

function closePreview() {
    const modal = document.getElementById('previewModal');
    modal.classList.remove('active');
    // Clear container
    const container = document.getElementById('previewFileContainer');
    if (container) {
        container.innerHTML = '<div style="text-align: center;"><div style="font-size: 2rem; margin-bottom: 1rem;">📄</div><p>Loading preview...</p></div>';
    }
}

function getFileExtension(filepath) {
    if (!filepath) return '';
    return filepath.split('.').pop().toLowerCase();
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('previewModal');
    if (event.target == modal) {
        closePreview();
    }
});

// Close modal with Escape key
window.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closePreview();
    }
});

// Debug function to test file access
function debugFileAccess(fileName) {
    console.log('Testing file access for:', fileName);
    fetch(`serve_file.php?file=${encodeURIComponent(fileName)}`, { method: 'HEAD' })
        .then(response => {
            console.log('File access:', response.ok ? 'SUCCESS' : 'FAILED');
            console.log('Status:', response.status);
            console.log('Content-Type:', response.headers.get('Content-Type'));
        })
        .catch(err => {
            console.log('Error:', err);
        });
}
</script>
<?php include("includes/footer.php"); ?>