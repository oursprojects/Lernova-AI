<?php
/*
 * LernovaAI - Create Subject Page
 */

// Start session and check authentication BEFORE any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in AND is a faculty member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    header("Location: ../login.php");
    exit;
}

// Connect to database
require_once '../config/db.php';

$faculty_id = $_SESSION['user_id'];
$error = '';

// --- Helper function to generate a random key ---
function generateEnrollmentKey($length = 6) {
    // A-Z, 0-9, excluding confusing chars like 0, O, 1, I, l
    $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $key = '';
    for ($i = 0; $i < $length; $i++) {
        $key .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $key;
}

// --- Handle Form Submission (BEFORE header include) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['subject_name'])) {
    $subject_name = $conn->real_escape_string($_POST['subject_name']);
    
    if (empty($subject_name)) {
        $_SESSION['create_subject_error'] = "Subject name is required.";
    } else {
        // Generate a unique key
        do {
            $enrollment_key = generateEnrollmentKey();
            $stmt_check = $conn->prepare("SELECT id FROM subjects WHERE enrollment_key = ?");
            $stmt_check->bind_param("s", $enrollment_key);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
        } while ($result_check->num_rows > 0);
        $stmt_check->close();

        // Insert the new subject
        $stmt_insert = $conn->prepare("INSERT INTO subjects (faculty_id, name, enrollment_key) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("iss", $faculty_id, $subject_name, $enrollment_key);
        
        if ($stmt_insert->execute()) {
            $stmt_insert->close();
            $conn->close();
            // Redirect BEFORE any output
            header("Location: manage_subjects.php?create=success");
            exit;
        } else {
            $_SESSION['create_subject_error'] = "Error creating subject. It might be a duplicate.";
        }
        $stmt_insert->close();
    }
    $conn->close();
}

// Now include the header (after POST handling is done)
require_once '../includes/faculty_header.php';

// Get error from session if it exists
if (isset($_SESSION['create_subject_error'])) {
    $error = $_SESSION['create_subject_error'];
    unset($_SESSION['create_subject_error']);
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="mb-0"><i class="bi bi-plus-circle"></i> Create New Subject</h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;">Create a subject to enroll students and manage lessons</p>
    </div>
    <a href="manage_subjects.php" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back to Subjects
    </a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 500px;">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="bi bi-card-text"></i> Subject Details</h3>
    </div>
    <div class="card-body">
        <form action="create_subject.php" method="POST">
            <div class="mb-3">
                <label for="subject_name" class="form-label">
                    <i class="bi bi-book text-primary"></i> Subject Name
                </label>
                <input type="text" id="subject_name" name="subject_name" class="form-control" required 
                       placeholder="e.g., Physics 101">
                <small class="text-muted">
                    <i class="bi bi-info-circle"></i> An enrollment key will be automatically generated
                </small>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle"></i> Create Subject
                </button>
                <a href="manage_subjects.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php
// 3. Include the footer
require_once '../includes/faculty_footer.php';
?>