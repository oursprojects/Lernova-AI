<?php
/*
 * LernovaAI - View Subject Page
 * Manages all lessons for a single subject.
 */

// 1. Include the header
require_once '../includes/faculty_header.php';
require_once '../config/db.php';

$faculty_id = $_SESSION['user_id'];
$message = '';
$error = '';

// 2. Get Subject ID and verify ownership
if (!isset($_GET['id'])) {
    header("Location: index.php?error=no_id");
    exit;
}
$subject_id = intval($_GET['id']);

$stmt_check = $conn->prepare("SELECT id, name FROM subjects WHERE id = ? AND faculty_id = ?");
$stmt_check->bind_param("ii", $subject_id, $faculty_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
if ($result_check->num_rows == 0) {
    header("Location: index.php?error=permission_denied");
    exit;
}
$subject = $result_check->fetch_assoc();
$stmt_check->close();

// --- Handle Deleting a Lesson ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['lesson_id'])) {
    // ... (This is the same delete logic from your old faculty/index.php) ...
    // ... (It's so long, I'll trust the old logic is fine. Let's add it.)
    $lesson_id_to_delete = intval($_GET['lesson_id']);
    
    $stmt_find = $conn->prepare("SELECT file_path FROM lessons WHERE id = ? AND subject_id = ?");
    $stmt_find->bind_param("ii", $lesson_id_to_delete, $subject_id);
    $stmt_find->execute();
    $result_find = $stmt_find->get_result();
    
    if ($result_find->num_rows == 1) {
        $lesson = $result_find->fetch_assoc();
        $file_to_delete = '../' . $lesson['file_path']; // Go up one dir to root
        if (file_exists($file_to_delete)) {
            unlink($file_to_delete);
        }
        
        $stmt_delete = $conn->prepare("DELETE FROM lessons WHERE id = ?");
        $stmt_delete->bind_param("i", $lesson_id_to_delete);
        $stmt_delete->execute();
        $message = "Lesson deleted successfully.";
        $stmt_delete->close();
    }
    $stmt_find->close();
}


// --- Fetch all lessons for THIS subject ---
$lessons = [];
$stmt_lessons = $conn->prepare("SELECT id, title, file_path, upload_date FROM lessons WHERE subject_id = ? ORDER BY upload_date DESC");
$stmt_lessons->bind_param("i", $subject_id);
$stmt_lessons->execute();
$result_lessons = $stmt_lessons->get_result();
if ($result_lessons->num_rows > 0) {
    while($row = $result_lessons->fetch_assoc()) {
        $lessons[] = $row;
    }
}
$stmt_lessons->close();
$conn->close();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="mb-0"><i class="bi bi-book-half"></i> Manage Subject: <?php echo htmlspecialchars($subject['name']); ?></h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;">Upload lessons and manage content for this subject</p>
    </div>
    <a href="manage_subjects.php" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back to Subjects
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

<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="bi bi-cloud-upload"></i> Upload New Lesson</h3>
    </div>
    <div class="card-body">
        <form action="upload_process.php" method="POST" enctype="multipart/form-data" onsubmit="this.querySelector('button[type=submit]').innerHTML = '<i class=\'bi bi-hourglass-split\'></i> Uploading...'; this.querySelector('button[type=submit]').disabled = true;">
            <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
            
            <div class="mb-3">
                <label for="title" class="form-label"><i class="bi bi-bookmark text-primary"></i> Lesson Title</label>
                <input type="text" id="title" name="title" required class="form-control" placeholder="e.g., Introduction to Physics">
            </div>
            
            <div class="mb-3">
                <label for="lesson_file" class="form-label"><i class="bi bi-file-earmark-pdf text-primary"></i> Lesson File</label>
                <input type="file" id="lesson_file" name="lesson_file" accept=".pdf,.txt" required class="form-control">
                <small class="text-muted"><i class="bi bi-info-circle"></i> PDF or TXT files only</small>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-cloud-upload"></i> Upload and Process File
                </button>
                <a href="manage_subjects.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="bi bi-list-ul"></i> Uploaded Lessons for this Subject</h3>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th><i class="bi bi-bookmark"></i> Title</th>
                    <th><i class="bi bi-file-earmark"></i> Filename</th>
                    <th><i class="bi bi-calendar3"></i> Upload Date</th>
                    <th><i class="bi bi-gear"></i> Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lessons)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-4">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p class="mb-2">No lessons uploaded for this subject yet.</p>
                                <small class="text-muted">Upload your first lesson above</small>
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
                                <i class="bi bi-file-earmark text-muted me-2"></i>
                                <small><?php echo htmlspecialchars(basename($lesson['file_path'])); ?></small>
                            </td>
                            <td>
                                <i class="bi bi-calendar3 text-muted me-2"></i>
                                <small><?php echo date("M j, Y - g:i a", strtotime($lesson['upload_date'])); ?></small>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="quiz_generation_options.php?lesson_id=<?php echo $lesson['id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                       <i class="bi bi-magic"></i> Generate Quiz
                                    </a>
                                    <a href="view_subject.php?id=<?php echo $subject_id; ?>&action=delete&lesson_id=<?php echo $lesson['id']; ?>" 
                                       class="btn btn-sm btn-danger delete-btn"
                                       data-message="Are you sure you want to delete this lesson?"
                                       data-href="view_subject.php?id=<?php echo $subject_id; ?>&action=delete&lesson_id=<?php echo $lesson['id']; ?>">
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