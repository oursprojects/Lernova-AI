<?php
/*
 * LernovaAI - Submit Quiz Script
 * Grades the submitted quiz and saves the result.
 */

// 1. Include Session & DB
session_start();
require_once '../config/db.php';

// 2. Security Check: Must be a student and must be a POST request
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student' || $_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: ../login.php");
    exit;
}

// 3. Get Data from Form and Validate
$student_id = $_SESSION['user_id'];
$quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
$submitted_answers = isset($_POST['answers']) ? $_POST['answers'] : [];

// Validate inputs
if ($quiz_id <= 0) {
    header("Location: index.php?error=invalid_quiz_id");
    exit;
}

if (empty($submitted_answers) || !is_array($submitted_answers)) {
    header("Location: index.php?error=no_answers_submitted");
    exit;
}

// Verify quiz exists and is published
$stmt_verify = $conn->prepare("
    SELECT q.id, q.allow_retake 
    FROM quizzes q
    JOIN lessons l ON q.lesson_id = l.id
    JOIN subjects s ON l.subject_id = s.id
    JOIN enrollments e ON s.id = e.subject_id
    WHERE q.id = ? AND q.status = 'published' AND e.student_id = ?
");
$stmt_verify->bind_param("ii", $quiz_id, $student_id);
$stmt_verify->execute();
$result_verify = $stmt_verify->get_result();

if ($result_verify->num_rows == 0) {
    $stmt_verify->close();
    header("Location: index.php?error=quiz_unavailable");
    exit;
}

$quiz_verify = $result_verify->fetch_assoc();
$stmt_verify->close();

// Check retake permission if already attempted
$stmt_check = $conn->prepare("SELECT COUNT(*) AS count FROM student_attempts WHERE quiz_id = ? AND student_id = ?");
$stmt_check->bind_param("ii", $quiz_id, $student_id);
$stmt_check->execute();
$check_result = $stmt_check->get_result();
$check_data = $check_result->fetch_assoc();
$stmt_check->close();

if ($check_data['count'] > 0 && $quiz_verify['allow_retake'] == 0) {
    header("Location: index.php?error=quiz_already_completed");
    exit;
}

// --- 4. Grading Logic ---
$score = 0;
$total_questions = count($submitted_answers);

// Validate total questions count
$stmt_count = $conn->prepare("SELECT COUNT(*) AS total FROM questions WHERE quiz_id = ?");
$stmt_count->bind_param("i", $quiz_id);
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$count_data = $count_result->fetch_assoc();
$actual_total = intval($count_data['total']);
$stmt_count->close();

if ($actual_total == 0) {
    header("Location: index.php?error=quiz_has_no_questions");
    exit;
}

if ($total_questions > $actual_total) {
    header("Location: index.php?error=invalid_submission");
    exit;
}

try {
    // We will check each submitted answer one by one
    foreach ($submitted_answers as $question_id => $answer_id) {
        $q_id = intval($question_id);
        $a_id = intval($answer_id);
        
        if ($q_id <= 0 || $a_id <= 0) {
            continue; // Skip invalid IDs
        }
        
        // Verify question belongs to this quiz
        $stmt_verify_q = $conn->prepare("SELECT id FROM questions WHERE id = ? AND quiz_id = ?");
        $stmt_verify_q->bind_param("ii", $q_id, $quiz_id);
        $stmt_verify_q->execute();
        $verify_q_result = $stmt_verify_q->get_result();
        
        if ($verify_q_result->num_rows == 0) {
            $stmt_verify_q->close();
            continue; // Skip if question doesn't belong to quiz
        }
        $stmt_verify_q->close();
        
        // Prepare a statement to check if this answer is correct
        // We check 'is_correct' = 1 AND the ID matches
        $stmt = $conn->prepare("
            SELECT id 
            FROM answers 
            WHERE id = ? AND question_id = ? AND is_correct = 1
        ");
        $stmt->bind_param("ii", $a_id, $q_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // If we get a row back, the answer was correct
        if ($result->num_rows == 1) {
            $score++; // Increment the score
        }
        $stmt->close();
    }
    
    // --- 5. Save the Attempt to the Database ---
    $stmt_save = $conn->prepare("
        INSERT INTO student_attempts (student_id, quiz_id, score, total_questions) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt_save->bind_param("iiii", $student_id, $quiz_id, $score, $total_questions);
    
    if (!$stmt_save->execute()) {
        throw new Exception("Failed to save quiz attempt: " . $stmt_save->error);
    }
    
    // Get the ID of the attempt we just saved
    $new_attempt_id = $stmt_save->insert_id;
    $stmt_save->close();
    
    if ($new_attempt_id <= 0) {
        throw new Exception("Failed to retrieve attempt ID");
    }
    
    // --- 6. Redirect to the Results Page ---
    header("Location: show_result.php?attempt_id=" . $new_attempt_id);
    exit;

} catch (Exception $e) {
    // Handle any database errors
    header("Location: index.php?error=" . urlencode($e->getMessage()));
    exit;
}
?>