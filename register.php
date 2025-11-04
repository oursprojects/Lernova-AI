<?php
/*
 * LernovaAI - Registration Page
 * This file handles new user registration.
 * (Admin option removed for security)
 */

// Include our database connection
require_once 'config/db.php';

// Initialize variables for messages
$message = '';
$error = '';

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get and sanitize form data
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    $role = $conn->real_escape_string($_POST['role']);

    // --- Validation ---
    if (empty($first_name) || empty($last_name) || empty($username) || empty($password) || empty($role)) {
        $error = "All fields are required.";
    } 
    // --- NEW SECURITY CHECK ---
    // Block any attempt to register as 'admin'
    elseif ($role == 'admin') {
        $error = "Admin registration is not permitted.";
    }
    // --- End Security Check ---
    else {
        // Check if username already exists
        $sql_check = "SELECT id FROM users WHERE username = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $error = "Username already exists. Please choose another one.";
        } else {
            // Hash the password for security
            $password_hashed = password_hash($password, PASSWORD_BCRYPT);

            // Create the SQL query using prepared statements
            $sql_insert = $conn->prepare("INSERT INTO users (first_name, last_name, username, password, role) VALUES (?, ?, ?, ?, ?)");
            
            // Bind parameters: "sssss" means 5 string parameters
            $sql_insert->bind_param("sssss", $first_name, $last_name, $username, $password_hashed, $role);

            // Execute the statement
            if ($sql_insert->execute()) {
                $message = "Registration successful! You can now log in.";
            } else {
                $error = "Error: " . $sql_insert->error;
            }

            // Close the statement
            $sql_insert->close();
        }
        $stmt_check->close();
    }
    
    // Close the connection
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - LernovaAI</title>
    
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
        
        .register-container {
            background: #ffffff;
            padding: 2rem 2rem;
            border-radius: 1.25rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.1);
            width: 100%;
            max-width: 450px;
            position: relative;
            overflow: hidden;
        }
        
        .register-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .register-header .icon-wrapper {
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
        
        .register-header .icon-wrapper::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 3px solid rgba(102, 126, 234, 0.2);
            animation: ripple 2s ease-out infinite;
        }
        
        .register-header .icon-wrapper i {
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
        
        .register-header h2 {
            color: #1a1a1a;
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 0.375rem;
            letter-spacing: -0.5px;
        }
        
        .register-header p {
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
        
        .input-group .form-control, .input-group .form-select {
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
        
        .input-group:focus-within .form-control,
        .input-group:focus-within .form-select {
            border-color: #667eea;
            background-color: #ffffff;
        }
        
        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }
        
        .form-text {
            font-size: 0.8125rem;
            color: #6b7280;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }
        
        .btn-register {
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
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.5);
        }
        
        .btn-register:active {
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
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 2px dashed #e5e7eb;
            position: relative;
        }
        
        .login-link::before {
            content: '';
            position: absolute;
            top: -2px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, transparent, #667eea, transparent);
        }
        
        .login-link p {
            margin: 0 0 1rem 0;
            font-size: 0.875rem;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 500;
        }
        
        .login-link .btn {
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
        
        .login-link .btn:hover {
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
        
        .row {
            margin-left: -0.5rem;
            margin-right: -0.5rem;
        }
        
        .row > * {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
        
        @media (max-width: 576px) {
            .register-container {
                padding: 2rem 1.5rem;
            }
            
            .register-header h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container fade-in">
        <div class="register-header">
            <div class="icon-wrapper">
                <i class="bi bi-person-plus-fill"></i>
            </div>
            <h2><i class="bi bi-gem me-2" style="font-size: 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i>Join LernovaAI</h2>
            <p><i class="bi bi-stars"></i> Create your account and start learning today</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <strong>Success!</strong> <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Error!</strong> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" class="needs-validation" novalidate>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="first_name" class="form-label">
                        <i class="bi bi-person"></i> First Name
                    </label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                        <input type="text" class="form-control" id="first_name" name="first_name" placeholder="First name" required>
                        <div class="invalid-feedback">
                            <i class="bi bi-exclamation-circle me-1"></i> Please provide your first name.
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="last_name" class="form-label">
                        <i class="bi bi-person"></i> Last Name
                    </label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person-badge-fill"></i></span>
                        <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Last name" required>
                        <div class="invalid-feedback">
                            <i class="bi bi-exclamation-circle me-1"></i> Please provide your last name.
                        </div>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label for="username" class="form-label">
                    <i class="bi bi-person-circle"></i> Username
                </label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-at"></i></span>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Choose a username" required>
                    <div class="invalid-feedback">
                        <i class="bi bi-exclamation-circle me-1"></i> Please choose a username.
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">
                    <i class="bi bi-shield-lock"></i> Password
                </label>
                <div class="input-group password-toggle-wrapper">
                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Create a password" required>
                    <i class="bi bi-eye password-toggle-icon" id="togglePassword" data-bs-toggle="tooltip" data-bs-placement="right" title="Show/Hide Password"></i>
                </div>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">
                    <i class="bi bi-person-badge"></i> I am a
                </label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person-badge-fill"></i></span>
                    <select class="form-select" id="role" name="role" required>
                        <option value=""><i class="bi bi-arrow-down-circle"></i> -- Select a Role --</option>
                        <option value="faculty">👨‍🏫 Teacher/Professor</option>
                        <option value="student">👨‍🎓 Student</option>
                    </select>
                    <div class="invalid-feedback">
                        <i class="bi bi-exclamation-circle me-1"></i> Please select a role.
                    </div>
                </div>
            </div>
            <div class="d-grid gap-2 mt-3">
                <button type="submit" class="btn btn-register">
                    <i class="bi bi-person-check-fill me-2"></i> Create Account
                    <i class="bi bi-arrow-right ms-2"></i>
                </button>
            </div>
        </form>
        
        <div class="login-link">
            <p class="mb-2">
                <i class="bi bi-check-circle text-muted me-1"></i>
                Already have an account?
            </p>
            <a href="login.php" class="btn btn-outline-primary btn-sm w-100">
                <i class="bi bi-box-arrow-in-right-fill me-2"></i>Sign In Here
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