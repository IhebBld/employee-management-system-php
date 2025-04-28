
USE company_db;

-- Departments table
CREATE TABLE departments (
    dept_id INT AUTO_INCREMENT PRIMARY KEY,
    dept_name VARCHAR(100) NOT NULL UNIQUE,
    
    nb_emp INT DEFAULT 0
    
);

-- Users table (for login)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    dept_id INT NULL,  -- NULL = admin, non-NULL = department head
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE SET NULL
);

-- Employees table with ON DELETE SET NULL
CREATE TABLE employees (
    emp_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(100),
    position VARCHAR(100),
    dept_id INT NULL,  -- Changed to allow NULL
    dept_name VARCHAR(100) NULL,  -- Changed to allow NULL
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE SET NULL
);

DELIMITER //

-- Trigger 1: When NEW employee is INSERTED
CREATE TRIGGER after_employee_insert 
AFTER INSERT ON employees 
FOR EACH ROW 
BEGIN
    IF NEW.dept_id IS NOT NULL THEN
        UPDATE departments 
        SET nb_emp = nb_emp + 1 
        WHERE dept_id = NEW.dept_id;
    END IF;
END//

-- Trigger 2: When employee is DELETED
CREATE TRIGGER after_employee_delete 
AFTER DELETE ON employees 
FOR EACH ROW 
BEGIN
    IF OLD.dept_id IS NOT NULL THEN
        UPDATE departments 
        SET nb_emp = nb_emp - 1 
        WHERE dept_id = OLD.dept_id;
    END IF;
END//

-- Trigger 3: When employee's department is UPDATED
CREATE TRIGGER after_employee_update 
AFTER UPDATE ON employees 
FOR EACH ROW 
BEGIN
    -- Handle old department count
    IF OLD.dept_id IS NOT NULL THEN
        UPDATE departments 
        SET nb_emp = nb_emp - 1 
        WHERE dept_id = OLD.dept_id;
    END IF;
    
    -- Handle new department count
    IF NEW.dept_id IS NOT NULL THEN
        UPDATE departments 
        SET nb_emp = nb_emp + 1 
        WHERE dept_id = NEW.dept_id;
    END IF;
END//

-- Trigger 4: When department is DELETED, set dept_name to NULL in employees
CREATE TRIGGER after_department_delete 
AFTER DELETE ON departments 
FOR EACH ROW 
BEGIN
    UPDATE employees
    SET dept_name = NULL
    WHERE dept_id IS NULL AND dept_name IS NOT NULL;
END//

-- Trigger 5: Register department head in users table
CREATE TRIGGER register_dept_head 
AFTER INSERT ON employees 
FOR EACH ROW 
BEGIN
    IF NEW.position LIKE '%Department Head%' AND NEW.dept_id IS NOT NULL THEN
        INSERT INTO users (email, dept_id)
        VALUES (NEW.email, NEW.dept_id)
        ON DUPLICATE KEY UPDATE dept_id = NEW.dept_id;
    END IF;
END//

DELIMITER ;
-- Insert departments
INSERT INTO departments (dept_name) VALUES
('Human Resources'),
('Information Technology'),
('Finance'),
('Marketing'),
('Operations');

-- Insert admin user (no department association)
INSERT INTO users (email, password) VALUES
('admin@company.com', '$2y$10$abcdefghijklmnopqrstuv');

-- Insert employees (5 department heads and 5 regular employees)
-- Department Heads
INSERT INTO employees (name, first_name, phone_number, email, position, dept_id, dept_name) VALUES
('Adams', 'Jennifer', '555-123-4567', 'j.adams@company.com', 'HR Department Head', 1, 'Human Resources'),
('Chen', 'Michael', '555-234-5678', 'm.chen@company.com', 'IT Department Head', 2, 'Information Technology'),
('Johnson', 'Sarah', '555-345-6789', 's.johnson@company.com', 'Finance Department Head', 3, 'Finance'),
('Wilson', 'David', '555-456-7890', 'd.wilson@company.com', 'Marketing Department Head', 4, 'Marketing'),
('Garcia', 'Robert', '555-567-8901', 'r.garcia@company.com', 'Operations Department Head', 5, 'Operations');

-- Regular Employees
INSERT INTO employees (name, first_name, phone_number, email, position, dept_id, dept_name) VALUES
('Smith', 'Emily', '555-678-9012', 'e.smith@company.com', 'HR Specialist', 1, 'Human Resources'),
('Patel', 'Raj', '555-789-0123', 'r.patel@company.com', 'Systems Administrator', 2, 'Information Technology'),
('Brown', 'Jessica', '555-890-1234', 'j.brown@company.com', 'Accountant', 3, 'Finance'),
('Taylor', 'Mark', '555-901-2345', 'm.taylor@company.com', 'Marketing Specialist', 4, 'Marketing'),
('Lopez', 'Maria', '555-012-3456', 'm.lopez@company.com', 'Operations Coordinator', 5, 'Operations');

-- Set passwords for department heads in users table (should be created by the trigger, just updating passwords)
UPDATE users SET password = '$2y$10$hr_department_head_hash' WHERE email = 'j.adams@company.com';
UPDATE users SET password = '$2y$10$it_department_head_hash' WHERE email = 'm.chen@company.com';
UPDATE users SET password = '$2y$10$finance_department_head_hash' WHERE email = 's.johnson@company.com';
UPDATE users SET password = '$2y$10$marketing_department_hash' WHERE email = 'd.wilson@company.com';
UPDATE users SET password = '$2y$10$operations_department_hash' WHERE email = 'r.garcia@company.com';