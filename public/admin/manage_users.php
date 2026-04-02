<?php
require_once("../../config/config.php");
require_once __DIR__ . '/../includes/EmailSender.php';
require_once __DIR__ . '/../../config/smtp_config.php';

// only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$page_title = "Manage Users - Admin";
$message = "";
$messageType = "danger";

// Handle add user
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    try {
        $name = trim($_POST["name"] ?? '');
        $email = trim($_POST["email"] ?? '');
        $password = $_POST["password"] ?? '';
        $confirm_password = $_POST["confirm_password"] ?? '';
        $role = $_POST["role"] ?? "student";

        // Validation
        if (empty($name)) {
            throw new Exception("Full name is required.");
        } elseif (strlen($name) < 2 || strlen($name) > 100) {
            throw new Exception("Name must be between 2 and 100 characters.");
        } elseif (!preg_match("/^[a-zA-Z\s\.\-']+$/", $name)) {
            throw new Exception("Name can only contain letters, spaces, hyphens, and apostrophes.");
        }

        if (empty($email)) {
            throw new Exception("Email address is required.");
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }

        if (empty($password)) {
            throw new Exception("Password is required.");
        } elseif (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters long.");
        } elseif (!preg_match("/[A-Z]/", $password)) {
            throw new Exception("Password must contain at least one uppercase letter.");
        } elseif (!preg_match("/[a-z]/", $password)) {
            throw new Exception("Password must contain at least one lowercase letter.");
        } elseif (!preg_match("/[0-9]/", $password)) {
            throw new Exception("Password must contain at least one number.");
        } elseif (!preg_match("/[!@#$%^&*(),.?\":{}|<>]/", $password)) {
            throw new Exception("Password must contain at least one special character.");
        }

        if ($password !== $confirm_password) {
            throw new Exception("Passwords do not match.");
        }

        if (!in_array($role, ['student', 'teacher', 'admin'])) {
            throw new Exception("Invalid role selected.");
        }

        // Check if email already exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            throw new Exception("This email is already registered.");
        }

        // Add user
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$name, $email, $hashed_password, $role]);

        // Send welcome email
        try {
            $mailer = new EmailSender();
            $emailResult = $mailer->sendWelcomeEmail($email, $name, $password);
            error_log('Admin created user email sent to ' . $email . ': ' . json_encode($emailResult));
        } catch (Exception $e) {
            error_log('Admin created user email error: ' . $e->getMessage());
        }

        $message = "User added successfully! Welcome email sent to " . htmlspecialchars($email);
        $messageType = "success";
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = "danger";
    }
}

// Role update functionality removed

// Handle user delete
if (isset($_POST['delete_user'])) {
    try {
        $userId = (int)$_POST['user_id'];
        
        // Start transaction to ensure all deletions are atomic
        $pdo->beginTransaction();
        
        // Get all note IDs for this user to delete files later
        $notesStmt = $pdo->prepare("SELECT id, file_path FROM notes WHERE user_id = ?");
        $notesStmt->execute([$userId]);
        $notes = $notesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get all note IDs for cascading deletes
        $noteIds = array_column($notes, 'id');
        
        // Delete downloads_log entries for notes uploaded by this user
        if (!empty($noteIds)) {
            $placeholders = implode(',', array_fill(0, count($noteIds), '?'));
            $stmt = $pdo->prepare("DELETE FROM downloads_log WHERE note_id IN ($placeholders)");
            $stmt->execute($noteIds);
            
            // Delete downloads entries for notes uploaded by this user
            $stmt = $pdo->prepare("DELETE FROM downloads WHERE note_id IN ($placeholders)");
            $stmt->execute($noteIds);
        }
        
        // Delete all notes uploaded by this user
        $stmt = $pdo->prepare("DELETE FROM notes WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Delete the user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        // Commit transaction
        $pdo->commit();
        
        // Delete uploaded files from the file system
        foreach ($notes as $note) {
            $filePath = __DIR__ . '/../../uploads/' . $note['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        $message = "User and all their uploaded notes have been deleted successfully!";
        $messageType = "success";
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "Error deleting user: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Fetch users
try {
    $users = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
    $message = "Error fetching users: " . $e->getMessage();
    $messageType = "danger";
}

include("./header.php");
?>
<div class="container mt-4">

    <!-- Header Row -->
    <div class="row mb-4 align-items-center">
        <div class="col-lg-6">
            <h2 class="mb-0">
                <i class="bi bi-people-fill me-2"></i>Manage Users
            </h2>
        </div>
        <div class="col-lg-6 text-lg-end mt-3 mt-lg-0">
            <button class="btn" style="background: #14B8A6; color: white;"
                data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-plus-circle me-1"></i> Add New User
            </button>
        </div>
    </div>

        <!-- Users Table -->
    <div class="card shadow-sm">
        <div class="card-header" style="background: #14B8A6; color: white;">
            <i class="bi bi-table me-1"></i>User List (Total: <?php echo count($users); ?>)
        </div>

        <div class="card-body p-0">

            <?php if (count($users) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($u['id']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($u['name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>
                                        <span class="badge" style="background: <?php echo $u['role'] === 'admin' ? '#EF4444' : ($u['role'] === 'teacher' ? '#3B82F6' : '#10B981'); ?>">
                                            <?php echo ucfirst(htmlspecialchars($u['role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo date('M d, Y', strtotime($u['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <form method="POST" style="display:inline;"
                                            onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" name="delete_user"
                                                class="btn btn-sm"
                                                style="background: #F59E0B; color: white;">
                                                <i class="bi bi-trash me-1"></i>Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Bottom Action Bar -->
                <div class="d-flex justify-content-between align-items-center p-3 border-top">

                    <a href="dashboard.php"
                        class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                    </a>

                    <a href="download_users_report.php"
                        class="btn btn-info">
                        <i class="bi bi-download me-1"></i> Download Users Report
                    </a>

                </div>

            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="text-muted mt-3">No users found.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>


</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: #14B8A6; color: white;">
                <h5 class="modal-title" id="addUserModalLabel">
                    <i class="bi bi-person-plus me-2"></i>Add New User
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                        <small class="text-muted">2-100 characters, letters/spaces/hyphens/apostrophes only</small>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <small class="text-muted">
                            Minimum 8 characters with:
                            <ul class="mb-0 mt-2">
                                <li>1 uppercase letter</li>
                                <li>1 lowercase letter</li>
                                <li>1 number</li>
                                <li>1 special character (!@#$%^&*...)</li>
                            </ul>
                        </small>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" style="background: #14B8A6; color: white;">
                        <i class="bi bi-plus-circle me-1"></i>Add User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Display Message Alert -->
<?php if ($message): ?>
    <div class="position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 1050; margin-top: 20px;">
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <strong><?php echo ucfirst($messageType) === 'Success' ? '✓' : '⚠'; ?></strong>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>

<?php include("../includes/footer.php"); ?>