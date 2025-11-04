<?php
/*
 * LernovaAI - Generate Reviewer Page (Markdown Enabled + SAVES)
 * Generates and saves AI-powered summaries from lessons.
 */

// 1. Include the header, DB, and Gemini Service
require_once '../includes/student_header.php';
require_once '../config/db.php';
require_once '../config/gemini.php'; // We need this for the AI call
require_once '../vendor/autoload.php'; // Load Composer libraries (for Parsedown)

// Get Student ID from session (defined in header)
$student_id = $_SESSION['user_id'];

// 2. Initialize variables
$reviewer_html = '';
$lesson_title = '';
$error = '';
$is_loading = false; 

// --- 3. Check if a Lesson ID was provided in the URL ---
if (isset($_GET['lesson_id'])) {
    $lesson_id = intval($_GET['lesson_id']);
    $is_loading = true; 

    try {
        // a. Fetch the lesson text from the database
        // We must also check if the student is ENROLLED in the subject for this lesson
        $stmt_check = $conn->prepare("
            SELECT l.title, l.extracted_text FROM lessons l
            JOIN subjects s ON l.subject_id = s.id
            JOIN enrollments e ON s.id = e.subject_id
            WHERE l.id = ? AND e.student_id = ?
        ");
        $stmt_check->bind_param("ii", $lesson_id, $student_id);
        $stmt_check->execute();
        $result_lesson = $stmt_check->get_result();

        if ($result_lesson->num_rows == 0) {
            throw new Exception("Lesson not found or you are not enrolled in this subject.");
        }

        $lesson = $result_lesson->fetch_assoc();
        $lesson_title = $lesson['title'];
        $lesson_text = $lesson['extracted_text'];
        $stmt_check->close();
        
        if (empty(trim($lesson_text))) {
            throw new Exception("This lesson's file has no extractable text.");
        }

        // c. Define the new AI Prompt (asking for MARKDOWN)
        $prompt = "
            You are a helpful study assistant.
            Based on the following lesson text, generate a clear and concise reviewer for a university student.
            LESSON TEXT:
            \"" . $lesson_text . "\"
            IMPORTANT: Respond with ONLY the reviewer text, formatted using **Markdown**.
            Use headings (##), bold text (**bold**), and bullet points (*) for clarity.
        ";

        // d. Call the Gemini API (requesting 'text' format)
        $raw_reviewer_markdown = callGemini($prompt, 'text');
        
        // e. Convert the AI's Markdown response to HTML
        $Parsedown = new Parsedown();
        $reviewer_html = $Parsedown->text($raw_reviewer_markdown);

        // --- 4. NEW: Save the reviewer to the database ---
        $stmt_save = $conn->prepare("
            INSERT INTO student_reviewers (student_id, lesson_id, lesson_title, reviewer_html) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt_save->bind_param("iiss", $student_id, $lesson_id, $lesson_title, $reviewer_html);
        $stmt_save->execute();
        $stmt_save->close();
        // --- End of new save logic ---

    } catch (Exception $e) {
        $error = "Error generating reviewer: " . $e->getMessage();
    }
    
    $is_loading = false; // Generation finished
}

// --- 5. Fetch All Available Lessons for the list (from ENROLLED subjects) ---
$lessons = [];
$sql_lessons = "
    SELECT l.id, l.title, l.file_path, CONCAT(u.first_name, ' ', u.last_name) AS faculty_name
    FROM lessons l
    JOIN subjects s ON l.subject_id = s.id
    JOIN users u ON s.faculty_id = u.id
    JOIN enrollments e ON s.id = e.subject_id
    WHERE e.student_id = ?
    ORDER BY l.title ASC
";
$stmt_lessons = $conn->prepare($sql_lessons);
$stmt_lessons->bind_param("i", $student_id);
$stmt_lessons->execute();
$result_lessons = $stmt_lessons->get_result();

if ($result_lessons->num_rows > 0) {
    while ($row = $result_lessons->fetch_assoc()) {
        $lessons[] = $row;
    }
}
$stmt_lessons->close();
$conn->close();
?>

<style>
    .reviewer-card { background-color: #fdfdfd; border: 1px solid #e0e0e0; line-height: 1.6; }
    .reviewer-body { padding: 25px; font-size: 1.1em; }
    .reviewer-body h2 { margin-top: 20px; margin-bottom: 15px; border-bottom: 2px solid #eee; padding-bottom: 5px; }
    .reviewer-body ul { padding-left: 25px; }
    .reviewer-body li { margin-bottom: 8px; }
    .loading-card { 
        text-align: center; 
        padding: 2rem; 
        font-size: 1rem; 
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: 2px solid #dee2e6;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-0"><i class="bi bi-magic text-primary"></i> AI Reviewer Generator</h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;"><i class="bi bi-info-circle me-1"></i> Select a lesson from your enrolled subjects to generate an AI-powered summary</p>
    </div>
    <a href="index.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
</div>

<?php if ($is_loading): ?>
    <div class="card loading-card">
        <div class="d-flex align-items-center justify-content-center gap-3">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div>
                <strong>🤖 Generating your reviewer...</strong>
                <p class="mb-0 text-muted" style="font-size: 0.875rem;">This may take a moment. Please wait...</p>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($reviewer_html)): ?>
    <div class="card reviewer-card shadow-sm mb-4">
        <div class="card-header bg-success text-white">
            <h3 class="mb-0"><i class="bi bi-check-circle-fill me-2"></i> Reviewer Generated Successfully!</h3>
        </div>
        <div class="reviewer-body">
            <div class="alert alert-info mb-3">
                <i class="bi bi-info-circle-fill me-2"></i><strong>Reviewer saved!</strong> This reviewer has been saved to your <a href="my_reviewers.php" class="alert-link">My Reviewers</a> page.
            </div>
            <h2><i class="bi bi-file-earmark-text text-primary me-2"></i>Reviewer for: <?php echo htmlspecialchars($lesson_title); ?></h2>
            <hr>
            <?php echo $reviewer_html; // This is the AI-generated HTML ?>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="bi bi-list-ul"></i> Select a Lesson</h3>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th><i class="bi bi-file-earmark-text"></i> Lesson Title</th>
                    <th><i class="bi bi-mortarboard"></i> Prepared by</th>
                    <th><i class="bi bi-gear"></i> Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lessons)): ?>
                    <tr>
                        <td colspan="3" class="text-center py-4">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p class="mb-0">You are not enrolled in any subjects, or your subjects have no lessons.</p>
                                <a href="index.php" class="btn btn-sm btn-primary mt-2">
                                    <i class="bi bi-arrow-left"></i> Go to Dashboard
                                </a>
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
                                <i class="bi bi-mortarboard text-info me-2"></i>
                                <?php echo htmlspecialchars($lesson['faculty_name']); ?>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
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
    
    // If page is loading (generating), disable all buttons and sidebar
    <?php if ($is_loading): ?>
    allActionButtons.forEach(function(btn) {
        btn.disabled = true;
        btn.style.pointerEvents = 'none';
        btn.style.opacity = '0.6';
    });
    
    // Update generate buttons to show generating state
    generateButtons.forEach(function(btn) {
        btn.classList.add('generating');
        const btnText = btn.querySelector('.btn-text');
        if (btnText) {
            btnText.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Generating...';
        }
    });
    
    // Disable sidebar navigation while generating
    disableSidebar();
    <?php endif; ?>
    
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
});
</script>

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
// 5. Include the footer
require_once '../includes/student_footer.php';
?>