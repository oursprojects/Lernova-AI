<?php
/*
 * LernovaAI - My Quizzes Page (Student)
 * Shows all quizzes from all enrolled subjects with status indicators
 */

require_once '../includes/student_header.php';
require_once '../config/db.php';

$student_id = $_SESSION['user_id'];

// Fetch all quizzes from all enrolled subjects
$quizzes_by_subject = [];

// Get all enrolled subjects
$stmt_subjects = $conn->prepare("
    SELECT s.id, s.name 
    FROM subjects s
    JOIN enrollments e ON s.id = e.subject_id
    WHERE e.student_id = ?
    ORDER BY s.name ASC
");
$stmt_subjects->bind_param("i", $student_id);
$stmt_subjects->execute();
$result_subjects = $stmt_subjects->get_result();

$total_available = 0;
$total_undone = 0;

while ($subject_row = $result_subjects->fetch_assoc()) {
    $subject_id = $subject_row['id'];
    
    // Get all published quizzes for this subject
    $quizzes = [];
    $stmt_quizzes = $conn->prepare("
        SELECT 
            q.id, 
            q.title, 
            q.allow_retake,
            l.title AS lesson_title,
            (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS question_count,
            (SELECT COUNT(*) FROM student_attempts WHERE quiz_id = q.id AND student_id = ? AND (hidden_from_student = 0 OR hidden_from_student IS NULL)) AS attempt_count
        FROM quizzes q
        JOIN lessons l ON q.lesson_id = l.id
        WHERE l.subject_id = ? AND q.status = 'published'
        ORDER BY q.created_at DESC
    ");
    $stmt_quizzes->bind_param("ii", $student_id, $subject_id);
    $stmt_quizzes->execute();
    $result_quizzes = $stmt_quizzes->get_result();
    
    while ($quiz_row = $result_quizzes->fetch_assoc()) {
        $attempt_count = intval($quiz_row['attempt_count']);
        $can_take = ($attempt_count == 0) || ($quiz_row['allow_retake'] == 1);
        
        $quiz_row['attempt_count'] = $attempt_count;
        $quiz_row['can_take'] = $can_take;
        $quiz_row['status'] = $attempt_count > 0 ? 'completed' : 'available';
        
        $quizzes[] = $quiz_row;
        
        // Count statistics
        // Available: quizzes that can be taken (not taken OR retake allowed)
        if ($can_take) {
            $total_available++;
        }
        // Undone: only quizzes that have NEVER been taken (attempt_count == 0)
        if ($attempt_count == 0) {
            $total_undone++;
        }
    }
    $stmt_quizzes->close();
    
    if (!empty($quizzes)) {
        $quizzes_by_subject[] = [
            'subject_id' => $subject_id,
            'subject_name' => $subject_row['name'],
            'quizzes' => $quizzes
        ];
    }
}
$stmt_subjects->close();
$conn->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-0"><i class="bi bi-clipboard-check text-primary"></i> My Quizzes</h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;"><i class="bi bi-info-circle me-1"></i> View and take quizzes from all your enrolled subjects</p>
    </div>
    <a href="index.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
</div>

<!-- Statistics Cards -->
<?php if ($total_available > 0 || $total_undone > 0): ?>
<div class="row g-3 mb-4">
    <?php if ($total_undone > 0): ?>
    <div class="col-md-6">
        <div class="card border-warning shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-exclamation-circle-fill text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="card-title mb-1">
                            <i class="bi bi-clock-history"></i> Undone Quizzes
                        </h5>
                        <h2 class="mb-0 text-warning"><?php echo $total_undone; ?></h2>
                        <small class="text-muted">Quizzes you haven't taken yet</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="col-md-6">
        <div class="card border-success shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-success bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="card-title mb-1">
                            <i class="bi bi-trophy-fill"></i> All Quizzes Completed!
                        </h5>
                        <h2 class="mb-0 text-success">0</h2>
                        <small class="text-muted">Great job! All quizzes are done.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($total_available > 0): ?>
    <div class="col-md-6">
        <div class="card border-primary shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-list-check text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="card-title mb-1">
                            <i class="bi bi-clipboard-check"></i> Available Quizzes
                        </h5>
                        <h2 class="mb-0 text-primary"><?php echo $total_available; ?></h2>
                        <small class="text-muted">Quizzes you can take or retake</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (empty($quizzes_by_subject)): ?>
    <div class="card">
        <div class="empty-state text-center py-5">
            <i class="bi bi-clipboard-x" style="font-size: 3rem; color: #9CA3AF; display: block; margin-bottom: 1rem;"></i>
            <h3 class="text-muted mb-2">No Quizzes Available</h3>
            <p class="text-muted mb-3">You don't have any quizzes available yet. Check back later or contact your instructors.</p>
            <a href="index.php" class="btn btn-primary">
                <i class="bi bi-arrow-left"></i> Back to My Subjects
            </a>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($quizzes_by_subject as $subject_group): ?>
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">
                        <i class="bi bi-book"></i> <?php echo htmlspecialchars($subject_group['subject_name']); ?>
                    </h3>
                    <span class="badge bg-primary">
                        <i class="bi bi-clipboard-check"></i> <?php echo count($subject_group['quizzes']); ?> Quiz<?php echo count($subject_group['quizzes']) != 1 ? 'zes' : ''; ?>
                    </span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th><i class="bi bi-card-text"></i> Quiz Title</th>
                            <th><i class="bi bi-file-earmark-text"></i> Lesson</th>
                            <th><i class="bi bi-list-ol"></i> Questions</th>
                            <th><i class="bi bi-info-circle"></i> Status</th>
                            <th><i class="bi bi-gear"></i> Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subject_group['quizzes'] as $quiz): ?>
                            <tr>
                                <td>
                                    <i class="bi bi-clipboard-check text-primary me-2"></i>
                                    <strong><?php echo htmlspecialchars($quiz['title']); ?></strong>
                                </td>
                                <td>
                                    <i class="bi bi-file-earmark-text text-muted me-2"></i>
                                    <small><?php echo htmlspecialchars($quiz['lesson_title']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <i class="bi bi-list-ol"></i> <?php echo $quiz['question_count']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($quiz['status'] == 'completed'): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle-fill"></i> Completed
                                        </span>
                                        <?php if ($quiz['allow_retake'] == 1): ?>
                                            <span class="badge bg-warning text-dark ms-1">
                                                <i class="bi bi-arrow-repeat"></i> Retake Available
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-clock-history"></i> Not Taken
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($quiz['can_take']): ?>
                                        <?php if ($quiz['attempt_count'] > 0): ?>
                                            <!-- Quiz has been taken - show Retake button -->
                                            <a href="take_quiz.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-warning">
                                                <i class="bi bi-arrow-repeat"></i> Retake Quiz
                                            </a>
                                        <?php else: ?>
                                            <!-- Quiz has not been taken - show Take button -->
                                            <a href="take_quiz.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-success">
                                                <i class="bi bi-pencil-square"></i> Take Quiz
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <!-- Quiz completed and retake not allowed -->
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-check-circle-fill"></i> Already Completed
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once '../includes/student_footer.php'; ?>

