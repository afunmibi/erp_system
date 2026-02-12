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
            'patient_code' => 'PAT-' . strtoupper(substr(uniqid(), -6)),
            'first_name' => $_POST['first_name'],
            'last_name' => $_POST['last_name'],
            'date_of_birth' => $_POST['date_of_birth'],
            'gender' => $_POST['gender'],
            'phone' => $_POST['phone'] ?? '',
            'email' => $_POST['email'] ?? '',
            'address' => $_POST['address'] ?? '',
            'hmo_provider_id' => $_POST['hmo_provider_id'] ?? null,
            'policy_number' => $_POST['policy_number'] ?? '',
            'enrollment_date' => $_POST['enrollment_date'] ?? null,
            'status' => $_POST['status'] ?? 'active'
        ];
        
        if ($id) {
            unset($data['patient_code']);
            update('hmo_patients', $data, 'id = :id', ['id' => $id]);
            logActivity('Update Patient', 'hmo', "Updated patient: {$data['first_name']} {$data['last_name']}");
            $_SESSION['alert'] = ['message' => 'Patient updated successfully', 'type' => 'success'];
        } else {
            $newId = insert('hmo_patients', $data);
            logActivity('Create Patient', 'hmo', "Created patient ID: $newId");
            $_SESSION['alert'] = ['message' => 'Patient created successfully', 'type' => 'success'];
        }
        
        header('Location: patients.php');
        exit;
    }
}

if ($action === 'delete' && $id) {
    $patient = fetchOne("SELECT first_name, last_name FROM hmo_patients WHERE id = ?", [$id]);
    delete('hmo_patients', 'id = :id', ['id' => $id]);
    logActivity('Delete Patient', 'hmo', "Deleted patient: {$patient['first_name']} {$patient['last_name']}");
    $_SESSION['alert'] = ['message' => 'Patient deleted successfully', 'type' => 'success'];
    header('Location: patients.php');
    exit;
}

$patient = null;
if ($id && ($action === 'edit' || $action === 'view')) {
    $patient = fetchOne("SELECT * FROM hmo_patients WHERE id = ?", [$id]);
}

$patients = fetchAll("SELECT p.*, pr.provider_name FROM hmo_patients p LEFT JOIN hmo_providers pr ON p.hmo_provider_id = pr.id ORDER BY p.created_at DESC");
$providers = fetchAll("SELECT id, provider_name FROM hmo_providers WHERE status = 'active' ORDER BY provider_name");

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-user-injured me-2"></i>HMO Patients</h2>
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Patient
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
                    <h6 class="text-muted mb-1">Total Patients</h6>
                    <h4 class="mb-0"><?php echo count($patients); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-user-check"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Active Patients</h6>
                    <h4 class="mb-0"><?php echo count(array_filter($patients, fn($p) => $p['status'] === 'active')); ?></h4>
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
                            <th>Patient Code</th>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Provider</th>
                            <th>Policy #</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $p): ?>
                        <tr>
                            <td><strong><?php echo $p['patient_code']; ?></strong></td>
                            <td><?php echo $p['first_name'] . ' ' . $p['last_name']; ?></td>
                            <td><?php echo ucfirst($p['gender']); ?></td>
                            <td><?php echo $p['provider_name'] ?: 'N/A'; ?></td>
                            <td><?php echo $p['policy_number'] ?: '-'; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $p['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($p['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="?action=view&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?action=edit&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?action=delete&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirmDelete()">
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
                    <h5 class="mb-0"><?php echo $id ? 'Edit' : 'Add New'; ?> Patient</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="?action=save<?php echo $id ? '&id=' . $id : ''; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" name="first_name" class="form-control" value="<?php echo $patient['first_name'] ?? ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="last_name" class="form-control" value="<?php echo $patient['last_name'] ?? ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date of Birth *</label>
                                <input type="date" name="date_of_birth" class="form-control" value="<?php echo $patient['date_of_birth'] ?? ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gender *</label>
                                <select name="gender" class="form-select" required>
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo ($patient['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo ($patient['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo ($patient['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo $patient['phone'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo $patient['email'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"><?php echo $patient['address'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">HMO Provider</label>
                                <select name="hmo_provider_id" class="form-select">
                                    <option value="">Select Provider</option>
                                    <?php foreach ($providers as $pr): ?>
                                    <option value="<?php echo $pr['id']; ?>" <?php echo ($patient['hmo_provider_id'] ?? '') == $pr['id'] ? 'selected' : ''; ?>>
                                        <?php echo $pr['provider_name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Policy Number</label>
                                <input type="text" name="policy_number" class="form-control" value="<?php echo $patient['policy_number'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Enrollment Date</label>
                                <input type="date" name="enrollment_date" class="form-control" value="<?php echo $patient['enrollment_date'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="active" <?php echo ($patient['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($patient['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="suspended" <?php echo ($patient['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Patient
                            </button>
                            <a href="patients.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($action === 'view' && $patient): ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Patient Information</h5>
                </div>
                <div class="card-body text-center">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px; font-size: 2.5rem;">
                        <i class="fas fa-user"></i>
                    </div>
                    <h4><?php echo $patient['first_name'] . ' ' . $patient['last_name']; ?></h4>
                    <p class="text-muted"><?php echo $patient['patient_code']; ?></p>
                    <span class="badge bg-<?php echo $patient['status'] === 'active' ? 'success' : 'secondary'; ?> fs-6">
                        <?php echo ucfirst($patient['status']); ?>
                    </span>
                    
                    <hr>
                    
                    <div class="text-start">
                        <p><i class="fas fa-calendar me-2 text-primary"></i> DOB: <?php echo formatDate($patient['date_of_birth']); ?></p>
                        <p><i class="fas fa-venus-mars me-2 text-primary"></i> <?php echo ucfirst($patient['gender']); ?></p>
                        <p><i class="fas fa-phone me-2 text-primary"></i> <?php echo $patient['phone'] ?: 'N/A'; ?></p>
                        <p><i class="fas fa-envelope me-2 text-primary"></i> <?php echo $patient['email'] ?: 'N/A'; ?></p>
                    </div>
                    
                    <div class="d-flex gap-2 justify-content-center mt-3">
                        <a href="?action=edit&id=<?php echo $patient['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i>Edit
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Insurance Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>HMO Provider:</strong> <?php echo $patient['provider_name'] ?: 'Not assigned'; ?></p>
                            <p><strong>Policy Number:</strong> <?php echo $patient['policy_number'] ?: 'N/A'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Enrollment Date:</strong> <?php echo $patient['enrollment_date'] ? formatDate($patient['enrollment_date']) : 'N/A'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Address</h5>
                </div>
                <div class="card-body">
                    <p><?php echo nl2br($patient['address']) ?: 'No address provided'; ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
