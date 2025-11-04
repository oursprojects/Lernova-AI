<?php
/*
 * LernovaAI - Login Page
 * This file handles user authentication.
 */

// --- Session Management ---
// session_start() MUST be the very first thing on the page,
// before any HTML or even blank spaces.
session_start();

// If the user is ALREADY logged in, redirect them to their dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin/index.php");
    } elseif ($_SESSION['role'] == 'faculty') {
        header("Location: faculty/index.php");
    } else {
        header("Location: student/index.php");
    }
    exit; // Stop the script from running further
}

// Include our database connection
require_once 'config/db.php';

// Initialize variables
$error = '';

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get and sanitize username
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Username and password are required.";
    } else {
        // --- Security: Use Prepared Statements to find the user ---
        $sql = "SELECT id, username, password, role, first_name FROM users WHERE username = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            // User found, now fetch their data
            $user = $result->fetch_assoc();

            // --- Security: Verify the hashed password ---
            if (password_verify($password, $user['password'])) {
                // Password is correct!
                
                // --- Create the user session ---
                // We store this data to know who is logged in
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];

                // --- Redirect based on role ---
                if ($user['role'] == 'admin') {
                    header("Location: admin/index.php");
                } elseif ($user['role'] == 'faculty') {
                    header("Location: faculty/index.php");
                } elseif ($user['role'] == 'student') {
                    header("Location: student/index.php");
                }
                exit; // Important: Stop the script after redirecting

            } else {
                // Invalid password
                $error = "Invalid username or password.";
            }
        } else {
            // User not found
            $error = "Invalid username or password.";
        }
        
        $stmt->close();
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LernovaAI</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .login-container {
            background: #ffffff;
            padding: 2rem 2rem;
            border-radius: 1.25rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.1);
            width: 100%;
            max-width: 380px;
            position: relative;
            overflow: hidden;
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .login-header .icon-wrapper {
            width: 75px;
            height: 75px;
            margin: 0 auto 1.25rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            position: relative;
            animation: pulse 2s ease-in-out infinite;
        }
        
        .login-header .icon-wrapper::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 3px solid rgba(102, 126, 234, 0.2);
            animation: ripple 2s ease-out infinite;
        }
        
        .login-header .icon-wrapper i {
            font-size: 2.25rem;
            color: white;
            position: relative;
            z-index: 1;
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }
        
        @keyframes ripple {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            100% {
                transform: scale(1.3);
                opacity: 0;
            }
        }
        
        .login-header h2 {
            color: #1a1a1a;
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 0.375rem;
            letter-spacing: -0.5px;
        }
        
        .login-header p {
            color: #6b7280;
            font-size: 0.875rem;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .form-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-label i {
            color: #667eea;
            font-size: 1.1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .input-group-text {
            background: linear-gradient(135deg, #f9fafb 0%, #f0f4ff 100%);
            border: 2px solid #e5e7eb;
            border-right: none;
            color: #667eea;
            font-size: 1.15rem;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .input-group-text::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 1px;
            height: 60%;
            background: #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .input-group .form-control {
            border-left: none;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-left: none;
            font-size: 0.9375rem;
            background-color: #ffffff;
            transition: all 0.3s ease;
        }
        
        .input-group:focus-within .input-group-text {
            border-color: #667eea;
            background-color: #f0f4ff;
            color: #667eea;
        }
        
        .input-group:focus-within .form-control {
            border-color: #667eea;
            background-color: #ffffff;
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 0.625rem;
            font-size: 1rem;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.5);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 0.75rem;
            border: none;
            padding: 1rem 1.25rem;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 2px dashed #e5e7eb;
            position: relative;
        }
        
        .register-link::before {
            content: '';
            position: absolute;
            top: -2px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, transparent, #667eea, transparent);
        }
        
        .register-link p {
            margin: 0 0 1rem 0;
            font-size: 0.875rem;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 500;
        }
        
        .register-link .btn {
            border-radius: 0.625rem;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .register-link .btn:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.3);
        }
        
        .password-toggle-wrapper {
            position: relative;
        }
        
        .password-toggle-icon {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #9ca3af;
            font-size: 1.1rem;
            z-index: 10;
            transition: all 0.3s ease;
            padding: 0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            line-height: 1;
        }
        
        .password-toggle-icon:hover {
            color: #667eea;
            background-color: #f0f4ff;
        }
        
        .password-toggle-wrapper .form-control {
            padding-right: 46px;
        }
        
        .invalid-feedback {
            display: none !important;
        }
        
        .form-text {
            font-size: 0.8125rem;
            color: #6b7280;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }
        
        .form-text i {
            font-size: 0.875rem;
        }
        
        .login-loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(102, 126, 234, 0.95);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            animation: fadeIn 0.3s ease;
        }
        
        .login-loading-content {
            text-align: center;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease;
        }
        
        @media (max-width: 576px) {
            .login-container {
                padding: 2rem 1.5rem;
            }
            
            .login-header h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container fade-in">
        <div class="login-header">
            <div class="icon-wrapper">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h2><i class="bi bi-gem me-2" style="font-size: 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i>Welcome to LernovaAI</h2>
            <p><i class="bi bi-mortarboard-fill"></i> Sign in to continue your learning journey</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Error!</strong> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['logout_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <strong>Success!</strong> <?php echo htmlspecialchars($_SESSION['logout_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['logout_message']); ?>
        <?php endif; ?>

        <form action="login.php" method="POST" id="loginForm" class="needs-validation" novalidate>
            <div class="mb-3">
                <label for="username" class="form-label">
                    <i class="bi bi-person-circle"></i> Username
                </label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person-circle-fill"></i></span>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required>
                    <div class="invalid-feedback">
                        <i class="bi bi-exclamation-circle me-1"></i> Please provide a valid username.
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">
                    <i class="bi bi-shield-lock"></i> Password
                </label>
                <div class="input-group password-toggle-wrapper">
                    <span class="input-group-text"><i class="bi bi-shield-lock-fill"></i></span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                    <i class="bi bi-eye password-toggle-icon" id="togglePassword" data-bs-toggle="tooltip" data-bs-placement="right" title="Show/Hide Password"></i>
                </div>
            </div>
            <div class="d-grid gap-2 mt-3">
                <button type="submit" class="btn btn-login" id="loginBtn">
                    <i class="bi bi-box-arrow-in-right-fill me-2"></i> <span class="btn-text">Sign In</span>
                    <i class="bi bi-arrow-right ms-2"></i>
                </button>
            </div>
        </form>
        
        <!-- Loading Overlay -->
        <div id="loginLoadingOverlay" class="login-loading-overlay" style="display: none;">
            <div class="login-loading-content">
                <div class="spinner-border text-light mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="text-light mb-2"><i class="bi bi-hourglass-split"></i> Signing In</h5>
                <p class="text-light mb-0">Please wait while we verify your credentials...</p>
            </div>
        </div>
        
        <div class="register-link">
            <p class="mb-2">
                <i class="bi bi-question-circle text-muted me-1"></i>
                Don't have an account?
            </p>
            <a href="register.php" class="btn btn-outline-primary btn-sm w-100">
                <i class="bi bi-person-plus-fill me-2"></i>Create New Account
            </a>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        // Bootstrap form validation
        (function() {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    } else {
                        // Show loading animation on valid form submission
                        const loadingOverlay = document.getElementById('loginLoadingOverlay');
                        const loginBtn = document.getElementById('loginBtn');
                        const btnText = loginBtn.querySelector('.btn-text');
                        
                        if (loadingOverlay && loginBtn) {
                            loadingOverlay.style.display = 'flex';
                            loginBtn.disabled = true;
                            if (btnText) {
                                btnText.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Signing In...';
                            }
                        }
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();

        // Toggle password visibility
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const password = document.getElementById('password');
            
            if (togglePassword && password) {
                togglePassword.addEventListener('click', function() {
                    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                    password.setAttribute('type', type);
                    
                    // Toggle icon
                    this.classList.toggle('bi-eye');
                    this.classList.toggle('bi-eye-slash');
                });
            }
            
            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>