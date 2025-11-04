<?php
/*
 * LernovaAI - Faculty Dashboard (My Subjects)
 * This page lists the faculty's subjects.
 */

// 1. Include the header. This will now correctly start the session.
require_once '../includes/faculty_header.php';
// We will include the DB connection only when we need it.

$faculty_id = $_SESSION['user_id']; // This will now work
$message = '';
$error = '';

// --- Handle Delete Subject ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    require_once '../config/db.php'; // Connect to DB
    $subject_id_to_delete = intval($_GET['id']);
    
    $stmt_delete = $conn->prepare("DELETE FROM subjects WHERE id = ? AND faculty_id = ?");
    $stmt_delete->bind_param("ii", $subject_id_to_delete, $faculty_id);
    if ($stmt_delete->execute()) {
        $message = "Subject deleted successfully.";
    } else {
        $error = "Error deleting subject.";
    }
    $stmt_delete->close();
    $conn->close(); // Close DB connection
}

// --- Fetch all subjects for THIS faculty member ---
require_once '../config/db.php'; // Connect to DB for this main query
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

// --- Fetch Statistics ---
$stats_subjects = count($subjects);
$stats_lessons = $conn->query("SELECT COUNT(*) as count FROM lessons WHERE subject_id IN (SELECT id FROM subjects WHERE faculty_id = $faculty_id)")->fetch_assoc()['count'];
$stats_quizzes = $conn->query("SELECT COUNT(*) as count FROM quizzes WHERE lesson_id IN (SELECT id FROM lessons WHERE subject_id IN (SELECT id FROM subjects WHERE faculty_id = $faculty_id))")->fetch_assoc()['count'];
$stats_students = $conn->query("SELECT COUNT(DISTINCT student_id) as count FROM enrollments WHERE subject_id IN (SELECT id FROM subjects WHERE faculty_id = $faculty_id)")->fetch_assoc()['count'];

$conn->close(); // Close DB connection
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="mb-0"><i class="bi bi-speedometer2"></i> Faculty Dashboard</h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;">Manage your subjects, lessons, and quizzes all in one place</p>
    </div>
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

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #4F46E5 0%, #6366F1 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50">Subjects</h6>
                        <h2 class="mb-0"><?php echo $stats_subjects; ?></h2>
                    </div>
                    <i class="bi bi-book-half" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50">Lessons</h6>
                        <h2 class="mb-0"><?php echo $stats_lessons; ?></h2>
                    </div>
                    <i class="bi bi-file-earmark-text" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #F59E0B 0%, #F97316 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50">Quizzes</h6>
                        <h2 class="mb-0"><?php echo $stats_quizzes; ?></h2>
                    </div>
                    <i class="bi bi-clipboard-check" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #8B5CF6 0%, #A78BFA 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50">Enrolled Students</h6>
                        <h2 class="mb-0"><?php echo $stats_students; ?></h2>
                    </div>
                    <i class="bi bi-people" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Section -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-primary">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-book text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="card-title mb-1"><i class="bi bi-bookmark"></i> Manage Subjects</h5>
                        <p class="card-text text-muted mb-2" style="font-size: 0.875rem;">Create and manage your subjects</p>
                        <a href="manage_subjects.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-arrow-right-circle"></i> Go to Subjects
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-success">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-success bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-cloud-upload text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="card-title mb-1"><i class="bi bi-file-earmark-text"></i> Upload Lessons</h5>
                        <p class="card-text text-muted mb-2" style="font-size: 0.875rem;">Upload PDF or text files for lessons</p>
                        <a href="upload.php" class="btn btn-sm btn-success">
                            <i class="bi bi-arrow-right-circle"></i> Upload Now
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-warning">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-clipboard-check text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="card-title mb-1"><i class="bi bi-clipboard-check"></i> Manage Quizzes</h5>
                        <p class="card-text text-muted mb-2" style="font-size: 0.875rem;">Create and manage quizzes</p>
                        <a href="quizzes.php" class="btn btn-sm btn-warning">
                            <i class="bi bi-arrow-right-circle"></i> Go to Quizzes
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-info">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-info bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-people text-info" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="card-title mb-1"><i class="bi bi-people"></i> Manage Students</h5>
                        <p class="card-text text-muted mb-2" style="font-size: 0.875rem;">View and manage enrolled students</p>
                        <a href="manage_students.php" class="btn btn-sm btn-info">
                            <i class="bi bi-arrow-right-circle"></i> Go to Students
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// 3. Include the footer
require_once '../includes/faculty_footer.php';
?>