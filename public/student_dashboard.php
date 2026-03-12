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
        WHERE n.user_id <> ? AND n.status = 'approved'
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



<!-- Welcome Section -->
<div class="welcome-header mb-4">
    <div class="welcome-content">
        <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($userName); ?> 👋</h1>
        <p class="welcome-subtitle">Here's a quick snapshot of your contribution to the community.</p>
    </div>
    <div class="welcome-actions">
        <a href="upload_notes.php" class="btn" style="background: #14B8A6; color: white; border-radius: 8px; font-weight: 600; padding: 0.625rem 1.25rem;">
            <i class="bi bi-upload me-1"></i>Upload Notes
        </a>
        <a href="view_notes.php" class="btn btn-outline-secondary" style="border-radius: 8px; font-weight: 600; padding: 0.625rem 1.25rem;">Browse Notes</a>
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
        <div class="stat-card stat-downloads">
            <div class="stat-icon">📥</div>
            <div class="stat-body">
                <p class="stat-label">Total Downloads</p>
                <h3 class="stat-value"><?php echo (int)$stats["total_downloads"]; ?></h3>
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
                <p>You haven't uploaded any notes yet. Start by sharing your first study resource!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Content Grid Section -->
<div class="content-grid">
    <!-- Fellow Students Section -->
    <div class="fellow-students-section">
        <div class="section-header">
            <i class="bi bi-people me-2"></i>Newest from Students
            <a href="view_notes.php" class="btn btn-sm btn-view-all">View All →</a>
        </div>
        <div class="section-content">
            <?php if (is_array($recentFromOthers) && count($recentFromOthers) > 0): ?>
                <div class="activity-cards">
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
                        <a href="javascript:void(0);" onclick="openPreview(<?php echo htmlspecialchars(json_encode($note)); ?>)" class="activity-card">
                            <div class="card-thumb <?php echo $typeClass; ?>">
                                <?php echo $fileTypeIcon; ?>
                            </div>
                            <div class="card-body">
                                <div class="card-title"><?php echo htmlspecialchars($note["title"]); ?></div>
                                <div class="card-desc"><?php echo htmlspecialchars($note["description"] ?: "No description"); ?></div>
                                <div class="card-meta">
                                    <span><i class="bi bi-person"></i> <?php echo htmlspecialchars($note["uploader_name"] ?? "Unknown"); ?></span>
                                    <span><i class="bi bi-calendar3"></i> <?php echo date("M d", strtotime($note["uploaded_at"])); ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-search"></i>
                    <p>No new uploads from other students yet. Check back soon!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Access Cards -->
    <div class="quick-access-section">
        <div class="quick-card assessments-card">
            <div class="card-header">
                <i class="bi bi-clipboard-data me-2"></i>Assessments
            </div>
            <div class="card-body">
                <p>Prepare for exams with the latest assessments shared by teachers.</p>
                <a href="view_assessments.php" class="btn-card-action">
                    Browse Assessments →
                </a>
            </div>
        </div>

        <div class="quick-card papers-card">
            <div class="card-header">
                <i class="bi bi-archive me-2"></i>Past Papers
            </div>
            <div class="card-body">
                <p>Access previous year papers to practice before your tests.</p>
                <a href="view_papers.php" class="btn-card-action">
                    Browse Papers →
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* Dashboard Styles */
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

.activity-cards {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.activity-card {
    display: grid;
    grid-template-columns: 130px 1fr;
    gap: 1rem;
    padding: 1rem;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
}

.activity-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(20, 184, 166, 0.15);
    border-color: #14B8A6;
}

.card-thumb {
    width: 130px;
    height: 130px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    flex-shrink: 0;
}

