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
            'provider_name' => $_POST['provider_name'],
            'provider_code' => $_POST['provider_code'] ?: 'HMO-' . strtoupper(substr(uniqid(), -4)),
            'contact_person' => $_POST['contact_person'] ?? '',
            'contact_phone' => $_POST['contact_phone'] ?? '',
            'email' => $_POST['email'] ?? '',
            'address' => $_POST['address'] ?? '',
            'contract_start' => $_POST['contract_start'] ?? null,
            'contract_end' => $_POST['contract_end'] ?? null,
            'discount_rate' => $_POST['discount_rate'] ?? 0,
            'status' => $_POST['status'] ?? 'active'
        ];
        
        if ($id) {
            update('hmo_providers', $data, 'id = :id', ['id' => $id]);
            logActivity('Update HMO Provider', 'hmo', "Updated provider: {$data['provider_name']}");
            $_SESSION['alert'] = ['message' => 'Provider updated successfully', 'type' => 'success'];
        } else {
            $newId = insert('hmo_providers', $data);
            logActivity('Create HMO Provider', 'hmo', "Created provider ID: $newId");
            $_SESSION['alert'] = ['message' => 'Provider created successfully', 'type' => 'success'];
        }
        
        header('Location: providers.php');
        exit;
    }
}

if ($action === 'delete' && $id) {
    $provider = fetchOne("SELECT provider_name FROM hmo_providers WHERE id = ?", [$id]);
    delete('hmo_providers', 'id = :id', ['id' => $id]);
    logActivity('Delete HMO Provider', 'hmo', "Deleted provider: {$provider['provider_name']}");
    $_SESSION['alert'] = ['message' => 'Provider deleted successfully', 'type' => 'success'];
    header('Location: providers.php');
    exit;
}

$provider = null;
if ($id && ($action === 'edit' || $action === 'view')) {
    $provider = fetchOne("SELECT * FROM hmo_providers WHERE id = ?", [$id]);
}

$providers = fetchAll("SELECT * FROM hmo_providers ORDER BY provider_name");

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-hospital me-2"></i>HMO Providers</h2>
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Provider
        </a>
        <?php endif; ?>
    </div>
    
    <?php if ($action === 'list'): ?>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-hospital"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Providers</h6>
                    <h4 class="mb-0"><?php echo count($providers); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Active Providers</h6>
                    <h4 class="mb-0"><?php echo count(array_filter($providers, fn($p) => $p['status'] === 'active')); ?></h4>
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
                            <th>Provider Code</th>
                            <th>Provider Name</th>
                            <th>Contact Person</th>
                            <th>Phone</th>
                            <th>Contract Period</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($providers as $p): ?>
                        <tr>
                            <td><strong><?php echo $p['provider_code']; ?></strong></td>
                            <td><?php echo $p['provider_name']; ?></td>
                            <td><?php echo $p['contact_person'] ?: '-'; ?></td>
                            <td><?php echo $p['contact_phone'] ?: '-'; ?></td>
                            <td>
                                <?php echo $p['contract_start'] ? formatDate($p['contract_start']) : '-'; ?>
                                <?php echo $p['contract_end'] ? ' - ' . formatDate($p['contract_end']) : ''; ?>
                            </td>
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
                    <h5 class="mb-0"><?php echo $id ? 'Edit' : 'Add New'; ?> HMO Provider</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="?action=save<?php echo $id ? '&id=' . $id : ''; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Provider Name *</label>
                                <input type="text" name="provider_name" class="form-control" value="<?php echo $provider['provider_name'] ?? ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Provider Code</label>
                                <input type="text" name="provider_code" class="form-control" value="<?php echo $provider['provider_code'] ?? ''; ?>" placeholder="Auto-generated if empty">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Person</label>
                                <input type="text" name="contact_person" class="form-control" value="<?php echo $provider['contact_person'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Phone</label>
                                <input type="text" name="contact_phone" class="form-control" value="<?php echo $provider['contact_phone'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo $provider['email'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Discount Rate (%)</label>
                                <input type="number" name="discount_rate" class="form-control" step="0.01" min="0" max="100" value="<?php echo $provider['discount_rate'] ?? 0; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"><?php echo $provider['address'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contract Start</label>
                                <input type="date" name="contract_start" class="form-control" value="<?php echo $provider['contract_start'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contract End</label>
                                <input type="date" name="contract_end" class="form-control" value="<?php echo $provider['contract_end'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?php echo ($provider['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($provider['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="pending" <?php echo ($provider['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Provider
                            </button>
                            <a href="providers.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($action === 'view' && $provider): ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Provider Details</h5>
                </div>
                <div class="card-body text-center">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px; font-size: 2.5rem;">
                        <i class="fas fa-hospital"></i>
                    </div>
                    <h4><?php echo $provider['provider_name']; ?></h4>
                    <p class="text-muted"><?php echo $provider['provider_code']; ?></p>
                    <span class="badge bg-<?php echo $provider['status'] === 'active' ? 'success' : 'secondary'; ?> fs-6">
                        <?php echo ucfirst($provider['status']); ?>
                    </span>
                    
                    <hr>
                    
                    <div class="text-start">
                        <p><i class="fas fa-user me-2 text-primary"></i> <?php echo $provider['contact_person'] ?: 'N/A'; ?></p>
                        <p><i class="fas fa-phone me-2 text-primary"></i> <?php echo $provider['contact_phone'] ?: 'N/A'; ?></p>
                        <p><i class="fas fa-envelope me-2 text-primary"></i> <?php echo $provider['email'] ?: 'N/A'; ?></p>
                        <p><i class="fas fa-percent me-2 text-primary"></i> Discount: <?php echo $provider['discount_rate']; ?>%</p>
                    </div>
                    
                    <div class="d-flex gap-2 justify-content-center mt-3">
                        <a href="?action=edit&id=<?php echo $provider['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i>Edit
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Contract Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Contract Start:</strong> <?php echo $provider['contract_start'] ? formatDate($provider['contract_start']) : 'Not set'; ?></p>
                            <p><strong>Contract End:</strong> <?php echo $provider['contract_end'] ? formatDate($provider['contract_end']) : 'Not set'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Discount Rate:</strong> <?php echo $provider['discount_rate']; ?>%</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Address</h5>
                </div>
                <div class="card-body">
                    <p><?php echo nl2br($provider['address']) ?: 'No address provided'; ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
