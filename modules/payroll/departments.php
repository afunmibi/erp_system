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
            'department_name' => $_POST['department_name'],
            'department_code' => $_POST['department_code'] ?: 'DEPT-' . strtoupper(substr(uniqid(), -3)),
            'description' => $_POST['description'] ?? '',
            'manager_id' => $_POST['manager_id'] ?: null,
            'status' => $_POST['status'] ?? 'active'
        ];
        
        if ($id) {
            update('payroll_departments', $data, 'id = :id', ['id' => $id]);
            logActivity('Update Department', 'payroll', "Updated department: {$data['department_name']}");
            $_SESSION['alert'] = ['message' => 'Department updated successfully', 'type' => 'success'];
        } else {
            $newId = insert('payroll_departments', $data);
            logActivity('Create Department', 'payroll', "Created department ID: $newId");
            $_SESSION['alert'] = ['message' => 'Department created successfully', 'type' => 'success'];
        }
        
        header('Location: departments.php');
        exit;
    }
}

if ($action === 'delete' && $id) {
    $dept = fetchOne("SELECT department_name FROM payroll_departments WHERE id = ?", [$id]);
    delete('payroll_departments', 'id = :id', ['id' => $id]);
    logActivity('Delete Department', 'payroll', "Deleted department: {$dept['department_name']}");
    $_SESSION['alert'] = ['message' => 'Department deleted successfully', 'type' => 'success'];
    header('Location: departments.php');
    exit;
}

$department = null;
if ($id && ($action === 'edit' || $action === 'view')) {
    $department = fetchOne("SELECT d.*, m.first_name as manager_first_name, m.last_name as manager_last_name 
        FROM payroll_departments d 
        LEFT JOIN payroll_employees m ON d.manager_id = m.id 
        WHERE d.id = ?", [$id]);
}

$departments = fetchAll("SELECT d.*, 
    (SELECT COUNT(*) FROM payroll_employees WHERE department_id = d.id AND status = 'active') as employee_count 
    FROM payroll_departments d 
    ORDER BY d.department_name");
$managers = fetchAll("SELECT id, first_name, last_name FROM payroll_employees WHERE status = 'active' ORDER BY last_name, first_name");

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-building me-2"></i>Departments</h2>
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Department
        </a>
        <?php endif; ?>
    </div>
    
    <?php if ($action === 'list'): ?>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-building"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Departments</h6>
                    <h4 class="mb-0"><?php echo count($departments); ?></h4>
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
                            <th>Department Code</th>
                            <th>Department Name</th>
                            <th>Manager</th>
                            <th>Employees</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $d): ?>
                        <tr>
                            <td><strong><?php echo $d['department_code']; ?></strong></td>
                            <td><?php echo $d['department_name']; ?></td>
                            <td>
                                <?php if ($d['manager_first_name']): ?>
                                <?php echo $d['manager_first_name'] . ' ' . $d['manager_last_name']; ?>
                                <?php else: ?>
                                <span class="text-muted">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo $d['employee_count']; ?> employees</span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $d['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($d['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="?action=view&id=<?php echo $d['id']; ?>" class="btn btn-sm btn-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?action=edit&id=<?php echo $d['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?action=delete&id=<?php echo $d['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirmDelete()">
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
        <div class="col-md-6 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?php echo $id ? 'Edit' : 'Add New'; ?> Department</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="?action=save<?php echo $id ? '&id=' . $id : ''; ?>">
                        <div class="mb-3">
                            <label class="form-label">Department Name *</label>
                            <input type="text" name="department_name" class="form-control" value="<?php echo $department['department_name'] ?? ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Department Code</label>
                            <input type="text" name="department_code" class="form-control" value="<?php echo $department['department_code'] ?? ''; ?>" placeholder="Auto-generated if empty">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Manager</label>
                            <select name="manager_id" class="form-select">
                                <option value="">Select Manager</option>
                                <?php foreach ($managers as $m): ?>
                                <option value="<?php echo $m['id']; ?>" <?php echo ($department['manager_id'] ?? '') == $m['id'] ? 'selected' : ''; ?>>
                                    <?php echo $m['last_name'] . ', ' . $m['first_name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"><?php echo $department['description'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?php echo ($department['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($department['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Department
                            </button>
                            <a href="departments.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($action === 'view' && $department): ?>
    <div class="row">
        <div class="col-md-6 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Department Details</h5>
                </div>
                <div class="card-body text-center">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                        <i class="fas fa-building"></i>
                    </div>
                    <h4><?php echo $department['department_name']; ?></h4>
                    <p class="text-muted"><?php echo $department['department_code']; ?></p>
                    <span class="badge bg-<?php echo $department['status'] === 'active' ? 'success' : 'secondary'; ?> fs-6">
                        <?php echo ucfirst($department['status']); ?>
                    </span>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <small class="text-muted">Manager</small>
                        <p class="mb-0">
                            <?php if ($department['manager_first_name']): ?>
                            <strong><?php echo $department['manager_first_name'] . ' ' . $department['manager_last_name']; ?></strong>
                            <?php else: ?>
                            Not assigned
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted">Employees</small>
                        <h5 class="mb-0"><?php echo $department['employee_count']; ?></h5>
                    </div>
                    
                    <?php if ($department['description']): ?>
                    <div class="mb-3">
                        <small class="text-muted">Description</small>
                        <p class="mb-0"><?php echo nl2br($department['description']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex gap-2 justify-content-center mt-3">
                        <a href="?action=edit&id=<?php echo $department['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i>Edit
                        </a>
                        <a href="departments.php" class="btn btn-secondary">Back to List</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
