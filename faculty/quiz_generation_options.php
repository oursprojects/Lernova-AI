<?php
/*
 * LernovaAI - Quiz Generation Options Page
 * Allows faculty to select quiz generation parameters before generating
 */

// Start session and check authentication BEFORE any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in AND is a faculty member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    header("Location: ../login.php");
    exit;
}

// Connect to database
require_once '../config/db.php';

$faculty_id = $_SESSION['user_id'];
$error = '';

// Get Lesson ID from URL
if (!isset($_GET['lesson_id'])) {
    header("Location: quizzes.php?error=No lesson ID provided");
    exit;
}

$lesson_id = intval($_GET['lesson_id']);

// Verify lesson belongs to this faculty
$stmt_check = $conn->prepare("
    SELECT l.id, l.title, l.extracted_text
    FROM lessons l
    JOIN subjects s ON l.subject_id = s.id
    WHERE l.id = ? AND s.faculty_id = ?
");
$stmt_check->bind_param("ii", $lesson_id, $faculty_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows == 0) {
    header("Location: quizzes.php?error=Lesson not found or permission denied");
    exit;
}

$lesson = $result_check->fetch_assoc();
$lesson_title = $lesson['title'];
$lesson_text_length = strlen($lesson['extracted_text']);
$stmt_check->close();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['num_questions'])) {
    $num_questions = intval($_POST['num_questions']);
    $question_type = isset($_POST['question_type']) ? $_POST['question_type'] : 'both';
    $difficulty = isset($_POST['difficulty']) ? $_POST['difficulty'] : 'medium';
    $allow_retake = isset($_POST['allow_retake']) ? 1 : 0; // Checkbox: checked = 1, unchecked = 0
    
    // Validation
    if ($num_questions < 1 || $num_questions > 50) {
        $error = "Number of questions must be between 1 and 50.";
    } elseif (empty(trim($lesson['extracted_text']))) {
        $error = "This lesson has no extractable text. Cannot generate quiz.";
    } else {
        // Store options in session
        $_SESSION['quiz_gen_options'] = [
            'lesson_id' => $lesson_id,
            'num_questions' => $num_questions,
            'question_type' => $question_type,
            'difficulty' => $difficulty,
            'allow_retake' => $allow_retake
        ];
        
        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            // Return JSON response for AJAX
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Quiz options stored successfully'
            ]);
            exit;
        }
        
        // Otherwise, redirect to generation (non-AJAX fallback)
        header("Location: generate_quiz.php");
        exit;
    }
    
    // If validation failed and this is an AJAX request, return error
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $error
        ]);
        exit;
    }
}

$conn->close();

