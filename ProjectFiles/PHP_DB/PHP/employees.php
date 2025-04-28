<?php
session_start();

// Database connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=company_db;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit();
}

// Get user role and department ID from session
$user_role = $_SESSION['role'] ?? null;
$user_dept_id = $_SESSION['dept_id'] ?? null;

// Initialize messages
$error_message = '';
$success_message = '';

// Form state variables
$show_add_form = isset($_GET['show_add_form']);
$edit_emp_id = isset($_GET['edit_emp_id']) ? (int)$_GET['edit_emp_id'] : null;
$employee_to_edit = null;

// Search and filter parameters
$search_query = $_GET['search'] ?? '';
$filter_by = $_GET['filter'] ?? 'none'; // none, department, name, position

// Fetch all departments for dropdowns (admins see all, dept heads see only their dept)
try {
    if ($user_role === 'admin') {
        $stmt = $pdo->query("SELECT dept_id, dept_name FROM departments");
    } else {
        $stmt = $pdo->prepare("SELECT dept_id, dept_name FROM departments WHERE dept_id = ?");
        $stmt->execute([$user_dept_id]);
    }
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching departments: " . $e->getMessage();
    $departments = [];
}

// Handle Add Employee Form Submission (Admin only)
if ($user_role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    // Validate input
    if (empty($_POST['name']) || empty($_POST['first_name']) || empty($_POST['phone_number'])) {
        $error_message = "Please fill in all required fields.";
    } else {
        try {
            // Get department name if department is selected
            $dept_name = null;
            if (!empty($_POST['dept_id'])) {
                $deptStmt = $pdo->prepare("SELECT dept_name FROM departments WHERE dept_id = ?");
                $deptStmt->execute([$_POST['dept_id']]);
                $dept = $deptStmt->fetch();
                $dept_name = $dept ? $dept['dept_name'] : null;
            }
            
            // Insert the employee
            $stmt = $pdo->prepare("INSERT INTO employees (name, first_name, phone_number, email, position, dept_id, dept_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['name'],
                $_POST['first_name'],
                $_POST['phone_number'],
                $_POST['email'] ?? '',
                $_POST['position'] ?? '',
                $_POST['dept_id'] === '' ? null : $_POST['dept_id'],
                $dept_name
            ]);

            $success_message = "Employee added successfully.";
            $show_add_form = false;
        } catch (PDOException $e) {
            $error_message = "Error adding employee: " . $e->getMessage();
        }
    }
}

// Handle Update Employee Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee'])) {
    // Validate input
    if (empty($_POST['edit_name']) || empty($_POST['edit_first_name']) || empty($_POST['edit_phone_number'])) {
        $error_message = "Please fill in all required fields.";
    } else {
        try {
            $emp_id = (int)$_POST['edit_emp_id'];
            $can_update = false;
            
            // Check permissions
            if ($user_role === 'admin') {
                $can_update = true;
            } elseif ($user_role === 'dept_head') {
                // Verify employee belongs to department head's department
                $stmt = $pdo->prepare("SELECT dept_id FROM employees WHERE emp_id = ?");
                $stmt->execute([$emp_id]);
                $emp = $stmt->fetch();
                
                if ($emp && $emp['dept_id'] == $user_dept_id) {
                    $can_update = true;
                } else {
                    $error_message = "You can only update employees in your department.";
                }
            }
            
            if ($can_update) {
                // Get department info
                $dept_name = null;
                $dept_id = null;
                
                if ($user_role === 'admin') {
                    // Admins can change department
                    $dept_id = $_POST['edit_dept_id'] === '' ? null : $_POST['edit_dept_id'];
                    if ($dept_id) {
                        $deptStmt = $pdo->prepare("SELECT dept_name FROM departments WHERE dept_id = ?");
                        $deptStmt->execute([$dept_id]);
                        $dept = $deptStmt->fetch();
                        $dept_name = $dept ? $dept['dept_name'] : null;
                    }
                } else {
                    // Department heads can't change department - use their department
                    $dept_id = $user_dept_id;
                    $dept_name = $_SESSION['dept_name'] ?? null;
                }
                
                // Update the employee
                $stmt = $pdo->prepare("UPDATE employees SET name = ?, first_name = ?, phone_number = ?, email = ?, position = ?, dept_id = ?, dept_name = ? WHERE emp_id = ?");
                $stmt->execute([
                    $_POST['edit_name'],
                    $_POST['edit_first_name'],
                    $_POST['edit_phone_number'],
                    $_POST['edit_email'] ?? '',
                    $_POST['edit_position'] ?? '',
                    $dept_id,
                    $dept_name,
                    $emp_id
                ]);

                $success_message = "Employee updated successfully.";
                $edit_emp_id = null;
                
                // Preserve search parameters after update
                $search_params = [];
                if (!empty($search_query)) $search_params[] = "search=" . urlencode($search_query);
                if ($filter_by != 'none') $search_params[] = "filter=" . urlencode($filter_by);
                $query_string = !empty($search_params) ? '?' . implode('&', $search_params) : '';
                
                header("Location: employees.php$query_string");
                exit();
            }
        } catch (PDOException $e) {
            $error_message = "Error updating employee: " . $e->getMessage();
        }
    }
}

