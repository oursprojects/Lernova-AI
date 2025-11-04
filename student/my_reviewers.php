<?php
/*
 * LernovaAI - My Reviewers Page
 * Shows a history of all generated reviewers for the student.
 */

// 1. Include the header
require_once '../includes/student_header.php';
require_once '../config/db.php';

// Get the student_id from the session (defined in the header)
$student_id = $_SESSION['user_id'];
$message = '';
$error = '';

// --- NEW: Handle Delete Action ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $reviewer_id_to_delete = intval($_GET['id']);
    
    // Delete the reviewer, but ONLY if it belongs to this student
    $stmt_delete = $conn->prepare("DELETE FROM student_reviewers WHERE id = ? AND student_id = ?");
    $stmt_delete->bind_param("ii", $reviewer_id_to_delete, $student_id);
    
    if ($stmt_delete->execute()) {
        if ($stmt_delete->affected_rows > 0) {
            $message = "Reviewer deleted successfully.";
        } else {
            $error = "Could not delete reviewer. It may not exist or not belong to you.";
        }
    } else {
        $error = "Error deleting reviewer.";
    }
    $stmt_delete->close();
}
// --- End Delete Action ---


// --- 2. Fetch All Saved Reviewers for this Student ---
$reviewers = [];
$stmt = $conn->prepare("
    SELECT id, lesson_title, generated_at
    FROM student_reviewers
    WHERE student_id = ?
    ORDER BY generated_at DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $reviewers[] = $row;
    }
}
$stmt->close();
$conn->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-0"><i class="bi bi-file-earmark-text-fill text-primary"></i> My Generated Reviewers</h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;"><i class="bi bi-info-circle me-1"></i> History of all AI reviewers you have created</p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
        <a href="generate_reviewer.php" class="btn btn-success btn-sm">
            <i class="bi bi-plus-circle-fill"></i> Generate New Reviewer
        </a>
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

<div class="card">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="bi bi-list-ul"></i> Saved Reviewers</h3>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th><i class="bi bi-file-earmark-text"></i> Lesson Title</th>
                    <th><i class="bi bi-calendar3"></i> Date Generated</th>
                    <th><i class="bi bi-gear"></i> Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reviewers)): ?>
                    <tr>
                        <td colspan="3" class="text-center py-4">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p class="mb-2">You have not generated any reviewers yet.</p>
                                <a href="generate_reviewer.php" class="btn btn-sm btn-primary">
                                    <i class="bi bi-magic"></i> Generate Your First Reviewer
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reviewers as $reviewer): ?>
                        <tr>
                            <td>
                                <i class="bi bi-file-earmark-text text-primary me-2"></i>
                                <strong><?php echo htmlspecialchars($reviewer['lesson_title']); ?></strong>
                            </td>
                            <td>
                                <i class="bi bi-calendar3 text-muted me-2"></i>
                                <small><?php echo date("M j, Y - g:i a", strtotime($reviewer['generated_at'])); ?></small>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="view_reviewer.php?id=<?php echo $reviewer['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-eye-fill"></i> View
                                    </a>
                                    <a href="my_reviewers.php?action=delete&id=<?php echo $reviewer['id']; ?>" 
                                       class="btn btn-sm btn-danger"
                                       data-message="Are you sure you want to delete this reviewer?">
                                       <i class="bi bi-trash-fill"></i> Delete
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
require_once '../includes/student_footer.php';
?>