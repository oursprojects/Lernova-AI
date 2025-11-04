<?php
/*
 * LernovaAI - View Reviewer Page
 * Displays the full content of a single saved reviewer.
 */

// 1. Include the header
require_once '../includes/student_header.php';
require_once '../config/db.php';

// Get Student ID from session (defined in header)
$student_id = $_SESSION['user_id'];

// 2. Get Reviewer ID from URL and validate
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: my_reviewers.php?error=no_id");
    exit;
}
$reviewer_id = intval($_GET['id']);

if ($reviewer_id <= 0) {
    header("Location: my_reviewers.php?error=invalid_id");
    exit;
}

// 3. Fetch the saved reviewer from the DB
$stmt = $conn->prepare("
    SELECT lesson_title, reviewer_html, generated_at
    FROM student_reviewers
    WHERE id = ? AND student_id = ?
");
$stmt->bind_param("ii", $reviewer_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Reviewer not found or doesn't belong to this student
    $stmt->close();
    $conn->close();
    header("Location: my_reviewers.php?error=not_found");
    exit;
}
$reviewer = $result->fetch_assoc();
$stmt->close();

// Validate reviewer has content
if (empty($reviewer['reviewer_html'])) {
    $conn->close();
    header("Location: my_reviewers.php?error=empty_reviewer");
    exit;
}

$conn->close();
?>

<style>
    /* Styles for the rendered Markdown */
    .reviewer-card { background-color: #fdfdfd; border: 1px solid #e0e0e0; line-height: 1.6; }
    .reviewer-body { padding: 25px; font-size: 1.1em; }
    .reviewer-body h2 { margin-top: 20px; margin-bottom: 15px; border-bottom: 2px solid #eee; padding-bottom: 5px; }
    .reviewer-body ul { padding-left: 25px; }
    .reviewer-body li { margin-bottom: 8px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-0"><i class="bi bi-file-earmark-text-fill text-primary"></i> Reviewer</h1>
        <p class="text-muted mb-0" style="font-size: 0.875rem;"><i class="bi bi-info-circle me-1"></i> AI-generated study material</p>
    </div>
    <div class="d-flex gap-2">
        <a href="my_reviewers.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Reviewers
        </a>
        <a href="download_reviewer.php?id=<?php echo $reviewer_id; ?>" class="btn btn-success btn-sm">
            <i class="bi bi-download"></i> Download Reviewer
        </a>
    </div>
</div>

<div class="card reviewer-card shadow-sm">
    <div class="card-header bg-primary text-white">
        <h3 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i> <?php echo htmlspecialchars($reviewer['lesson_title']); ?></h3>
    </div>
    <div class="reviewer-body">
        <p class="text-muted mb-3">
            <i class="bi bi-calendar3 me-1"></i> Generated on: <strong><?php echo date("M j, Y - g:i a", strtotime($reviewer['generated_at'])); ?></strong>
        </p>
        <hr>
        <div id="reviewer-content">
            <?php echo $reviewer['reviewer_html']; // Echo the saved HTML ?>
        </div>
    </div>
</div>

<?php
// 4. Include the footer
require_once '../includes/student_footer.php';
?>