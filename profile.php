<?php
require_once 'includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        update('users', [
            'email' => $_POST['email']
        ], 'id = ?', [$_SESSION['user_id']]);
        
        $_SESSION['user_email'] = $_POST['email'];
        logActivity('Update Profile', 'auth', 'User updated profile');
        showAlert('Profile updated successfully');
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $user = fetchOne("SELECT password FROM users WHERE id = ?", [$_SESSION['user_id']]);
        
        if (!password_verify($current_password, $user['password'])) {
            showAlert('Current password is incorrect', 'danger');
        } elseif ($new_password !== $confirm_password) {
            showAlert('New passwords do not match', 'danger');
        } elseif (strlen($new_password) < 6) {
            showAlert('Password must be at least 6 characters', 'danger');
        } else {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            update('users', ['password' => $hash], 'id = ?', [$_SESSION['user_id']]);
            logActivity('Change Password', 'auth', 'User changed password');
            showAlert('Password changed successfully');
        }
    }
    
    header('Location: profile.php');
    exit;
}

$user = fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">My Profile</h2>
            <p class="text-muted mb-0">Manage your account settings</p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 100px; height: 100px; font-size: 2.5rem;">
                        <i class="fas fa-user"></i>
                    </div>
                    <h4><?php echo $user['username']; ?></h4>
                    <p class="text-muted"><?php echo ucfirst($user['role']); ?></p>
                    <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>">
                        <?php echo ucfirst($user['status']); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Profile Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?php echo $user['username']; ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo $user['email']; ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Member Since</label>
                                <input type="text" class="form-control" value="<?php echo formatDate($user['created_at']); ?>" disabled>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" minlength="6" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-key me-2"></i>Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
