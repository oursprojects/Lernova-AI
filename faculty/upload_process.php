<?php
/*
 * LernovaAI - Upload Processing Script (Updated for Subjects)
 */

// 1. Load Session and Security
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    header("Location: ../login.php");
    exit;
}

// 2. Load Composer's autoloader (to use pdfparser) and DB connection
require_once '../vendor/autoload.php';
require_once '../config/db.php';

// 3. Check if the form was submitted correctly
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["lesson_file"]) && isset($_POST['subject_id'])) {
    
    // Get data from form
    $title = $conn->real_escape_string($_POST['title']);
    $subject_id = intval($_POST['subject_id']); // NEW
    $faculty_id = $_SESSION['user_id']; // For verification
    $file = $_FILES["lesson_file"];
    
    // --- 4. Verify Faculty Owns this Subject ---
    $stmt_check = $conn->prepare("SELECT id FROM subjects WHERE id = ? AND faculty_id = ?");
    $stmt_check->bind_param("ii", $subject_id, $faculty_id);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows == 0) {
        $_SESSION['upload_error'] = "Permission denied or invalid subject.";
        header("Location: index.php"); // Redirect to main faculty page
        exit;
    }
    $stmt_check->close();

    // File properties
    $file_name = $file["name"];
    $file_tmp_name = $file["tmp_name"];
    $file_size = $file["size"];
    $file_error = $file["error"];
    $file_ext_lower = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_exts = array("pdf", "txt");

    // --- 5. File Validation ---
    if (in_array($file_ext_lower, $allowed_exts)) {
        if ($file_error === 0) {
            if ($file_size <= 10000000) { // 10MB limit
                
                $file_name_new = uniqid('', true) . "." . $file_ext_lower;
                $file_destination = "uploads/" . $file_name_new; 
                $file_destination_full = "../" . $file_destination; 

                if (move_uploaded_file($file_tmp_name, $file_destination_full)) {
                    
                    // --- 6. Text Extraction ---
                    $extracted_text = "";
                    try {
                        if ($file_ext_lower == "pdf") {
                            $parser = new \Smalot\PdfParser\Parser();
                            $pdf = $parser->parseFile($file_destination_full);
                            $extracted_text = $pdf->getText();
                        } elseif ($file_ext_lower == "txt") {
                            $extracted_text = file_get_contents($file_destination_full);
                        }
                        
                        $extracted_text = preg_replace('/\s+/', ' ', $extracted_text);
                        $extracted_text = trim($extracted_text);

                        // Validate extracted text
                        if (empty(trim($extracted_text))) {
                            // Delete the uploaded file if text extraction failed
                            if (file_exists($file_destination_full)) {
                                unlink($file_destination_full);
                            }
                            throw new Exception("Failed to extract text from file. The file may be corrupted, password-protected, or contain only images.");
                        }
                        
                        // Validate text length (minimum 50 characters)
                        if (strlen(trim($extracted_text)) < 50) {
                            if (file_exists($file_destination_full)) {
                                unlink($file_destination_full);
                            }
                            throw new Exception("Extracted text is too short (minimum 50 characters required). The file may not contain sufficient text content.");
                        }
                        
                        // Validate title
                        if (empty(trim($title))) {
                            if (file_exists($file_destination_full)) {
                                unlink($file_destination_full);
                            }
                            throw new Exception("Lesson title cannot be empty.");
                        }
                        
                        // --- 7. Save to Database (UPDATED QUERY) ---
                        $stmt = $conn->prepare("INSERT INTO lessons (subject_id, title, file_path, extracted_text) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("isss", $subject_id, $title, $file_destination, $extracted_text);
                        
                        if ($stmt->execute()) {
                            $stmt->close();
                            $conn->close();
                            // SUCCESS! Redirect to manage lessons
                            header("Location: manage_lessons.php?upload=success");
                            exit;
                        } else {
                            // Delete uploaded file if database save fails
                            if (file_exists($file_destination_full)) {
                                unlink($file_destination_full);
                            }
                            throw new Exception("Database error: " . $stmt->error);
                        }
                        $stmt->close();

                    } catch (Exception $e) {
                        // Clean up uploaded file on error
                        if (isset($file_destination_full) && file_exists($file_destination_full)) {
                            unlink($file_destination_full);
                        }
                        $error = "Error processing file: " . $e->getMessage();
                    }
                } else {
                    $error = "Failed to move uploaded file.";
                }
            } else {
                $error = "Your file is too large (Max 10MB).";
            }
        } else {
            $error = "There was an error uploading your file (Code: $file_error).";
        }
    } else {
        $error = "Cannot upload files of this type. Only .pdf and .txt are allowed.";
    }

    // --- 8. Handle Errors ---
    $_SESSION['upload_error'] = $error;
    header("Location: upload.php");
    exit;

} else {
    header("Location: index.php");
    exit;
}
?>