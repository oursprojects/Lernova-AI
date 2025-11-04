<?php
/*
 * LernovaAI - Generate Quiz Script
 * Fetches lesson text, calls Gemini, and saves the quiz.
 */

// 1. Load Session, DB, and Gemini Service
session_start();
require_once '../config/db.php';
require_once '../config/gemini.php'; // Our new API service

// 2. Security Check: Must be faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    header("Location: ../login.php");
    exit;
}

// 3. Get quiz generation options from session
if (!isset($_SESSION['quiz_gen_options'])) {
    header("Location: quizzes.php?generated_error=Quiz generation options not found. Please configure options first.");
    exit;
}

$quiz_options = $_SESSION['quiz_gen_options'];
$lesson_id = intval($quiz_options['lesson_id']);
$num_questions = intval($quiz_options['num_questions']);
$question_type = isset($quiz_options['question_type']) ? $quiz_options['question_type'] : 'both';
$difficulty = isset($quiz_options['difficulty']) ? $quiz_options['difficulty'] : 'medium';

// Clear the options from session
unset($_SESSION['quiz_gen_options']);

// Validate options
if ($num_questions < 1 || $num_questions > 50) {
    header("Location: quizzes.php?generated_error=Invalid number of questions. Must be between 1 and 50.");
    exit;
}

$faculty_id = $_SESSION['user_id'];

// --- Transaction: We'll wrap all our database queries in a transaction.
// This means if *any* part fails (e.g., saving a question), the *entire*
// quiz is rolled back. This prevents partial, broken quizzes.
$conn->begin_transaction();

