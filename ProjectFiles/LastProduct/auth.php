<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: emp.php');
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
                
                header('Location: emp.php');
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
    <title>Project#12</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="c4.css">
</head>
<body class="login-page">
    <div class="login-form">
        <h3>LOGIN TO YOUR ACCOUNT</h3>
        
        <?php if ($error_message): ?>
            <div style="color: var(--danger); background-color: rgba(247, 37, 133, 0.1); padding: 12px; border-radius: var(--radius); margin-bottom: 15px; font-size: 14px; text-align: center;">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div style="color: var(--success); background-color: rgba(76, 201, 240, 0.1); padding: 12px; border-radius: var(--radius); margin-bottom: 15px; font-size: 14px; text-align: center;">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="auth.php">
            <div class="form-group">
                <label for="login-email">Email</label>
                <input type="text" id="login-email" name="email" placeholder="Enter your email" required />
            </div>
            <div class="form-group">
                <label for="login-password">Password</label>
                <input type="password" id="login-password" name="password" placeholder="Enter your password" required />
                <div style="display: flex; align-items: center; margin-top: 8px; color: var(--text-light); font-size: 13px;">
                    <input type="checkbox" id="show-password" style="width: auto; margin-right: 6px;"> 
                    <label for="show-password" style="margin-bottom: 0; cursor: pointer;">Show Password</label>
                </div>
            </div>
            <button type="submit" name="login" class="login-btn btn-outline">SIGN IN</button>
        </form>

    </div>
    
    <script>
        const passwordInput = document.getElementById('login-password');
        const showPasswordCheckbox = document.getElementById('show-password');

        showPasswordCheckbox.addEventListener('change', function () {
            passwordInput.type = this.checked ? 'text' : 'password';
        });
    </script>
</body>
</html>