<?php
/*
 * LernovaAI - Download Reviewer Page
 * Downloads a reviewer as HTML or PDF
 */

// 1. Include the header
require_once '../includes/student_header.php';
require_once '../config/db.php';

// Get Student ID from session
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

// 4. Create HTML content for download
$html_content = "
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Reviewer: " . htmlspecialchars($reviewer['lesson_title']) . "</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; line-height: 1.6; }
        h1 { color: #333; border-bottom: 2px solid #4F46E5; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        p { margin: 10px 0; }
        ul, ol { margin: 10px 0; padding-left: 30px; }
        li { margin: 5px 0; }
        strong { color: #333; }
        .meta { color: #666; font-style: italic; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>Reviewer for: " . htmlspecialchars($reviewer['lesson_title']) . "</h1>
    <p class='meta'>Generated on: " . date("M j, Y - g:i a", strtotime($reviewer['generated_at'])) . "</p>
    <hr>
    " . $reviewer['reviewer_html'] . "
</body>
</html>
";

// 5. Set headers for download
$filename = "Reviewer_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $reviewer['lesson_title']) . "_" . date('Y-m-d') . ".html";
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($html_content));

// 6. Output the content
echo $html_content;
exit;
?>

