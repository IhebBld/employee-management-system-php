<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error_message = '';
$success_message = '';

// Database connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=company_db;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && $password === $user['password']) { // Note: In production, use password_verify()
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['email'] = $user['email'];
                
                // Set role
                if (is_null($user['dept_id'])) {
                    $_SESSION['role'] = 'admin';
                } else {
                    $_SESSION['role'] = 'dept_head';
                    $_SESSION['dept_id'] = $user['dept_id'];

                    $deptStmt = $pdo->prepare("SELECT dept_name FROM departments WHERE dept_id = ?");
                    $deptStmt->execute([$user['dept_id']]);
                    $dept = $deptStmt->fetch();
                    $_SESSION['dept_name'] = $dept['dept_name'] ?? 'Unknown Department';
                }
                
                header('Location: index.php');
                exit();
            } else {
                $error_message = $user ? "Invalid password." : "User not found.";
            }
        } catch (PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Company Management System</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .message { color: red; margin-bottom: 15px; }
        .form-group { margin-bottom: 10px; }
    </style>
</head>
<body>
    <h1>Company Management System</h1>

    <?php if ($error_message): ?>
        <div class="message"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="message" style="color:green;"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <div>
        <h2>Login</h2>
        <form method="POST" action="auth.php">
            <div class="form-group">
                <label for="email">Email:</label><br>
                <input type="email" name="email" id="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label><br>
                <input type="password" name="password" id="password" required>
                <br>
                <input type="checkbox" id="show-password"> Show Password
            </div>
            <div class="form-group">
                <button type="submit" name="login">Login</button>
            </div>
        </form>
    </div>

    <p><strong>Demo credentials:</strong></p>
    <ul>
        <li><strong>Admin:</strong> admin@company.com / admin_password</li>
        <li><strong>Department Head:</strong> Use email of any department head / password</li>
    </ul>

    <script>
        const passwordInput = document.getElementById('password');
        const showPasswordCheckbox = document.getElementById('show-password');

        showPasswordCheckbox.addEventListener('change', function () {
            passwordInput.type = this.checked ? 'text' : 'password';
        });
    </script>
</body>
</html>
