<?php
/*
 * LernovaAI - Admin Dashboard (Manage Users)
 * This is the main page for the Admin role.
 */

// 1. Include the header. This file also starts the session and checks if we are logged in.
require_once '../includes/admin_header.php';
// 2. Include the database connection.
require_once '../config/db.php';

$message = '';
$error = '';

// --- Handle Form Submissions (Create, Update, Delete) ---

// --- ACTION: CREATE NEW USER ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'create') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? '');

    // Validate all fields
    if (empty($first_name) || empty($last_name) || empty($username) || empty($password) || empty($role)) {
        $error = "All fields are required for new user.";
    } elseif (!in_array($role, ['admin', 'faculty', 'student'])) {
        $error = "Invalid role selected.";
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = "Username must be between 3 and 50 characters.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (strlen($first_name) > 100 || strlen($last_name) > 100) {
        $error = "Name fields must be less than 100 characters.";
    } else {
        // Check if username already exists
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $error = "Username already exists.";
        } else {
            // Hash password and insert user
            $password_hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt_insert = $conn->prepare("INSERT INTO users (first_name, last_name, username, password, role) VALUES (?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("sssss", $first_name, $last_name, $username, $password_hashed, $role);
            if ($stmt_insert->execute()) {
                $message = "User created successfully.";
            } else {
                $error = "Failed to create user: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}

// --- ACTION: DELETE USER ---
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $user_id_to_delete = intval($_GET['id']);
    
    if ($user_id_to_delete <= 0) {
        $error = "Invalid user ID.";
    } elseif ($user_id_to_delete == $_SESSION['user_id']) {
        // Safety check: Don't let an admin delete themselves
        $error = "You cannot delete your own account.";
    } else {
        // Verify user exists before deleting
        $stmt_verify = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $stmt_verify->bind_param("i", $user_id_to_delete);
        $stmt_verify->execute();
        
        if ($stmt_verify->get_result()->num_rows == 0) {
            $error = "User not found.";
        } else {
            $stmt_delete = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt_delete->bind_param("i", $user_id_to_delete);
            if ($stmt_delete->execute()) {
                $message = "User deleted successfully.";
            } else {
                $error = "Failed to delete user: " . $stmt_delete->error;
            }
            $stmt_delete->close();
        }
        $stmt_verify->close();
    }
}


// --- Fetch All Users for Display ---
// We run this query *after* any create/delete actions to get the most up-to-date list
$sql_users = "SELECT id, first_name, last_name, username, role, created_at FROM users";
$result_users = $conn->query($sql_users);

// --- Fetch Statistics ---
$stats_total_users = $result_users->num_rows;
$stats_faculty = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'faculty'")->fetch_assoc()['count'];
$stats_students = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'];
$stats_subjects = $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'];
?>

<h1><i class="bi bi-speedometer2"></i> Dashboard Overview</h1>
<p>Manage users and monitor system statistics.</p>

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
        <div class="card text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50">Total Users</h6>
                        <h2 class="mb-0"><?php echo $stats_total_users; ?></h2>
                    </div>
                    <i class="bi bi-people-fill" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #4F46E5 0%, #6366F1 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50">Faculty Members</h6>
                        <h2 class="mb-0"><?php echo $stats_faculty; ?></h2>
                    </div>
                    <i class="bi bi-mortarboard-fill" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); border: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50">Students</h6>
                        <h2 class="mb-0"><?php echo $stats_students; ?></h2>
                    </div>
                    <i class="bi bi-graduation-cap-fill" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #F59E0B 0%, #F97316 100%); border: none;">
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
</div>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <div>
        <h2 class="mb-0"><i class="bi bi-person-gear"></i> User Management</h2>
        <p class="text-muted mb-0" style="font-size: 0.875rem;">Create, view, and delete user accounts for the system</p>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="bi bi-person-plus"></i> Create New User</h3>
    </div>
    <div class="card-body">
        <form action="index.php" method="POST">
            <input type="hidden" name="action" value="create">
            <div class="row g-2">
                <div class="col-md-6">
                    <label for="first_name" class="form-label"><i class="bi bi-person text-primary"></i> First Name</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" placeholder="Enter first name" required>
                </div>
                <div class="col-md-6">
                    <label for="last_name" class="form-label"><i class="bi bi-person text-primary"></i> Last Name</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Enter last name" required>
                </div>
                <div class="col-md-4">
                    <label for="username" class="form-label"><i class="bi bi-at text-primary"></i> Username</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Enter username" required>
                </div>
                <div class="col-md-4">
                    <label for="password" class="form-label"><i class="bi bi-lock text-primary"></i> Password</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
                </div>
                <div class="col-md-4">
                    <label for="role" class="form-label"><i class="bi bi-person-badge text-primary"></i> Role</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="">-- Select Role --</option>
                        <option value="admin">Admin</option>
                        <option value="faculty">Faculty</option>
                        <option value="student">Student</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-check"></i> Create User
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="bi bi-list-ul"></i> Existing Users</h3>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th><i class="bi bi-hash"></i> ID</th>
                    <th><i class="bi bi-person-circle"></i> Name</th>
                    <th><i class="bi bi-at"></i> Username</th>
                    <th><i class="bi bi-person-badge"></i> Role</th>
                    <th><i class="bi bi-calendar"></i> Date Joined</th>
                    <th><i class="bi bi-gear"></i> Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Loop through the query results and display users
                if ($result_users->num_rows > 0) {
                    while($row = $result_users->fetch_assoc()) {
                        // Determine icon based on role
                        $role_icon = '';
                        $role_badge = '';
                        if ($row['role'] == 'admin') {
                            $role_icon = '<i class="bi bi-shield-check text-warning"></i>';
                            $role_badge = '<span class="badge bg-danger">Admin</span>';
                        } elseif ($row['role'] == 'faculty') {
                            $role_icon = '<i class="bi bi-mortarboard text-primary"></i>';
                            $role_badge = '<span class="badge bg-primary">Faculty</span>';
                        } else {
                            $role_icon = '<i class="bi bi-graduation-cap text-success"></i>';
                            $role_badge = '<span class="badge bg-success">Student</span>';
                        }
                        
                        echo "<tr>";
                        echo "<td>" . $row['id'] . "</td>";
                        echo "<td>" . htmlspecialchars($row['first_name']) . " " . htmlspecialchars($row['last_name']) . "</td>";
                        echo "<td><i class=\"bi bi-at text-muted\"></i> " . htmlspecialchars($row['username']) . "</td>";
                        echo "<td>" . $role_icon . " " . $role_badge . "</td>";
                        echo "<td><i class=\"bi bi-calendar3 text-muted\"></i> " . date("M j, Y", strtotime($row['created_at'])) . "</td>";
                        echo "<td>";
                        
                        if ($row['id'] != $_SESSION['user_id']) {
                            echo "<a href='index.php?action=delete&id=" . $row['id'] . "' 
                                   data-message='Are you sure you want to delete this user? This action cannot be undone.' 
                                   data-href='index.php?action=delete&id=" . $row['id'] . "' 
                                   class='btn btn-sm btn-danger delete-btn'>
                                   <i class=\"bi bi-trash\"></i> Delete
                                   </a>";
                        } else {
                            echo "<span class=\"badge bg-secondary\"><i class=\"bi bi-person-check me-1\"></i>Current User</span>";
                        }
                        
                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='6' class='text-center py-4'>";
                    echo "<div class='empty-state'>";
                    echo "<i class='bi bi-inbox'></i>";
                    echo "<p class='mb-2'>No users found.</p>";
                    echo "</div>";
                    echo "</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// 3. Include the footer
require_once '../includes/admin_footer.php';
// 4. Close the database connection
$conn->close();
?>