<?php
/*
 * LernovaAI - Enroll in Subject Page
 */

// Start session and check authentication BEFORE any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Student Security Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit;
}

$student_id = $_SESSION['user_id'];

// Include database connection
require_once '../config/db.php';

// --- Handle Form Submission FIRST (before any output) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['enroll_key'])) {
    $enroll_key_raw = trim($_POST['enroll_key']);
    
    if (empty($enroll_key_raw)) {
        $_SESSION['enroll_error'] = "Enrollment key is required.";
        header("Location: enroll.php");
        exit;
    } else {
        // Sanitize and validate enrollment key
        $enroll_key = strtoupper($enroll_key_raw);
        $enroll_key = preg_replace('/[^A-Z0-9]/', '', $enroll_key); // Remove non-alphanumeric
        
        if (empty($enroll_key)) {
            $_SESSION['enroll_error'] = "Invalid enrollment key format.";
            header("Location: enroll.php");
            exit;
        } elseif (strlen($enroll_key) < 3 || strlen($enroll_key) > 20) {
            $_SESSION['enroll_error'] = "Enrollment key must be between 3 and 20 characters.";
            header("Location: enroll.php");
            exit;
        } else {
            // Find the subject with this key (including faculty_id)
            $stmt_find = $conn->prepare("SELECT id, name, faculty_id FROM subjects WHERE enrollment_key = ?");
            $stmt_find->bind_param("s", $enroll_key);
            $stmt_find->execute();
            $result_find = $stmt_find->get_result();

            if ($result_find->num_rows == 1) {
                $subject = $result_find->fetch_assoc();
                $subject_id = intval($subject['id']);
                $subject_name = $subject['name'];
                $faculty_id = intval($subject['faculty_id']);
                
                if ($subject_id <= 0) {
                    $_SESSION['enroll_error'] = "Invalid subject data.";
                    $stmt_find->close();
                    $conn->close();
                    header("Location: enroll.php");
                    exit;
                } else {
                    // Check if student is already enrolled in this exact subject
                    $stmt_check = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND subject_id = ?");
                    $stmt_check->bind_param("ii", $student_id, $subject_id);
                    $stmt_check->execute();
                    $result_check = $stmt_check->get_result();
                    
                    if ($result_check->num_rows > 0) {
                        // Already enrolled in this exact subject
                        $_SESSION['enroll_error'] = "You are already enrolled in this subject.";
                        $stmt_check->close();
                        $stmt_find->close();
                        $conn->close();
                        header("Location: enroll.php");
                        exit;
                    }
                    $stmt_check->close();
                    
                    // Check if student is enrolled in a subject with the same name taught by the same professor
                    $stmt_check_same = $conn->prepare("
                        SELECT e.id 
                        FROM enrollments e
                        JOIN subjects s ON e.subject_id = s.id
                        WHERE e.student_id = ? 
                        AND s.name = ? 
                        AND s.faculty_id = ?
                    ");
                    $stmt_check_same->bind_param("isi", $student_id, $subject_name, $faculty_id);
                    $stmt_check_same->execute();
                    $result_check_same = $stmt_check_same->get_result();
                    
                    if ($result_check_same->num_rows > 0) {
                        // Already enrolled in a subject with the same name from the same professor
                        $_SESSION['enroll_error'] = "You are already enrolled in a subject with the same name ('" . htmlspecialchars($subject_name) . "') taught by the same professor.";
                        $stmt_check_same->close();
                        $stmt_find->close();
                        $conn->close();
                        header("Location: enroll.php");
                        exit;
                    }
                    $stmt_check_same->close();
                    
                    // Try to enroll the student
                    try {
                        $stmt_enroll = $conn->prepare("INSERT INTO enrollments (student_id, subject_id) VALUES (?, ?)");
                        $stmt_enroll->bind_param("ii", $student_id, $subject_id);
                        
                        if ($stmt_enroll->execute()) {
                            $stmt_enroll->close();
                            $stmt_find->close();
                            $conn->close();
                            header("Location: enroll.php?success=1&subject=" . urlencode($subject['name']));
                            exit;
                        } else {
                            $_SESSION['enroll_error'] = "Error enrolling in subject. Please try again.";
                            $stmt_enroll->close();
                            $stmt_find->close();
                            $conn->close();
                            header("Location: enroll.php");
                            exit;
                        }
                    } catch (mysqli_sql_exception $e) {
                        // Handle duplicate entry or other SQL errors
                        if ($e->getCode() == 1062 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                            $_SESSION['enroll_error'] = "You are already enrolled in this subject.";
                        } else {
                            $_SESSION['enroll_error'] = "Error enrolling in subject: " . $e->getMessage();
                        }
                        if (isset($stmt_enroll)) {
                            $stmt_enroll->close();
                        }
                        $stmt_find->close();
                        $conn->close();
                        header("Location: enroll.php");
                        exit;
                    }
                }
            } else {
                $_SESSION['enroll_error'] = "Invalid enrollment key. Please check with your faculty.";
                $stmt_find->close();
                $conn->close();
                header("Location: enroll.php");
                exit;
            }
        }
    }
}

// Now include the header (after POST handling is done)
require_once '../includes/student_header.php';

$error = '';
$message = '';

// Get error from session if it exists
if (isset($_SESSION['enroll_error'])) {
    $error = $_SESSION['enroll_error'];
    unset($_SESSION['enroll_error']);
}

// Handle success message
if (isset($_GET['success']) && $_GET['success'] == '1' && isset($_GET['subject'])) {
    $message = "Successfully enrolled in " . htmlspecialchars($_GET['subject']) . "!";
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-0"><i class="bi bi-plus-circle-fill text-success"></i> Enroll in a Subject</h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;"><i class="bi bi-info-circle me-1"></i> Enter the enrollment key provided by your faculty</p>
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

<div class="card" style="max-width: 500px;">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="bi bi-key-fill text-primary"></i> Enter Enrollment Key</h3>
    </div>
    <div class="card-body">
        <form action="enroll.php" method="POST">
            <div class="mb-3">
                <label for="enroll_key" class="form-label">
                    <i class="bi bi-key text-primary"></i> Enrollment Key
                </label>
                <input type="text" id="enroll_key" name="enroll_key" class="form-control text-uppercase" required 
                       placeholder="e.g., ABC123" style="letter-spacing: 0.1em;">
                <small class="text-muted">
                    <i class="bi bi-info-circle"></i> Enter the 6-character enrollment key provided by your faculty
                </small>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle-fill"></i> Enroll Now
                </button>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php
// 3. Include the footer
require_once '../includes/student_footer.php';
?>