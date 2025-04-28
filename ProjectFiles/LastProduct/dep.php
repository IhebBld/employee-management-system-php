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

// Count total departments
$total_departments = count($departments);

// Calculate total employees across all departments
$total_employees = 0;
foreach ($departments as $dept) {
    $total_employees += $dept['nb_emp'];
}

// Count departments with assigned heads
$departments_with_head = 0;
foreach ($departments as $dept) {
    if (!empty($dept['dept_head'])) {
        $departments_with_head++;
    }
}

// Calculate average employees per department
$avg_employees = $total_departments > 0 ? round($total_employees / $total_departments, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <!-- Basic Meta Tags -->
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <!-- Page Title -->
    <title>Project#12 - Departments</title>

    <!-- External CSS Dependencies -->
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
    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="c4.css" />
    
    <style>
      /* Additional styles for modals */
      .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.7);
      }
      
      .modal-content {
        background-color: #111111;
        margin: 10% auto;
        padding: 25px;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        width: 60%;
        max-width: 500px;
        box-shadow: var(--shadow);
        animation: fadeInUp 0.4s ease;
      }
      
      .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        border-bottom: 1px solid var(--border);
        padding-bottom: 10px;
      }
      
      .modal-header h3 {
        margin: 0;
        color: var(--text);
      }
      
      .close-modal {
        color: var(--text-light);
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
      }
      
      .close-modal:hover {
        color: var(--primary-light);
      }
      
      .form-group input {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        font-size: 14px;
        transition: var(--transition);
        color: var(--text);
        background-color: #222222;
        margin-bottom: 15px;
      }
      
      .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
      }
      
      /* Alert messages */
      .alert {
        padding: 15px;
        border-radius: var(--radius);
        margin-bottom: 20px;
        animation: fadeIn 0.5s ease;
      }
      
      .alert-success {
        background-color: rgba(76, 201, 240, 0.1);
        border: 1px solid var(--success);
        color: var(--success);
      }
      
      .alert-error {
        background-color: rgba(247, 37, 133, 0.1);
        border: 1px solid var(--danger);
        color: var(--danger);
      }
    </style>
  </head>

  <body>
    <!-- Main Application Container -->
    <div class="container">
      <!-- ==================== HEADER SECTION ==================== -->
      <div class="header">
        <!-- Dashboard Title -->
        <div>
          <h2>Project#12</h2>
        </div>

        <!-- Primary Navigation Buttons -->
        <div class="header-buttons">
          <!-- Employees Page Link -->
          <a href="emp.php">
            <button class="btn btn-outline btn-sm">
              <i class="fas fa-users"></i> <span>Employees</span>
            </button>
          </a>

          <!-- Departments Page Link -->
          <a href="dep.php">
            <button class="btn btn-outline btn-sm">
              <i class="fas fa-building"></i> <span>Departments</span>
            </button>
          </a>
        </div>

        <!-- Logout Button -->
        <div class="header-buttons">
          <a href="logout.php">
            <button class="btn btn-outline btn-sm logout">
              <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
            </button>
          </a>
        </div>
      </div>

      <!-- ==================== MAIN CONTENT AREA ==================== -->
      <div class="main-content">
        <?php if ($error_message): ?>
        <div class="alert alert-error">
          <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
        <div class="alert alert-success">
          <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($_SESSION['role'] !== 'admin'): ?>
        <div class="alert alert-error">
          <i class="fas fa-exclamation-triangle"></i> You don't have permission to manage departments.
        </div>
        <?php else: ?>

        <!-- Page Title and Action Buttons Section -->
        <div class="page-title">
          <h1>Departments Management</h1>

          <!-- Action Buttons Container -->
          <div class="action-buttons">
            <!-- Add New Department Button -->
            <button class="btn btn-outline btn-sm" id="openAddModal">
              <i class="fas fa-plus-circle"></i> Add New Department
            </button>
          </div>
        </div>

        <!-- ==================== STATISTICS CARDS ==================== -->
        <div class="stats-cards">
          <!-- Card 1: Total Departments -->
          <div class="stat-card">
            <div class="card-header">
              <div>
                <div class="card-value"><?= $total_departments ?></div>
                <div class="card-label">Total Departments</div>
              </div>
              <div class="card-icon purple">
                <i class="fas fa-building"></i>
              </div>
            </div>
            <div class="card-change positive">
              <i class="fas fa-chart-line"></i>
              <span>Department Overview</span>
            </div>
          </div>

          <!-- Card 2: Total Employees -->
          <div class="stat-card">
            <div class="card-header">
              <div>
                <div class="card-value"><?= $total_employees ?></div>
                <div class="card-label">Total Employees</div>
              </div>
              <div class="card-icon blue">
                <i class="fas fa-users"></i>
              </div>
            </div>
            <div class="card-change positive">
              <i class="fas fa-user-check"></i>
              <span>Workforce Overview</span>
            </div>
          </div>

          <!-- Card 3: Departments with Heads -->
          <div class="stat-card">
            <div class="card-header">
              <div>
                <div class="card-value"><?= $departments_with_head ?></div>
                <div class="card-label">Departments with Head</div>
              </div>
              <div class="card-icon green">
                <i class="fas fa-user-tie"></i>
              </div>
            </div>
            <div class="card-change positive">
              <i class="fas fa-check-circle"></i>
              <span>Leadership Overview</span>
            </div>
          </div>

          <!-- Card 4: Average Employees per Department -->
          <div class="stat-card">
            <div class="card-header">
              <div>
                <div class="card-value"><?= $avg_employees ?></div>
                <div class="card-label">Avg. Employees/Dept</div>
              </div>
              <div class="card-icon orange">
                <i class="fas fa-chart-pie"></i>
              </div>
            </div>
            <div class="card-change positive">
              <i class="fas fa-balance-scale"></i>
              <span>Distribution Metrics</span>
            </div>
          </div>
        </div>

        <?php if (empty($departments)): ?>
        <div class="alert alert-error">
          <i class="fas fa-info-circle"></i> No department data found.
        </div>
        <?php else: ?>
        <!-- ==================== DATA TABLE SECTION ==================== -->
        <div class="table-card">
          <div class="table-container">
            <table class="data-table">
              <!-- Table Header -->
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Department Name</th>
                  <th>Department Head</th>
                  <th>Employees</th>
                  <th>Actions</th>
                </tr>
              </thead>

              <!-- Table Body -->
              <tbody>
                <?php foreach ($departments as $dept): ?>
                <tr>
                  <td>#<?= htmlspecialchars($dept['dept_id']) ?></td>
                  <td><?= htmlspecialchars($dept['dept_name']) ?></td>
                  <td><?= $dept['dept_head'] ? htmlspecialchars($dept['dept_head']) : '<span style="color: var(--text-light);">Not assigned</span>' ?></td>
                  <td><?= htmlspecialchars($dept['nb_emp']) ?></td>
                  <td>
                    <!-- Edit Button -->
                    <button class="btn btn-outline btn-sm btn-edit edit-btn" data-id="<?= $dept['dept_id'] ?>" data-name="<?= htmlspecialchars($dept['dept_name']) ?>">
                      <i class="fas fa-pen-to-square"></i> Edit
                    </button>
                    <!-- Delete Button -->
                    <button class="btn btn-outline btn-sm btn-delete delete-btn" data-id="<?= $dept['dept_id'] ?>" data-name="<?= htmlspecialchars($dept['dept_name']) ?>">
                      <i class="fas fa-trash"></i> Delete
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>
        
        <!-- Add Department Modal -->
        <div id="addModal" class="modal">
          <div class="modal-content">
            <div class="modal-header">
              <h3>Add New Department</h3>
              <span class="close-modal">&times;</span>
            </div>
            <form method="POST" action="dep.php">
              <div class="form-group">
                <label for="add_dept_name">Department Name</label>
                <input type="text" id="add_dept_name" name="add_dept_name" placeholder="Enter department name" required>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm close-btn">Cancel</button>
                <button type="submit" class="btn btn-outline btn-sm btn-edit">Add Department</button>
              </div>
            </form>
          </div>
        </div>
        
        <!-- Edit Department Modal -->
        <div id="editModal" class="modal">
          <div class="modal-content">
            <div class="modal-header">
              <h3>Edit Department</h3>
              <span class="close-modal">&times;</span>
            </div>
            <form method="POST" action="dep.php">
              <input type="hidden" id="edit_dept_id" name="edit_dept_id" value="">
              <div class="form-group">
                <label for="edit_dept_name">Department Name</label>
                <input type="text" id="edit_dept_name" name="edit_dept_name" placeholder="Enter department name" required>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm close-btn">Cancel</button>
                <button type="submit" class="btn btn-outline btn-sm btn-edit">Update Department</button>
              </div>
            </form>
          </div>
        </div>
        
        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="modal">
          <div class="modal-content">
            <div class="modal-header">
              <h3>Confirm Delete</h3>
              <span class="close-modal">&times;</span>
            </div>
            <p>Are you sure you want to delete department <strong id="deleteDeptName"></strong>?</p>
            <p>Note: Employees in this department will be kept but their department reference will be set to NULL.</p>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline btn-sm close-btn">Cancel</button>
              <a href="#" id="confirmDelete" class="btn btn-outline btn-sm btn-delete">Delete</a>
            </div>
          </div>
        </div>
        
        <?php endif; ?>
      </div>
    </div>

    <script>
      // Modal functionality
      document.addEventListener('DOMContentLoaded', function() {
        // Get modal elements
        const addModal = document.getElementById('addModal');
        const editModal = document.getElementById('editModal');
        const deleteModal = document.getElementById('deleteModal');
        
        // Add Department button
        const openAddModalBtn = document.getElementById('openAddModal');
        if(openAddModalBtn) {
          openAddModalBtn.addEventListener('click', function() {
            addModal.style.display = 'block';
          });
        }
        
        // Edit Department buttons
        const editBtns = document.querySelectorAll('.edit-btn');
        editBtns.forEach(btn => {
          btn.addEventListener('click', function() {
            const deptId = this.getAttribute('data-id');
            const deptName = this.getAttribute('data-name');
            
            document.getElementById('edit_dept_id').value = deptId;
            document.getElementById('edit_dept_name').value = deptName;
            
            editModal.style.display = 'block';
          });
        });
        
        // Delete Department buttons
        const deleteBtns = document.querySelectorAll('.delete-btn');
        deleteBtns.forEach(btn => {
          btn.addEventListener('click', function() {
            const deptId = this.getAttribute('data-id');
            const deptName = this.getAttribute('data-name');
            
            document.getElementById('deleteDeptName').textContent = deptName;
            document.getElementById('confirmDelete').href = 'dep.php?delete=' + deptId;
            
            deleteModal.style.display = 'block';
          });
        });
        
        // Close buttons
        const closeBtns = document.querySelectorAll('.close-modal, .close-btn');
        closeBtns.forEach(btn => {
          btn.addEventListener('click', function() {
            addModal.style.display = 'none';
            editModal.style.display = 'none';
            deleteModal.style.display = 'none';
          });
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
          if (event.target == addModal) {
            addModal.style.display = 'none';
          } else if (event.target == editModal) {
            editModal.style.display = 'none';
          } else if (event.target == deleteModal) {
            deleteModal.style.display = 'none';
          }
        }
        
        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
          setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 1s';
            setTimeout(() => {
              alert.style.display = 'none';
            }, 1000);
          }, 5000);
        });
      });
    </script>
  </body>
</html>