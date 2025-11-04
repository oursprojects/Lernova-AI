<?php
/*
 * LernovaAI - Faculty Header
 * This file contains the top part of the faculty UI,
 * including navigation and session security.
 */

// Start session on every protected page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Faculty Security Check ---
// Check if user is logged in AND is a faculty member.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty') {
    // Not faculty or not logged in.
    // Redirect to login page
    header("Location: ../login.php"); // ../ goes up one folder
    exit;
}

// Get user's name for a nice welcome message
$faculty_name = htmlspecialchars($_SESSION['first_name']);
$faculty_id = $_SESSION['user_id']; // We'll need this ID often

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LernovaAI - Faculty Panel</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        body { 
            font-family: var(--font-secondary);
            margin: 0; 
            background-color: var(--bg-color); 
        }
        
        .admin-header { 
            background: linear-gradient(135deg, #4F46E5, #6366F1);
            color: white; 
            padding: 0.75rem 1.5rem; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: var(--shadow-md);
        }
        
        .admin-header .logo { 
            font-family: var(--font-secondary);
            font-size: 1.25rem; 
            font-weight: 800; 
        }
        
        .admin-header .logo a { 
            color: white; 
            text-decoration: none; 
        }
        
        .admin-header .user-info { 
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
            font-size: 0.875rem;
        }
        
        .admin-header .user-info a { 
            color: #FCD34D; 
            text-decoration: none; 
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .admin-header .user-info a:hover {
            color: #FDE047;
            text-decoration: underline;
        }
        
        .admin-container { 
            display: flex; 
        }
        
        .admin-nav { 
            width: 210px; 
            background-color: var(--card-bg); 
            min-height: calc(100vh - 70px);
            padding: 0.875rem; 
            box-shadow: var(--shadow-md);
        }
        
        .admin-nav ul { 
            list-style: none; 
            padding: 0; 
            margin: 0; 
        }
        
        .admin-nav li { 
            margin-bottom: 0.375rem; 
        }
        
        .admin-nav a { 
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem; 
            text-decoration: none; 
            color: var(--text-secondary); 
            border-radius: var(--radius-sm); 
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .admin-nav a i {
            font-size: 1rem;
            width: 18px;
            text-align: center;
        }
        
        .admin-nav a:hover, 
        .admin-nav a.active { 
            background: linear-gradient(135deg, #4F46E5, #6366F1);
            color: white; 
        }
        
        .admin-main { 
            flex-grow: 1; 
            padding: 1.25rem; 
            max-width: calc(100vw - 210px);
            overflow-x: hidden;
        }
        
        .admin-main h1 { 
            margin-top: 0; 
            color: var(--text-primary); 
            font-family: var(--font-secondary);
            font-weight: 700;
        }
        
        .card { 
            background-color: var(--card-bg); 
            border-radius: var(--radius-lg); 
            box-shadow: var(--shadow-md); 
            padding: 1.5rem; 
            margin-bottom: 1.5rem; 
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        /* Button styles */
        .btn { 
            background-color: #4F46E5; 
            color: white; 
            padding: 0.75rem 1.5rem; 
            border: none; 
            border-radius: var(--radius-md); 
            cursor: pointer; 
            font-size: 1rem; 
            text-decoration: none; 
            display: inline-block;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-success { 
            background-color: var(--success-color); 
        }
        
        .btn-danger { 
            background-color: var(--danger-color); 
        }
        
        .btn:hover { 
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        /* Table styles */
        table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        
        th, td { 
            padding: 1rem; 
            border-bottom: 1px solid var(--border-color); 
            text-align: left; 
        }
        
        th { 
            background: linear-gradient(135deg, #4F46E5, #6366F1);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
        }
        
        tr:hover { 
            background-color: #F3F4F6; 
        }
    </style>
</head>
<body>

<header class="admin-header">
    <div class="logo">
        <a href="index.php">
            <i class="bi bi-mortarboard-fill"></i> LernovaAI Faculty
        </a>
    </div>
    <div class="user-info">
        <i class="bi bi-person-circle"></i>
        <span>Welcome, <?php echo $faculty_name; ?>!</span>
        <a href="../logout.php" class="logout-btn" data-message="Are you sure you want to logout?">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</header>

<div class="admin-container">
    <nav class="admin-nav">
        <ul>
            <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
            <li>
                <a href="index.php" class="<?php if($current_page == 'index.php') echo 'active'; ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="manage_subjects.php" class="<?php if($current_page == 'manage_subjects.php') echo 'active'; ?>">
                    <i class="bi bi-book"></i>
                    <span>Manage Subjects</span>
                </a>
            </li>
            <li>
                <a href="upload.php" class="<?php if($current_page == 'upload.php') echo 'active'; ?>">
                    <i class="bi bi-cloud-upload"></i>
                    <span>Upload Lessons</span>
                </a>
            </li>
            <li>
                <a href="manage_lessons.php" class="<?php if($current_page == 'manage_lessons.php') echo 'active'; ?>">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Manage Lessons</span>
                </a>
            </li>
            <li>
                <a href="quizzes.php" class="<?php if($current_page == 'quizzes.php') echo 'active'; ?>">
                    <i class="bi bi-clipboard-check"></i>
                    <span>Manage Quizzes</span>
                </a>
            </li>
            <li>
                <a href="manage_students.php" class="<?php if($current_page == 'manage_students.php' || $current_page == 'view_student.php') echo 'active'; ?>">
                    <i class="bi bi-people"></i>
                    <span>Manage Students</span>
                </a>
            </li>
            <li>
                <a href="quiz_results.php" class="<?php if($current_page == 'quiz_results.php') echo 'active'; ?>">
                    <i class="bi bi-clipboard-data"></i>
                    <span>Quiz Results</span>
                </a>
            </li>
            <li>
                <a href="account.php" class="<?php if($current_page == 'account.php') echo 'active'; ?>">
                    <i class="bi bi-person-gear"></i>
                    <span>Account</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <main class="admin-main fade-in">