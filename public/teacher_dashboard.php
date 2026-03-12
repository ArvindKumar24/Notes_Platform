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



<!-- Welcome Section -->
<div class="welcome-header mb-4">
    <div class="welcome-content">
        <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($userName); ?> 👋</h1>
        <p class="welcome-subtitle">Plan your uploads and keep the community engaged.</p>
    </div>
    <div class="welcome-actions">
        <a href="upload_notes.php" class="btn" style="background: #14B8A6; color: white; border-radius: 8px; font-weight: 600; padding: 0.625rem 1.25rem;">
            <i class="bi bi-upload me-1"></i>Upload Notes
        </a>
        <a href="upload_assessments.php" class="btn btn-outline-warning" style="border-radius: 8px; font-weight: 600; padding: 0.625rem 1.25rem;">Upload Assessment</a>
        <a href="upload_papers.php" class="btn btn-outline-secondary" style="border-radius: 8px; font-weight: 600; padding: 0.625rem 1.25rem;">Upload Paper</a>
    </div>
</div>

<!-- Profile & Stats Section -->
<div class="profile-stats-section mb-4">
    <div class="profile-card">
        <div class="profile-header">
            <i class="bi bi-person-circle me-2"></i>Your Profile
        </div>
        <div class="profile-content">
            <div class="profile-avatar">
                <?php 
                $profilePicPath = null;
                if (!empty($userProfile['profile_picture'])) {
                    if (strpos($userProfile['profile_picture'], 'uploads/profiles/') === 0) {
                        $profilePicPath = '../' . $userProfile['profile_picture'];
                    } else {
                        $profilePicPath = $userProfile['profile_picture'];
                    }
                    
                    if (file_exists($profilePicPath)) {
                        echo '<img src="' . htmlspecialchars($profilePicPath) . '" alt="Profile Picture" class="profile-pic">';
                    } else {
                        echo '<div class="profile-pic-placeholder"><i class="bi bi-person-fill"></i></div>';
                    }
                } else {
                    echo '<div class="profile-pic-placeholder"><i class="bi bi-person-fill"></i></div>';
                }
                ?>
            </div>
            <div class="profile-info">
                <h5 class="profile-name"><?php echo htmlspecialchars($userProfile['name']); ?></h5>
                <div class="profile-details">
                    <div class="profile-detail-item">
                        <span class="detail-label">Email</span>
                        <span class="detail-value"><?php echo htmlspecialchars($userProfile['email']); ?></span>
                    </div>
                    <div class="profile-detail-item">
                        <span class="detail-label">Role</span>
                        <span class="detail-badge"><?php echo ucfirst(htmlspecialchars($userProfile['role'])); ?></span>
                    </div>
                    <div class="profile-detail-item">
                        <span class="detail-label">Joined</span>
                        <span class="detail-value"><?php echo date('M d, Y', strtotime($userProfile['created_at'])); ?></span>
                    </div>
                </div>
                <a href="edit_profile.php" class="btn btn-edit-profile">
                    <i class="bi bi-pencil me-1"></i>Edit Profile
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-uploads">
            <div class="stat-icon">📤</div>
            <div class="stat-body">
                <p class="stat-label">Total Uploads</p>
                <h3 class="stat-value"><?php echo (int)$stats["total_uploads"]; ?></h3>
            </div>
        </div>
        <div class="stat-card stat-assessments">
            <div class="stat-icon">📋</div>
            <div class="stat-body">
                <p class="stat-label">Assessments</p>
                <h3 class="stat-value"><?php echo (int)$stats["total_assessments"]; ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- My Recent Uploads Section -->
<div class="recent-uploads-section mb-4">
    <div class="section-header">
        <i class="bi bi-journal me-2"></i>My Recent Uploads
        <a href="upload_notes.php" class="btn btn-sm btn-upload-new">
            <i class="bi bi-plus me-1"></i>Upload New
        </a>
    </div>
    <div class="section-content">
        <?php if (count($myNotes) > 0): ?>
            <div class="table-responsive">
                <table class="uploads-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Uploaded</th>
                            <th>Downloads</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myNotes as $note): ?>
                            <tr>
                                <td class="title-cell"><?php echo htmlspecialchars($note["title"]); ?></td>
                                <td class="category-cell"><?php echo ucfirst(str_replace('_', ' ', $note["type"])); ?></td>
                                <td class="category-cell"><?php echo htmlspecialchars($note["category_name"] ?? "Uncategorized"); ?></td>
                                <td class="date-cell"><?php echo date("M d, Y", strtotime($note["uploaded_at"])); ?></td>
                                <td class="downloads-cell"><?php echo (int)$note["downloads_count"]; ?></td>
                                <td class="action-cell">
                                    <a href="download.php?id=<?php echo $note["id"]; ?>" class="btn-download-table">
                                        <i class="bi bi-download"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>You haven't uploaded any content yet. Start sharing your teaching materials!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Content Grid Section -->
