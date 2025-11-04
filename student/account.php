<?php
/*
 * LernovaAI - Account Management Page (Student)
 * Allows students to view and update their account information
 */

// Start session and security check BEFORE header include
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

$student_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action == 'update_profile') {
        // Update profile information (first name, last name, username)
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        
        // Validation
        if (empty($first_name) || empty($last_name) || empty($username)) {
            $_SESSION['account_error'] = "All fields are required.";
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $_SESSION['account_error'] = "Username must be between 3 and 50 characters.";
        } elseif (strlen($first_name) > 100 || strlen($last_name) > 100) {
            $_SESSION['account_error'] = "Name fields must be less than 100 characters.";
        } else {
            // Check if username is taken by another user
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt_check->bind_param("si", $username, $student_id);
            $stmt_check->execute();
            
            if ($stmt_check->get_result()->num_rows > 0) {
                $_SESSION['account_error'] = "Username already exists. Please choose another one.";
            } else {
                // Update profile
                $stmt_update = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, username = ? WHERE id = ?");
                $stmt_update->bind_param("sssi", $first_name, $last_name, $username, $student_id);
                
                if ($stmt_update->execute()) {
                    // Update session variables
                    $_SESSION['first_name'] = $first_name;
                    $_SESSION['last_name'] = $last_name;
                    $_SESSION['username'] = $username;
                    $_SESSION['account_message'] = "Profile updated successfully.";
                } else {
                    $_SESSION['account_error'] = "Error updating profile: " . $stmt_update->error;
                }
                $stmt_update->close();
            }
            $stmt_check->close();
        }
    } elseif ($action == 'change_password') {
        // Change password
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $_SESSION['account_error'] = "All password fields are required.";
        } elseif (strlen($new_password) < 6) {
            $_SESSION['account_error'] = "New password must be at least 6 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['account_error'] = "New password and confirmation do not match.";
        } else {
            // Verify current password
            $stmt_verify = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt_verify->bind_param("i", $student_id);
            $stmt_verify->execute();
            $result_verify = $stmt_verify->get_result();
            
            if ($result_verify->num_rows == 1) {
                $user = $result_verify->fetch_assoc();
                
                if (password_verify($current_password, $user['password'])) {
                    // Current password is correct, update to new password
                    $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt_update->bind_param("si", $new_password_hash, $student_id);
                    
                    if ($stmt_update->execute()) {
                        $_SESSION['account_message'] = "Password changed successfully.";
                    } else {
                        $_SESSION['account_error'] = "Error changing password: " . $stmt_update->error;
                    }
                    $stmt_update->close();
                } else {
                    $_SESSION['account_error'] = "Current password is incorrect.";
                }
            } else {
                $_SESSION['account_error'] = "User not found.";
            }
            $stmt_verify->close();
        }
    }
    
    $conn->close();
    header("Location: account.php");
    exit;
}

// Fetch current user information
$stmt_user = $conn->prepare("SELECT id, username, first_name, last_name, role, created_at FROM users WHERE id = ?");
$stmt_user->bind_param("i", $student_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();
$stmt_user->close();
$conn->close();

// Get messages from session
if (isset($_SESSION['account_message'])) {
    $message = $_SESSION['account_message'];
    unset($_SESSION['account_message']);
}

if (isset($_SESSION['account_error'])) {
    $error = $_SESSION['account_error'];
    unset($_SESSION['account_error']);
}

// Now include the header
require_once '../includes/student_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-0"><i class="bi bi-person-gear text-primary"></i> Account Management</h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;"><i class="bi bi-info-circle me-1"></i> Manage your account information and password</p>
    </div>
    <a href="index.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-3">
    <!-- Profile Information Card -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="bi bi-person-circle"></i> Profile Information</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="account.php">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="mb-3">
                        <label for="first_name" class="form-label">
                            <i class="bi bi-person text-primary"></i> First Name
                        </label>
                        <input type="text" class="form-control" id="first_name" name="first_name" 
                               value="<?php echo htmlspecialchars($user['first_name']); ?>" required maxlength="100">
                    </div>
                    
                    <div class="mb-3">
                        <label for="last_name" class="form-label">
                            <i class="bi bi-person text-primary"></i> Last Name
                        </label>
                        <input type="text" class="form-control" id="last_name" name="last_name" 
                               value="<?php echo htmlspecialchars($user['last_name']); ?>" required maxlength="100">
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="bi bi-at text-primary"></i> Username
                        </label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo htmlspecialchars($user['username']); ?>" required minlength="3" maxlength="50">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> Username must be between 3 and 50 characters
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="bi bi-shield-check text-primary"></i> Role
                        </label>
                        <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="bi bi-calendar text-primary"></i> Account Created
                        </label>
                        <input type="text" class="form-control" 
                               value="<?php echo date("F j, Y", strtotime($user['created_at'])); ?>" disabled>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Change Password Card -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="bi bi-key"></i> Change Password</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="account.php">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">
                            <i class="bi bi-lock text-primary"></i> Current Password
                        </label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">
                            <i class="bi bi-lock-fill text-primary"></i> New Password
                        </label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> Password must be at least 6 characters long
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">
                            <i class="bi bi-lock-fill text-primary"></i> Confirm New Password
                        </label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-key-fill"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/student_footer.php'; ?>

