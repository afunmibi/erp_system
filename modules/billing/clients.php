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
            'client_name' => $_POST['client_name'],
            'client_type' => $_POST['client_type'],
            'contact_person' => $_POST['contact_person'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'email' => $_POST['email'] ?? '',
            'address' => $_POST['address'] ?? '',
            'credit_limit' => $_POST['credit_limit'] ?? 0,
            'status' => $_POST['status'] ?? 'active'
        ];
        
        if ($id) {
            update('billing_clients', $data, 'id = :id', ['id' => $id]);
            logActivity('Update Client', 'billing', "Updated client: {$data['client_name']}");
            $_SESSION['alert'] = ['message' => 'Client updated successfully', 'type' => 'success'];
        } else {
            $newId = insert('billing_clients', $data);
            $client_code = 'CLI' . str_pad($newId, 5, '0', STR_PAD_LEFT);
            update('billing_clients', ['client_code' => $client_code], 'id = :id', ['id' => $newId]);
            logActivity('Create Client', 'billing', "Created client ID: $newId");
            $_SESSION['alert'] = ['message' => 'Client created successfully', 'type' => 'success'];
        }
        
        header('Location: clients.php');
        exit;
    }
}

if ($action === 'delete' && $id) {
    $client = fetchOne("SELECT client_name FROM billing_clients WHERE id = ?", [$id]);
    delete('billing_clients', 'id = :id', ['id' => $id]);
    logActivity('Delete Client', 'billing', "Deleted client: {$client['client_name']}");
    $_SESSION['alert'] = ['message' => 'Client deleted successfully', 'type' => 'success'];
    header('Location: clients.php');
    exit;
}

$client = null;
if ($id && ($action === 'edit' || $action === 'view')) {
    $client = fetchOne("SELECT * FROM billing_clients WHERE id = ?", [$id]);
}

$clients = fetchAll("SELECT * FROM billing_clients ORDER BY created_at DESC");

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-users me-2"></i>Billing Clients</h2>
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Client
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
                    <h6 class="text-muted mb-1">Total Clients</h6>
                    <h4 class="mb-0"><?php echo count($clients); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Active Clients</h6>
                    <h4 class="mb-0"><?php echo count(array_filter($clients, fn($c) => $c['status'] === 'active')); ?></h4>
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
                            <th>Client Code</th>
                            <th>Client Name</th>
                            <th>Type</th>
                            <th>Contact Person</th>
                            <th>Phone</th>
                            <th>Credit Limit</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $c): ?>
                        <tr>
                            <td><strong><?php echo $c['client_code']; ?></strong></td>
                            <td><?php echo $c['client_name']; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $c['client_type'] === 'company' ? 'info' : ($c['client_type'] === 'government' ? 'primary' : 'secondary'); ?>">
                                    <?php echo ucfirst($c['client_type']); ?>
                                </span>
                            </td>
                            <td><?php echo $c['contact_person'] ?: '-'; ?></td>
                            <td><?php echo $c['phone'] ?: '-'; ?></td>
                            <td><?php echo formatCurrency($c['credit_limit']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $c['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($c['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="?action=view&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?action=delete&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirmDelete()">
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
                    <h5 class="mb-0"><?php echo $id ? 'Edit' : 'Add New'; ?> Client</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="?action=save<?php echo $id ? '&id=' . $id : ''; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Client Name *</label>
                                <input type="text" name="client_name" class="form-control" value="<?php echo $client['client_name'] ?? ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Client Type *</label>
                                <select name="client_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="individual" <?php echo ($client['client_type'] ?? '') === 'individual' ? 'selected' : ''; ?>>Individual</option>
                                    <option value="company" <?php echo ($client['client_type'] ?? '') === 'company' ? 'selected' : ''; ?>>Company</option>
                                    <option value="government" <?php echo ($client['client_type'] ?? '') === 'government' ? 'selected' : ''; ?>>Government</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Person</label>
                                <input type="text" name="contact_person" class="form-control" value="<?php echo $client['contact_person'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo $client['phone'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo $client['email'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Credit Limit</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="credit_limit" class="form-control" step="0.01" min="0" value="<?php echo $client['credit_limit'] ?? 0; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"><?php echo $client['address'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?php echo ($client['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($client['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Client
                            </button>
                            <a href="clients.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($action === 'view' && $client): ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Client Information</h5>
                </div>
                <div class="card-body text-center">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px; font-size: 2.5rem;">
                        <i class="fas fa-user"></i>
                    </div>
                    <h4><?php echo $client['client_name']; ?></h4>
                    <p class="text-muted"><?php echo $client['client_code']; ?></p>
                    <span class="badge bg-<?php echo $client['status'] === 'active' ? 'success' : 'secondary'; ?> fs-6">
                        <?php echo ucfirst($client['status']); ?>
                    </span>
                    
                    <hr>
                    
                    <div class="text-start">
                        <p><i class="fas fa-user me-2 text-primary"></i> <?php echo $client['contact_person'] ?: 'N/A'; ?></p>
                        <p><i class="fas fa-phone me-2 text-primary"></i> <?php echo $client['phone'] ?: 'N/A'; ?></p>
                        <p><i class="fas fa-envelope me-2 text-primary"></i> <?php echo $client['email'] ?: 'N/A'; ?></p>
                    </div>
                    
                    <div class="d-flex gap-2 justify-content-center mt-3">
                        <a href="?action=edit&id=<?php echo $client['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i>Edit
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Billing Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Client Type:</strong> <?php echo ucfirst($client['client_type']); ?></p>
                            <p><strong>Credit Limit:</strong> <?php echo formatCurrency($client['credit_limit']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Address</h5>
                </div>
                <div class="card-body">
                    <p><?php echo nl2br($client['address']) ?: 'No address provided'; ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