// Now include the header
require_once '../includes/faculty_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-0"><i class="bi bi-gear-fill text-primary"></i> Quiz Generation Options</h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;">
            <i class="bi bi-file-earmark-text"></i> Configure quiz parameters for: <strong><?php echo htmlspecialchars($lesson_title); ?></strong>
        </p>
    </div>
    <a href="quizzes.php" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left-circle"></i> Back to Quizzes
    </a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($lesson_text_length < 100): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Warning:</strong> This lesson has very little text (<i class="bi bi-file-text"></i> <?php echo $lesson_text_length; ?> characters). 
        Quiz generation may produce limited or poor quality questions.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white">
                <h3 class="card-title mb-0">
                    <i class="bi bi-sliders"></i> Quiz Configuration
                </h3>
            </div>
            <div class="card-body p-4">
        <form id="quizOptionsForm">
            
            <!-- Number of Questions -->
            <div class="mb-4">
                <label for="num_questions" class="form-label fw-bold">
                    <i class="bi bi-123 text-primary"></i> Number of Questions
                    <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-light">
                        <i class="bi bi-question-circle text-primary"></i>
                    </span>
                    <input type="number" 
                           class="form-control" 
                           id="num_questions" 
                           name="num_questions" 
                           min="1" 
                           max="50" 
                           value="10" 
                           required
                           placeholder="Enter number of questions">
                    <span class="input-group-text bg-light">1-50</span>
                </div>
                <small class="text-muted d-block mt-2">
                    <i class="bi bi-info-circle"></i> Enter a number between 1 and 50 questions
                </small>
            </div>
            
            <!-- Question Types -->
            <div class="mb-4">
                <label class="form-label fw-bold">
                    <i class="bi bi-check2-square text-primary"></i> Question Types
                    <span class="text-danger">*</span>
                </label>
                <div class="card border-0 bg-light">
                    <div class="card-body p-3">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="question_type" id="type_both" value="both" checked>
                            <label class="form-check-label w-100" for="type_both">
                                <i class="bi bi-collection-fill text-success me-2"></i>
                                <strong>Both Types</strong>
                                <br>
                                <small class="text-muted ms-4">Multiple Choice + True/False questions</small>
                            </label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="question_type" id="type_mcq" value="mcq">
                            <label class="form-check-label w-100" for="type_mcq">
                                <i class="bi bi-list-ul text-info me-2"></i>
                                <strong>Multiple Choice Only</strong>
                                <br>
                                <small class="text-muted ms-4">Generate only multiple choice questions</small>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="question_type" id="type_tf" value="tf">
                            <label class="form-check-label w-100" for="type_tf">
                                <i class="bi bi-toggle-on text-warning me-2"></i>
                                <strong>True/False Only</strong>
                                <br>
                                <small class="text-muted ms-4">Generate only true/false questions</small>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Difficulty Level -->
            <div class="mb-4">
                <label for="difficulty" class="form-label fw-bold">
                    <i class="bi bi-bar-chart-fill text-primary"></i> Difficulty Level
                    <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-light">
                        <i class="bi bi-graph-up text-primary"></i>
                    </span>
                    <select class="form-select" id="difficulty" name="difficulty" required>
                        <option value="easy">Easy - Basic understanding and recall questions</option>
                        <option value="medium" selected>Medium - Application and analysis questions</option>
                        <option value="hard">Hard - Complex synthesis and evaluation questions</option>
                    </select>
                </div>
                <small class="text-muted d-block mt-2">
                    <i class="bi bi-info-circle"></i> This affects the complexity and depth of questions
                </small>
            </div>
            
            <!-- Allow Retake -->
            <div class="mb-4">
                <label class="form-label fw-bold">
                    <i class="bi bi-arrow-repeat text-primary"></i> Retake Options
                </label>
                <div class="card border-0 bg-light">
                    <div class="card-body p-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="allow_retake" id="allow_retake" value="1" checked>
                            <label class="form-check-label w-100" for="allow_retake">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                <strong>Allow students to retake this quiz</strong>
                                <br>
                                <small class="text-muted ms-4">
                                    <i class="bi bi-info-circle"></i> If unchecked, students can only take the quiz once. 
                                    If checked, students can retake the quiz multiple times.
                                </small>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Info Alert -->
            <div class="alert alert-info border-0 shadow-sm">
                <div class="d-flex align-items-start">
                    <i class="bi bi-info-circle-fill me-3 fs-5"></i>
                    <div>
                        <strong><i class="bi bi-clock-history"></i> Generation Time:</strong>
                        <p class="mb-0">Quiz generation may take 30-60 seconds depending on the number of questions and lesson content.</p>
                    </div>
                </div>
            </div>
            
            <!-- Submit Button -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="bi bi-magic"></i> Generate Quiz
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('quizOptionsForm');
    const numQuestionsInput = document.getElementById('num_questions');
    
    // Validate number of questions
    numQuestionsInput.addEventListener('change', function() {
        const value = parseInt(this.value);
        if (value < 1) {
            this.value = 1;
        } else if (value > 50) {
            this.value = 50;
        }
    });
    
    // Show loading state and disable all interactive elements on submit
    form.addEventListener('submit', function(e) {
        e.preventDefault(); // Prevent default form submission
        
        // Get form data
        const formData = new FormData(this);
        formData.append('lesson_id', '<?php echo $lesson_id; ?>');
        
        // Disable submit button
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating Quiz...';
        
        // Disable all form inputs
        const formInputs = this.querySelectorAll('input, select, textarea, button');
        formInputs.forEach(input => {
            input.disabled = true;
        });
        
        // Disable back button
        const backBtn = document.querySelector('a[href="quizzes.php"]');
        if (backBtn) {
            backBtn.style.pointerEvents = 'none';
            backBtn.style.opacity = '0.6';
            backBtn.style.cursor = 'not-allowed';
        }
        
        // Disable all sidebar navigation links
        const sidebarLinks = document.querySelectorAll('.admin-nav a');
        sidebarLinks.forEach(link => {
            if (!link.hasAttribute('disabled')) {
                link.style.pointerEvents = 'none';
                link.style.opacity = '0.6';
                link.style.cursor = 'not-allowed';
            }
        });
        
        // Disable header logout link
        const logoutLink = document.querySelector('header .user-info a[href*="logout"]');
        if (logoutLink) {
            logoutLink.style.pointerEvents = 'none';
            logoutLink.style.opacity = '0.6';
            logoutLink.style.cursor = 'not-allowed';
        }
        
        // Show a loading overlay message
        const loadingOverlay = document.createElement('div');
        loadingOverlay.id = 'loadingOverlay';
        loadingOverlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; display: flex; align-items: center; justify-content: center; flex-direction: column;';
        loadingOverlay.innerHTML = `
            <div class="bg-white p-5 rounded shadow-lg text-center" style="max-width: 400px;">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="mb-2"><i class="bi bi-hourglass-split"></i> Generating Quiz</h5>
                <p class="text-muted mb-0">Please wait while we generate your quiz. This may take 30-60 seconds...</p>
                <div class="progress mt-3" style="height: 6px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                </div>
            </div>
        `;
        document.body.appendChild(loadingOverlay);
        
        // First, store options in session via AJAX
        fetch('quiz_generation_options.php?lesson_id=<?php echo $lesson_id; ?>', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Now call generate_quiz.php via AJAX
                return fetch('generate_quiz.php', {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
            } else {
                throw new Error(data.message || 'Failed to store quiz options');
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            // Remove loading overlay
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.remove();
            
            if (data.success) {
                // Success - redirect to quizzes page
                window.location.href = 'quizzes.php?generated=success';
            } else {
                // Error - show error and re-enable form
                throw new Error(data.message || 'Quiz generation failed');
            }
        })
        .catch(error => {
            // Remove loading overlay
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.remove();
            
            // Re-enable form
            formInputs.forEach(input => {
                input.disabled = false;
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-magic"></i> Generate Quiz';
            
            // Re-enable buttons
            if (backBtn) {
                backBtn.style.pointerEvents = '';
                backBtn.style.opacity = '';
                backBtn.style.cursor = '';
            }
            sidebarLinks.forEach(link => {
                link.style.pointerEvents = '';
                link.style.opacity = '';
                link.style.cursor = '';
            });
            if (logoutLink) {
                logoutLink.style.pointerEvents = '';
                logoutLink.style.opacity = '';
                logoutLink.style.cursor = '';
            }
            
            // Show error message
            showToast('Error: ' + (error.message || 'An error occurred during quiz generation'), 'error');
        });
    });
});
</script>

<?php
require_once '../includes/faculty_footer.php';
?>