.card-thumb.pdf {
    background: linear-gradient(135deg, #38BDF8 0%, #0284C7 100%);
}

.card-thumb.doc {
    background: linear-gradient(135deg, #38BDF8 0%, #06B6D4 100%);
}

.card-body {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.card-title {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.35rem;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.card-desc {
    font-size: 0.85rem;
    color: #64748b;
    margin-bottom: 0.75rem;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.card-meta {
    display: flex;
    gap: 1.25rem;
    font-size: 0.8rem;
    color: #64748b;
}

.card-meta i {
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

.assessments-card .card-header {
    background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
}

.papers-card .card-header {
    background: linear-gradient(135deg, #14B8A6 0%, #0d9488 100%);
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

    .activity-card {
        grid-template-columns: 100px 1fr;
    }

    .card-thumb {
        width: 100px;
        height: 100px;
        font-size: 2rem;
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

    .activity-card {
        grid-template-columns: 80px 1fr;
    }

    .card-thumb {
        width: 80px;
        height: 80px;
        font-size: 1.5rem;
    }
}
/* Preview Modal Styles */
.preview-modal {
    display: none;
    position: fixed;
    z-index: 1050;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.3s ease;
}

.preview-modal.active {
    display: flex !important;
    align-items: center;
    justify-content: center;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideIn {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.preview-modal-dialog {
    position: relative;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    animation: slideIn 0.3s ease;
}

.preview-modal-content {
    border-radius: 12px;
    overflow: hidden;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    background: white;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.preview-modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

.preview-modal-title {
    margin: 0;
    color: #1e293b;
    font-weight: 600;
    font-size: 1.25rem;
}

.preview-modal-close {
    background: none;
    border: none;
    font-size: 1.75rem;
    cursor: pointer;
    color: #64748b;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    border-radius: 6px;
}

.preview-modal-close:hover {
    background: #e2e8f0;
    color: #1e293b;
}

.preview-modal-body {
    padding: 1.5rem;
    overflow-y: auto;
    flex: 1;
}

.preview-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
}

.preview-info-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.preview-info-label {
    font-weight: 600;
    color: #64748b;
    font-size: 0.75rem;
    text-transform: uppercase;
}

.preview-info-value {
    color: #1e293b;
    font-size: 0.95rem;
}

.preview-description-section {
    margin-bottom: 1.5rem;
}

.preview-section-title {
    color: #64748b;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    margin: 0 0 0.5rem 0;
}

.preview-description-text {
    color: #475569;
    line-height: 1.6;
    margin: 0;
    font-size: 0.95rem;
}

.preview-file-container {
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: 1.5rem;
    min-height: 300px;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    text-align: center;
    color: #64748b;
}

.preview-file-container img {
    max-width: 100%;
    max-height: 400px;
    border-radius: 8px;
}

.preview-file-container iframe {
    width: 100%;
    height: 400px;
    border-radius: 8px;
    border: none;
}

.preview-file-container pre {
    font-family: "Courier New", monospace;
    font-size: 0.9rem;
    line-height: 1.6;
    color: #334155;
    max-height: 400px;
    overflow-y: auto;
    margin: 0;
    white-space: pre-wrap;
    word-wrap: break-word;
    text-align: left;
    width: 100%;
    padding: 1rem;
    background: white;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}

.preview-modal-footer {
    padding: 1.5rem;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    flex-shrink: 0;
}

.preview-btn {
    padding: 0.625rem 1.25rem;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.preview-btn-primary {
    background: #14B8A6;
    color: white;
}

.preview-btn-primary:hover {
    background: #0d9488;
    transform: translateY(-2px);
}

.preview-btn-secondary {
    background: #e2e8f0;
    color: #334155;
}

.preview-btn-secondary:hover {
    background: #cbd5e1;
    transform: translateY(-2px);
}

/* Responsive Modal */
@media (max-width: 768px) {
    .preview-modal-dialog {
        width: 95%;
        max-height: 95vh;
    }

    .preview-modal-body {
        max-height: calc(95vh - 150px);
    }

    .preview-info-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .preview-file-container {
        min-height: 200px;
    }

    .preview-file-container iframe {
        height: 250px;
    }

    .preview-file-container img {
        max-height: 300px;
    }
}

@media (max-width: 480px) {
    .preview-modal-dialog {
        width: 98%;
    }

    .preview-info-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
        padding: 0.75rem;
    }

    .preview-modal-header,
    .preview-modal-body,
    .preview-modal-footer {
        padding: 1rem;
    }

    .preview-modal-title {
        font-size: 1rem;
    }
}
</style>

<!-- Preview Modal -->
<div id="previewModal" class="preview-modal">
    <div class="preview-modal-dialog">
        <div class="preview-modal-content">
            <!-- Modal Header -->
            <div class="preview-modal-header">
                <h5 class="preview-modal-title" id="previewTitle"></h5>
                <button type="button" class="preview-modal-close" onclick="closePreview()" aria-label="Close">×</button>
            </div>

            <!-- Modal Body -->
            <div class="preview-modal-body">
                <!-- Note Info -->
                <div class="preview-info-grid">
                    <div class="preview-info-item">
                        <div class="preview-info-label">Category</div>
                        <div class="preview-info-value" id="previewCategory"></div>
                    </div>
                    <div class="preview-info-item">
                        <div class="preview-info-label">Type</div>
                        <div class="preview-info-value" id="previewType"></div>
                    </div>
                    <div class="preview-info-item">
                        <div class="preview-info-label">Uploader</div>
                        <div class="preview-info-value" id="previewUploader"></div>
                    </div>
                    <div class="preview-info-item">
                        <div class="preview-info-label">Date</div>
                        <div class="preview-info-value" id="previewDate"></div>
                    </div>
                </div>

                <!-- Description -->
                <div class="preview-description-section">
                    <h6 class="preview-section-title">Description</h6>
                    <p id="previewDescription" class="preview-description-text"></p>
                </div>

                <!-- File Preview Container -->
                <div id="previewFileContainer" class="preview-file-container"></div>
            </div>

            <!-- Modal Footer -->
            <div class="preview-modal-footer">
                <button type="button" class="preview-btn preview-btn-secondary" onclick="closePreview()">Close</button>
                <a id="previewDownloadLink" href="#" download class="preview-btn preview-btn-primary">📥 Download</a>
            </div>
        </div>
    </div>
</div>

<script>
function openPreview(note) {
    const modal = document.getElementById('previewModal');
    const filePath = '../uploads/' + note.file_path;
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
        img.onerror = () => {
            container.innerHTML = '<div style="text-align: center;"><div style="font-size: 2rem; margin-bottom: 1rem;">🖼️</div><p>Image could not be loaded</p><a href="' + filePath + '" download style="color: #14B8A6; text-decoration: none; font-weight: 600;">Download image instead</a></div>';
        };
        container.appendChild(img);
        container.style.alignItems = 'center';
        container.style.justifyContent = 'center';
    } else if (ext === 'pdf') {
        const iframe = document.createElement('iframe');
        iframe.src = filePath;
        iframe.onerror = () => {
            container.innerHTML = '<div style="text-align: center;"><div style="font-size: 2rem; margin-bottom: 1rem;">📄</div><p>PDF preview not available</p><a href="' + filePath + '" download style="color: #14B8A6; text-decoration: none; font-weight: 600;">Download PDF instead</a></div>';
        };
        container.appendChild(iframe);
    } else if (ext === 'txt') {
        fetch(filePath)
            .then(response => response.text())
            .then(text => {
                const pre = document.createElement('pre');
                pre.textContent = text.substring(0, 2000) + (text.length > 2000 ? '\n\n... (Preview truncated)' : '');
                container.appendChild(pre);
                container.style.alignItems = 'flex-start';
                container.style.justifyContent = 'flex-start';
            })
            .catch(() => {
                container.innerHTML = '<div style="text-align: center;"><div style="font-size: 2rem; margin-bottom: 1rem;">📝</div><p>Text file preview not available</p><a href="' + filePath + '" download style="color: #14B8A6; text-decoration: none; font-weight: 600;">Download file instead</a></div>';
            });
    } else {
        container.innerHTML = '<div style="text-align: center;"><div style="font-size: 2rem; margin-bottom: 1rem;">📦</div><p>Preview not available for this file type</p><p style="font-size: 0.9rem; color: #64748b; margin-top: 0.5rem;">File format: ' + ext.toUpperCase() + '</p><a href="' + filePath + '" download style="display: inline-block; margin-top: 1rem; color: #14B8A6; text-decoration: none; font-weight: 600;">Download file instead</a></div>';
    }

    modal.classList.add('active');
}

function closePreview() {
    const modal = document.getElementById('previewModal');
    modal.classList.remove('active');
}

function getFileExtension(filepath) {
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
</script>

<?php include("includes/footer.php"); ?> 