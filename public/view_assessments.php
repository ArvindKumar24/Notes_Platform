<?php
require_once("../config/config.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Get filters
$category_filter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for assessments only
$query = "SELECT n.*, u.name AS uploader, c.name AS category_name 
          FROM notes n 
          LEFT JOIN users u ON n.user_id = u.id 
          LEFT JOIN categories c ON n.category_id = c.id 
          WHERE n.type = 'assessment' AND n.status = 'approved'";

$params = [];

// Apply category filter
if ($category_filter && is_numeric($category_filter)) {
    $query .= " AND n.category_id = ?";
    $params[] = $category_filter;
}

// Apply search filter
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

// Get categories for filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$page_title = "Browse Assessments - Notes Platform";
include("includes/header.php");
?>



<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h4 mb-3">Browse Assessments</h2>
        <p class="text-muted">Quizzes, tests, and assignments shared by teachers</p>
        
        <form method="GET">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Search Assessments</label>
                    <input type="text" class="form-control" name="search" 
                           placeholder="Search by title, description, or category..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <select class="form-select" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" 
                                <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
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
        <?php foreach ($assessments as $assessment): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-warning">
                    <div class="card-header bg-warning text-white">
                        <i class="bi bi-clipboard-data me-1"></i>Assessment
                    </div>
                    <div class="card-body d-flex flex-column">
                        <h3 class="h6 mb-2"><?php echo htmlspecialchars($assessment['title']); ?></h3>
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($assessment['description'] ?: 'No description provided'); ?></p>
                        <ul class="list-unstyled small text-muted mb-3">
                            <li><strong>Category:</strong> <?php echo htmlspecialchars($assessment['category_name']); ?></li>
                            <li><strong>Teacher:</strong> <?php echo htmlspecialchars($assessment['uploader']); ?></li>
                            <li><strong>Uploaded:</strong> <?php echo date('M j, Y', strtotime($assessment['uploaded_at'])); ?></li>
                            <li><strong>Downloads:</strong> <?php echo $assessment['downloads_count']; ?></li>
                        </ul>
                        <div class="mt-auto">
                            <a href="download.php?id=<?php echo $assessment['id']; ?>" class="btn btn-warning w-100">
                                <i class="bi bi-download me-1"></i>Download
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card text-center shadow-sm">
        <div class="card-body py-5">
            <div class="text-warning mb-3">
                <i class="bi bi-clipboard-x" style="font-size: 3rem;"></i>
            </div>
            <h3 class="h5">No assessments available</h3>
            <p class="text-muted mb-4">Teachers haven't uploaded any assessments yet. Check back later!</p>
        </div>
    </div>
<?php endif; ?>

<div class="text-center my-4">
    <a href="student_dashboard.php" class="btn btn-outline-secondary">← Back to Dashboard</a>
</div>

<?php include("includes/footer.php"); ?>
