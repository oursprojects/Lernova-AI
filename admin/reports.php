<?php
/*
 * LernovaAI - Admin Reports Page
 * This page displays statistics and recent activity.
 */

// 1. Include the header (which includes session check and db connection)
require_once '../includes/admin_header.php';
require_once '../config/db.php';

// --- 2. Fetch Statistics ---

// a. User Counts
$sql_users = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$result_users = $conn->query($sql_users);
$user_counts = ['admin' => 0, 'faculty' => 0, 'student' => 0];
while ($row = $result_users->fetch_assoc()) {
    $user_counts[$row['role']] = $row['count'];
}

// b. Content Counts
$sql_lessons = "SELECT COUNT(*) as count FROM lessons";
$lessons_count = $conn->query($sql_lessons)->fetch_assoc()['count'];

$sql_quizzes = "SELECT COUNT(*) as count FROM quizzes";
$quizzes_count = $conn->query($sql_quizzes)->fetch_assoc()['count'];

$sql_reviewers = "SELECT COUNT(*) as count FROM student_reviewers";
$reviewers_count = $conn->query($sql_reviewers)->fetch_assoc()['count'];

// c. Recent Quiz Attempts
$recent_attempts = [];
$sql_attempts = "
    SELECT 
        sa.attempt_date, 
        sa.score, 
        sa.total_questions,
        CONCAT(u.first_name, ' ', u.last_name) AS student_name,
        q.title AS quiz_title
    FROM student_attempts sa
    JOIN users u ON sa.student_id = u.id
    JOIN quizzes q ON sa.quiz_id = q.id
    ORDER BY sa.attempt_date DESC
    LIMIT 10
";
$result_attempts = $conn->query($sql_attempts);
if ($result_attempts->num_rows > 0) {
    while ($row = $result_attempts->fetch_assoc()) {
        $recent_attempts[] = $row;
    }
}
$conn->close();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="mb-0"><i class="bi bi-graph-up"></i> System Reports</h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;">Summary of all activity on the LernovaAI platform</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #4F46E5 0%, #6366F1 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50">Total Faculty</h6>
                        <h2 class="mb-0"><?php echo $user_counts['faculty']; ?></h2>
                    </div>
                    <i class="bi bi-mortarboard-fill" style="font-size: 2.5rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50">Total Students</h6>
                        <h2 class="mb-0"><?php echo $user_counts['student']; ?></h2>
                    </div>
                    <i class="bi bi-graduation-cap-fill" style="font-size: 2.5rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #F59E0B 0%, #F97316 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50">Total Lessons</h6>
                        <h2 class="mb-0"><?php echo $lessons_count; ?></h2>
                    </div>
                    <i class="bi bi-file-earmark-text" style="font-size: 2.5rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #8B5CF6 0%, #A78BFA 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50">Total Quizzes</h6>
                        <h2 class="mb-0"><?php echo $quizzes_count; ?></h2>
                    </div>
                    <i class="bi bi-clipboard-check" style="font-size: 2.5rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="bi bi-clock-history"></i> Recent Student Activity (Last 10 Quiz Attempts)</h3>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th><i class="bi bi-person"></i> Student Name</th>
                    <th><i class="bi bi-card-text"></i> Quiz Title</th>
                    <th><i class="bi bi-star"></i> Score</th>
                    <th><i class="bi bi-calendar3"></i> Date Taken</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_attempts)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-4">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p class="mb-0">No student quiz attempts found.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_attempts as $attempt): ?>
                        <tr>
                            <td>
                                <i class="bi bi-person-circle text-primary me-2"></i>
                                <?php echo htmlspecialchars($attempt['student_name']); ?>
                            </td>
                            <td>
                                <i class="bi bi-clipboard-check text-info me-2"></i>
                                <?php echo htmlspecialchars($attempt['quiz_title']); ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo ($attempt['score'] / $attempt['total_questions'] * 100) >= 50 ? 'success' : 'danger'; ?>">
                                    <i class="bi bi-star-fill"></i> <?php echo $attempt['score']; ?>/<?php echo $attempt['total_questions']; ?>
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

<?php
// 3. Include the footer
require_once '../includes/admin_footer.php';
?>