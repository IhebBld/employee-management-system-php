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
                
                header("Location: emp.php$query_string");
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
            
            header("Location: emp.php$query_string");
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
    <!-- Meta tags for character set and responsive viewport -->
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    
    <!-- Page title -->
    <title>Project#12 - Employee Management</title>
    
    <!-- External CSS libraries -->
    <!-- Font Awesome for icons -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
    />
    <!-- Animate.css for animations -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"
    />
    <!-- Google Fonts - Poppins -->
    <link
      href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <!-- Custom CSS file -->
    <link rel="stylesheet" href="c4.css" />
  </head>
  
  <body>
    <!-- Main container for the entire dashboard -->
    <div class="container">
      
      <!-- Header section with navigation -->
      <div class="header">
        <!-- Dashboard title -->
        <div>
          <h2>Project#12</h2>
        </div>

        <!-- Navigation buttons group 1 -->
        <div class="header-buttons">
          <!-- Employees button -->
          <a href="emp.php">
            <button class="btn btn-outline btn-sm">
              <i class="fas fa-users"></i> <span>Employees</span>
            </button>
          </a>
          
          <?php if ($user_role === 'admin'): ?>
          <!-- Departments button -->
          <a href="dep.php">
            <button class="btn btn-outline btn-sm">
              <i class="fas fa-building"></i> <span>Departments</span>
            </button>
          </a>
          <?php endif; ?>
        </div>

        <!-- Logout button -->
        <div class="header-buttons">
          <a href="logout.php">
            <button class="btn btn-outline btn-sm logout">
              <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
            </button>
          </a>
        </div>
      </div>

      <!-- Main content area -->
      <div class="main-content">
        
        <?php if (!empty($error_message)): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
          <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        
        <!-- Search and filter section -->
        <div class="page-title">
          <div class="search-sort-container">
            <!-- Search bar with icon -->
            <form method="GET" action="emp.php" class="search-bar">
              <i class="fas fa-search"></i>
              <input
                type="text"
                id="search"
                name="search"
                placeholder="Search employee..."
                value="<?= htmlspecialchars($search_query) ?>"
              />
            </form>
            
            <!-- Sorting dropdown -->
            <select name="filter" id="filter" class="styled-select" onchange="this.form.submit()">
              <option value="none" <?= $filter_by === 'none' ? 'selected' : '' ?>>Search All</option>
              <option value="name" <?= $filter_by === 'name' ? 'selected' : '' ?>>Name</option>
              <option value="position" <?= $filter_by === 'position' ? 'selected' : '' ?>>Position</option>
              <?php if ($user_role === 'admin'): ?>
                <option value="department" <?= $filter_by === 'department' ? 'selected' : '' ?>>Department</option>
              <?php endif; ?>
            </select>
          </div>

          <!-- Action buttons -->
          <div class="action-buttons">
            <?php if ($user_role === 'admin' && !$edit_emp_id && !$show_add_form): ?>
              <a href="emp.php?show_add_form=1">
                <button class="btn btn-outline btn-sm">
                  <i class="fas fa-plus"></i> Add Employee
                </button>
              </a>
            <?php endif; ?>
            
            <?php if (!empty($search_query)): ?>
              <a href="emp.php">
                <button class="btn btn-outline btn-sm">
                  <i class="fas fa-times"></i> Clear Search
                </button>
              </a>
            <?php endif; ?>
          </div>
        </div>

        <!-- Add/Edit employee form -->
        <?php if ($user_role === 'admin' && $show_add_form): ?>
          <div class="table-card">
            <div class="table-container">
              <h3 style="padding: 15px 20px; border-bottom: 1px solid var(--border);">Add New Employee</h3>
              <form method="POST" action="emp.php" style="padding: 20px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                  <div class="form-group">
                    <label for="name">Last Name:</label>
                    <input type="text" id="name" name="name" required class="search-bar" style="width: 100%;">
                  </div>
                  
                  <div class="form-group">
                    <label for="first_name">First Name:</label>
                    <input type="text" id="first_name" name="first_name" required class="search-bar" style="width: 100%;">
                  </div>
                  
                  <div class="form-group">
                    <label for="phone_number">Phone Number:</label>
                    <input type="text" id="phone_number" name="phone_number" required class="search-bar" style="width: 100%;">
                  </div>
                  
                  <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" class="search-bar" style="width: 100%;">
                  </div>
                  
                  <div class="form-group">
                    <label for="position">Position:</label>
                    <input type="text" id="position" name="position" class="search-bar" style="width: 100%;">
                  </div>
                  
                  <div class="form-group">
                    <label for="dept_id">Department:</label>
                    <select id="dept_id" name="dept_id" class="styled-select" style="width: 100%;">
                      <option value="">None</option>
                      <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept['dept_id']) ?>">
                          <?= htmlspecialchars($dept['dept_name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                  <button type="submit" name="add_employee" class="btn btn-outline btn-sm">
                    <i class="fas fa-save"></i> Add Employee
                  </button>
                  <a href="emp.php">
                    <button type="button" class="btn btn-outline btn-sm btn-delete">
                      <i class="fas fa-times"></i> Cancel
                    </button>
                  </a>
                </div>
              </form>
            </div>
          </div>
        <?php endif; ?>
        
        <?php if ($edit_emp_id && $employee_to_edit): ?>
          <div class="table-card">
            <div class="table-container">
              <h3 style="padding: 15px 20px; border-bottom: 1px solid var(--border);">Edit Employee</h3>
              <form method="POST" action="emp.php" style="padding: 20px;">
                <input type="hidden" name="edit_emp_id" value="<?= $employee_to_edit['emp_id'] ?>">
                <?php if (!empty($search_query)): ?>
                  <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
                  <input type="hidden" name="filter" value="<?= htmlspecialchars($filter_by) ?>">
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                  <div class="form-group">
                    <label for="edit_name">Last Name:</label>
                    <input type="text" id="edit_name" name="edit_name" value="<?= htmlspecialchars($employee_to_edit['name']) ?>" required class="search-bar" style="width: 100%;">
                  </div>
                  
                  <div class="form-group">
                    <label for="edit_first_name">First Name:</label>
                    <input type="text" id="edit_first_name" name="edit_first_name" value="<?= htmlspecialchars($employee_to_edit['first_name']) ?>" required class="search-bar" style="width: 100%;">
                  </div>
                  
                  <div class="form-group">
                    <label for="edit_phone_number">Phone Number:</label>
                    <input type="text" id="edit_phone_number" name="edit_phone_number" value="<?= htmlspecialchars($employee_to_edit['phone_number']) ?>" required class="search-bar" style="width: 100%;">
                  </div>
                  
                  <div class="form-group">
                    <label for="edit_email">Email:</label>
                    <input type="email" id="edit_email" name="edit_email" value="<?= htmlspecialchars($employee_to_edit['email'] ?? '') ?>" class="search-bar" style="width: 100%;">
                  </div>
                  
                  <div class="form-group">
                    <label for="edit_position">Position:</label>
                    <input type="text" id="edit_position" name="edit_position" value="<?= htmlspecialchars($employee_to_edit['position'] ?? '') ?>" class="search-bar" style="width: 100%;">
                  </div>
                  
                  <?php if ($user_role === 'admin'): ?>
                    <div class="form-group">
                      <label for="edit_dept_id">Department:</label>
                      <select id="edit_dept_id" name="edit_dept_id" class="styled-select" style="width: 100%;">
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
                      <div class="search-bar" style="width: 100%; display: block; padding: 12px 15px;"><?= htmlspecialchars($employee_to_edit['dept_name'] ?? 'None') ?></div>
                    </div>
                  <?php endif; ?>
                </div>
                
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                  <button type="submit" name="update_employee" class="btn btn-outline btn-sm btn-edit">
                    <i class="fas fa-save"></i> Update Employee
                  </button>
                  <a href="emp.php<?= !empty($search_query) ? '?search=' . urlencode($search_query) . '&filter=' . urlencode($filter_by) : '' ?>">
                    <button type="button" class="btn btn-outline btn-sm btn-delete">
                      <i class="fas fa-times"></i> Cancel
                    </button>
                  </a>
                </div>
              </form>
            </div>
          </div>
        <?php endif; ?>

        <!-- Statistics cards section -->
        <?php if (!$show_add_form && !$edit_emp_id): ?>
        <!-- Statistics cards section -->
        <div class="stats-cards">
          <!-- Card 1 - Total Employees -->
          <div class="stat-card">
            <div class="card-header">
              <div>
                <div class="card-value"><?= count($employees) ?></div>
                <div class="card-label">Total Employees</div>
              </div>
              <div class="card-icon purple">
                <i class="fas fa-users"></i>
              </div>
            </div>
            <div class="card-change positive">
              <i class="fas fa-database"></i>
              <span>Current count</span>
            </div>
          </div>
          
          <!-- Card 2 - Departments -->
          <div class="stat-card">
            <div class="card-header">
              <div>
                <div class="card-value"><?= count($departments) ?></div>
                <div class="card-label">Departments</div>
              </div>
              <div class="card-icon blue">
                <i class="fas fa-building"></i>
              </div>
            </div>
            <div class="card-change positive">
              <i class="fas fa-database"></i>
              <span>Active departments</span>
            </div>
          </div>
          
          <!-- Card 3 - Recent Activities -->
          <div class="stat-card">
            <div class="card-header">
              <div>
                <div class="card-value">Staff</div>
                <div class="card-label">Management</div>
              </div>
              <div class="card-icon green">
                <i class="fas fa-chart-line"></i>
              </div>
            </div>
            <div class="card-change positive">
              <i class="fas fa-check-circle"></i>
              <span>System active</span>
            </div>
          </div>
          
          <!-- Card 4 - User Role -->
          <div class="stat-card">
            <div class="card-header">
              <div>
                <div class="card-value"><?= ucfirst($user_role ?? 'User') ?></div>
                <div class="card-label">Account Type</div>
              </div>
              <div class="card-icon orange">
                <i class="fas fa-user-shield"></i>
              </div>
            </div>
            <div class="card-change positive">
              <i class="fas fa-unlock"></i>
              <span>Full access</span>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Data table section -->
        <?php if (!$show_add_form && !$edit_emp_id): ?>
        <div class="table-card">
          <div class="table-container">
            <table class="data-table">
              <!-- Table header -->
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Last Name</th>
                  <th>First Name</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>Position</th>
                  <th>Department</th>
                  <th>Action</th>
                </tr>
              </thead>
              
              <!-- Table body with data -->
              <tbody>
                <?php if (empty($employees)): ?>
                  <tr>
                    <td colspan="8" style="text-align:center;">No employees found<?= !empty($search_query) ? ' matching your search' : '' ?>.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($employees as $employee): ?>
                    <tr>
                      <td><?= htmlspecialchars($employee['emp_id']) ?></td>
                      <td><?= htmlspecialchars($employee['name']) ?></td>
                      <td><?= htmlspecialchars($employee['first_name']) ?></td>
                      <td><?= htmlspecialchars($employee['email'] ?? '') ?></td>
                      <td><?= htmlspecialchars($employee['phone_number']) ?></td>
                      <td><?= htmlspecialchars($employee['position'] ?? '') ?></td>
                      <td><?= htmlspecialchars($employee['dept_name'] ?? 'None') ?></td>
                      <td style="white-space: nowrap;">
                        <?php if ($user_role === 'admin' || ($user_role === 'dept_head' && $employee['dept_id'] == $user_dept_id)): ?>
                          <a href="emp.php?edit_emp_id=<?= $employee['emp_id'] ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) . '&filter=' . urlencode($filter_by) : '' ?>">
                            <button class="btn btn-outline btn-sm btn-edit">
                              <i class="fas fa-pen-to-square"></i> Edit
                            </button>
                          </a>
                          <a href="emp.php?delete_employee=<?= $employee['emp_id'] ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) . '&filter=' . urlencode($filter_by) : '' ?>" 
                            onclick="return confirm('Are you sure you want to delete <?= htmlspecialchars(addslashes($employee['name'])) ?>?')">
                            <button class="btn btn-outline btn-sm btn-delete">
                              <i class="fas fa-trash"></i> Delete
                            </button>
                          </a>
                        <?php else: ?>
                          <button class="btn btn-outline btn-sm" disabled>
                            <i class="fas fa-lock"></i> No Access
                          </button>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </body>

  <script>
    // Simple script to make the filter dropdown submit the form automatically
    document.getElementById('filter').addEventListener('change', function() {
      this.form.submit();
    });
  </script>
</html>