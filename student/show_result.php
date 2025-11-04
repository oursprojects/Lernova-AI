<?php
/*
 * LernovaAI - Show Result Page
 * Displays the student's score after a quiz attempt.
 */

// 1. Include the header
require_once '../includes/student_header.php';
require_once '../config/db.php';

// 2. Get the Attempt ID and validate
if (!isset($_GET['attempt_id']) || empty($_GET['attempt_id'])) {
    header("Location: index.php?error=no_attempt");
    exit;
}
$attempt_id = intval($_GET['attempt_id']);
$student_id = $_SESSION['user_id']; // From header

if ($attempt_id <= 0) {
    header("Location: index.php?error=invalid_attempt_id");
    exit;
}

// --- 3. Fetch the Attempt Details ---
// We join with quizzes and lessons to get the titles
$stmt = $conn->prepare("
    SELECT 
        sa.score, 
        sa.total_questions, 
        sa.attempt_date,
        q.title AS quiz_title,
        l.title AS lesson_title
    FROM student_attempts sa
    JOIN quizzes q ON sa.quiz_id = q.id
    JOIN lessons l ON q.lesson_id = l.id
    WHERE sa.id = ? AND sa.student_id = ?
");
$stmt->bind_param("ii", $attempt_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Attempt not found or doesn't belong to this student
    $stmt->close();
    $conn->close();
    header("Location: index.php?error=invalid_attempt");
    exit;
}
$attempt = $result->fetch_assoc();
$stmt->close();

// Validate score data
$score = intval($attempt['score']);
$total_questions = intval($attempt['total_questions']);

if ($total_questions <= 0) {
    $conn->close();
    header("Location: index.php?error=invalid_score_data");
    exit;
}

if ($score < 0 || $score > $total_questions) {
    $conn->close();
    header("Location: index.php?error=invalid_score_range");
    exit;
}

$conn->close();

// Calculate percentage
$percentage = round(($score / $total_questions) * 100);
?>

<style>
    .result-card {
        text-align: center;
        max-width: 600px;
        margin: 40px auto;
    }
    .result-score {
        font-size: 5em; /* 500% of base font size */
        font-weight: bold;
        color: #333;
        margin: 20px 0;
    }
    .result-score .total {
        font-size: 0.5em; /* 50% of 5em */
        font-weight: normal;
        color: #777;
    }
    .result-percentage {
        font-size: 2.5em;
        font-weight: bold;
        color: <?php echo ($percentage >= 50) ? '#28a745' : '#dc3545'; ?>; /* Green or Red */
        margin-bottom: 30px;
    }
</style>

<div class="card result-card shadow-lg">
    <div class="card-header bg-<?php echo ($percentage >= 50) ? 'success' : 'danger'; ?> text-white text-center">
        <h1 class="mb-0"><i class="bi bi-trophy-fill me-2"></i>Quiz Complete!</h1>
    </div>
    <div class="card-body">
        <p class="text-muted text-center mb-3" style="font-size: 1.1em;">
            <i class="bi bi-clipboard-check text-primary"></i> You completed: <strong><?php echo htmlspecialchars($attempt['quiz_title']); ?></strong>
        </p>
        
        <hr style="border: 0; border-top: 2px solid #eee; margin: 30px 0;">
        
        <h2 class="text-center mb-4"><i class="bi bi-graph-up text-info"></i> Your Score</h2>
    <div class="result-score">
        <?php echo $score; ?>
        <span class="total">/ <?php echo $total_questions; ?></span>
    </div>
    
        <div class="result-percentage">
            <i class="bi bi-percent"></i><?php echo $percentage; ?>%
        </div>
        
        <div class="d-flex gap-2 justify-content-center mt-4">
            <a href="index.php" class="btn btn-primary">
                <i class="bi bi-house-door"></i> Back to Dashboard
            </a>
            <a href="my_results.php" class="btn btn-info">
                <i class="bi bi-list-check"></i> View All Results
            </a>
        </div>
    </div>
</div>

<?php
// 3. Include the footer
require_once '../includes/student_footer.php';
?>