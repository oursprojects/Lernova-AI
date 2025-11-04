<?php
/*
 * LernovaAI - Take Quiz Page
 * Displays the quiz questions and answers for the student.
 */

// 1. Include the header
require_once '../includes/student_header.php';
require_once '../config/db.php';

// 2. Check for Quiz ID and validate
if (!isset($_GET['quiz_id']) || empty($_GET['quiz_id'])) {
    header("Location: index.php?error=no_id");
    exit;
}

$quiz_id = intval($_GET['quiz_id']);
$student_id = $_SESSION['user_id'];

if ($quiz_id <= 0) {
    header("Location: index.php?error=invalid_quiz_id");
    exit;
}

// --- 3. Verify quiz exists, is published, and check retake permissions ---
$stmt_quiz = $conn->prepare("
    SELECT q.title, q.allow_retake, l.title AS lesson_title, s.id AS subject_id
    FROM quizzes q
    JOIN lessons l ON q.lesson_id = l.id
    JOIN subjects s ON l.subject_id = s.id
    JOIN enrollments e ON s.id = e.subject_id
    WHERE q.id = ? AND q.status = 'published' AND e.student_id = ?
");
$stmt_quiz->bind_param("ii", $quiz_id, $student_id);
$stmt_quiz->execute();
$result_quiz = $stmt_quiz->get_result();

if ($result_quiz->num_rows == 0) {
    $stmt_quiz->close();
    header("Location: index.php?error=quiz_unavailable");
    exit;
}

$quiz = $result_quiz->fetch_assoc();
$stmt_quiz->close();

// Check if student already took this quiz and retake is not allowed
$stmt_check_attempt = $conn->prepare("
    SELECT COUNT(*) AS attempt_count 
    FROM student_attempts 
    WHERE quiz_id = ? AND student_id = ?
");
$stmt_check_attempt->bind_param("ii", $quiz_id, $student_id);
$stmt_check_attempt->execute();
$result_attempt = $stmt_check_attempt->get_result();
$attempt_data = $result_attempt->fetch_assoc();
$attempt_count = intval($attempt_data['attempt_count']);
$stmt_check_attempt->close();

if ($attempt_count > 0 && $quiz['allow_retake'] == 0) {
    header("Location: index.php?error=quiz_already_completed");
    exit;
}


// --- 4. Fetch All Questions and Answers for this Quiz ---
$questions_data = [];
$stmt_q = $conn->prepare("
    SELECT id, question_text, question_type 
    FROM questions 
    WHERE quiz_id = ? 
    ORDER BY id ASC
");
$stmt_q->bind_param("i", $quiz_id);
$stmt_q->execute();
$result_q = $stmt_q->get_result();

if ($result_q->num_rows == 0) {
    $stmt_q->close();
    $conn->close();
    header("Location: index.php?error=quiz_has_no_questions");
    exit;
}

while ($q_row = $result_q->fetch_assoc()) {
    $answers_data = [];
    // We only fetch the answer ID and text, NOT the is_correct column.
    // The student should not be able to see the correct answer in the HTML source.
    $stmt_a = $conn->prepare("
        SELECT id, answer_text 
        FROM answers 
        WHERE question_id = ? 
        ORDER BY RAND()
    ");
    $stmt_a->bind_param("i", $q_row['id']);
    $stmt_a->execute();
    $result_a = $stmt_a->get_result();
    
    if ($result_a->num_rows == 0) {
        $stmt_a->close();
        continue; // Skip questions without answers
    }
    
    while ($a_row = $result_a->fetch_assoc()) {
        $answers_data[] = $a_row;
    }
    $q_row['answers'] = $answers_data;
    $questions_data[] = $q_row;
    $stmt_a->close();
}
$stmt_q->close();

if (empty($questions_data)) {
    $conn->close();
    header("Location: index.php?error=quiz_has_no_valid_questions");
    exit;
}

$conn->close();
?>

<style>
    /* Specific styles for the quiz form */
    .question-card { background-color: #fdfdfd; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 25px; }
    .question-header { background-color: #f9f9f9; padding: 15px 20px; border-bottom: 1px solid #e0e0e0; }
    .question-header h3 { margin: 0; }
    .question-body { padding: 20px; }
    .answer-option { display: block; margin-bottom: 15px; font-size: 1.1em; cursor: pointer; }
    .answer-option input[type="radio"] { margin-right: 15px; transform: scale(1.2); }
    .submit-btn-container { text-align: center; }
</style>

<div class="mb-4">
    <h1 class="mb-2"><i class="bi bi-clipboard-check text-primary"></i> <?php echo htmlspecialchars($quiz['title']); ?></h1>
    <p class="text-muted mb-2" style="font-size: 1.1em;">
        <i class="bi bi-file-earmark-text text-info"></i> Based on the lesson: <strong><?php echo htmlspecialchars($quiz['lesson_title']); ?></strong>
    </p>
    <p class="text-muted">
        <i class="bi bi-info-circle"></i> Please answer all questions to the best of your ability.
    </p>
</div>

<div class="card">
    <form action="submit_quiz.php" method="POST">
        <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
        
        <?php foreach ($questions_data as $index => $q): ?>
            <div class="question-card">
                <div class="question-header">
                    <h3 class="mb-0"><i class="bi bi-question-circle-fill text-primary me-2"></i>Question <?php echo $index + 1; ?></h3>
                </div>
                <div class="question-body">
                    <p style="font-size: 1.2em; font-weight: bold; margin-top: 0;">
                        <?php echo htmlspecialchars($q['question_text']); ?>
                    </p>
                    
                    <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
                    
                    <?php if (empty($q['answers'])): ?>
                        <p style="color: red;">This question has no answers. Please notify your instructor.</p>
                    <?php else: ?>
                        <?php foreach ($q['answers'] as $a): ?>
                            <label class="answer-option">
                                <input type="radio" 
                                       name="answers[<?php echo $q['id']; ?>]" 
                                       value="<?php echo $a['id']; ?>" 
                                       required>
                                <?php echo htmlspecialchars($a['answer_text']); ?>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="submit-btn-container">
            <button type="submit" class="btn btn-success btn-lg" style="font-size: 1.2em; padding: 15px 40px;">
                <i class="bi bi-check-circle-fill me-2"></i> Submit Quiz
            </button>
        </div>
    </form>
</div>

<?php
// 3. Include the footer
require_once '../includes/student_footer.php';
?>