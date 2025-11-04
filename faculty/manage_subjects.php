<?php
require_once '../includes/faculty_header.php';
require_once '../config/db.php';

$faculty_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $subject_id_to_delete = intval($_GET['id']);
    
    $stmt_delete = $conn->prepare("DELETE FROM subjects WHERE id = ? AND faculty_id = ?");
    $stmt_delete->bind_param("ii", $subject_id_to_delete, $faculty_id);
    if ($stmt_delete->execute()) {
        $message = "Subject deleted successfully.";
    } else {
        $error = "Error deleting subject.";
    }
    $stmt_delete->close();
}

// Fetch all subjects
$subjects = [];
$stmt = $conn->prepare("SELECT id, name, enrollment_key, created_at FROM subjects WHERE faculty_id = ? ORDER BY name ASC");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result_subjects = $stmt->get_result();

if ($result_subjects->num_rows > 0) {
    while($row = $result_subjects->fetch_assoc()) {
        $subjects[] = $row;
    }
}
$stmt->close();
$conn->close();
?>


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

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="mb-0"><i class="bi bi-book"></i> Manage Subjects</h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;">Create, view, and manage your subjects</p>
    </div>
    <a href="create_subject.php" class="btn btn-success">
        <i class="bi bi-plus-circle"></i> Create New Subject
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="bi bi-list-ul"></i> Subject List</h3>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th><i class="bi bi-book-half"></i> Subject Name</th>
                    <th><i class="bi bi-key"></i> Enrollment Key</th>
                    <th><i class="bi bi-calendar"></i> Date Created</th>
                    <th><i class="bi bi-gear"></i> Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subjects)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-4">
                            <i class="bi bi-inbox" style="font-size: 2rem; color: #9CA3AF; display: block; margin-bottom: 0.5rem;"></i>
                            <span class="text-muted">You have not created any subjects yet.</span>
                            <br>
                            <a href="create_subject.php" class="btn btn-sm btn-primary mt-2">
                                <i class="bi bi-plus-circle"></i> Create Your First Subject
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subjects as $subject): ?>
                        <tr>
                            <td>
                                <i class="bi bi-bookmark text-primary me-2"></i>
                                <strong><?php echo htmlspecialchars($subject['name']); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-info text-dark" style="font-size: 0.875rem; padding: 0.5rem 0.75rem;">
                                    <i class="bi bi-key"></i> <?php echo htmlspecialchars($subject['enrollment_key']); ?>
                                </span>
                            </td>
                            <td>
                                <i class="bi bi-calendar3 text-muted me-2"></i>
                                <small><?php echo date("M j, Y", strtotime($subject['created_at'])); ?></small>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="view_subject.php?id=<?php echo $subject['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <a href="manage_subjects.php?action=delete&id=<?php echo $subject['id']; ?>" 
                                       class="btn btn-sm btn-danger delete-btn"
                                       data-message="Are you sure you want to delete this subject? This will delete ALL lessons, quizzes, and student enrollments for this subject."
                                       data-href="manage_subjects.php?action=delete&id=<?php echo $subject['id']; ?>">
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

<?php require_once '../includes/faculty_footer.php'; ?>

