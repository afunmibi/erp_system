<?php
// Process form submissions BEFORE including header
require_once '../../includes/database.php';
require_once '../../auth/config.php';
requireAuth();

// Check if user is admin
if (!hasRole('admin')) {
    $_SESSION['alert'] = ['message' => 'Access denied. Admin privileges required.', 'type' => 'danger'];
    header('Location: ' . $projectRootPath . '/index.php');
    exit;
}

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save') {
        $data = [
            'username' => $_POST['username'],
            'email' => $_POST['email'],
            'role' => $_POST['role'],
            'status' => $_POST['status']
        ];
        
        // Handle password
        if (!empty($_POST['password'])) {
            $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
        
        if ($id) {
            update('users', $data, 'id = :id', ['id' => $id]);
            logActivity('Update User', 'admin', "Updated user: {$_POST['username']}");
            $_SESSION['alert'] = ['message' => 'User updated successfully', 'type' => 'success'];
        } else {
            if (empty($data['password'])) {
                $_SESSION['alert'] = ['message' => 'Password is required for new users', 'type' => 'danger'];
            } else {
                $newId = insert('users', $data);
                logActivity('Create User', 'admin', "Created user: {$_POST['username']}");
                $_SESSION['alert'] = ['message' => 'User created successfully', 'type' => 'success'];
            }
        }
        
        header('Location: users.php');
        exit;
    }
    
    if ($action === 'reset_password') {
        $password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        update('users', ['password' => $password], 'id = :id', ['id' => $id]);
        logActivity('Reset Password', 'admin', "Password reset for user ID: $id");
        $_SESSION['alert'] = ['message' => 'Password reset successfully', 'type' => 'success'];
        header('Location: users.php');
        exit;
    }
}

// Handle delete
if ($action === 'delete' && $id) {
    $user = fetchOne("SELECT username FROM users WHERE id = ?", [$id]);
    delete('users', 'id = :id', ['id' => $id]);
    logActivity('Delete User', 'admin', "Deleted user: {$user['username']}");
    $_SESSION['alert'] = ['message' => 'User deleted successfully', 'type' => 'success'];
    header('Location: users.php');
    exit;
}

// Get user data if editing/viewing
$user = null;
if ($id && ($action === 'edit' || $action === 'view')) {
    $user = fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
}

// Get all users
$users = fetchAll("SELECT id, username, email, role, status, created_at, 
                   (SELECT MAX(created_at) FROM activity_logs WHERE user_id = users.id) as last_login 
                   FROM users ORDER BY created_at DESC");

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-users-cog me-2"></i>User Management</h2>
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New User
        </a>
        <?php endif; ?>
    </div>
    
    <?php if ($action === 'list'): ?>
    <!-- List View -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Users</h6>
                    <h3 class="mb-0"><?php echo count($users); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-user-check"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Active Users</h6>
                    <h3 class="mb-0"><?php echo count(array_filter($users, fn($u) => $u['status'] === 'active')); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Admin Users</h6>
                    <h3 class="mb-0"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'admin')); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-user-times"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Inactive Users</h6>
                    <h3 class="mb-0"><?php echo count(array_filter($users, fn($u) => $u['status'] === 'inactive')); ?></h3>
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
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px; font-size: 12px;">
                                        <?php echo strtoupper(substr($u['username'], 0, 2)); ?>
                                    </div>
                                    <strong><?php echo $u['username']; ?></strong>
                                </div>
                            </td>
                            <td><?php echo $u['email']; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'manager' ? 'warning' : 'info'); ?>">
                                    <?php echo ucfirst($u['role']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $u['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($u['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $u['last_login'] ? date('M d, H:i', strtotime($u['last_login'])) : 'Never'; ?></td>
                            <td><?php echo formatDate($u['created_at']); ?></td>
                            <td>
                                <a href="?action=view&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?action=edit&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?action=reset_password&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-secondary" title="Reset Password" onclick="showPasswordReset(<?php echo $u['id']; ?>, '<?php echo $u['username']; ?>'); return false;">
                                    <i class="fas fa-key"></i>
                                </a>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <a href="?action=delete&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirmDelete()">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Form View -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?php echo $id ? 'Edit' : 'Add New'; ?> User</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="?action=save<?php echo $id ? '&id=' . $id : ''; ?>">
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" class="form-control" value="<?php echo $user['username'] ?? ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" value="<?php echo $user['email'] ?? ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password <?php echo $id ? '(leave blank to keep current)' : '*'; ?></label>
                            <input type="password" name="password" class="form-control" <?php echo !$id ? 'required' : ''; ?>>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role *</label>
                            <select name="role" class="form-select" required>
                                <option value="">Select Role</option>
                                <option value="staff" <?php echo ($user['role'] ?? '') === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                <option value="manager" <?php echo ($user['role'] ?? '') === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                <option value="admin" <?php echo ($user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?php echo ($user['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($user['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save User
                            </button>
                            <a href="users.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($action === 'view' && $user): ?>
    <!-- View User Details -->
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">User Profile</h5>
                </div>
                <div class="card-body text-center">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px; font-size: 2.5rem;">
                        <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                    </div>
                    <h4><?php echo $user['username']; ?></h4>
                    <p class="text-muted"><?php echo $user['email']; ?></p>
                    
                    <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'manager' ? 'warning' : 'info'); ?> fs-6">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                    
                    <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?> fs-6">
                        <?php echo ucfirst($user['status']); ?>
                    </span>
                    
                    <hr>
                    
                    <div class="text-start">
                        <p><i class="fas fa-calendar me-2 text-primary"></i> Created: <?php echo formatDate($user['created_at']); ?></p>
                        <p><i class="fas fa-clock me-2 text-primary"></i> Last Login: <?php echo date('M d, H:i', strtotime($user['last_login'] ?? '1970-01-01')); ?></p>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i>Edit User
                        </a>
                        <a href="#" class="btn btn-secondary" onclick="showPasswordReset(<?php echo $user['id']; ?>, '<?php echo $user['username']; ?>'); return false;">
                            <i class="fas fa-key me-2"></i>Reset Password
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Activity Log</h5>
                </div>
                <div class="card-body">
                    <?php
                    $activities = fetchAll("SELECT action, module, description, created_at 
                        FROM activity_logs 
                        WHERE user_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 50", [$user['id']]);
                    
                    if ($activities):
                    ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Module</th>
                                    <th>Description</th>
                                    <th>Date/Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activities as $activity): ?>
                                <tr>
                                    <td><?php echo $activity['action']; ?></td>
                                    <td><span class="badge bg-secondary"><?php echo ucfirst($activity['module']); ?></span></td>
                                    <td><?php echo $activity['description'] ?: '-'; ?></td>
                                    <td><?php echo date('M d, H:i:s', strtotime($activity['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No activity recorded for this user.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Password Reset Modal -->
<div class="modal fade" id="passwordResetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="?action=reset_password&id=" id="passwordResetForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        Resetting password for: <strong id="resetUsername"></strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password *</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password *</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showPasswordReset(userId, username) {
    document.getElementById('resetUsername').textContent = username;
    document.getElementById('passwordResetForm').action = '?action=reset_password&id=' + userId;
    new bootstrap.Modal(document.getElementById('passwordResetModal')).show();
}

// Password confirmation
document.getElementById('passwordResetForm').addEventListener('submit', function(e) {
    const password = this.elements.new_password.value;
    const confirmPassword = this.elements.confirm_password.value;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
    
    if (password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters!');
        return false;
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
