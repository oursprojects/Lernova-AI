<?php
/*
 * LernovaAI - View Subject Page (Student)
 * Shows lessons and quizzes for an enrolled subject.
 */

// 1. Include the header
require_once '../includes/student_header.php';
require_once '../config/db.php';

$student_id = $_SESSION['user_id'];
$message = '';
$error = '';

// 2. Get Subject ID and verify enrollment
if (!isset($_GET['id'])) {
    header("Location: index.php?error=no_id");
    exit;
}
$subject_id = intval($_GET['id']);

$stmt_check = $conn->prepare("
    SELECT s.id, s.name 
    FROM subjects s
    JOIN enrollments e ON s.id = e.subject_id
    WHERE s.id = ? AND e.student_id = ?
");
$stmt_check->bind_param("ii", $subject_id, $student_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
if ($result_check->num_rows == 0) {
    header("Location: index.php?error=permission_denied");
    exit;
}
$subject = $result_check->fetch_assoc();
$stmt_check->close();

// --- 3. Fetch all PUBLISHED quizzes for THIS subject with retake info ---
$quizzes = [];
$stmt_q = $conn->prepare("
    SELECT 
        q.id, 
        q.title, 
        q.allow_retake,
        (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS question_count,
        (SELECT COUNT(*) FROM student_attempts WHERE quiz_id = q.id AND student_id = ? AND (hidden_from_student = 0 OR hidden_from_student IS NULL)) AS attempt_count
    FROM quizzes q
    JOIN lessons l ON q.lesson_id = l.id
    WHERE l.subject_id = ? AND q.status = 'published'
    ORDER BY q.created_at DESC
");
$stmt_q->bind_param("ii", $student_id, $subject_id);
$stmt_q->execute();
$result_quizzes = $stmt_q->get_result();
if ($result_quizzes->num_rows > 0) {
    while($row = $result_quizzes->fetch_assoc()) {
        // Check if student can take/retake this quiz
        $row['can_take'] = ($row['attempt_count'] == 0) || ($row['allow_retake'] == 1);
        $row['attempt_count'] = intval($row['attempt_count']);
        $quizzes[] = $row;
    }
}
$stmt_q->close();

// --- 4. Fetch all lessons for THIS subject ---
$lessons = [];
$stmt_l = $conn->prepare("SELECT id, title, file_path FROM lessons WHERE subject_id = ? ORDER BY title ASC");
$stmt_l->bind_param("i", $subject_id);
$stmt_l->execute();
$result_lessons = $stmt_l->get_result();
if ($result_lessons->num_rows > 0) {
    while($row = $result_lessons->fetch_assoc()) {
        $lessons[] = $row;
    }
}
$stmt_l->close();
$conn->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-0"><i class="bi bi-book-fill text-primary"></i> <?php echo htmlspecialchars($subject['name']); ?></h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;"><i class="bi bi-info-circle me-1"></i> Access quizzes, lessons, and study materials</p>
    </div>
    <a href="index.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="bi bi-clipboard-check"></i> Available Quizzes</h3>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th><i class="bi bi-card-text"></i> Quiz Title</th>
                    <th><i class="bi bi-list-ol"></i> Questions</th>
                    <th><i class="bi bi-gear"></i> Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($quizzes)): ?>
                    <tr>
                        <td colspan="3" class="text-center py-4">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p class="mb-0">No published quizzes for this subject yet.</p>
                            </div>
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
                                <span class="badge bg-info">
                                    <i class="bi bi-list-ol"></i> <?php echo $quiz['question_count']; ?> questions
                                </span>
                            </td>
                            <td>
                                <?php if ($quiz['can_take']): ?>
                                    <?php if ($quiz['attempt_count'] > 0): ?>
                                        <!-- Quiz has been taken - show Retake button -->
                                        <a href="take_quiz.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="bi bi-arrow-repeat"></i> Retake Quiz
                                        </a>
                                    <?php else: ?>
                                        <!-- Quiz has not been taken - show Take button -->
                                        <a href="take_quiz.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-success">
                                            <i class="bi bi-pencil-square"></i> Take Quiz
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <!-- Quiz completed and retake not allowed -->
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-check-circle-fill"></i> Already Completed
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="bi bi-file-earmark-text"></i> Lesson Materials & Reviewers</h3>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th><i class="bi bi-file-earmark-text"></i> Lesson Title</th>
                    <th><i class="bi bi-gear"></i> Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lessons)): ?>
                    <tr>
                        <td colspan="2" class="text-center py-4">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p class="mb-0">No lessons uploaded for this subject yet.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($lessons as $lesson): ?>
                        <tr>
                            <td>
                                <i class="bi bi-file-earmark-text text-primary me-2"></i>
                                <strong><?php echo htmlspecialchars($lesson['title']); ?></strong>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <button type="button" 
                                            class="btn btn-sm btn-info view-pdf-btn" 
                                            data-pdf-url="../<?php echo htmlspecialchars($lesson['file_path']); ?>"
                                            data-pdf-title="<?php echo htmlspecialchars($lesson['title']); ?>">
                                        <i class="bi bi-eye-fill"></i> View PDF
                                    </button>
                                    <a href="generate_reviewer.php?lesson_id=<?php echo $lesson['id']; ?>" 
                                       class="btn btn-sm btn-primary generate-reviewer-btn" 
                                       data-lesson-id="<?php echo $lesson['id']; ?>">
                                        <i class="bi bi-magic"></i> <span class="btn-text">Generate Reviewer</span>
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

<!-- PDF Viewer Modal -->
<div class="modal fade" id="pdfViewerModal" tabindex="-1" aria-labelledby="pdfViewerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pdfViewerModalLabel"><i class="bi bi-file-earmark-pdf"></i> PDF Viewer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="pdfViewerFrame" src="" style="width: 100%; height: 100%; min-height: 600px; border: none;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // PDF Viewer Modal
    const pdfModal = new bootstrap.Modal(document.getElementById('pdfViewerModal'));
    const pdfFrame = document.getElementById('pdfViewerFrame');
    const pdfModalTitle = document.getElementById('pdfViewerModalLabel');
    
    document.querySelectorAll('.view-pdf-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const pdfUrl = this.getAttribute('data-pdf-url');
            const pdfTitle = this.getAttribute('data-pdf-title') || 'PDF Viewer';
            pdfFrame.src = pdfUrl;
            pdfModalTitle.innerHTML = '<i class="bi bi-file-earmark-pdf"></i> ' + pdfTitle;
            pdfModal.show();
        });
    });
    
    // Clear iframe when modal is hidden
    document.getElementById('pdfViewerModal').addEventListener('hidden.bs.modal', function() {
        pdfFrame.src = '';
    });
    
    const generateButtons = document.querySelectorAll('.generate-reviewer-btn');
    const allActionButtons = document.querySelectorAll('.btn');
    const sidebarLinks = document.querySelectorAll('.admin-nav a');
    
    function disableSidebar() {
        sidebarLinks.forEach(function(link) {
            link.style.pointerEvents = 'none';
            link.style.opacity = '0.5';
            link.style.cursor = 'not-allowed';
        });
    }
    
    function enableSidebar() {
        sidebarLinks.forEach(function(link) {
            link.style.pointerEvents = 'auto';
            link.style.opacity = '1';
            link.style.cursor = 'pointer';
        });
    }
    
    generateButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            // Prevent multiple clicks
            if (this.classList.contains('generating')) {
                e.preventDefault();
                return false;
            }
            
            // Mark as generating
            this.classList.add('generating');
            this.disabled = true;
            
            // Update button text with spinner
            const btnText = this.querySelector('.btn-text');
            if (btnText) {
                btnText.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Generating...';
            } else {
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Generating...';
            }
            
            // Disable all other buttons on the page
            allActionButtons.forEach(function(btn) {
                if (btn !== button) {
                    btn.disabled = true;
                    btn.style.pointerEvents = 'none';
                    btn.style.opacity = '0.6';
                }
            });
            
            // Disable sidebar navigation
            disableSidebar();
            
            // Allow navigation to proceed
            // The button will remain disabled until page reloads
        });
    });
});
</script>

<style>
.generate-reviewer-btn.generating {
    cursor: not-allowed;
    opacity: 0.7;
    pointer-events: none;
}

.spinner-border-sm {
    width: 0.875rem;
    height: 0.875rem;
    border-width: 0.15em;
}
</style>

<?php
// 3. Include the footer
require_once '../includes/student_footer.php';
?>