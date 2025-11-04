<?php
/*
 * LernovaAI - Edit Quiz Page (UPGRADED)
 * Allows faculty to review, edit, AND ADD new questions.
 */

// 1. Include the header
require_once '../includes/faculty_header.php';
require_once '../config/db.php';

$faculty_id = $_SESSION['user_id'];
$message = '';
$error = '';

// 2. Check for Quiz ID
if (!isset($_GET['quiz_id'])) {
    header("Location: quizzes.php?error=no_id");
    exit;
}
$quiz_id = intval($_GET['quiz_id']);

// 3. Verify this quiz belongs to this faculty
$stmt_check = $conn->prepare("
    SELECT q.id, q.title, q.status, COALESCE(q.allow_retake, 1) AS allow_retake
    FROM quizzes q 
    JOIN lessons l ON q.lesson_id = l.id 
    JOIN subjects s ON l.subject_id = s.id
    WHERE q.id = ? AND s.faculty_id = ?
");
$stmt_check->bind_param("ii", $quiz_id, $faculty_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows == 0) {
    header("Location: quizzes.php?error=permission_denied");
    exit;
}
$quiz = $result_check->fetch_assoc();
$stmt_check->close();

// --- 4. HANDLE ALL FORM SUBMISSIONS (UPDATE, ADD NEW) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Begin transaction for safety
    $conn->begin_transaction();
    try {
        
        // --- ACTION: UPDATE EXISTING QUIZ ---
        if (isset($_POST['action']) && $_POST['action'] == 'update_quiz') {
            // a. Update the main quiz title and allow_retake
            $new_quiz_title = $conn->real_escape_string($_POST['quiz_title']);
            $allow_retake = isset($_POST['allow_retake']) ? 1 : 0;
            $stmt_update_quiz = $conn->prepare("UPDATE quizzes SET title = ?, allow_retake = ? WHERE id = ?");
            $stmt_update_quiz->bind_param("sii", $new_quiz_title, $allow_retake, $quiz_id);
            $stmt_update_quiz->execute();
            $stmt_update_quiz->close();
            
            // Update local quiz array for display
            $quiz['title'] = $new_quiz_title;
            $quiz['allow_retake'] = $allow_retake;

            // b. Loop through each existing question
            if (isset($_POST['questions'])) {
                foreach ($_POST['questions'] as $q_id => $q_data) {
                    // ... (rest of update logic is the same) ...
                    $q_id = intval($q_id);
                    $question_text = $conn->real_escape_string($q_data['text']);
                    
                    $stmt_q = $conn->prepare("UPDATE questions SET question_text = ? WHERE id = ? AND quiz_id = ?");
                    $stmt_q->bind_param("sii", $question_text, $q_id, $quiz_id);
                    $stmt_q->execute();
                    $stmt_q->close();

                    if (isset($q_data['answers'])) {
                        $correct_answer_id = intval($q_data['correct_answer']);
                        foreach ($q_data['answers'] as $a_id => $a_text) {
                            $a_id = intval($a_id);
                            $a_text = $conn->real_escape_string($a_text);
                            $is_correct = ($a_id == $correct_answer_id) ? 1 : 0;
                            
                            $stmt_a = $conn->prepare("UPDATE answers SET answer_text = ?, is_correct = ? WHERE id = ? AND question_id = ?");
                            $stmt_a->bind_param("siii", $a_text, $is_correct, $a_id, $q_id);
                            $stmt_a->execute();
                            $stmt_a->close();
                        }
                    }
                }
            }
            $message = "Quiz updated successfully!";
        }

        // --- NEW ACTION: ADD NEW QUESTION ---
        if (isset($_POST['action']) && $_POST['action'] == 'add_question') {
            $question_text = $conn->real_escape_string($_POST['new_question_text']);
            $question_type = $conn->real_escape_string($_POST['new_question_type']);
            
            if (empty($question_text) || empty($question_type)) {
                throw new Exception("Question text and type are required.");
            }

            // a. Insert the new question
            $stmt_new_q = $conn->prepare("INSERT INTO questions (quiz_id, question_text, question_type) VALUES (?, ?, ?)");
            $stmt_new_q->bind_param("iss", $quiz_id, $question_text, $question_type);
            $stmt_new_q->execute();
            $new_question_id = $stmt_new_q->insert_id;
            $stmt_new_q->close();

            // b. Insert the answers
            if ($question_type == 'mcq') {
                $answers = $_POST['new_answers']; // This is an array
                $correct_index = intval($_POST['new_correct_answer_mcq']); // This is the index (0-3)
                
                for ($i = 0; $i < count($answers); $i++) {
                    $answer_text = $conn->real_escape_string($answers[$i]);
                    $is_correct = ($i == $correct_index) ? 1 : 0;
                    
                    $stmt_new_a = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                    $stmt_new_a->bind_param("isi", $new_question_id, $answer_text, $is_correct);
                    $stmt_new_a->execute();
                    $stmt_new_a->close();
                }
            } 
            elseif ($question_type == 'tf') {
                $correct_answer = $_POST['new_correct_answer_tf']; // "True" or "False"
                
                // Save "True" option
                $is_true_correct = ($correct_answer == 'True') ? 1 : 0;
                $stmt_ans_true = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, 'True', ?)");
                $stmt_ans_true->bind_param("ii", $new_question_id, $is_true_correct);
                $stmt_ans_true->execute();
                $stmt_ans_true->close();

                // Save "False" option
                $is_false_correct = ($correct_answer == 'False') ? 1 : 0;
                $stmt_ans_false = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, 'False', ?)");
                $stmt_ans_false->bind_param("ii", $new_question_id, $is_false_correct);
                $stmt_ans_false->execute();
                $stmt_ans_false->close();
            }
            // After adding question, redirect to prevent form resubmission
            header("Location: edit_quiz.php?quiz_id=" . $quiz_id . "&added=success");
            exit;
        }

        // d. If everything worked, commit
        $conn->commit();
        
        // Refresh quiz data after update
        $quiz['title'] = $new_quiz_title ?? $quiz['title'];
        
    } catch (Exception $e) {
        // Something failed, roll back
        $conn->rollback();
        $error = "Error updating quiz: " . $e->getMessage();
    }
}

