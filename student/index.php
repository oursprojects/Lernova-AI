<?php
/*
 * LernovaAI - Student Dashboard (My Subjects)
 * This is the main page for the Student role.
 */

// 1. Include the header
require_once '../includes/student_header.php';

// Get the student_id from the session
$student_id = $_SESSION['user_id'];
$message = '';
$error = '';

// --- Include database connection once at the top ---
require_once '../config/db.php';

// --- Handle Unenroll ---
if (isset($_GET['action']) && $_GET['action'] == 'unenroll' && isset($_GET['id'])) {
    $subject_id_to_unenroll = intval($_GET['id']);
    $stmt_unenroll = $conn->prepare("DELETE FROM enrollments WHERE subject_id = ? AND student_id = ?");
    $stmt_unenroll->bind_param("ii", $subject_id_to_unenroll, $student_id);
    if ($stmt_unenroll->execute()) {
        $message = "Successfully unenrolled from subject.";
    } else {
        $error = "Error unenrolling.";
    }
    $stmt_unenroll->close();
}

// --- Fetch all ENROLLED subjects ---
$subjects = [];
$stmt = $conn->prepare("
    SELECT s.id, s.name, u.first_name, u.last_name
    FROM subjects s
    JOIN enrollments e ON s.id = e.subject_id
    JOIN users u ON s.faculty_id = u.id
    WHERE e.student_id = ?
    ORDER BY s.name ASC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result_subjects = $stmt->get_result();

if ($result_subjects->num_rows > 0) {
    while($row = $result_subjects->fetch_assoc()) {
        $subjects[] = $row;
    }
}
$stmt->close();

// --- Fetch Statistics ---
$stats_enrolled = count($subjects);
// Exclude hidden attempts from student view
$stats_quizzes_taken = $conn->query("SELECT COUNT(*) as count FROM student_attempts WHERE student_id = $student_id AND (hidden_from_student = 0 OR hidden_from_student IS NULL)")->fetch_assoc()['count'];
$stats_reviewers = $conn->query("SELECT COUNT(*) as count FROM student_reviewers WHERE student_id = $student_id")->fetch_assoc()['count'];

// Count undone quizzes (quizzes with no attempts)
$stmt_undone = $conn->prepare("
    SELECT COUNT(DISTINCT q.id) as count
    FROM quizzes q
    JOIN lessons l ON q.lesson_id = l.id
    JOIN subjects s ON l.subject_id = s.id
    JOIN enrollments e ON s.id = e.subject_id
    LEFT JOIN student_attempts sa ON q.id = sa.quiz_id AND sa.student_id = ? AND (sa.hidden_from_student = 0 OR sa.hidden_from_student IS NULL)
    WHERE e.student_id = ? 
    AND q.status = 'published'
    AND sa.id IS NULL
");
$stmt_undone->bind_param("ii", $student_id, $student_id);
$stmt_undone->execute();
$result_undone = $stmt_undone->get_result();
$stats_undone_quizzes = $result_undone->fetch_assoc()['count'];
$stmt_undone->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-0"><i class="bi bi-speedometer2 text-primary"></i> Student Dashboard</h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;"><i class="bi bi-info-circle me-1"></i> Track your progress and manage your enrolled subjects</p>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($stats_undone_quizzes > 0): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
            <div class="flex-grow-1">
                <strong><i class="bi bi-clock-history"></i> You have <?php echo $stats_undone_quizzes; ?> undone quiz<?php echo $stats_undone_quizzes != 1 ? 'zes' : ''; ?>!</strong>
                <p class="mb-0">Check your <a href="my_quizzes.php" class="alert-link fw-bold">My Quizzes</a> page to see available quizzes.</p>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-white" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50"><i class="bi bi-bookmark-check me-1"></i> Enrolled Subjects</h6>
                        <h2 class="mb-0"><?php echo $stats_enrolled; ?></h2>
                    </div>
                    <i class="bi bi-bookmark-check-fill" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white" style="background: linear-gradient(135deg, #F59E0B 0%, #F97316 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50"><i class="bi bi-clipboard-check me-1"></i> Quizzes Taken</h6>
                        <h2 class="mb-0"><?php echo $stats_quizzes_taken; ?></h2>
                        <?php if ($stats_undone_quizzes > 0): ?>
                            <small class="text-white-50">
                                <i class="bi bi-exclamation-circle me-1"></i> <?php echo $stats_undone_quizzes; ?> undone
                            </small>
                        <?php endif; ?>
                    </div>
                    <i class="bi bi-clipboard-check-fill" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white" style="background: linear-gradient(135deg, #8B5CF6 0%, #A78BFA 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50"><i class="bi bi-file-earmark-text me-1"></i> Reviewers Generated</h6>
                        <h2 class="mb-0"><?php echo $stats_reviewers; ?></h2>
                    </div>
                    <i class="bi bi-file-earmark-text-fill" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <div>
        <h2 class="mb-0"><i class="bi bi-book"></i> My Subjects</h2>
        <p class="text-muted mb-0" style="font-size: 0.875rem;">Select a subject to view its lessons and quizzes</p>
    </div>
    <div class="d-flex gap-2">
        <a href="my_quizzes.php" class="btn btn-primary">
            <i class="bi bi-clipboard-check"></i> My Quizzes
        </a>
        <a href="enroll.php" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Enroll in a New Subject
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="bi bi-list-ul"></i> Enrolled Subjects</h3>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th><i class="bi bi-book-half"></i> Subject Name</th>
                    <th><i class="bi bi-person-badge"></i> Faculty</th>
                    <th><i class="bi bi-gear"></i> Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subjects)): ?>
                    <tr>
                        <td colspan="3" class="text-center py-4">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p class="mb-2">You are not enrolled in any subjects yet.</p>
                                <a href="enroll.php" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-circle"></i> Enroll in Your First Subject
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subjects as $subject): ?>
                        <tr>
                            <td>
                                <i class="bi bi-bookmark text-success me-2"></i>
                                <strong><?php echo htmlspecialchars($subject['name']); ?></strong>
                            </td>
                            <td>
                                <i class="bi bi-mortarboard text-primary me-2"></i>
                                <?php echo htmlspecialchars("Prof. " . $subject['first_name'] . " " . $subject['last_name']); ?>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="view_subject.php?id=<?php echo $subject['id']; ?>" class="btn btn-sm btn-success">
                                        <i class="bi bi-eye-fill"></i> View
                                    </a>
                                    <a href="index.php?action=unenroll&id=<?php echo $subject['id']; ?>" 
                                       class="btn btn-sm btn-danger"
                                       data-message="Are you sure you want to unenroll from this subject? You will lose access to all lessons and quizzes.">
                                       <i class="bi bi-x-circle-fill"></i> Unenroll
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// 3. Include the footer
require_once '../includes/student_footer.php';
?>