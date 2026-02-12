<?php
// Process form submissions BEFORE including header
require_once '../../includes/database.php';
require_once '../../auth/config.php';
requireAuth();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save') {
        $data = [
            'patient_id' => $_POST['patient_id'],
            'provider_id' => $_POST['provider_id'],
            'service_date' => $_POST['service_date'],
            'claim_type' => $_POST['claim_type'],
            'description' => $_POST['description'],
            'amount' => $_POST['amount'],
            'status' => $_POST['status']
        ];
        
        if ($id) {
            update('hmo_claims', $data, 'id = :id', ['id' => $id]);
            logActivity('Update Claim', 'hmo', "Updated claim ID: $id");
            $_SESSION['alert'] = ['message' => 'Claim updated successfully', 'type' => 'success'];
        } else {
            $datePrefix = 'CLM-' . date('Ymd') . '-';
            $lastClaim = fetchOne("SELECT claim_number FROM hmo_claims WHERE claim_number LIKE ? ORDER BY id DESC LIMIT 1", [$datePrefix . '%']);
            if ($lastClaim) {
                $lastNumber = intval(substr($lastClaim['claim_number'], -4));
                $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '0001';
            }
            $data['claim_number'] = $datePrefix . $newNumber;
            $data['submitted_date'] = date('Y-m-d H:i:s');
            $newId = insert('hmo_claims', $data);
            logActivity('Create Claim', 'hmo', "Created claim ID: $newId");
            $_SESSION['alert'] = ['message' => 'Claim created successfully', 'type' => 'success'];
        }
        
        header('Location: claims.php');
        exit;
    }
    
    if ($action === 'approve' && $id) {
        $data = [
            'status' => 'approved',
            'approved_amount' => $_POST['approved_amount'],
            'processed_date' => date('Y-m-d H:i:s'),
            'processed_by' => $_SESSION['user_id'],
            'notes' => $_POST['notes'] ?? ''
        ];
        update('hmo_claims', $data, 'id = :id', ['id' => $id]);
        logActivity('Approve Claim', 'hmo', "Approved claim ID: $id");
        $_SESSION['alert'] = ['message' => 'Claim approved successfully', 'type' => 'success'];
        header('Location: claims.php');
        exit;
    }
    
    if ($action === 'reject' && $id) {
        $data = [
            'status' => 'rejected',
            'approved_amount' => 0,
            'processed_date' => date('Y-m-d H:i:s'),
            'processed_by' => $_SESSION['user_id'],
            'notes' => $_POST['notes'] ?? ''
        ];
        update('hmo_claims', $data, 'id = :id', ['id' => $id]);
        logActivity('Reject Claim', 'hmo', "Rejected claim ID: $id");
        $_SESSION['alert'] = ['message' => 'Claim rejected successfully', 'type' => 'success'];
        header('Location: claims.php');
        exit;
    }
    
    if ($action === 'pay' && $id) {
        $data = [
            'status' => 'paid',
            'processed_date' => date('Y-m-d H:i:s'),
            'processed_by' => $_SESSION['user_id']
        ];
        update('hmo_claims', $data, 'id = :id', ['id' => $id]);
        logActivity('Process Payment', 'hmo', "Processed payment for claim ID: $id");
        $_SESSION['alert'] = ['message' => 'Claim marked as paid successfully', 'type' => 'success'];
        header('Location: claims.php');
        exit;
    }
}

if ($action === 'delete' && $id) {
    delete('hmo_claims', 'id = :id', ['id' => $id]);
    logActivity('Delete Claim', 'hmo', "Deleted claim ID: $id");
    $_SESSION['alert'] = ['message' => 'Claim deleted successfully', 'type' => 'success'];
    header('Location: claims.php');
    exit;
}

