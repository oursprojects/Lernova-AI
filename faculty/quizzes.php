<?php
/*
 * LernovaAI - Manage Quizzes Page (Updated for Subjects)
 * Lists all quizzes created by the faculty.
 */

// 1. Include the header
require_once '../includes/faculty_header.php';
require_once '../config/db.php';

$faculty_id = $_SESSION['user_id'];
$message = '';
$error = '';

if (isset($_GET['generated']) && $_GET['generated'] == 'success') {
    $message = "Quiz generated successfully!";
}
if (isset($_GET['generated_error'])) {
    $error = "Error generating quiz: " . htmlspecialchars($_GET['generated_error']);
}

// --- Handle Publishing/Unpublishing/Deleting ---
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $quiz_id = intval($_GET['quiz_id']);
    
    // --- UPDATED OWNERSHIP CHECK ---
    // Check if this quiz belongs to a lesson in a subject owned by this faculty
    $stmt_check = $conn->prepare("
        SELECT q.id 
        FROM quizzes q
        JOIN lessons l ON q.lesson_id = l.id
        JOIN subjects s ON l.subject_id = s.id
        WHERE q.id = ? AND s.faculty_id = ?
    ");
    $stmt_check->bind_param("ii", $quiz_id, $faculty_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows == 1) {
        if ($action == 'publish') {
            $stmt_update = $conn->prepare("UPDATE quizzes SET status = 'published' WHERE id = ?");
            $stmt_update->bind_param("i", $quiz_id);
            $stmt_update->execute();
            $message = "Quiz published successfully.";
        } 
        elseif ($action == 'unpublish') {
            $stmt_update = $conn->prepare("UPDATE quizzes SET status = 'draft' WHERE id = ?");
            $stmt_update->bind_param("i", $quiz_id);
            $stmt_update->execute();
            $message = "Quiz unpublished and set to draft.";
        }
        elseif ($action == 'delete') {
            $stmt_delete = $conn->prepare("DELETE FROM quizzes WHERE id = ?");
            $stmt_delete->bind_param("i", $quiz_id);
            $stmt_delete->execute();
            $message = "Quiz and all its questions deleted successfully.";
        }
    } else {
        $error = "Invalid quiz or permission denied.";
    }
}


// --- Fetch all quizzes for this faculty (UPDATED QUERY) ---
$quizzes = [];
$sql_quizzes = "
    SELECT 
        q.id, 
        q.title, 
        q.status, 
        q.created_at, 
        l.title AS lesson_title,
        s.name AS subject_name
    FROM quizzes q
    JOIN lessons l ON q.lesson_id = l.id
    JOIN subjects s ON l.subject_id = s.id
    WHERE s.faculty_id = ?
    ORDER BY q.created_at DESC
";
$stmt = $conn->prepare($sql_quizzes);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result_quizzes = $stmt->get_result();

if ($result_quizzes->num_rows > 0) {
    while($row = $result_quizzes->fetch_assoc()) {
        $quizzes[] = $row;
    }
}
$stmt->close();

// Fetch lessons without quizzes for AI generation
$lessons_no_quiz = [];
$sql_lessons = "
    SELECT l.id, l.title, s.name AS subject_name
    FROM lessons l
    JOIN subjects s ON l.subject_id = s.id
    WHERE s.faculty_id = ? 
    AND l.id NOT IN (SELECT lesson_id FROM quizzes)
    ORDER BY l.upload_date DESC
";
$stmt_lessons = $conn->prepare($sql_lessons);
$stmt_lessons->bind_param("i", $faculty_id);
$stmt_lessons->execute();
$result_lessons = $stmt_lessons->get_result();

if ($result_lessons->num_rows > 0) {
    while($row = $result_lessons->fetch_assoc()) {
        $lessons_no_quiz[] = $row;
    }
}
$stmt_lessons->close();
$conn->close();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="mb-0"><i class="bi bi-clipboard-check"></i> Manage Quizzes</h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;">Generate quizzes from lessons, review, edit, publish, and delete all quizzes</p>
    </div>
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