<div class="content-grid">
    <!-- Student Uploads Section -->
    <div class="fellow-students-section">
        <div class="section-header">
            <i class="bi bi-people me-2"></i>Recent Student Uploads
            <a href="view_notes.php" class="btn btn-sm btn-view-all">View All →</a>
        </div>
        <div class="section-content">
            <?php if (is_array($recentStudentNotes) && count($recentStudentNotes) > 0): ?>
                <div class="activity-list">
                    <?php foreach ($recentStudentNotes as $note): ?>
                        <div class="activity-item">
                            <div class="activity-icon">👨‍🎓</div>
                            <div class="activity-body">
                                <div class="activity-title"><?php echo htmlspecialchars($note["title"]); ?></div>
                                <div class="activity-meta">
                                    <span><i class="bi bi-person"></i> <?php echo htmlspecialchars($note["uploader_name"] ?? "Unknown"); ?></span>
                                    <span><i class="bi bi-calendar3"></i> <?php echo date("M d, Y", strtotime($note["uploaded_at"])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-search"></i>
                    <p>No student uploads yet. Check back soon!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Access Cards -->
    <div class="quick-access-section">
        <div class="quick-card papers-card">
            <div class="card-header">
                <i class="bi bi-archive me-2"></i>Past Papers
            </div>
            <div class="card-body">
                <p>Access and review past papers to prepare assessments.</p>
                <a href="view_papers.php" class="btn-card-action">
                    Browse Papers →
                </a>
            </div>
        </div>

        <div class="quick-card notes-card">
            <div class="card-header" style="background: linear-gradient(135deg, #38BDF8 0%, #0284C7 100%);">
                <i class="bi bi-book me-2"></i>Student Notes
            </div>
            <div class="card-body">
                <p>Review the study materials uploaded by your students.</p>
                <a href="view_notes.php" class="btn-card-action" style="color: #38BDF8;">
                    Browse Notes →
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* Dashboard Styles - Shared with Student Dashboard */
.welcome-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid #e2e8f0;
}

.welcome-content {
    flex: 1;
    min-width: 300px;
}

.welcome-title {
    font-size: 2rem;
    color: #1e293b;
    margin: 0 0 0.5rem 0;
    font-weight: 700;
}

.welcome-subtitle {
    color: #64748b;
    margin: 0;
    font-size: 0.95rem;
}

.welcome-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

/* Profile & Stats Section */
.profile-stats-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.profile-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

.profile-header {
    padding: 1.25rem;
    background: linear-gradient(135deg, #14B8A6 0%, #0d9488 100%);
    color: white;
    font-weight: 600;
    font-size: 1rem;
    display: flex;
    align-items: center;
}

.profile-content {
    padding: 1.5rem;
    display: grid;
    grid-template-columns: 120px 1fr;
    gap: 1.5rem;
}

.profile-avatar {
    display: flex;
    align-items: flex-start;
}

.profile-pic {
    width: 110px;
    height: 110px;
    border-radius: 10px;
    object-fit: cover;
    border: 3px solid #14B8A6;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.profile-pic-placeholder {
    width: 110px;
    height: 110px;
    border-radius: 10px;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: #14B8A6;
    border: 2px solid #e2e8f0;
}

.profile-info {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.profile-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 1rem 0;
}

.profile-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.profile-detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.detail-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
}

.detail-value {
    color: #1e293b;
    font-size: 0.95rem;
    word-break: break-all;
}

.detail-badge {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    background: #dbeafe;
    color: #0369a1;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    width: fit-content;
}

.btn-edit-profile {
    padding: 0.5rem 1rem;
    background: #f1f5f9;
    color: #14B8A6;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-edit-profile:hover {
    background: #14B8A6;
    color: white;
    text-decoration: none;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid #e2e8f0;
    display: flex;
    gap: 1rem;
    align-items: center;
}

