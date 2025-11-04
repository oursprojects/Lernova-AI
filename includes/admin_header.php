<?php
/*
 * LernovaAI - Admin Header
 * This file contains the top part of the admin UI,
 * including navigation and session security.
 */

// Start session on every protected page (only if not already started)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Admin Security Check ---
// Check if user is logged in AND is an admin.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    // Not an admin or not logged in.
    // Redirect to login page
    header("Location: ../login.php"); // ../ goes up one folder
    exit;
}

// Get user's name for a nice welcome message
$admin_name = htmlspecialchars($_SESSION['first_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LernovaAI - Admin Panel</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        /* Admin Layout Styles */
        body { 
            font-family: var(--font-secondary);
            margin: 0; 
            background-color: var(--bg-color);
        }
        
        .admin-header { 
            background: linear-gradient(135deg, #1F2937, #374151);
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
            color: #FBBF24; 
            text-decoration: none; 
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .admin-header .user-info a:hover {
            color: #FCD34D;
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
            background: linear-gradient(135deg, #374151, #4B5563);
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
    </style>
</head>
<body>

<header class="admin-header">
    <div class="logo">
        <a href="index.php">
            <i class="bi bi-shield-check"></i> LernovaAI Admin
        </a>
    </div>
    <div class="user-info">
        <i class="bi bi-person-circle"></i>
        <span>Welcome, <?php echo $admin_name; ?>!</span>
        <a href="../logout.php" class="logout-btn" data-message="Are you sure you want to logout?">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</header>

<div class="admin-container">
    <nav class="admin-nav">
        <ul>
            <li>
                <a href="index.php" class="<?php if(basename($_SERVER['PHP_SELF']) == 'index.php') echo 'active'; ?>">
                    <i class="bi bi-people"></i>
                    <span>Manage Users</span>
                </a>
            </li>
            <li>
                <a href="reports.php" class="<?php if(basename($_SERVER['PHP_SELF']) == 'reports.php') echo 'active'; ?>">
                    <i class="bi bi-graph-up"></i>
                    <span>System Reports</span>
                </a>
            </li>
            <li>
                <a href="account.php" class="<?php if(basename($_SERVER['PHP_SELF']) == 'account.php') echo 'active'; ?>">
                    <i class="bi bi-person-gear"></i>
                    <span>Account</span>
                </a>
            </li>
        </ul>
    </nav>

    <main class="admin-main fade-in">