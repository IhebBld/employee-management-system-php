<?php
session_start();

// Database connection
$pdo = new PDO('mysql:host=localhost;dbname=company_db;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit();
}

$departments = [];
$error_message = '';
$success_message = '';
$edit_dept = null;

if ($_SESSION['role'] === 'admin') {
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!empty($_POST['add_dept_name'])) {
                $deptName = trim($_POST['add_dept_name']);
                if ($deptName !== '') {
                    $stmt = $pdo->prepare("INSERT INTO departments (dept_name) VALUES (?)");
                    $stmt->execute([$deptName]);
                    $success_message = "New department added successfully.";
                } else {
                    $error_message = "Department name cannot be empty.";
                }
            }

            if (!empty($_POST['edit_dept_id']) && is_numeric($_POST['edit_dept_id'])) {
                $deptId = $_POST['edit_dept_id'];
                $deptName = trim($_POST['edit_dept_name']);
                if ($deptName !== '') {
                    $stmt = $pdo->prepare("UPDATE departments SET dept_name = ? WHERE dept_id = ?");
                    $stmt->execute([$deptName, $deptId]);
                    $success_message = "Department updated successfully.";
                } else {
                    $error_message = "Department name cannot be empty.";
                }
            }
        }

        if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
            $deptId = $_GET['delete'];
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM departments WHERE dept_id = ?");
            $stmt->execute([$deptId]);
            $department = $stmt->fetch();

            if ($department) {
                $deleteStmt = $pdo->prepare("DELETE FROM departments WHERE dept_id = ?");
                $deleteStmt->execute([$deptId]);
                $pdo->commit();
                $success_message = "Department '{$department['dept_name']}' successfully deleted.";
            } else {
                $pdo->rollBack();
                $error_message = "Department not found.";
            }
        }

        if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
            $editId = $_GET['edit'];
            $stmt = $pdo->prepare("SELECT * FROM departments WHERE dept_id = ?");
            $stmt->execute([$editId]);
            $edit_dept = $stmt->fetch();

            if (!$edit_dept) {
                $error_message = "Department not found for editing.";
            }
        }

        $stmt = $pdo->query("
            SELECT d.dept_id, d.dept_name, d.nb_emp,
                   CONCAT(e.first_name, ' ', e.name) AS dept_head
            FROM departments d
            LEFT JOIN employees e ON d.dept_id = e.dept_id AND e.position LIKE '%Department Head%'
            ORDER BY d.dept_name
        ");
        $departments = $stmt->fetchAll();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Departments Dashboard</title>
    <style>
        .department-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        .department-card {
            border: 1px solid #ddd;
            padding: 10px;
            width: 200px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
    </style>
</head>
<body>

<div>
    <a href="index.php">Home</a>
    <a href="departments.php">Departments</a>
    <a href="employees.php">Employees</a>
</div>

<h1>Department Dashboard</h1>

<?php if ($error_message): ?>
    <div><?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<?php if ($success_message): ?>
    <div><?= htmlspecialchars($success_message) ?></div>
<?php endif; ?>

<?php if ($_SESSION['role'] !== 'admin'): ?>
    <p>You don't have permission to manage departments.</p>
<?php else: ?>
    <?php if (empty($departments)): ?>
        <p>No department data found.</p>
    <?php else: ?>
        <div class="department-cards">
            <?php foreach ($departments as $dept): ?>
                <div class="department-card">
                    <h3><?= htmlspecialchars($dept['dept_name']) ?></h3>
                    <div><strong>Head:</strong> <?= $dept['dept_head'] ? htmlspecialchars($dept['dept_head']) : 'Not assigned' ?></div>
                    <div><strong>Employees:</strong> <?= htmlspecialchars($dept['nb_emp']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <h2>Manage Departments</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Department</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($departments as $dept): ?>
                    <tr>
                        <td><?= htmlspecialchars($dept['dept_id']) ?></td>
                        <td><?= htmlspecialchars($dept['dept_name']) ?></td>
                        <td>
                            <form method="GET" action="departments.php" style="display:inline;">
                                <input type="hidden" name="edit" value="<?= $dept['dept_id'] ?>">
                                <input type="submit" value="Edit">
                            </form>

                            <form method="GET" action="departments.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete the department \'<?= htmlspecialchars(addslashes($dept['dept_name'])) ?>\'?\n\nNote: Employees in this department will be kept but their department reference will be set to NULL.');">
                                <input type="hidden" name="delete" value="<?= $dept['dept_id'] ?>">
                                <input type="submit" value="Delete">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($edit_dept): ?>
        <div>
            <h2>Edit Department</h2>
            <form method="POST" action="departments.php">
                <input type="hidden" name="edit_dept_id" value="<?= htmlspecialchars($edit_dept['dept_id']) ?>">
                
                <label for="edit_dept_name">Department Name:</label>
                <input type="text" id="edit_dept_name" name="edit_dept_name" value="<?= htmlspecialchars($edit_dept['dept_name']) ?>" required><br>
                
                <input type="submit" value="Update Department">
                <a href="departments.php">Cancel</a>
            </form>
        </div>
    <?php else: ?>
        <div>
            <h2>Add New Department</h2>
            <form method="POST" action="departments.php">
                <label for="add_dept_name">Department Name:</label>
                <input type="text" id="add_dept_name" name="add_dept_name" required><br>
                
                <input type="submit" value="Add Department">
            </form>
        </div>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>