.stat-icon {
    font-size: 2.5rem;
}

.stat-body {
    flex: 1;
}

.stat-label {
    color: #64748b;
    font-size: 0.85rem;
    margin: 0;
    font-weight: 600;
}

.stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: #14B8A6;
    margin: 0.25rem 0 0 0;
}

/* Recent Uploads Section */
.recent-uploads-section {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.section-header {
    padding: 1.25rem 1.5rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    color: #1e293b;
}

.section-header i {
    color: #14B8A6;
}

.btn-upload-new, .btn-view-all {
    background: white;
    color: #14B8A6;
    border: 1px solid #14B8A6;
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-size: 0.85rem;
    white-space: nowrap;
    transition: all 0.3s ease;
}

.btn-upload-new:hover, .btn-view-all:hover {
    background: #14B8A6;
    color: white;
}

.section-content {
    padding: 0;
}

.uploads-table {
    width: 100%;
    border-collapse: collapse;
}

.uploads-table thead {
    background: #f8fafc;
}

.uploads-table th {
    padding: 1rem 1.5rem;
    text-align: left;
    font-weight: 600;
    color: #64748b;
    font-size: 0.85rem;
    text-transform: uppercase;
    border-bottom: 1px solid #e2e8f0;
}

.uploads-table tbody tr {
    border-bottom: 1px solid #e2e8f0;
    transition: background 0.2s ease;
}

.uploads-table tbody tr:hover {
    background: #f8fafc;
}

.uploads-table td {
    padding: 1rem 1.5rem;
    color: #475569;
}

.title-cell {
    font-weight: 600;
    color: #1e293b;
}

.category-cell {
    color: #64748b;
    font-size: 0.9rem;
}

.date-cell {
    font-size: 0.9rem;
    color: #64748b;
}

.downloads-cell {
    font-weight: 600;
    color: #14B8A6;
}

.action-cell {
    text-align: center;
}

.btn-download-table {
    padding: 0.5rem 0.75rem;
    background: #14B8A6;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
}

.btn-download-table:hover {
    background: #0d9488;
    transform: scale(1.05);
}

.empty-state {
    padding: 3rem 1.5rem;
    text-align: center;
    color: #64748b;
}

.empty-state i {
    font-size: 2.5rem;
    color: #cbd5e1;
    display: block;
    margin-bottom: 1rem;
}

/* Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
}

.fellow-students-section,
.quick-access-section {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.activity-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    padding: 1rem;
}

.activity-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    border-radius: 8px;
    background: #f8fafc;
    transition: all 0.3s ease;
    border-left: 4px solid #14B8A6;
}

.activity-item:hover {
    background: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.activity-icon {
    font-size: 2rem;
    flex-shrink: 0;
}

.activity-body {
    flex: 1;
}

.activity-title {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.5rem;
}

.activity-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
    color: #64748b;
}

.activity-meta i {
    color: #14B8A6;
    margin-right: 0.35rem;
}

/* Quick Cards */
.quick-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
}

.quick-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.quick-card .card-header {
    padding: 1.25rem;
    font-weight: 600;
    color: white;
    display: flex;
    align-items: center;
}

.papers-card .card-header {
    background: linear-gradient(135deg, #14B8A6 0%, #0d9488 100%);
}

.notes-card .card-header {
    background: linear-gradient(135deg, #38BDF8 0%, #0284C7 100%);
}

.quick-card .card-body {
    padding: 1.5rem;
}

.quick-card p {
    color: #64748b;
    margin: 0 0 1.25rem 0;
    line-height: 1.5;
    font-size: 0.95rem;
}

.btn-card-action {
    color: #14B8A6;
    text-decoration: none;
    font-weight: 600;
    display: inline-block;
    transition: all 0.3s ease;
}

.btn-card-action:hover {
    color: #0d9488;
    transform: translateX(4px);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .welcome-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .profile-stats-section {
        grid-template-columns: 1fr;
    }

    .profile-content {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .uploads-table {
        font-size: 0.85rem;
    }

    .uploads-table th,
    .uploads-table td {
        padding: 0.75rem;
    }

    .section-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .welcome-actions {
        width: 100%;
    }

    .welcome-actions .btn {
        flex: 1;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .welcome-title {
        font-size: 1.5rem;
    }

    .uploads-table {
        display: block;
        overflow-x: auto;
    }
}
</style>

<?php include("includes/footer.php"); ?>
       