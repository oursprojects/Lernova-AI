<?php
/*
 * LernovaAI - View Student Details Page
 * Shows detailed information about a student's performance in a subject
 */

require_once '../includes/faculty_header.php';
require_once '../config/db.php';

$faculty_id = $_SESSION['user_id'];

// Validate student_id and subject_id
if (!isset($_GET['student_id']) || !isset($_GET['subject_id'])) {
    header("Location: manage_students.php?error=Missing parameters");
    exit;
}

$student_id = intval($_GET['student_id']);
$subject_id = intval($_GET['subject_id']);

if ($student_id <= 0 || $subject_id <= 0) {
    header("Location: manage_students.php?error=Invalid parameters");
    exit;
}

// Verify the student is enrolled in this subject and the subject belongs to this faculty
$stmt_verify = $conn->prepare("
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        u.username,
        s.name AS subject_name,
        e.enrolled_at
    FROM enrollments e
    JOIN subjects s ON e.subject_id = s.id
    JOIN users u ON e.student_id = u.id
    WHERE e.student_id = ? AND e.subject_id = ? AND s.faculty_id = ?
");
$stmt_verify->bind_param("iii", $student_id, $subject_id, $faculty_id);
$stmt_verify->execute();
$result_verify = $stmt_verify->get_result();

if ($result_verify->num_rows == 0) {
    $stmt_verify->close();
    $conn->close();
    header("Location: manage_students.php?error=Student not found or permission denied");
    exit;
}

$student = $result_verify->fetch_assoc();
$stmt_verify->close();

// Fetch all quiz attempts for this student in this subject (faculty sees all, including hidden from student)
$quiz_attempts = [];
$stmt_attempts = $conn->prepare("
    SELECT 
        sa.id AS attempt_id,
        sa.score,
        sa.total_questions,
        sa.attempt_date,
        q.title AS quiz_title,
        q.id AS quiz_id,
        l.title AS lesson_title
    FROM student_attempts sa
    JOIN quizzes q ON sa.quiz_id = q.id
    JOIN lessons l ON q.lesson_id = l.id
    WHERE sa.student_id = ? AND l.subject_id = ?
    ORDER BY sa.attempt_date DESC
");
$stmt_attempts->bind_param("ii", $student_id, $subject_id);
$stmt_attempts->execute();
$result_attempts = $stmt_attempts->get_result();

while ($row = $result_attempts->fetch_assoc()) {
    $percentage = ($row['total_questions'] > 0) ? round(($row['score'] / $row['total_questions']) * 100, 1) : 0;
    $row['percentage'] = $percentage;
    $quiz_attempts[] = $row;
}
$stmt_attempts->close();

// Calculate statistics
$total_attempts = count($quiz_attempts);
$total_quizzes_taken = count(array_unique(array_column($quiz_attempts, 'quiz_id')));
$avg_score = 0;
if ($total_attempts > 0) {
    $sum_percentages = 0;
    foreach ($quiz_attempts as $attempt) {
        $sum_percentages += $attempt['percentage'];
    }
    $avg_score = round($sum_percentages / $total_attempts, 1);
}

// Get all quizzes in this subject
$all_quizzes = [];
$stmt_quizzes = $conn->prepare("
    SELECT q.id, q.title, l.title AS lesson_title
    FROM quizzes q
    JOIN lessons l ON q.lesson_id = l.id
    WHERE l.subject_id = ? AND q.status = 'published'
    ORDER BY q.title ASC
");
$stmt_quizzes->bind_param("i", $subject_id);
$stmt_quizzes->execute();
$result_quizzes = $stmt_quizzes->get_result();

while ($row = $result_quizzes->fetch_assoc()) {
    $all_quizzes[] = $row;
}
$stmt_quizzes->close();

$conn->close();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="mb-0"><i class="bi bi-person-circle"></i> Student Details</h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;">
            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?> - 
            <?php echo htmlspecialchars($student['subject_name']); ?>
        </p>
    </div>
    <a href="manage_students.php" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back to Students
    </a>
</div>

<!-- Student Information Card -->
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="bi bi-person-badge"></i> Student Information</h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong><i class="bi bi-person text-primary"></i> Name:</strong> <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                <p><strong><i class="bi bi-at text-primary"></i> Username:</strong> <?php echo htmlspecialchars($student['username']); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong><i class="bi bi-book text-primary"></i> Subject:</strong> <?php echo htmlspecialchars($student['subject_name']); ?></p>
                <p><strong><i class="bi bi-calendar text-primary"></i> Enrolled:</strong> <?php echo date("M j, Y", strtotime($student['enrolled_at'])); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #4F46E5 0%, #6366F1 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50"><i class="bi bi-clipboard-check"></i> Quiz Attempts</h6>
                        <h2 class="mb-0"><?php echo $total_attempts; ?></h2>
                    </div>
                    <i class="bi bi-clipboard-data" style="font-size: 2rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50"><i class="bi bi-book"></i> Quizzes Taken</h6>
                        <h2 class="mb-0"><?php echo $total_quizzes_taken; ?>/<?php echo count($all_quizzes); ?></h2>
                    </div>
                    <i class="bi bi-book-half" style="font-size: 2rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #F59E0B 0%, #F97316 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50"><i class="bi bi-star"></i> Average Score</h6>
                        <h2 class="mb-0"><?php echo $avg_score; ?>%</h2>
                    </div>
                    <i class="bi bi-star-fill" style="font-size: 2rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #8B5CF6 0%, #A78BFA 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50"><i class="bi bi-percent"></i> Completion Rate</h6>
                        <h2 class="mb-0"><?php echo count($all_quizzes) > 0 ? round(($total_quizzes_taken / count($all_quizzes)) * 100, 1) : 0; ?>%</h2>
                    </div>
                    <i class="bi bi-check-circle" style="font-size: 2rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quiz Attempts -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="bi bi-list-ul"></i> Quiz Attempts</h3>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th><i class="bi bi-clipboard-check"></i> Quiz Title</th>
                    <th><i class="bi bi-file-earmark-text"></i> Lesson</th>
                    <th><i class="bi bi-star"></i> Score</th>
                    <th><i class="bi bi-percent"></i> Percentage</th>
                    <th><i class="bi bi-calendar"></i> Date Taken</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($quiz_attempts)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4">
                            <i class="bi bi-inbox" style="font-size: 2rem; color: #9CA3AF; display: block; margin-bottom: 0.5rem;"></i>
                            <span class="text-muted">No quiz attempts yet.</span>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($quiz_attempts as $attempt): ?>
                        <tr>
                            <td>
                                <i class="bi bi-clipboard-check text-primary me-2"></i>
                                <strong><?php echo htmlspecialchars($attempt['quiz_title']); ?></strong>
                            </td>
                            <td>
                                <i class="bi bi-file-earmark-text text-muted me-2"></i>
                                <small><?php echo htmlspecialchars($attempt['lesson_title']); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-info">
                                    <?php echo $attempt['score']; ?>/<?php echo $attempt['total_questions']; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $percentage = $attempt['percentage'];
                                $badge_color = $percentage >= 70 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger');
                                ?>
                                <span class="badge bg-<?php echo $badge_color; ?>">
                                    <i class="bi bi-star-fill"></i> <?php echo $percentage; ?>%
                                </span>
                            </td>
                            <td>
                                <i class="bi bi-calendar3 text-muted me-2"></i>
                                <small><?php echo date("M j, Y - g:i a", strtotime($attempt['attempt_date'])); ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/faculty_footer.php'; ?>

