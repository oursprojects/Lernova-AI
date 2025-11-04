<?php
/*
 * LernovaAI - Quiz Results Page (Faculty)
 * Shows quiz results organized by subject, listing all students who took each quiz
 */

require_once '../includes/faculty_header.php';
require_once '../config/db.php';

$faculty_id = $_SESSION['user_id'];

// Fetch all subjects for this faculty
$subjects = [];
$stmt_subjects = $conn->prepare("
    SELECT id, name 
    FROM subjects 
    WHERE faculty_id = ? 
    ORDER BY name ASC
");
$stmt_subjects->bind_param("i", $faculty_id);
$stmt_subjects->execute();
$result_subjects = $stmt_subjects->get_result();

while ($row = $result_subjects->fetch_assoc()) {
    $subject_id = $row['id'];
    
    // Get all quizzes for this subject
    $quizzes = [];
    $stmt_quizzes = $conn->prepare("
        SELECT 
            q.id,
            q.title,
            q.created_at,
            (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS question_count
        FROM quizzes q
        JOIN lessons l ON q.lesson_id = l.id
        WHERE l.subject_id = ? AND q.status = 'published'
        ORDER BY q.created_at DESC
    ");
    $stmt_quizzes->bind_param("i", $subject_id);
    $stmt_quizzes->execute();
    $result_quizzes = $stmt_quizzes->get_result();
    
    while ($quiz_row = $result_quizzes->fetch_assoc()) {
        $quiz_id = $quiz_row['id'];
        
        // Get all attempts for this quiz
        $attempts = [];
        $stmt_attempts = $conn->prepare("
            SELECT 
                sa.id AS attempt_id,
                sa.score,
                sa.total_questions,
                sa.attempt_date,
                u.id AS student_id,
                u.first_name,
                u.last_name,
                u.username
            FROM student_attempts sa
            JOIN users u ON sa.student_id = u.id
            WHERE sa.quiz_id = ?
            ORDER BY sa.attempt_date DESC
        ");
        $stmt_attempts->bind_param("i", $quiz_id);
        $stmt_attempts->execute();
        $result_attempts = $stmt_attempts->get_result();
        
        while ($attempt_row = $result_attempts->fetch_assoc()) {
            $percentage = ($attempt_row['total_questions'] > 0) 
                ? round(($attempt_row['score'] / $attempt_row['total_questions']) * 100, 1) 
                : 0;
            $attempt_row['percentage'] = $percentage;
            $attempts[] = $attempt_row;
        }
        $stmt_attempts->close();
        
        $quiz_row['attempts'] = $attempts;
        $quiz_row['total_attempts'] = count($attempts);
        
        // Calculate average score for this quiz
        $avg_score = 0;
        if (count($attempts) > 0) {
            $sum_percentages = 0;
            foreach ($attempts as $attempt) {
                $sum_percentages += $attempt['percentage'];
            }
            $avg_score = round($sum_percentages / count($attempts), 1);
        }
        $quiz_row['avg_score'] = $avg_score;
        
        $quizzes[] = $quiz_row;
    }
    $stmt_quizzes->close();
    
    $row['quizzes'] = $quizzes;
    $row['total_quizzes'] = count($quizzes);
    
    // Count total attempts across all quizzes in this subject
    $total_attempts_subject = 0;
    foreach ($quizzes as $quiz) {
        $total_attempts_subject += $quiz['total_attempts'];
    }
    $row['total_attempts'] = $total_attempts_subject;
    
    $subjects[] = $row;
}
$stmt_subjects->close();

$conn->close();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1><i class="bi bi-clipboard-data"></i> Quiz Results</h1>
</div>

<?php if (empty($subjects)): ?>
    <div class="card">
        <div class="empty-state text-center py-5">
            <i class="bi bi-clipboard-x" style="font-size: 3rem; color: #9CA3AF; display: block; margin-bottom: 1rem;"></i>
            <h3 class="text-muted mb-2">No Subjects Found</h3>
            <p class="text-muted mb-3">You haven't created any subjects yet.</p>
            <a href="manage_subjects.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Create Your First Subject
            </a>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($subjects as $subject): ?>
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">
                        <i class="bi bi-book"></i> <?php echo htmlspecialchars($subject['name']); ?>
                    </h3>
                    <div class="d-flex gap-3">
                        <span class="badge bg-info">
                            <i class="bi bi-clipboard-check"></i> <?php echo $subject['total_quizzes']; ?> Quiz<?php echo $subject['total_quizzes'] != 1 ? 'zes' : ''; ?>
                        </span>
                        <span class="badge bg-primary">
                            <i class="bi bi-people"></i> <?php echo $subject['total_attempts']; ?> Attempt<?php echo $subject['total_attempts'] != 1 ? 's' : ''; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <?php if (empty($subject['quizzes'])): ?>
                <div class="card-body">
                    <div class="empty-state text-center py-4">
                        <i class="bi bi-clipboard-x" style="font-size: 2rem; color: #9CA3AF; display: block; margin-bottom: 0.5rem;"></i>
                        <p class="text-muted mb-0">No published quizzes in this subject yet.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($subject['quizzes'] as $quiz): ?>
                    <div class="card-body border-bottom">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h4 class="mb-1">
                                    <i class="bi bi-clipboard-check text-primary"></i> 
                                    <?php echo htmlspecialchars($quiz['title']); ?>
                                </h4>
                                <small class="text-muted">
                                    <i class="bi bi-calendar"></i> Created: <?php echo date("M j, Y", strtotime($quiz['created_at'])); ?>
                                    <span class="ms-2">
                                        <i class="bi bi-question-circle"></i> <?php echo $quiz['question_count']; ?> Question<?php echo $quiz['question_count'] != 1 ? 's' : ''; ?>
                                    </span>
                                </small>
                            </div>
                            <div class="d-flex gap-2">
                                <span class="badge bg-secondary">
                                    <i class="bi bi-people"></i> <?php echo $quiz['total_attempts']; ?> Attempt<?php echo $quiz['total_attempts'] != 1 ? 's' : ''; ?>
                                </span>
                                <?php if ($quiz['total_attempts'] > 0): ?>
                                    <span class="badge bg-info">
                                        <i class="bi bi-graph-up"></i> Avg: <?php echo $quiz['avg_score']; ?>%
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (empty($quiz['attempts'])): ?>
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle"></i> No students have taken this quiz yet.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th><i class="bi bi-person"></i> Student</th>
                                            <th><i class="bi bi-star"></i> Score</th>
                                            <th><i class="bi bi-percent"></i> Percentage</th>
                                            <th><i class="bi bi-calendar"></i> Date Taken</th>
                                            <th><i class="bi bi-eye"></i> Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($quiz['attempts'] as $attempt): ?>
                                            <tr>
                                                <td>
                                                    <i class="bi bi-person-circle text-primary me-2"></i>
                                                    <strong><?php echo htmlspecialchars($attempt['first_name'] . ' ' . $attempt['last_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">@<?php echo htmlspecialchars($attempt['username']); ?></small>
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
                                                <td>
                                                    <a href="view_student.php?student_id=<?php echo $attempt['student_id']; ?>&subject_id=<?php echo $subject['id']; ?>" 
                                                       class="btn btn-sm btn-primary" 
                                                       title="View Student Details">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once '../includes/faculty_footer.php'; ?>