// Handle Delete Employee
if (isset($_GET['delete_employee']) && is_numeric($_GET['delete_employee'])) {
    $delete_emp_id = (int)$_GET['delete_employee'];
    $can_delete = false;
    
    // Check permissions
    if ($user_role === 'admin') {
        $can_delete = true;
    } elseif ($user_role === 'dept_head') {
        // Verify employee belongs to department head's department
        $stmt = $pdo->prepare("SELECT dept_id FROM employees WHERE emp_id = ?");
        $stmt->execute([$delete_emp_id]);
        $emp = $stmt->fetch();
        
        if ($emp && $emp['dept_id'] == $user_dept_id) {
            $can_delete = true;
        } else {
            $error_message = "You can only delete employees in your department.";
        }
    }
    
    if ($can_delete) {
        try {
            $stmt = $pdo->prepare("DELETE FROM employees WHERE emp_id = ?");
            $stmt->execute([$delete_emp_id]);
            $success_message = "Employee deleted successfully.";
            
            // Preserve search parameters after delete
            $search_params = [];
            if (!empty($search_query)) $search_params[] = "search=" . urlencode($search_query);
            if ($filter_by != 'none') $search_params[] = "filter=" . urlencode($filter_by);
            $query_string = !empty($search_params) ? '?' . implode('&', $search_params) : '';
            
            header("Location: employees.php$query_string");
            exit();
        } catch (PDOException $e) {
            $error_message = "Error deleting employee: " . $e->getMessage();
        }
    }
}

