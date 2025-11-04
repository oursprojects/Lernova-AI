<?php
/*
 * LernovaAI - Manage Students Page
 * Allows faculty to view and manage students enrolled in their subjects
 */

require_once '../includes/faculty_header.php';
require_once '../config/db.php';

$faculty_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle unenroll action
if (isset($_GET['action']) && $_GET['action'] == 'unenroll' && isset($_GET['enrollment_id'])) {
    $enrollment_id = intval($_GET['enrollment_id']);
    
    // Verify the enrollment belongs to one of this faculty's subjects
    $stmt_verify = $conn->prepare("
        SELECT e.id 
        FROM enrollments e
        JOIN subjects s ON e.subject_id = s.id
        WHERE e.id = ? AND s.faculty_id = ?
    ");
    $stmt_verify->bind_param("ii", $enrollment_id, $faculty_id);
    $stmt_verify->execute();
    $result_verify = $stmt_verify->get_result();
    
    if ($result_verify->num_rows == 0) {
        $stmt_verify->close();
        $conn->close();
        header("Location: manage_students.php?error=Enrollment not found or permission denied");
        exit;
    } else {
        $stmt_delete = $conn->prepare("DELETE FROM enrollments WHERE id = ?");
        $stmt_delete->bind_param("i", $enrollment_id);
        
        if ($stmt_delete->execute()) {
            $stmt_delete->close();
            $stmt_verify->close();
            $conn->close();
            header("Location: manage_students.php?unenrolled=success");
            exit;
        } else {
            $error = "Error unenrolling student: " . $stmt_delete->error;
            $stmt_delete->close();
        }
        $stmt_verify->close();
    }
}

// Handle success/error messages from redirects
if (isset($_GET['unenrolled']) && $_GET['unenrolled'] == 'success') {
    $message = "Student unenrolled successfully.";
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

// Fetch all students grouped by subject
$students_by_subject = [];

// First, get all enrollments with basic student info
$sql = "
    SELECT 
        s.id AS subject_id,
        s.name AS subject_name,
        u.id AS student_id,
        u.first_name,
        u.last_name,
        u.username,
        e.id AS enrollment_id,
        e.enrolled_at
    FROM enrollments e
    JOIN subjects s ON e.subject_id = s.id
    JOIN users u ON e.student_id = u.id
    WHERE s.faculty_id = ?
    ORDER BY s.name ASC, u.last_name ASC, u.first_name ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $subject_id = $row['subject_id'];
    $student_id = $row['student_id'];
    
    // Get quiz attempts and average score for this student in this subject (faculty sees all attempts)
    $stmt_stats = $conn->prepare("
        SELECT 
            COUNT(DISTINCT sa.id) AS quiz_attempts,
            COALESCE(AVG(sa.score * 100.0 / NULLIF(sa.total_questions, 0)), 0) AS avg_score
        FROM student_attempts sa
        JOIN quizzes q ON sa.quiz_id = q.id
        JOIN lessons l ON q.lesson_id = l.id
        WHERE sa.student_id = ? AND l.subject_id = ?
    ");
    $stmt_stats->bind_param("ii", $student_id, $subject_id);
    $stmt_stats->execute();
    $result_stats = $stmt_stats->get_result();
    $stats = $result_stats->fetch_assoc();
    $stmt_stats->close();
    
    $row['quiz_attempts'] = intval($stats['quiz_attempts']);
    $row['avg_score'] = round(floatval($stats['avg_score']), 1);
    
    if (!isset($students_by_subject[$subject_id])) {
        $students_by_subject[$subject_id] = [
            'subject_name' => $row['subject_name'],
            'students' => []
        ];
    }
    $students_by_subject[$subject_id]['students'][] = $row;
}
$stmt->close();

// Get statistics
$total_students = 0;
foreach ($students_by_subject as $subject) {
    $total_students += count($subject['students']);
}
$total_subjects = count($students_by_subject);

$conn->close();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="mb-0"><i class="bi bi-people"></i> Manage Students</h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;">View and manage students enrolled in your subjects</p>
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

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-white" style="background: linear-gradient(135deg, #4F46E5 0%, #6366F1 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50"><i class="bi bi-book"></i> Subjects</h6>
                        <h2 class="mb-0"><?php echo $total_subjects; ?></h2>
                    </div>
                    <i class="bi bi-book-half" style="font-size: 2.5rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50"><i class="bi bi-people"></i> Total Students</h6>
                        <h2 class="mb-0"><?php echo $total_students; ?></h2>
                    </div>
                    <i class="bi bi-people-fill" style="font-size: 2.5rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white" style="background: linear-gradient(135deg, #F59E0B 0%, #F97316 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50"><i class="bi bi-graph-up"></i> Avg. Students/Subject</h6>
                        <h2 class="mb-0"><?php echo $total_subjects > 0 ? round($total_students / $total_subjects, 1) : 0; ?></h2>
                    </div>
                    <i class="bi bi-bar-chart" style="font-size: 2.5rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($students_by_subject)): ?>
    <div class="card">
        <div class="text-center py-5">
            <i class="bi bi-inbox" style="font-size: 3rem; color: #9CA3AF; display: block; margin-bottom: 1rem;"></i>
            <h3 class="text-muted">No Students Enrolled</h3>
            <p class="text-muted">No students have enrolled in your subjects yet.</p>
            <a href="manage_subjects.php" class="btn btn-primary mt-3">
                <i class="bi bi-book"></i> Manage Subjects
            </a>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($students_by_subject as $subject_id => $subject_data): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    <i class="bi bi-book-half text-primary"></i> <?php echo htmlspecialchars($subject_data['subject_name']); ?>
                    <span class="badge bg-secondary ms-2"><?php echo count($subject_data['students']); ?> student(s)</span>
                </h3>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th><i class="bi bi-person-circle"></i> Student Name</th>
                            <th><i class="bi bi-at"></i> Username</th>
                            <th><i class="bi bi-calendar"></i> Enrolled Date</th>
                            <th><i class="bi bi-clipboard-check"></i> Quiz Attempts</th>
                            <th><i class="bi bi-star"></i> Average Score</th>
                            <th><i class="bi bi-gear"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subject_data['students'] as $student): ?>
                            <tr>
                                <td>
                                    <i class="bi bi-person text-primary me-2"></i>
                                    <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                </td>
                                <td>
                                    <i class="bi bi-at text-muted me-2"></i>
                                    <?php echo htmlspecialchars($student['username']); ?>
                                </td>
                                <td>
                                    <i class="bi bi-calendar3 text-muted me-2"></i>
                                    <small><?php echo date("M j, Y", strtotime($student['enrolled_at'])); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <i class="bi bi-clipboard-check"></i> <?php echo intval($student['quiz_attempts']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $avg_score = round(floatval($student['avg_score']), 1);
                                    $score_color = $avg_score >= 70 ? 'success' : ($avg_score >= 50 ? 'warning' : 'danger');
                                    ?>
                                    <span class="badge bg-<?php echo $score_color; ?>">
                                        <i class="bi bi-star-fill"></i> <?php echo $avg_score; ?>%
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="view_student.php?student_id=<?php echo $student['student_id']; ?>&subject_id=<?php echo $subject_id; ?>" 
                                           class="btn btn-sm btn-info"
                                           title="View Student Details">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <a href="manage_students.php?action=unenroll&enrollment_id=<?php echo $student['enrollment_id']; ?>" 
                                           class="btn btn-sm btn-danger delete-btn"
                                           data-message="Are you sure you want to unenroll <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?> from <?php echo htmlspecialchars($subject_data['subject_name']); ?>?"
                                           data-href="manage_students.php?action=unenroll&enrollment_id=<?php echo $student['enrollment_id']; ?>"
                                           title="Unenroll Student">
                                            <i class="bi bi-person-x"></i> Unenroll
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once '../includes/faculty_footer.php'; ?>