$patients = fetchAll("SELECT id, patient_code, first_name, last_name FROM hmo_patients WHERE status = 'active' ORDER BY last_name, first_name");
$providers = fetchAll("SELECT id, provider_name FROM hmo_providers WHERE status = 'active' ORDER BY provider_name");
$claim = null;
if ($id && ($action === 'edit' || $action === 'view' || $action === 'approve' || $action === 'reject')) {
    $claim = fetchOne("SELECT c.*, p.first_name, p.last_name, p.patient_code, pr.provider_name 
        FROM hmo_claims c 
        JOIN hmo_patients p ON c.patient_id = p.id 
        JOIN hmo_providers pr ON c.provider_id = pr.id 
        WHERE c.id = ?", [$id]);
}
$claims = fetchAll("SELECT c.*, p.first_name, p.last_name, p.patient_code, pr.provider_name 
    FROM hmo_claims c 
    JOIN hmo_patients p ON c.patient_id = p.id 
    JOIN hmo_providers pr ON c.provider_id = pr.id 
    ORDER BY c.submitted_date DESC");
$totals = fetchOne("SELECT 
    COUNT(*) as total_claims,
    SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
    SUM(CASE WHEN status = 'approved' THEN approved_amount ELSE 0 END) as approved_amount,
    SUM(CASE WHEN status = 'paid' THEN approved_amount ELSE 0 END) as paid_amount
    FROM hmo_claims");

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'approved': return 'info';
        case 'rejected': return 'danger';
        case 'paid': return 'success';
        default: return 'secondary';
    }
}

$claimTypes = [
    'consultation' => 'Consultation',
    'procedure' => 'Procedure',
    'medication' => 'Medication',
    'laboratory' => 'Laboratory',
    'hospitalization' => 'Hospitalization',
    'other' => 'Other'
];

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-file-invoice-dollar me-2"></i>HMO Claims</h2>
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Claim
        </a>
        <?php endif; ?>
    </div>
    
    <?php if ($action === 'list'): ?>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Claims</h6>
                    <h4 class="mb-0"><?php echo number_format($totals['total_claims'] ?? 0); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Pending Amount</h6>
                    <h4 class="mb-0"><?php echo formatCurrency($totals['pending_amount'] ?? 0); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Approved Amount</h6>
                    <h4 class="mb-0"><?php echo formatCurrency($totals['approved_amount'] ?? 0); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Paid Amount</h6>
                    <h4 class="mb-0"><?php echo formatCurrency($totals['paid_amount'] ?? 0); ?></h4>
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
                            <th>Claim #</th>
                            <th>Patient</th>
                            <th>Provider</th>
                            <th>Service Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Approved</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($claims as $c): ?>
                        <tr>
                            <td><strong><?php echo $c['claim_number']; ?></strong></td>
                            <td><?php echo $c['first_name'] . ' ' . $c['last_name']; ?></td>
                            <td><?php echo $c['provider_name']; ?></td>
                            <td><?php echo formatDate($c['service_date']); ?></td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?php echo $claimTypes[$c['claim_type']] ?? ucfirst($c['claim_type']); ?>
                                </span>
                            </td>
                            <td><?php echo formatCurrency($c['amount']); ?></td>
                            <td><?php echo $c['approved_amount'] > 0 ? formatCurrency($c['approved_amount']) : '-'; ?></td>
                            <td>
                                <span class="badge bg-<?php echo getStatusBadgeClass($c['status']); ?>">
                                    <?php echo ucfirst($c['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="?action=view&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-info" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($c['status'] === 'pending'): ?>
                                    <a href="?action=approve&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-success" title="Approve">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <a href="?action=reject&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-danger" title="Reject">
                                        <i class="fas fa-times"></i>
                                    </a>
                                    <?php elseif ($c['status'] === 'approved'): ?>
                                    <a href="?action=pay&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-primary" title="Mark as Paid" onclick="return confirm('Mark this claim as paid?')">
                                        <i class="fas fa-money-bill"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?action=delete&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirmDelete('Are you sure you want to delete this claim?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
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
                    <h5 class="mb-0"><?php echo $id ? 'Edit' : 'Add New'; ?> Claim</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="?action=save<?php echo $id ? '&id=' . $id : ''; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Patient *</label>
                                <select name="patient_id" class="form-select" required>
                                    <option value="">Select Patient</option>
                                    <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>" <?php echo ($claim['patient_id'] ?? '') == $patient['id'] ? 'selected' : ''; ?>>
                                        <?php echo $patient['patient_code'] . ' - ' . $patient['last_name'] . ', ' . $patient['first_name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Provider *</label>
                                <select name="provider_id" class="form-select" required>
                                    <option value="">Select Provider</option>
                                    <?php foreach ($providers as $provider): ?>
                                    <option value="<?php echo $provider['id']; ?>" <?php echo ($claim['provider_id'] ?? '') == $provider['id'] ? 'selected' : ''; ?>>
                                        <?php echo $provider['provider_name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Service Date *</label>
                                <input type="date" name="service_date" class="form-control" value="<?php echo $claim['service_date'] ?? ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Claim Type *</label>
                                <select name="claim_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <?php foreach ($claimTypes as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($claim['claim_type'] ?? '') === $key ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"><?php echo $claim['description'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Amount *</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="amount" class="form-control" step="0.01" min="0" value="<?php echo $claim['amount'] ?? ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="pending" <?php echo ($claim['status'] ?? 'pending') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo ($claim['status'] ?? '') === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo ($claim['status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="paid" <?php echo ($claim['status'] ?? '') === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Claim
                            </button>
                            <a href="claims.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($action === 'view' && $claim): ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Claim Information</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <h4><?php echo $claim['claim_number']; ?></h4>
                        <span class="badge bg-<?php echo getStatusBadgeClass($claim['status']); ?> fs-6">
                            <?php echo ucfirst($claim['status']); ?>
                        </span>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <small class="text-muted">Claim Type</small>
                        <p class="mb-0"><strong><?php echo $claimTypes[$claim['claim_type']] ?? ucfirst($claim['claim_type']); ?></strong></p>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted">Service Date</small>
                        <p class="mb-0"><strong><?php echo formatDate($claim['service_date']); ?></strong></p>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted">Submitted Date</small>
                        <p class="mb-0"><strong><?php echo date('M d, Y h:i A', strtotime($claim['submitted_date'])); ?></strong></p>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted">Claim Amount</small>
                        <h5 class="mb-0 text-primary"><?php echo formatCurrency($claim['amount']); ?></h5>
                    </div>
                    
                    <?php if ($claim['approved_amount'] > 0): ?>
                    <div class="mb-3">
                        <small class="text-muted">Approved Amount</small>
                        <h5 class="mb-0 text-success"><?php echo formatCurrency($claim['approved_amount']); ?></h5>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if ($claim['status'] === 'pending'): ?>
                        <a href="?action=approve&id=<?php echo $claim['id']; ?>" class="btn btn-success">
                            <i class="fas fa-check me-2"></i>Approve Claim
                        </a>
                        <a href="?action=reject&id=<?php echo $claim['id']; ?>" class="btn btn-danger">
                            <i class="fas fa-times me-2"></i>Reject Claim
                        </a>
                        <?php elseif ($claim['status'] === 'approved'): ?>
                        <a href="?action=pay&id=<?php echo $claim['id']; ?>" class="btn btn-primary" onclick="return confirm('Mark this claim as paid?')">
                            <i class="fas fa-money-bill me-2"></i>Mark as Paid
                        </a>
                        <?php endif; ?>
                        <a href="?action=edit&id=<?php echo $claim['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i>Edit Claim
                        </a>
                        <a href="claims.php" class="btn btn-secondary">Back to List</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Patient Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Patient Name:</strong> <?php echo $claim['first_name'] . ' ' . $claim['last_name']; ?></p>
                            <p><strong>Patient Code:</strong> <?php echo $claim['patient_code']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Provider:</strong> <?php echo $claim['provider_name']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Description</h5>
                </div>
                <div class="card-body">
                    <?php if ($claim['description']): ?>
                    <p><?php echo nl2br($claim['description']); ?></p>
                    <?php else: ?>
                    <p class="text-muted">No description provided.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php elseif ($action === 'approve' && $id && $claim): ?>
    <div class="row">
        <div class="col-md-6 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Approve Claim: <?php echo $claim['claim_number']; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="?action=approve&id=<?php echo $id; ?>">
                        <div class="mb-3">
                            <label class="form-label">Claim Amount</label>
                            <input type="text" class="form-control" value="<?php echo formatCurrency($claim['amount']); ?>" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Approved Amount *</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" name="approved_amount" class="form-control" step="0.01" min="0" value="<?php echo $claim['amount']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Optional notes..."></textarea>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check me-2"></i>Approve Claim
                            </button>
                            <a href="claims.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php elseif ($action === 'reject' && $id && $claim): ?>
    <div class="row">
        <div class="col-md-6 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Reject Claim: <?php echo $claim['claim_number']; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="?action=reject&id=<?php echo $id; ?>">
                        <div class="mb-3">
                            <label class="form-label">Claim Amount</label>
                            <input type="text" class="form-control" value="<?php echo formatCurrency($claim['amount']); ?>" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Rejection *</label>
                            <textarea name="notes" class="form-control" rows="3" required></textarea>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-times me-2"></i>Reject Claim
                            </button>
                            <a href="claims.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