// Check for success message after redirect
if (isset($_GET['added']) && $_GET['added'] == 'success') {
    $message = "New question added successfully!";
}


// --- 5. FETCH ALL DATA FOR DISPLAY (Same as before) ---
$questions_data = [];
$stmt_q = $conn->prepare("SELECT id, question_text, question_type FROM questions WHERE quiz_id = ? ORDER BY id ASC");
$stmt_q->bind_param("i", $quiz_id);
$stmt_q->execute();
$result_q = $stmt_q->get_result();
while ($q_row = $result_q->fetch_assoc()) {
    $answers_data = [];
    $stmt_a = $conn->prepare("SELECT id, answer_text, is_correct FROM answers WHERE question_id = ? ORDER BY id ASC");
    $stmt_a->bind_param("i", $q_row['id']);
    $stmt_a->execute();
    $result_a = $stmt_a->get_result();
    while ($a_row = $result_a->fetch_assoc()) {
        $answers_data[] = $a_row;
    }
    $q_row['answers'] = $answers_data;
    $questions_data[] = $q_row;
    $stmt_a->close();
}
$stmt_q->close();
$conn->close();
?>

<style>
    /* Specific styles for the edit quiz form - Compact */
    .quiz-form-container { max-width: 100%; }
    .question-card { background-color: #fdfdfd; border: 1px solid #e0e0e0; border-radius: 0.5rem; margin-bottom: 1rem; }
    .question-header { background-color: #f9f9f9; padding: 0.75rem 1rem; border-bottom: 1px solid #e0e0e0; }
    .question-header h3 { margin: 0; font-size: 1rem; }
    .question-body { padding: 1rem; }
    .question-text-area { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #ccc; border-radius: 0.375rem; box-sizing: border-box; font-size: 0.875rem; }
    .answer-option { display: flex; align-items: center; margin-bottom: 0.75rem; gap: 0.5rem; }
    .answer-option input[type="radio"] { margin: 0; transform: scale(1.1); }
    .answer-option input[type="text"] { flex-grow: 1; padding: 0.5rem 0.75rem; border: 1px solid #ccc; border-radius: 0.375rem; font-size: 0.875rem; }
    .save-btn-container { text-align: right; }
    
    /* NEW STYLES for Add Question form */
    .add-question-card { background-color: #f8f9fa; border: 2px dashed #007bff; }
    #mcq_options, #tf_options { display: none; margin-top: 0.75rem; }
</style>

<div class="quiz-form-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="mb-0"><i class="bi bi-pencil-square"></i> Edit Quiz</h1>
            <p class="text-muted mb-0" style="font-size: 0.875rem;">Review and edit questions, change text, and select correct answers</p>
        </div>
        <a href="quizzes.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Back to Quizzes
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

    <form action="edit_quiz.php?quiz_id=<?php echo $quiz_id; ?>" method="POST">
        <input type="hidden" name="action" value="update_quiz">
        
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="bi bi-card-text"></i> Quiz Settings</h3>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="quiz_title" class="form-label"><i class="bi bi-bookmark text-primary"></i> Quiz Title</label>
                    <input type="text" name="quiz_title" id="quiz_title" class="form-control" value="<?php echo htmlspecialchars($quiz['title']); ?>" required>
                </div>
                <div class="mb-0">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="allow_retake" id="allow_retake" value="1" <?php echo ($quiz['allow_retake'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="allow_retake">
                            <i class="bi bi-arrow-repeat text-primary"></i> Allow students to retake this quiz
                        </label>
                        <small class="text-muted d-block mt-1">
                            <i class="bi bi-info-circle"></i> If unchecked, students can only take this quiz once
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-header mb-2">
            <h3 class="card-title mb-0"><i class="bi bi-list-ol"></i> Existing Questions</h3>
        </div>
        <?php if (empty($questions_data)): ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle me-2"></i>This is a blank quiz. Add your first question below!
            </div>
        <?php endif; ?>

        <?php foreach ($questions_data as $index => $q): ?>
            <div class="question-card">
                <div class="question-header">
                    <h3 class="mb-0">
                        <i class="bi bi-question-circle"></i> Question <?php echo $index + 1; ?> 
                        <span class="badge bg-<?php echo $q['question_type'] == 'mcq' ? 'primary' : 'info'; ?> ms-2" style="font-size: 0.75rem;">
                            <?php echo $q['question_type'] == 'mcq' ? 'Multiple Choice' : 'True/False'; ?>
                        </span>
                    </h3>
                </div>
                <div class="question-body">
                    <input type="hidden" name="questions[<?php echo $q['id']; ?>][id]" value="<?php echo $q['id']; ?>">
                    <label for="q_<?php echo $q['id']; ?>_text" class="form-label"><i class="bi bi-pencil"></i> Question Text:</label>
                    <textarea id="q_<?php echo $q['id']; ?>_text" name="questions[<?php echo $q['id']; ?>][text]" class="question-text-area form-control" rows="2"><?php echo htmlspecialchars($q['question_text']); ?></textarea>
                    
                    <h5 class="mt-3 mb-2" style="font-size: 0.9375rem;"><i class="bi bi-list-check"></i> Answers:</h5>
                    <?php 
                    $correct_answer_id = 0;
                    foreach ($q['answers'] as $a) { if ($a['is_correct']) { $correct_answer_id = $a['id']; break; } }
                    ?>
                    <input type="hidden" name="questions[<?php echo $q['id']; ?>][correct_answer_type]" value="<?php echo $correct_answer_id; ?>">
                    <?php foreach ($q['answers'] as $a): ?>
                        <div class="answer-option">
                            <input type="radio" id="a_<?php echo $a['id']; ?>" name="questions[<?php echo $q['id']; ?>][correct_answer]" value="<?php echo $a['id']; ?>" <?php if ($a['is_correct']) echo 'checked'; ?>>
                            <?php if ($q['question_type'] == 'mcq'): ?>
                                <input type="text" name="questions[<?php echo $q['id']; ?>][answers][<?php echo $a['id']; ?>]" value="<?php echo htmlspecialchars($a['answer_text']); ?>">
                            <?php else: ?>
                                <label for="a_<?php echo $a['id']; ?>" style="font-size: 1.1em; flex-grow: 1;"><?php echo htmlspecialchars($a['answer_text']); ?></label>
                                <input type="hidden" name="questions[<?php echo $q['id']; ?>][answers][<?php echo $a['id']; ?>]" value="<?php echo htmlspecialchars($a['answer_text']); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="card save-btn-container">
            <button type="submit" class="btn btn-success">
                <i class="bi bi-check-circle"></i> Save All Changes
            </button>
        </div>
    </form>
    
    <hr style="border: 0; border-top: 2px solid #ccc; margin: 2rem 0;">
    <div class="card-header mb-2">
        <h3 class="card-title mb-0"><i class="bi bi-plus-circle"></i> Add New Question</h3>
    </div>
    <div class="card question-card add-question-card">
        <form action="edit_quiz.php?quiz_id=<?php echo $quiz_id; ?>" method="POST" class="question-body">
            <input type="hidden" name="action" value="add_question">
            
            <div class="mb-3">
                <label for="new_question_text" class="form-label"><i class="bi bi-pencil"></i> Question Text:</label>
                <textarea id="new_question_text" name="new_question_text" class="form-control" rows="2" required></textarea>
            </div>
            
            <div class="mb-3">
                <label for="new_question_type" class="form-label"><i class="bi bi-list-check"></i> Question Type:</label>
                <select id="new_question_type" name="new_question_type" class="form-select" required>
                    <option value="">-- Select type --</option>
                    <option value="mcq">Multiple Choice</option>
                    <option value="tf">True/False</option>
                </select>
            </div>

            <div id="mcq_options">
                <label class="form-label"><i class="bi bi-list-ul"></i> Multiple Choice Options:</label>
                <small class="text-muted d-block mb-2">Select the radio button for the correct answer</small>
                <div class="answer-option">
                    <input type="radio" name="new_correct_answer_mcq" value="0" checked>
                    <input type="text" name="new_answers[]" placeholder="Answer 1">
                </div>
                <div class="answer-option">
                    <input type="radio" name="new_correct_answer_mcq" value="1">
                    <input type="text" name="new_answers[]" placeholder="Answer 2">
                </div>
                <div class="answer-option">
                    <input type="radio" name="new_correct_answer_mcq" value="2">
                    <input type="text" name="new_answers[]" placeholder="Answer 3">
                </div>
                <div class="answer-option">
                    <input type="radio" name="new_correct_answer_mcq" value="3">
                    <input type="text" name="new_answers[]" placeholder="Answer 4">
                </div>
            </div>

            <div id="tf_options">
                <label class="form-label"><i class="bi bi-check-circle"></i> Correct Answer:</label>
                <div class="answer-option">
                    <input type="radio" id="new_tf_true" name="new_correct_answer_tf" value="True" checked>
                    <label for="new_tf_true" style="font-size: 1.1em;">True</label>
                </div>
                <div class="answer-option">
                    <input type="radio" id="new_tf_false" name="new_correct_answer_tf" value="False">
                    <label for="new_tf_false" style="font-size: 1.1em;">False</label>
                </div>
            </div>
            
            <div class="save-btn-container mt-3">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-plus-circle"></i> Add This Question
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const questionTypeSelect = document.getElementById('new_question_type');
    const mcqOptions = document.getElementById('mcq_options');
    const tfOptions = document.getElementById('tf_options');
    const addQuestionForm = document.querySelector('.add-question-card form');
    
    // Handle question type change
    if (questionTypeSelect) {
        questionTypeSelect.addEventListener('change', function() {
            if (this.value == 'mcq') {
                mcqOptions.style.display = 'block';
                tfOptions.style.display = 'none';
            } else if (this.value == 'tf') {
                mcqOptions.style.display = 'none';
                tfOptions.style.display = 'block';
            } else {
                mcqOptions.style.display = 'none';
                tfOptions.style.display = 'none';
            }
        });
    }
    
    // Clear form after successful submission (when redirected with ?added=success)
    if (window.location.search.includes('added=success') && addQuestionForm) {
        addQuestionForm.reset();
        if (questionTypeSelect) {
            questionTypeSelect.dispatchEvent(new Event('change'));
        }
    }
});
</script>

<?php
// 3. Include the footer
require_once '../includes/faculty_footer.php';
?>