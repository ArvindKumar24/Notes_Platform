<?php
require_once("../config/config.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Get filters
$category_filter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for past papers only
$query = "SELECT n.*, u.name AS uploader, c.name AS category_name 
          FROM notes n 
          LEFT JOIN users u ON n.user_id = u.id 
          LEFT JOIN categories c ON n.category_id = c.id 
          WHERE n.type = 'past_paper'";

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
$papers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$page_title = "Browse Past Papers - Notes Platform";
include("includes/header.php");
?>

<!-- Back Button -->
<div class="mb-3">
    <a href="index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Home
    </a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h4 mb-3">Browse Past Papers</h2>
        <p class="text-muted">Previous exam papers shared by teachers for practice</p>
        
        <form method="GET">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Search Papers</label>
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
                    <button type="submit" class="btn btn-danger w-100">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (count($papers) > 0): ?>
    <div class="row g-3">
        <?php foreach ($papers as $paper): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-danger">
                    <div class="card-header bg-danger text-white">
                        <i class="bi bi-archive me-1"></i>Past Paper
                    </div>
                    <div class="card-body d-flex flex-column">
                        <h3 class="h6 mb-2"><?php echo htmlspecialchars($paper['title']); ?></h3>
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($paper['description'] ?: 'No description provided'); ?></p>
                        <ul class="list-unstyled small text-muted mb-3">
                            <li><strong>Category:</strong> <?php echo htmlspecialchars($paper['category_name']); ?></li>
                            <li><strong>Teacher:</strong> <?php echo htmlspecialchars($paper['uploader']); ?></li>
                            <li><strong>Uploaded:</strong> <?php echo date('M j, Y', strtotime($paper['uploaded_at'])); ?></li>
                            <li><strong>Downloads:</strong> <?php echo $paper['downloads_count']; ?></li>
                        </ul>
                        <div class="mt-auto">
                            <a href="download.php?id=<?php echo $paper['id']; ?>" class="btn btn-danger w-100">
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
            <div class="text-danger mb-3">
                <i class="bi bi-archive-x" style="font-size: 3rem;"></i>
            </div>
            <h3 class="h5">No past papers available</h3>
            <p class="text-muted mb-4">Teachers haven't uploaded any past papers yet. Check back later!</p>
        </div>
    </div>
<?php endif; ?>

<div class="text-center my-4">
    <a href="student_dashboard.php" class="btn btn-outline-secondary">← Back to Dashboard</a>
</div>

<?php include("includes/footer.php"); ?>
