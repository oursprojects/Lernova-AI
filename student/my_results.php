<?php
/*
 * LernovaAI - My Results Page
 * Shows a history of all quiz attempts for the logged-in student.
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

// --- Handle Clear History FIRST (before any output) ---
if (isset($_GET['action']) && $_GET['action'] == 'clear_history' && isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    // Validate student_id
    if ($student_id <= 0) {
        $_SESSION['results_error'] = "Invalid student ID.";
        $conn->close();
        header("Location: my_results.php");
        exit;
    } else {
        // Mark all attempts as hidden from student (soft delete) instead of actually deleting
        $stmt_update = $conn->prepare("UPDATE student_attempts SET hidden_from_student = 1 WHERE student_id = ?");
        $stmt_update->bind_param("i", $student_id);
        if ($stmt_update->execute()) {
            $stmt_update->close();
            $conn->close();
            header("Location: my_results.php?cleared=success");
            exit;
        } else {
            $_SESSION['results_error'] = "Error clearing history: " . $stmt_update->error;
            $stmt_update->close();
            $conn->close();
            header("Location: my_results.php");
            exit;
        }
    }
}

// Now include the header (after POST handling is done)
require_once '../includes/student_header.php';

$message = '';
$error = '';

// Get error from session if it exists
if (isset($_SESSION['results_error'])) {
    $error = $_SESSION['results_error'];
    unset($_SESSION['results_error']);
}

// Handle success message
if (isset($_GET['cleared']) && $_GET['cleared'] == 'success') {
    $message = "Quiz history cleared successfully.";
}

// --- 2. Fetch All Attempts for this Student (with subject info) - excluding hidden ones ---
$attempts = [];
$stmt = $conn->prepare("
    SELECT 
        sa.score, 
        sa.total_questions, 
        sa.attempt_date,
        q.title AS quiz_title,
        l.title AS lesson_title,
        s.name AS subject_name
    FROM student_attempts sa
    JOIN quizzes q ON sa.quiz_id = q.id
    JOIN lessons l ON q.lesson_id = l.id
    JOIN subjects s ON l.subject_id = s.id
    WHERE sa.student_id = ? AND (sa.hidden_from_student = 0 OR sa.hidden_from_student IS NULL)
    ORDER BY s.name ASC, sa.attempt_date DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Calculate percentage for each row
        $row['percentage'] = ($row['total_questions'] > 0) ? round(($row['score'] / $row['total_questions']) * 100) : 0;
        $attempts[] = $row;
    }
}
$stmt->close();

// Group attempts by subject
$attempts_by_subject = [];
foreach ($attempts as $attempt) {
    $subject_name = $attempt['subject_name'];
    if (!isset($attempts_by_subject[$subject_name])) {
        $attempts_by_subject[$subject_name] = [];
    }
    $attempts_by_subject[$subject_name][] = $attempt;
}

$conn->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-0"><i class="bi bi-trophy-fill text-warning"></i> My Quiz Results</h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;"><i class="bi bi-info-circle me-1"></i> History of all quizzes you have completed</p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
        <?php if (!empty($attempts)): ?>
            <a href="my_results.php?action=clear_history&confirm=yes" 
               class="btn btn-danger btn-sm"
               data-message="Are you sure you want to clear all quiz history? This action cannot be undone.">
                <i class="bi bi-trash-fill"></i> Clear History
            </a>
        <?php endif; ?>
    </div>
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

<?php if (empty($attempts)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p class="mb-2">You have not taken any quizzes yet.</p>
                <a href="index.php" class="btn btn-sm btn-primary">
                    <i class="bi bi-arrow-left"></i> Browse Subjects
                </a>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($attempts_by_subject as $subject_name => $subject_attempts): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    <i class="bi bi-book-half text-primary"></i> <?php echo htmlspecialchars($subject_name); ?>
                    <span class="badge bg-secondary ms-2"><?php echo count($subject_attempts); ?> attempt(s)</span>
                </h3>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th><i class="bi bi-card-text"></i> Quiz Title</th>
                            <th><i class="bi bi-file-earmark-text"></i> Based on Lesson</th>
                            <th><i class="bi bi-calendar3"></i> Date Taken</th>
                            <th><i class="bi bi-star"></i> Score</th>
                            <th><i class="bi bi-percent"></i> Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subject_attempts as $attempt): ?>
                            <tr>
                                <td>
                                    <i class="bi bi-clipboard-check text-primary me-2"></i>
                                    <strong><?php echo htmlspecialchars($attempt['quiz_title']); ?></strong>
                                </td>
                                <td>
                                    <i class="bi bi-file-earmark-text text-info me-2"></i>
                                    <?php echo htmlspecialchars($attempt['lesson_title']); ?>
                                </td>
                                <td>
                                    <i class="bi bi-calendar3 text-muted me-2"></i>
                                    <small><?php echo date("M j, Y - g:i a", strtotime($attempt['attempt_date'])); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo ($attempt['percentage'] >= 50) ? 'success' : 'danger'; ?>">
                                        <i class="bi bi-star-fill"></i> <?php echo $attempt['score']; ?>/<?php echo $attempt['total_questions']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo ($attempt['percentage'] >= 75) ? 'success' : (($attempt['percentage'] >= 50) ? 'warning' : 'danger'); ?>" style="font-size: 0.875rem;">
                                        <?php echo $attempt['percentage']; ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php
// 3. Include the footer
require_once '../includes/student_footer.php';
?>
?>