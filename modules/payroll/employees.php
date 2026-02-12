<?php
// Process form submissions BEFORE including header
require_once '../../includes/database.php';
require_once '../../auth/config.php';
requireAuth();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save') {
        $data = [
            'employee_code' => $_POST['employee_code'] ?: 'EMP-' . strtoupper(substr(uniqid(), -4)),
            'first_name' => $_POST['first_name'],
            'last_name' => $_POST['last_name'],
            'email' => $_POST['email'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'department_id' => $_POST['department_id'] ?: null,
            'position_id' => $_POST['position_id'] ?: null,
            'hire_date' => $_POST['hire_date'] ?? null,
            'basic_salary' => $_POST['salary'] ?? 0,
            'salary_type' => $_POST['salary_type'] ?? 'monthly',
            'status' => $_POST['status'] ?? 'active'
        ];
        
        if ($id) {
            update('payroll_employees', $data, 'id = :id', ['id' => $id]);
            logActivity('Update Employee', 'payroll', "Updated employee: {$data['first_name']} {$data['last_name']}");
            $_SESSION['alert'] = ['message' => 'Employee updated successfully', 'type' => 'success'];
        } else {
            $newId = insert('payroll_employees', $data);
            logActivity('Create Employee', 'payroll', "Created employee ID: $newId");
            $_SESSION['alert'] = ['message' => 'Employee created successfully', 'type' => 'success'];
        }
        
        header('Location: employees.php');
        exit;
    }
}

if ($action === 'delete' && $id) {
    $emp = fetchOne("SELECT first_name, last_name FROM payroll_employees WHERE id = ?", [$id]);
    delete('payroll_employees', 'id = :id', ['id' => $id]);
    logActivity('Delete Employee', 'payroll', "Deleted employee: {$emp['first_name']} {$emp['last_name']}");
    $_SESSION['alert'] = ['message' => 'Employee deleted successfully', 'type' => 'success'];
    header('Location: employees.php');
    exit;
}

$employee = null;
if ($id && ($action === 'edit' || $action === 'view')) {
    $employee = fetchOne("SELECT e.*, d.department_name 
        FROM payroll_employees e 
        LEFT JOIN payroll_departments d ON e.department_id = d.id 
        WHERE e.id = ?", [$id]);
}

$employees = fetchAll("SELECT e.*, d.department_name 
    FROM payroll_employees e 
    LEFT JOIN payroll_departments d ON e.department_id = d.id 
    ORDER BY e.last_name, e.first_name");
$departments = fetchAll("SELECT id, department_name FROM payroll_departments ORDER BY department_name");

$stats = fetchOne("SELECT 
    COUNT(*) as total_employees,
    SUM(CASE WHEN status = 'active' THEN basic_salary ELSE 0 END) as total_monthly_salary,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count
    FROM payroll_employees");

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-users me-2"></i>Employees</h2>
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Employee
        </a>
        <?php endif; ?>
    </div>
    
    <?php if ($action === 'list'): ?>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Employees</h6>
                    <h4 class="mb-0"><?php echo count($employees); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-user-check"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Active Employees</h6>
                    <h4 class="mb-0"><?php echo count(array_filter($employees, fn($e) => $e['status'] === 'active')); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-money-bill"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Monthly Payroll</h6>
                    <h4 class="mb-0"><?php echo formatCurrency($stats['total_monthly_salary'] ?? 0); ?></h4>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr>
                            <th>Employee Code</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Salary</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $e): ?>
                        <tr>
                            <td><strong><?php echo $e['employee_code']; ?></strong></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px; font-size: 12px;">
                                        <?php echo strtoupper(substr($e['first_name'], 0, 1) . substr($e['last_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <strong><?php echo $e['last_name'] . ', ' . $e['first_name']; ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo $e['department_name'] ?: '-'; ?></td>
                            <td><?php echo $e['position'] ?: '-'; ?></td>
                            <td><?php echo formatCurrency($e['basic_salary']); ?>/<?php echo $e['salary_type']; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $e['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($e['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="?action=view&id=<?php echo $e['id']; ?>" class="btn btn-sm btn-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?action=edit&id=<?php echo $e['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?action=delete&id=<?php echo $e['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirmDelete()">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php elseif ($action === 'add' || $action === 'edit'): ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?php echo $id ? 'Edit' : 'Add New'; ?> Employee</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="?action=save<?php echo $id ? '&id=' . $id : ''; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" name="first_name" class="form-control" value="<?php echo $employee['first_name'] ?? ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="last_name" class="form-control" value="<?php echo $employee['last_name'] ?? ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Employee Code</label>
                                <input type="text" name="employee_code" class="form-control" value="<?php echo $employee['employee_code'] ?? ''; ?>" placeholder="Auto-generated if empty">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo $employee['email'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo $employee['phone'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <select name="department_id" class="form-select">
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo $d['id']; ?>" <?php echo ($employee['department_id'] ?? '') == $d['id'] ? 'selected' : ''; ?>>
                                        <?php echo $d['department_name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Position</label>
                                <input type="text" name="position" class="form-control" value="<?php echo $employee['position'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Hire Date</label>
                                <input type="date" name="hire_date" class="form-control" value="<?php echo $employee['hire_date'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Salary *</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="salary" class="form-control" step="0.01" min="0" value="<?php echo $employee['basic_salary'] ?? 0; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Salary Type</label>
                                <select name="salary_type" class="form-select">
                                    <option value="monthly" <?php echo ($employee['salary_type'] ?? 'monthly') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    <option value="weekly" <?php echo ($employee['salary_type'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="hourly" <?php echo ($employee['salary_type'] ?? '') === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="active" <?php echo ($employee['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($employee['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="terminated" <?php echo ($employee['status'] ?? '') === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Employee
                            </button>
                            <a href="employees.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($action === 'view' && $employee): ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Employee Information</h5>
                </div>
                <div class="card-body text-center">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px; font-size: 2.5rem;">
                        <i class="fas fa-user"></i>
                    </div>
                    <h4><?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?></h4>
                    <p class="text-muted"><?php echo $employee['employee_code']; ?></p>
                    <span class="badge bg-<?php echo $employee['status'] === 'active' ? 'success' : 'secondary'; ?> fs-6">
                        <?php echo ucfirst($employee['status']); ?>
                    </span>
                    
                    <hr>
                    
                    <div class="text-start">
                        <p><i class="fas fa-envelope me-2 text-primary"></i> <?php echo $employee['email'] ?: 'N/A'; ?></p>
                        <p><i class="fas fa-phone me-2 text-primary"></i> <?php echo $employee['phone'] ?: 'N/A'; ?></p>
                        <p><i class="fas fa-calendar me-2 text-primary"></i> Hire: <?php echo $employee['hire_date'] ? formatDate($employee['hire_date']) : 'N/A'; ?></p>
                    </div>
                    
                    <div class="d-flex gap-2 justify-content-center mt-3">
                        <a href="?action=edit&id=<?php echo $employee['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i>Edit
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Employment Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Department:</strong> <?php echo $employee['department_name'] ?: 'Not assigned'; ?></p>
                            <p><strong>Position:</strong> <?php echo $employee['position'] ?: 'Not assigned'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Salary:</strong> <?php echo formatCurrency($employee['basic_salary']); ?>/<?php echo $employee['salary_type']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
