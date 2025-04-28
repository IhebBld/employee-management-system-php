<?php 
session_start(); 

// Check if user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit();
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();  // Destroy the session
    header('Location: auth.php');  // Redirect to login page (auth.php)
    exit();
}

// Get user role from session - using 'role' to match your departments.php
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
       
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome to the Dashboard</h1>
        
        <nav>
            <!-- Employee link is visible to all roles -->
            <a href="employees.php">View Employees</a> 
            
            <?php if ($user_role === 'admin'): ?>
                <!-- Department link is only visible to admins -->
                | <a href="departments.php">View Departments</a>
            <?php endif; ?>
            
            | <a href="?action=logout">Logout</a>
        </nav>
        
        <?php if ($user_role === 'admin'): ?>
            <div class="admin-section">
                <h2>Administration Options</h2>
                <p>As an administrator, you have full access to all system features.</p>
                <!-- Additional admin-only content can go here -->
            </div>
        <?php elseif ($user_role === 'department_head'): ?>
            <div class="dept-head-section">
                <h2>Department Management</h2>
                <p>As a department head, you can manage employees in your department.</p>
                <!-- Department head specific content can go here -->
            </div>
        <?php endif; ?>
    </div>
</body>
</html>