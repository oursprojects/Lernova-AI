<?php
// Start session and security check BEFORE header include
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

$faculty_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle lesson edit (POST request - must be before header)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'edit_lesson') {
    $lesson_id = isset($_POST['lesson_id']) ? intval($_POST['lesson_id']) : 0;
    $new_title = isset($_POST['title']) ? trim($_POST['title']) : '';
    
    if ($lesson_id <= 0) {
        $_SESSION['lesson_error'] = "Invalid lesson ID.";
        header("Location: manage_lessons.php");
        exit;
    }
    
    if (empty($new_title)) {
        $_SESSION['lesson_error'] = "Lesson title cannot be empty.";
        header("Location: manage_lessons.php");
        exit;
    }
    
    // Verify lesson belongs to this faculty
    $stmt_verify = $conn->prepare("
        SELECT l.id, l.file_path 
        FROM lessons l
        JOIN subjects s ON l.subject_id = s.id
        WHERE l.id = ? AND s.faculty_id = ?
    ");
    $stmt_verify->bind_param("ii", $lesson_id, $faculty_id);
    $stmt_verify->execute();
    $result_verify = $stmt_verify->get_result();
    
    if ($result_verify->num_rows == 0) {
        $stmt_verify->close();
        $_SESSION['lesson_error'] = "Permission denied or lesson not found.";
        header("Location: manage_lessons.php");
        exit;
    }
    
    $lesson_data = $result_verify->fetch_assoc();
    $stmt_verify->close();
    
    // Sanitize title
    $new_title = $conn->real_escape_string($new_title);
    
    // Update title in database
    $stmt_update = $conn->prepare("UPDATE lessons SET title = ? WHERE id = ?");
    $stmt_update->bind_param("si", $new_title, $lesson_id);
    
    if ($stmt_update->execute()) {
        $stmt_update->close();
        $conn->close();
        $_SESSION['lesson_message'] = "Lesson updated successfully.";
        header("Location: manage_lessons.php");
        exit;
    } else {
        $stmt_update->close();
        $_SESSION['lesson_error'] = "Error updating lesson: " . $stmt_update->error;
        header("Location: manage_lessons.php");
        exit;
    }
}

// Handle lesson deletion (must be before header)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['lesson_id'])) {
    $lesson_id_to_delete = intval($_GET['lesson_id']);
    
    // Verify lesson belongs to this faculty and get file path
    $stmt_find = $conn->prepare("
        SELECT l.id, l.file_path 
        FROM lessons l
        JOIN subjects s ON l.subject_id = s.id
        WHERE l.id = ? AND s.faculty_id = ?
    ");
    $stmt_find->bind_param("ii", $lesson_id_to_delete, $faculty_id);
    $stmt_find->execute();
    $result_find = $stmt_find->get_result();
    
    if ($result_find->num_rows == 1) {
        $lesson = $result_find->fetch_assoc();
        $file_to_delete = '../' . $lesson['file_path'];
        
        // Delete file from uploads folder if it exists
        if (file_exists($file_to_delete)) {
            if (!unlink($file_to_delete)) {
                $_SESSION['lesson_error'] = "Error deleting file from uploads folder.";
                $stmt_find->close();
                $conn->close();
                header("Location: manage_lessons.php");
                exit;
            }
        }
        
        // Delete from database
        $stmt_delete = $conn->prepare("DELETE FROM lessons WHERE id = ?");
        $stmt_delete->bind_param("i", $lesson_id_to_delete);
        if ($stmt_delete->execute()) {
            $stmt_delete->close();
            $stmt_find->close();
            $conn->close();
            $_SESSION['lesson_message'] = "Lesson deleted successfully.";
            header("Location: manage_lessons.php");
            exit;
        } else {
            $stmt_delete->close();
            $_SESSION['lesson_error'] = "Error deleting lesson from database.";
        }
        $stmt_delete->close();
    } else {
        $_SESSION['lesson_error'] = "Permission denied or lesson not found.";
    }
    $stmt_find->close();
    $conn->close();
    header("Location: manage_lessons.php");
    exit;
}

// Now include the header
require_once '../includes/faculty_header.php';
require_once '../config/db.php';

// Get messages from session
if (isset($_SESSION['lesson_message'])) {
    $message = $_SESSION['lesson_message'];
    unset($_SESSION['lesson_message']);
}

if (isset($_SESSION['lesson_error'])) {
    $error = $_SESSION['lesson_error'];
    unset($_SESSION['lesson_error']);
}

if (isset($_GET['upload']) && $_GET['upload'] == 'success') {
    $message = "Lesson uploaded successfully!";
}

// Fetch all lessons for this faculty
$lessons = [];
$stmt = $conn->prepare("
    SELECT l.id, l.title, l.file_path, l.upload_date, s.name as subject_name
    FROM lessons l
    JOIN subjects s ON l.subject_id = s.id
    WHERE s.faculty_id = ?
    ORDER BY l.upload_date DESC
");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Check if file exists in uploads folder (sync check)
        $file_path_full = '../' . $row['file_path'];
        $row['file_exists'] = file_exists($file_path_full);
        $lessons[] = $row;
    }
}
$stmt->close();
$conn->close();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="mb-0"><i class="bi bi-file-earmark-text"></i> Manage Lessons</h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;">View and manage all your uploaded lesson files</p>
    </div>
    <a href="upload.php" class="btn btn-success">
        <i class="bi bi-cloud-upload"></i> Upload New Lesson
    </a>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="bi bi-list-ul"></i> All Lessons</h3>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th><i class="bi bi-file-earmark-pdf"></i> Lesson Title</th>
                    <th><i class="bi bi-book"></i> Subject</th>
                    <th><i class="bi bi-calendar"></i> Upload Date</th>
                    <th><i class="bi bi-gear"></i> Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lessons)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-4">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p class="mb-2">No lessons uploaded yet.</p>
                                <a href="upload.php" class="btn btn-sm btn-primary">
                                    <i class="bi bi-cloud-upload"></i> Upload Your First Lesson
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($lessons as $lesson): ?>
                        <tr>
                            <td>
                                <i class="bi bi-file-earmark-pdf text-danger me-2"></i>
                                <strong><?php echo htmlspecialchars($lesson['title']); ?></strong>
                                <?php if (isset($lesson['file_exists']) && !$lesson['file_exists']): ?>
                                    <span class="badge bg-warning ms-2" title="File missing from uploads folder">
                                        <i class="bi bi-exclamation-triangle"></i> File Missing
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <i class="bi bi-bookmark text-primary me-2"></i>
                                <?php echo htmlspecialchars($lesson['subject_name']); ?>
                            </td>
                            <td>
                                <i class="bi bi-calendar3 text-muted me-2"></i>
                                <small><?php echo date("M j, Y", strtotime($lesson['upload_date'])); ?></small>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <?php if (isset($lesson['file_exists']) && $lesson['file_exists']): ?>
                                        <button type="button" 
                                                class="btn btn-sm btn-info view-pdf-btn" 
                                                data-pdf-url="../<?php echo htmlspecialchars($lesson['file_path']); ?>"
                                                data-pdf-title="<?php echo htmlspecialchars($lesson['title']); ?>">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                    <?php else: ?>
                                        <button type="button" 
                                                class="btn btn-sm btn-info" 
                                                disabled
                                                title="File not found in uploads folder">
                                            <i class="bi bi-eye-slash"></i> View
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" 
                                            class="btn btn-sm btn-primary edit-lesson-btn" 
                                            data-lesson-id="<?php echo $lesson['id']; ?>"
                                            data-lesson-title="<?php echo htmlspecialchars($lesson['title']); ?>">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <a href="manage_lessons.php?action=delete&lesson_id=<?php echo $lesson['id']; ?>" 
                                       class="btn btn-sm btn-danger delete-btn"
                                       data-message="Are you sure you want to delete this lesson? This will also delete the file from the uploads folder and any associated quizzes."
                                       data-href="manage_lessons.php?action=delete&lesson_id=<?php echo $lesson['id']; ?>">
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
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const pdfUrl = this.getAttribute('data-pdf-url');
            const pdfTitle = this.getAttribute('data-pdf-title') || 'PDF Viewer';
            pdfFrame.src = pdfUrl;
            pdfModalTitle.innerHTML = '<i class="bi bi-file-earmark-pdf"></i> ' + pdfTitle;
            pdfModal.show();
            return false;
        });
    });
    
    // Clear iframe when modal is hidden
    document.getElementById('pdfViewerModal').addEventListener('hidden.bs.modal', function() {
        pdfFrame.src = '';
    });
    
    // Edit Lesson Modal
    const editModal = new bootstrap.Modal(document.getElementById('editLessonModal'));
    const editForm = document.getElementById('editLessonForm');
    const editLessonId = document.getElementById('edit_lesson_id');
    const editLessonTitle = document.getElementById('edit_lesson_title');
    
    document.querySelectorAll('.edit-lesson-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const lessonId = this.getAttribute('data-lesson-id');
            const lessonTitle = this.getAttribute('data-lesson-title');
            editLessonId.value = lessonId;
            editLessonTitle.value = lessonTitle;
            editModal.show();
        });
    });
    
    // Handle form submission
    editForm.addEventListener('submit', function(e) {
        const title = editLessonTitle.value.trim();
        if (!title) {
            e.preventDefault();
            showToast('Lesson title cannot be empty.', 'error');
            return false;
        }
    });
});
</script>

<!-- Edit Lesson Modal -->
<div class="modal fade" id="editLessonModal" tabindex="-1" aria-labelledby="editLessonModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editLessonModalLabel">
                    <i class="bi bi-pencil"></i> Edit Lesson
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editLessonForm" method="POST" action="manage_lessons.php">
                <input type="hidden" name="action" value="edit_lesson">
                <input type="hidden" name="lesson_id" id="edit_lesson_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_lesson_title" class="form-label">
                            <i class="bi bi-file-earmark-text"></i> Lesson Title
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="edit_lesson_title" 
                               name="title" 
                               required 
                               maxlength="255"
                               placeholder="Enter lesson title">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/faculty_footer.php'; ?>

