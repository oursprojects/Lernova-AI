<?php
require_once '../includes/faculty_header.php';
require_once '../config/db.php';

$faculty_id = $_SESSION['user_id'];
$error = '';
if (isset($_SESSION['upload_error'])) {
    $error = $_SESSION['upload_error'];
    unset($_SESSION['upload_error']);
}

// Fetch all subjects for this faculty
$subjects = [];
$stmt = $conn->prepare("SELECT id, name FROM subjects WHERE faculty_id = ? ORDER BY name ASC");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
}
$stmt->close();
$conn->close();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="mb-0"><i class="bi bi-cloud-upload"></i> Upload New Lesson</h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;">Upload a .pdf or .txt file. The system will extract the text for AI quiz and reviewer generation.</p>
    </div>
    <a href="manage_lessons.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Back to Lessons
    </a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (empty($subjects)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>No Subjects Found!</strong> You need to create a subject first before uploading lessons.
        <br>
        <a href="create_subject.php" class="btn btn-sm btn-primary mt-2">
            <i class="bi bi-plus-circle"></i> Create a Subject
        </a>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="bi bi-file-earmark-plus"></i> Lesson Details</h3>
        </div>
        <div class="card-body">
            <form action="upload_process.php" method="POST" enctype="multipart/form-data" onsubmit="this.querySelector('button[type=submit]').innerHTML = '<i class=\'bi bi-hourglass-split\'></i> Uploading & Processing...'; this.querySelector('button[type=submit]').disabled = true;">
                
                <div class="mb-3">
                    <label for="subject_id" class="form-label">
                        <i class="bi bi-book text-primary"></i> Select Subject
                    </label>
                    <select class="form-select" id="subject_id" name="subject_id" required>
                        <option value="">-- Select a Subject --</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Choose the subject this lesson belongs to</small>
                </div>
                
                <div class="mb-3">
                    <label for="title" class="form-label">
                        <i class="bi bi-bookmark text-primary"></i> Lesson Title
                    </label>
                    <input type="text" class="form-control" id="title" name="title" placeholder="e.g., Introduction to Machine Learning" required>
                    <small class="text-muted">Enter a descriptive title for this lesson</small>
                </div>
                
                <div class="mb-3">
                    <label for="lesson_file" class="form-label">
                        <i class="bi bi-file-earmark-pdf text-primary"></i> Lesson File
                    </label>
                    <input type="file" class="form-control" id="lesson_file" name="lesson_file" accept=".pdf,.txt" required>
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> Accepted formats: PDF (.pdf) or Text (.txt) files only. Maximum file size: 10MB
                    </small>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-cloud-upload"></i> Upload and Process File
                    </button>
                    <a href="manage_lessons.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Cancel
                    </a>
                </div>
                
            </form>
        </div>
    </div>
<?php endif; ?>

<?php
// 3. Include the footer
require_once '../includes/faculty_footer.php';
?>