// If in edit mode, load the employee data
if ($edit_emp_id) {
    try {
        if ($user_role === 'dept_head') {
            // Department heads can only edit employees in their department
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = ? AND dept_id = ?");
            $stmt->execute([$edit_emp_id, $user_dept_id]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = ?");
            $stmt->execute([$edit_emp_id]);
        }
        
        $employee_to_edit = $stmt->fetch();
        
        if (!$employee_to_edit) {
            $error_message = "Employee not found or you don't have permission to edit this employee.";
            $edit_emp_id = null;
        }
    } catch (PDOException $e) {
        $error_message = "Error loading employee data: " . $e->getMessage();
        $edit_emp_id = null;
    }
}

// Fetch employees based on user role and search/filter
try {
    $query = "
        SELECT e.*, d.dept_name 
        FROM employees e
        LEFT JOIN departments d ON e.dept_id = d.dept_id
    ";
    
    $conditions = [];
    $params = [];
    
    // Apply department filter for department heads
    if ($user_role === 'dept_head') {
        $conditions[] = "e.dept_id = ?";
        $params[] = $user_dept_id;
    }
    
    // Apply search filter if search query is provided
    if (!empty($search_query)) {
        $search_term = "%$search_query%";
        
        switch ($filter_by) {
            case 'department':
                $conditions[] = "d.dept_name LIKE ?";
                $params[] = $search_term;
                break;
            case 'name':
                $conditions[] = "(e.name LIKE ? OR e.first_name LIKE ?)";
                $params[] = $search_term;
                $params[] = $search_term;
                break;
            case 'position':
                $conditions[] = "e.position LIKE ?";
                $params[] = $search_term;
                break;
            default: // 'none' or any other value
                $conditions[] = "(e.name LIKE ? OR e.first_name LIKE ? OR e.position LIKE ? OR d.dept_name LIKE ?)";
                $params[] = $search_term;
                $params[] = $search_term;
                $params[] = $search_term;
                $params[] = $search_term;
                break;
        }
    }
    
    // Build the complete query
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $employees = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching employees: " . $e->getMessage();
    $employees = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
       
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }

    </style>
</head>
<body>
    <div class="navigation">
        <a href="index.php">Home</a>
        <?php if ($user_role === 'admin' ): ?>
        <a href="departments.php">Departments</a>
        <?php endif; ?>
        <a href="employees.php">Employees</a>
    </div>

    <h1>Employee Management</h1>
    
    <?php if (!empty($error_message)): ?>
        <div class="message error"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
        <div class="message success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
   
        
    <!-- Search and Filter Form -->
    <form method="GET" action="employees.php" class="search-container">
        <input type="text" name="search" placeholder="Search employees..." value="<?= htmlspecialchars($search_query) ?>">
        <select name="filter">
            <option value="none" <?= $filter_by === 'none' ? 'selected' : '' ?>>Search All</option>
            <option value="name" <?= $filter_by === 'name' ? 'selected' : '' ?>>Name</option>
            <option value="position" <?= $filter_by === 'position' ? 'selected' : '' ?>>Position</option>
            <?php if ($user_role === 'admin' ): ?><option value="department" <?= $filter_by === 'department' ? 'selected' : '' ?>>Department</option><?php endif; ?>
        </select>
        <button type="submit">Search</button>
        <?php if (!empty($search_query)): ?>
            <a href="employees.php">Clear Search</a>
        <?php endif; ?>
    </form>

    <?php if ($user_role === 'admin' && $show_add_form): ?>
        <div class="form-container">
            <h2>Add New Employee</h2>
            <form method="POST" action="employees.php">
                <div class="form-group">
                    <label for="name">Last Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="first_name">First Name:</label>
                    <input type="text" id="first_name" name="first_name" required>
                </div>
                
                <div class="form-group">
                    <label for="phone_number">Phone Number:</label>
                    <input type="text" id="phone_number" name="phone_number" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email">
                </div>
                
                <div class="form-group">
                    <label for="position">Position:</label>
                    <input type="text" id="position" name="position">
                </div>
                
                <div class="form-group">
                    <label for="dept_id">Department:</label>
                    <select id="dept_id" name="dept_id">
                        <option value="">None</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept['dept_id']) ?>">
                                <?= htmlspecialchars($dept['dept_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <input type="submit" name="add_employee" value="Add Employee">
                <a href="employees.php"><button type="button" class="cancel">Cancel</button></a>
            </form>
        </div>
    <?php elseif ($user_role === 'admin' && !$edit_emp_id): ?>
        <p><a href="employees.php?show_add_form=1">Add New Employee</a></p>
    <?php endif; ?>

    <?php if ($edit_emp_id && $employee_to_edit): ?>
        <div class="form-container">
            <h2>Edit Employee</h2>
            <form method="POST" action="employees.php">
                <input type="hidden" name="edit_emp_id" value="<?= $employee_to_edit['emp_id'] ?>">
                <?php if (!empty($search_query)): ?>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter_by) ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="edit_name">Last Name:</label>
                    <input type="text" id="edit_name" name="edit_name" value="<?= htmlspecialchars($employee_to_edit['name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_first_name">First Name:</label>
                    <input type="text" id="edit_first_name" name="edit_first_name" value="<?= htmlspecialchars($employee_to_edit['first_name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_phone_number">Phone Number:</label>
                    <input type="text" id="edit_phone_number" name="edit_phone_number" value="<?= htmlspecialchars($employee_to_edit['phone_number']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_email">Email:</label>
                    <input type="email" id="edit_email" name="edit_email" value="<?= htmlspecialchars($employee_to_edit['email'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="edit_position">Position:</label>
                    <input type="text" id="edit_position" name="edit_position" value="<?= htmlspecialchars($employee_to_edit['position'] ?? '') ?>">
                </div>
                
                <?php if ($user_role === 'admin'): ?>
                    <div class="form-group">
                        <label for="edit_dept_id">Department:</label>
                        <select id="edit_dept_id" name="edit_dept_id">
                            <option value="">None</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept['dept_id']) ?>" 
                                    <?= ($dept['dept_id'] == $employee_to_edit['dept_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['dept_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="edit_dept_id" value="<?= htmlspecialchars($employee_to_edit['dept_id'] ?? '') ?>">
                    <div class="form-group">
                        <label>Department:</label>
                        <div><?= htmlspecialchars($employee_to_edit['dept_name'] ?? 'None') ?></div>
                    </div>
                <?php endif; ?>
                
                <input type="submit" name="update_employee" value="Update Employee">
                <a href="employees.php<?= !empty($search_query) ? '?search=' . urlencode($search_query) . '&filter=' . urlencode($filter_by) : '' ?>">
                    <button type="button" class="cancel">Cancel</button>
                </a>
            </form>
        </div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Last Name</th>
                <th>First Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Position</th>
                <th>Department</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($employees)): ?>
                <tr>
                    <td colspan="8">No employees found<?= !empty($search_query) ? ' matching your search' : '' ?>.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($employees as $employee): ?>
                    <tr>
                        <td><?= htmlspecialchars($employee['emp_id']) ?></td>
                        <td><?= htmlspecialchars($employee['name']) ?></td>
                        <td><?= htmlspecialchars($employee['first_name']) ?></td>
                        <td><?= htmlspecialchars($employee['phone_number']) ?></td>
                        <td><?= htmlspecialchars($employee['email'] ?? '') ?></td>
                        <td><?= htmlspecialchars($employee['position'] ?? '') ?></td>
                        <td><?= htmlspecialchars($employee['dept_name'] ?? '') ?></td>
                        <td>
                            <?php if ($user_role === 'admin' || ($user_role === 'dept_head' && $employee['dept_id'] == $user_dept_id)): ?>
                                <a href="employees.php?edit_emp_id=<?= $employee['emp_id'] ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) . '&filter=' . urlencode($filter_by) : '' ?>">Edit</a> | 
                                <a href="employees.php?delete_employee=<?= $employee['emp_id'] ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) . '&filter=' . urlencode($filter_by) : '' ?>" 
                                   onclick="return confirm('Are you sure you want to delete <?= htmlspecialchars(addslashes($employee['name'])) ?>?')">
                                    Delete
                                </a>
                            <?php else: ?>
                                No access
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>