<?php if (!empty($lessons_no_quiz)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title"><i class="bi bi-magic"></i> Generate Quiz from Lessons</h3>
        <p class="text-muted mb-0" style="font-size: 0.875rem;">Select a lesson to generate an AI quiz or create a manual quiz</p>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th><i class="bi bi-bookmark"></i> Lesson Title</th>
                    <th><i class="bi bi-book"></i> Subject</th>
                    <th><i class="bi bi-gear"></i> Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lessons_no_quiz as $lesson): ?>
                    <tr>
                        <td>
                            <i class="bi bi-file-earmark-text text-primary me-2"></i>
                            <strong><?php echo htmlspecialchars($lesson['title']); ?></strong>
                        </td>
                        <td>
                            <i class="bi bi-bookmark text-secondary me-2"></i>
                            <?php echo htmlspecialchars($lesson['subject_name']); ?>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="quiz_generation_options.php?lesson_id=<?php echo $lesson['id']; ?>" 
                                   class="btn btn-sm btn-primary">
                                   <i class="bi bi-magic"></i> Generate Quiz
                                </a>
                                <a href="create_manual_quiz.php?lesson_id=<?php echo $lesson['id']; ?>" 
                                   class="btn btn-sm btn-success">
                                   <i class="bi bi-pencil"></i> Manual Quiz
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title"><i class="bi bi-list-ul"></i> All Quizzes</h3>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th><i class="bi bi-card-text"></i> Quiz Title</th>
                    <th><i class="bi bi-book"></i> Subject</th>
                    <th><i class="bi bi-file-earmark-text"></i> Based on Lesson</th>
                    <th><i class="bi bi-info-circle"></i> Status</th>
                    <th><i class="bi bi-calendar3"></i> Created</th>
                    <th><i class="bi bi-gear"></i> Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($quizzes)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <i class="bi bi-inbox" style="font-size: 2rem; color: #9CA3AF; display: block; margin-bottom: 0.5rem;"></i>
                            <span class="text-muted">You have not created any quizzes yet.</span>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($quizzes as $quiz): ?>
                        <tr>
                            <td>
                                <i class="bi bi-clipboard-check text-primary me-2"></i>
                                <strong><?php echo htmlspecialchars($quiz['title']); ?></strong>
                            </td>
                            <td>
                                <i class="bi bi-bookmark text-secondary me-2"></i>
                                <?php echo htmlspecialchars($quiz['subject_name']); ?>
                            </td>
                            <td>
                                <i class="bi bi-file-earmark-text text-info me-2"></i>
                                <?php echo htmlspecialchars($quiz['lesson_title']); ?>
                            </td>
                            <td>
                                <?php if ($quiz['status'] == 'published'): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle-fill"></i> Published
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-pencil-square"></i> Draft
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <i class="bi bi-calendar3 text-muted me-2"></i>
                                <small><?php echo date("M j, Y", strtotime($quiz['created_at'])); ?></small>
                            </td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="edit_quiz.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    
                                    <?php if ($quiz['status'] == 'draft'): ?>
                                        <a href="quizzes.php?action=publish&quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-success">
                                            <i class="bi bi-check-circle"></i> Publish
                                        </a>
                                    <?php else: ?>
                                        <a href="quizzes.php?action=unpublish&quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="bi bi-x-circle"></i> Unpublish
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="quizzes.php?action=delete&quiz_id=<?php echo $quiz['id']; ?>" 
                                       class="btn btn-sm btn-danger delete-btn"
                                       data-message="Are you sure you want to delete this quiz? This will permanently delete all questions and answers."
                                       data-href="quizzes.php?action=delete&quiz_id=<?php echo $quiz['id']; ?>">
                                       <i class="bi bi-trash"></i> Delete
                                    </a>
                                </div>
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
require_once '../includes/faculty_footer.php';
?>