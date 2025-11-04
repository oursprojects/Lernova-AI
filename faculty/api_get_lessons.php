<?php
/*
 * LernovaAI - API: Get Lessons
 * This file fetches all lessons for a given subject
 * and returns them as JSON for our JavaScript.
 */

// 1. Start session and check security
session_start();
require_once '../config/db.php';

// Check if user is a logged-in faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}
$faculty_id = $_SESSION['user_id'];

// 2. Check for Subject ID and validate
if (!isset($_GET['subject_id']) || empty($_GET['subject_id'])) {
    echo json_encode(['success' => false, 'error' => 'No subject ID provided.']);
    exit;
}
$subject_id = intval($_GET['subject_id']);

if ($subject_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid subject ID.']);
    exit;
}

// 3. Fetch lessons (and verify faculty ownership)
$lessons = [];
$stmt = $conn->prepare("
    SELECT l.id, l.title 
    FROM lessons l
    JOIN subjects s ON l.subject_id = s.id
    WHERE l.subject_id = ? AND s.faculty_id = ?
    ORDER BY l.title ASC
");
$stmt->bind_param("ii", $subject_id, $faculty_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // We must htmlspecialchars here to prevent XSS issues in the dropdown
        $lessons[] = [
            'id' => $row['id'],
            'title' => htmlspecialchars($row['title'])
        ];
    }
}
$stmt->close();
$conn->close();

// 4. Return the data as JSON
echo json_encode(['success' => true, 'lessons' => $lessons]);
exit;
?>