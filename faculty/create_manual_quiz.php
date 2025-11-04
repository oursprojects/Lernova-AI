<?php
/*
 * LernovaAI - Create Manual Quiz Page
 * Step 1: Select Subject and Lesson
 */

// Start session and check authentication BEFORE any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Faculty Security Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    header("Location: ../login.php");
    exit;
}

// Include database connection
require_once '../config/db.php';

$faculty_id = $_SESSION['user_id'];

// --- Handle Form Submission FIRST (before any output) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['lesson_id'])) {
    $lesson_id = intval($_POST['lesson_id']);
    $quiz_title = $conn->real_escape_string($_POST['quiz_title']);

    if (empty($lesson_id) || empty($quiz_title)) {
        // Store error in session to display after redirect
        $_SESSION['quiz_error'] = "You must select a lesson and provide a quiz title.";
        header("Location: create_manual_quiz.php" . (isset($_GET['lesson_id']) ? "?lesson_id=" . intval($_GET['lesson_id']) : ""));
        exit;
    } else {
        // Verify this lesson belongs to the faculty
        $stmt_check = $conn->prepare("
            SELECT l.id FROM lessons l
            JOIN subjects s ON l.subject_id = s.id
            WHERE l.id = ? AND s.faculty_id = ?
        ");
        $stmt_check->bind_param("ii", $lesson_id, $faculty_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows == 1) {
            // Create the new blank quiz (as a 'draft')
            $stmt_quiz = $conn->prepare("INSERT INTO quizzes (lesson_id, title, status, allow_retake) VALUES (?, ?, 'draft', 1)");
            $stmt_quiz->bind_param("is", $lesson_id, $quiz_title);
            $stmt_quiz->execute();
            $new_quiz_id = $stmt_quiz->insert_id; // Get the ID of the quiz
            $stmt_quiz->close();
            $stmt_check->close();
            $conn->close();

            // Redirect to the editor to add questions
            header("Location: edit_quiz.php?quiz_id=" . $new_quiz_id . "&new=true");
            exit;
        } else {
            $stmt_check->close();
            $_SESSION['quiz_error'] = "Invalid lesson selected.";
            header("Location: create_manual_quiz.php" . (isset($_GET['lesson_id']) ? "?lesson_id=" . intval($_GET['lesson_id']) : ""));
            exit;
        }
    }
}

// Now include the header (after POST handling is done)
require_once '../includes/faculty_header.php';

$error = '';
// Get error from session if it exists
if (isset($_SESSION['quiz_error'])) {
    $error = $_SESSION['quiz_error'];
    unset($_SESSION['quiz_error']);
}

$pre_selected_lesson_id = isset($_GET['lesson_id']) ? intval($_GET['lesson_id']) : 0;
$pre_selected_subject_id = 0;