try {
    // 4. Fetch the lesson text from the database
    $stmt_lesson = $conn->prepare("
        SELECT l.title, l.extracted_text 
        FROM lessons l
        JOIN subjects s ON l.subject_id = s.id
        WHERE l.id = ? AND s.faculty_id = ?
    ");
    $stmt_lesson->bind_param("ii", $lesson_id, $faculty_id);
    $stmt_lesson->execute();
    $result_lesson = $stmt_lesson->get_result();

    if ($result_lesson->num_rows == 0) {
        throw new Exception("Lesson not found or permission denied.");
    }

    $lesson = $result_lesson->fetch_assoc();
    $lesson_title = $lesson['title'];
    $lesson_text = $lesson['extracted_text'];
    $stmt_lesson->close();

    // 5. Calculate question distribution based on type
    $mcq_count = 0;
    $tf_count = 0;
    
    if ($question_type == 'both') {
        // For mixed types, distribute 60% MCQ, 40% TF
        $mcq_count = max(1, round($num_questions * 0.6));
        $tf_count = $num_questions - $mcq_count;
    } elseif ($question_type == 'mcq') {
        $mcq_count = $num_questions;
        $tf_count = 0;
    } else { // tf
        $mcq_count = 0;
        $tf_count = $num_questions;
    }
    
    // 6. Define difficulty instructions
    $difficulty_instruction = '';
    switch ($difficulty) {
        case 'easy':
            $difficulty_instruction = "Create questions that test basic understanding, recall, and simple comprehension. Focus on key terms, definitions, and straightforward facts.";
            break;
        case 'hard':
            $difficulty_instruction = "Create challenging questions that test deep understanding, analysis, synthesis, and evaluation. Include complex scenarios, critical thinking, and application of concepts.";
            break;
        default: // medium
            $difficulty_instruction = "Create questions that test application and analysis. Include moderate complexity scenarios that require understanding concepts and their relationships.";
    }
    
    // 7. Build question type instruction
    $type_instruction = '';
    if ($mcq_count > 0 && $tf_count > 0) {
        $type_instruction = "- {$mcq_count} multiple-choice questions (mcq) with 4 options each\n        - {$tf_count} true/false questions (tf)";
    } elseif ($mcq_count > 0) {
        $type_instruction = "- {$mcq_count} multiple-choice questions (mcq) with 4 options each";
    } else {
        $type_instruction = "- {$tf_count} true/false questions (tf)";
    }
    
    // 8. Define the AI Prompt with options
    $prompt = "
        You are an expert quiz generation assistant for a university.
        Based on the following lesson text, generate exactly {$num_questions} quiz questions.
        
        QUESTION REQUIREMENTS:
        {$type_instruction}
        
        DIFFICULTY LEVEL: {$difficulty}
        {$difficulty_instruction}
        
        LESSON TEXT:
        \"" . substr($lesson_text, 0, 15000) . "\"
        
        IMPORTANT RULES:
        1. Generate EXACTLY {$num_questions} questions total
        2. Each multiple-choice question must have exactly 4 options
        3. Only ONE option should be marked as correct for MCQ
        4. Questions should be relevant to the lesson content
        5. Vary the question topics to cover different aspects of the lesson
        6. Make questions clear and unambiguous
        7. For {$difficulty} difficulty, ensure questions match the complexity level
        
        IMPORTANT: Respond with ONLY a valid JSON object, no additional text.
        The JSON format must be:
        {
          \"questions\": [
            {
              \"type\": \"mcq\",
              \"question_text\": \"What is the capital of France?\",
              \"options\": [
                {\"text\": \"Paris\", \"is_correct\": true},
                {\"text\": \"London\", \"is_correct\": false},
                {\"text\": \"Berlin\", \"is_correct\": false},
                {\"text\": \"Madrid\", \"is_correct\": false}
              ]
            },
            {
              \"type\": \"tf\",
              \"question_text\": \"The sky is blue.\",
              \"is_correct\": true
            }
          ]
        }
    ";

    // 8. Call the Gemini API with error handling
    try {
        $ai_response_json = callGemini($prompt, 'json');
        
        if (empty($ai_response_json)) {
            throw new Exception("AI API returned empty response. Please try again.");
        }
    } catch (Exception $api_error) {
        throw new Exception("Failed to generate quiz: " . $api_error->getMessage());
    }

    // 9. Parse the AI's JSON response
    $quiz_data = json_decode($ai_response_json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("AI returned invalid JSON format. JSON Error: " . json_last_error_msg());
    }

    if (!isset($quiz_data['questions']) || !is_array($quiz_data['questions'])) {
        throw new Exception("AI response missing 'questions' array. Response: " . substr($ai_response_json, 0, 500));
    }

    // Validate question count matches request
    $actual_count = count($quiz_data['questions']);
    if ($actual_count != $num_questions) {
        throw new Exception("AI generated {$actual_count} questions but {$num_questions} were requested. Please try again.");
    }

    // Validate each question structure
    foreach ($quiz_data['questions'] as $index => $q) {
        if (!isset($q['type']) || !isset($q['question_text'])) {
            throw new Exception("Question " . ($index + 1) . " is missing required fields (type or question_text).");
        }
        
        if ($q['type'] == 'mcq') {
            if (!isset($q['options']) || !is_array($q['options']) || count($q['options']) != 4) {
                throw new Exception("Question " . ($index + 1) . " (MCQ) must have exactly 4 options.");
            }
            $correct_count = 0;
            foreach ($q['options'] as $opt) {
                if (!isset($opt['text']) || !isset($opt['is_correct'])) {
                    throw new Exception("Question " . ($index + 1) . " has invalid option structure.");
                }
                if ($opt['is_correct']) $correct_count++;
            }
            if ($correct_count != 1) {
                throw new Exception("Question " . ($index + 1) . " (MCQ) must have exactly one correct answer.");
            }
        } elseif ($q['type'] == 'tf') {
            if (!isset($q['is_correct']) || !is_bool($q['is_correct'])) {
                throw new Exception("Question " . ($index + 1) . " (TF) must have a boolean is_correct field.");
            }
        } else {
            throw new Exception("Question " . ($index + 1) . " has invalid type: " . $q['type']);
        }
    }

    // --- 8. Save the Quiz to the Database ---

    // Get allow_retake from session options (default to 1 if not set for backward compatibility)
    $allow_retake = isset($_SESSION['quiz_gen_options']['allow_retake']) ? intval($_SESSION['quiz_gen_options']['allow_retake']) : 1;

    // a. Create the main quiz entry (as a 'draft')
    $quiz_title = "Quiz for: " . $lesson_title;
    $stmt_quiz = $conn->prepare("INSERT INTO quizzes (lesson_id, title, status, allow_retake) VALUES (?, ?, 'draft', ?)");
    $stmt_quiz->bind_param("isi", $lesson_id, $quiz_title, $allow_retake);
    $stmt_quiz->execute();
    $new_quiz_id = $stmt_quiz->insert_id; // Get the ID of the quiz we just created
    $stmt_quiz->close();

    // b. Loop through and save each question
    foreach ($quiz_data['questions'] as $q) {
        $stmt_question = $conn->prepare("INSERT INTO questions (quiz_id, question_text, question_type) VALUES (?, ?, ?)");
        $stmt_question->bind_param("iss", $new_quiz_id, $q['question_text'], $q['type']);
        $stmt_question->execute();
        $new_question_id = $stmt_question->insert_id; // Get ID of the question
        $stmt_question->close();

        // c. Save the answers for this question
        if ($q['type'] == 'mcq') {
            foreach ($q['options'] as $option) {
                $is_correct_int = $option['is_correct'] ? 1 : 0; // Convert boolean to 1 or 0
                $stmt_answer = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                $stmt_answer->bind_param("isi", $new_question_id, $option['text'], $is_correct_int);
                $stmt_answer->execute();
                $stmt_answer->close();
            }
        } elseif ($q['type'] == 'tf') {
            // For True/False, we store two answer records: "True" and "False"
            $is_true_correct = $q['is_correct'] ? 1 : 0;
            $is_false_correct = $q['is_correct'] ? 0 : 1;

            // Save "True" option
            $stmt_ans_true = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, 'True', ?)");
            $stmt_ans_true->bind_param("ii", $new_question_id, $is_true_correct);
            $stmt_ans_true->execute();
            $stmt_ans_true->close();

            // Save "False" option
            $stmt_ans_false = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, 'False', ?)");
            $stmt_ans_false->bind_param("ii", $new_question_id, $is_false_correct);
            $stmt_ans_false->execute();
            $stmt_ans_false->close();
        }
    }

    // 9. If everything worked, commit the transaction
    $conn->commit();
    
    // 10. Check if this is an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        // Return JSON response for AJAX
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Quiz generated successfully!'
        ]);
        exit;
    }
    
    // Otherwise, redirect to quizzes page with success message
    header("Location: quizzes.php?generated=success");
    exit;

} catch (Exception $e) {
    // 11. If *anything* failed, roll back all database changes
    $conn->rollback();
    
    error_log("Quiz Generation Failed: " . $e->getMessage());
    
    // Check if this is an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        // Return JSON response for AJAX
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
    
    // Otherwise, redirect to quizzes page with error
    header("Location: quizzes.php?generated_error=" . urlencode($e->getMessage()));
    exit;
}
?>