<?php
require_once("../../config/config.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$page_title = "Manage Courses - Admin";
$message    = "";
$msgType    = "danger";

// ── Add course ────────────────────────────────────────────────────────────────
if (isset($_POST['add_course'])) {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if (!empty($name)) {
        $pdo->prepare("INSERT INTO courses (name, description) VALUES (?, ?)")->execute([$name, $desc]);
        $message = "Course added successfully.";
        $msgType  = "success";
    } else {
        $message = "Course name is required.";
    }
}

// ── Edit course ───────────────────────────────────────────────────────────────
if (isset($_POST['edit_course'])) {
    $id   = (int)$_POST['course_id'];
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if (!empty($name) && $id > 0) {
        $pdo->prepare("UPDATE courses SET name = ?, description = ? WHERE id = ?")->execute([$name, $desc, $id]);
        $message = "Course updated.";
        $msgType  = "success";
    }
}

// ── Delete course ─────────────────────────────────────────────────────────────
if (isset($_POST['delete_course'])) {
    $id = (int)$_POST['course_id'];
    if ($id > 0) {
        $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$id]);
        $message = "Course deleted.";
        $msgType  = "success";
    }
}

// ── Enrol student manually ────────────────────────────────────────────────────
if (isset($_POST['enrol_student'])) {
    $sid = (int)$_POST['student_id'];
    $cid = (int)$_POST['course_id_enrol'];
    if ($sid > 0 && $cid > 0) {
        try {
            $pdo->prepare("INSERT IGNORE INTO course_enrollments (student_id, course_id) VALUES (?, ?)")->execute([$sid, $cid]);
            $message = "Student enrolled successfully.";
            $msgType  = "success";
        } catch (PDOException $e) {
            $message = "Enrolment failed: " . $e->getMessage();
        }
    }
}

// ── Remove enrolment ──────────────────────────────────────────────────────────
if (isset($_POST['remove_enrolment'])) {
    $eid = (int)$_POST['enrolment_id'];
    if ($eid > 0) {
        $pdo->prepare("DELETE FROM course_enrollments WHERE id = ?")->execute([$eid]);
        $message = "Enrolment removed.";
        $msgType  = "success";
    }
}

// ── Fetch data ────────────────────────────────────────────────────────────────
$courses  = $pdo->query("
    SELECT c.*, COUNT(ce.id) AS enrolled_count
    FROM courses c
    LEFT JOIN course_enrollments ce ON ce.course_id = c.id
    GROUP BY c.id
    ORDER BY c.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$students = $pdo->query("SELECT id, name, email FROM users WHERE role = 'student' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// All enrolments with student + course names
$enrolments = $pdo->query("
    SELECT ce.id, u.name AS student_name, u.email AS student_email, co.name AS course_name, ce.enrolled_at
    FROM course_enrollments ce
    JOIN users u ON u.id = ce.student_id
    JOIN courses co ON co.id = ce.course_id
    ORDER BY co.name, u.name
")->fetchAll(PDO::FETCH_ASSOC);

include("./header.php");
?>

<style>
.manage-header{background:linear-gradient(135deg,#14B8A6,#0d9488);color:white;padding:1.5rem 2rem;border-radius:12px;margin-bottom:2rem;}
.section-card{background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.1);padding:1.75rem;margin-bottom:2rem;}
.section-card h3{color:#1e293b;font-size:1.15rem;font-weight:700;margin-bottom:1.25rem;}
table{width:100%;border-collapse:collapse;}
table th{padding:.85rem 1rem;text-align:left;font-weight:700;color:#334155;font-size:.85rem;text-transform:uppercase;background:#f1f5f9;border-bottom:2px solid #e2e8f0;}
table td{padding:.85rem 1rem;border-bottom:1px solid #e2e8f0;color:#475569;}
table tbody tr:hover{background:#f8fafc;}
.badge-count{display:inline-block;padding:.25rem .6rem;background:#e0f2fe;color:#0369a1;border-radius:12px;font-size:.78rem;font-weight:700;}
</style>

<div class="manage-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h2 class="mb-0">📚 Manage Courses &amp; Enrolments</h2>
    <a href="dashboard.php" class="btn btn-outline-light btn-sm">← Back to Dashboard</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $msgType ?> alert-dismissible fade show">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Add Course -->
<div class="section-card">
    <h3>➕ Add New Course</h3>
    <form method="POST" class="row g-3">
        <div class="col-md-4">
            <input type="text" name="name" class="form-control" placeholder="Course name" required>
        </div>
        <div class="col-md-6">
            <input type="text" name="description" class="form-control" placeholder="Short description (optional)">
        </div>
        <div class="col-md-2">
            <button type="submit" name="add_course" class="btn w-100" style="background:#14B8A6;color:white;">Add</button>
        </div>
    </form>
</div>

<!-- Existing Courses -->
<div class="section-card">
    <h3>📋 Existing Courses</h3>
    <?php if ($courses): ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Enrolled Students</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $c): ?>
                <tr>
                    <td>#<?= $c['id'] ?></td>
                    <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                    <td><?= htmlspecialchars($c['description'] ?: '—') ?></td>
                    <td><span class="badge-count"><?= $c['enrolled_count'] ?> student(s)</span></td>
                    <td>
                        <!-- Edit inline -->
                        <form method="POST" class="d-flex gap-1 align-items-center mb-1">
                            <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                            <input type="text" name="name" class="form-control form-control-sm" value="<?= htmlspecialchars($c['name']) ?>" required style="width:140px;">
                            <input type="text" name="description" class="form-control form-control-sm" value="<?= htmlspecialchars($c['description'] ?? '') ?>" placeholder="Description" style="width:180px;">
                            <button type="submit" name="edit_course" class="btn btn-sm btn-primary">Update</button>
                        </form>
                        <!-- Delete -->
                        <form method="POST" onsubmit="return confirm('Delete this course? All enrolments will be removed.');">
                            <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                            <button type="submit" name="delete_course" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p class="text-muted">No courses yet. Add one above.</p>
    <?php endif; ?>
</div>

<!-- Enrol Student -->
<div class="section-card">
    <h3>👤 Manually Enrol a Student</h3>
    <form method="POST" class="row g-3">
        <div class="col-md-5">
            <label class="form-label">Student</label>
            <select name="student_id" class="form-select" required>
                <option value="">Select student</option>
                <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['email']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label">Course</label>
            <select name="course_id_enrol" class="form-select" required>
                <option value="">Select course</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" name="enrol_student" class="btn w-100" style="background:#14B8A6;color:white;">Enrol</button>
        </div>
    </form>
</div>

<!-- All Enrolments -->
<div class="section-card">
    <h3>📑 All Enrolments (<?= count($enrolments) ?>)</h3>
    <?php if ($enrolments): ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Email</th>
                    <th>Course</th>
                    <th>Enrolled At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($enrolments as $e): ?>
                <tr>
                    <td><?= htmlspecialchars($e['student_name']) ?></td>
                    <td><?= htmlspecialchars($e['student_email']) ?></td>
                    <td><?= htmlspecialchars($e['course_name']) ?></td>
                    <td><?= date('M j, Y', strtotime($e['enrolled_at'])) ?></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Remove this enrolment?');">
                            <input type="hidden" name="enrolment_id" value="<?= $e['id'] ?>">
                            <button type="submit" name="remove_enrolment" class="btn btn-sm btn-outline-danger">Remove</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p class="text-muted">No enrolments yet.</p>
    <?php endif; ?>
</div>

<?php include("../includes/footer.php"); ?>