// If lesson_id is provided, get its subject_id
if ($pre_selected_lesson_id > 0) {
    $stmt_pre = $conn->prepare("
        SELECT s.id as subject_id
        FROM lessons l
        JOIN subjects s ON l.subject_id = s.id
        WHERE l.id = ? AND s.faculty_id = ?
    ");
    $stmt_pre->bind_param("ii", $pre_selected_lesson_id, $faculty_id);
    $stmt_pre->execute();
    $result_pre = $stmt_pre->get_result();
    if ($result_pre->num_rows > 0) {
        $pre_selected_subject_id = $result_pre->fetch_assoc()['subject_id'];
    }
    $stmt_pre->close();
}


// --- Fetch all subjects for THIS faculty member ---
$subjects = [];
$stmt_subjects = $conn->prepare("SELECT id, name FROM subjects WHERE faculty_id = ? ORDER BY name ASC");
$stmt_subjects->bind_param("i", $faculty_id);
$stmt_subjects->execute();
$result_subjects = $stmt_subjects->get_result();

if ($result_subjects->num_rows > 0) {
    while($row = $result_subjects->fetch_assoc()) {
        $subjects[] = $row;
    }
}
$stmt_subjects->close();
$conn->close();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="mb-0"><i class="bi bi-pencil-square"></i> Create Manual Quiz</h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;">Select the subject and lesson this quiz will be for</p>
    </div>
    <a href="quizzes.php" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back to Quizzes
    </a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 600px;">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="bi bi-card-text"></i> Quiz Details</h3>
    </div>
    <div class="card-body">
        <form action="create_manual_quiz.php" method="POST">
            
            <div class="mb-3">
                <label for="subject_id" class="form-label">
                    <i class="bi bi-book text-primary"></i> 1. Select Subject
                </label>
                <select id="subject_id" name="subject_id" class="form-select" required>
                    <option value="">-- Select a Subject --</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="lesson_id" class="form-label">
                    <i class="bi bi-file-earmark-text text-primary"></i> 2. Select Lesson
                </label>
                <select id="lesson_id" name="lesson_id" class="form-select" required disabled>
                    <option value="">-- Select a subject first --</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="quiz_title" class="form-label">
                    <i class="bi bi-bookmark text-primary"></i> 3. Quiz Title
                </label>
                <input type="text" id="quiz_title" name="quiz_title" class="form-control" required 
                       placeholder="e.g., 'Chapter 1 Review Quiz'">
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success" id="create_btn" disabled>
                    <i class="bi bi-plus-circle"></i> Create Quiz and Add Questions
                </button>
                <a href="quizzes.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const subjectSelect = document.getElementById('subject_id');
    const lessonSelect = document.getElementById('lesson_id');
    const createBtn = document.getElementById('create_btn');
    const preSelectedLessonId = <?php echo $pre_selected_lesson_id; ?>;
    const preSelectedSubjectId = <?php echo $pre_selected_subject_id; ?>;
    
    // Function to load lessons for a subject
    function loadLessons(subjectId, callback) {
        lessonSelect.innerHTML = '<option value="">-- Loading lessons... --</option>';
        lessonSelect.disabled = true;
        createBtn.disabled = true;

        if (subjectId) {
            fetch('api_get_lessons.php?subject_id=' + subjectId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.lessons.length > 0) {
                        lessonSelect.innerHTML = '<option value="">-- Select a lesson --</option>';
                        data.lessons.forEach(lesson => {
                            lessonSelect.innerHTML += `<option value="${lesson.id}">${lesson.title}</option>`;
                        });
                        lessonSelect.disabled = false;
                        
                        // Call callback if provided (for auto-selection)
                        if (callback && typeof callback === 'function') {
                            callback();
                        }
                    } else {
                        lessonSelect.innerHTML = '<option value="">-- No lessons found for this subject --</option>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching lessons:', error);
                    lessonSelect.innerHTML = '<option value="">-- Error loading lessons --</option>';
                });
        } else {
            lessonSelect.innerHTML = '<option value="">-- Select a subject first --</option>';
        }
    }
    
    // If both are provided, auto-populate the form
    if (preSelectedLessonId > 0 && preSelectedSubjectId > 0) {
        subjectSelect.value = preSelectedSubjectId;
        
        // Load lessons and auto-select the lesson when done
        loadLessons(preSelectedSubjectId, function() {
            // Select the lesson after lessons are loaded
            if (lessonSelect.querySelector(`option[value="${preSelectedLessonId}"]`)) {
                lessonSelect.value = preSelectedLessonId;
                lessonSelect.dispatchEvent(new Event('change'));
            }
        });
    }
    
    subjectSelect.addEventListener('change', function() {
        loadLessons(this.value);
    });

    // Enable create button only when a lesson is selected
    lessonSelect.addEventListener('change', function() {
        if (this.value) {
            createBtn.disabled = false;
        } else {
            createBtn.disabled = true;
        }
    });
});
</script>

<?php
// 3. Include the footer
require_once '../includes/faculty_footer.php';
?>