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
            'supplier_name' => $_POST['supplier_name'],
            'supplier_code' => $_POST['supplier_code'] ?: 'SUP-' . strtoupper(substr(uniqid(), -4)),
            'contact_person' => $_POST['contact_person'] ?? '',
            'contact_phone' => $_POST['contact_phone'] ?? '',
            'email' => $_POST['email'] ?? '',
            'address' => $_POST['address'] ?? '',
            'payment_terms' => $_POST['payment_terms'] ?? '',
            'status' => $_POST['status'] ?? 'active'
        ];
        
        if ($id) {
            update('inventory_suppliers', $data, 'id = :id', ['id' => $id]);
            logActivity('Update Supplier', 'inventory', "Updated supplier: {$data['supplier_name']}");
            $_SESSION['alert'] = ['message' => 'Supplier updated successfully', 'type' => 'success'];
        } else {
            $newId = insert('inventory_suppliers', $data);
            logActivity('Create Supplier', 'inventory', "Created supplier ID: $newId");
            $_SESSION['alert'] = ['message' => 'Supplier created successfully', 'type' => 'success'];
        }
        
        header('Location: suppliers.php');
        exit;
    }
}

if ($action === 'delete' && $id) {
    $sup = fetchOne("SELECT supplier_name FROM inventory_suppliers WHERE id = ?", [$id]);
    delete('inventory_suppliers', 'id = :id', ['id' => $id]);
    logActivity('Delete Supplier', 'inventory', "Deleted supplier: {$sup['supplier_name']}");
    $_SESSION['alert'] = ['message' => 'Supplier deleted successfully', 'type' => 'success'];
    header('Location: suppliers.php');
    exit;
}

$supplier = null;
if ($id && ($action === 'edit' || $action === 'view')) {
    $supplier = fetchOne("SELECT * FROM inventory_suppliers WHERE id = ?", [$id]);
}

$suppliers = fetchAll("SELECT s.*, COUNT(p.id) as product_count 
    FROM inventory_suppliers s 
    LEFT JOIN inventory_products p ON s.id = p.supplier_id 
    GROUP BY s.id 
    ORDER BY s.supplier_name");

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-truck me-2"></i>Suppliers</h2>
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Supplier
        </a>
        <?php endif; ?>
    </div>
    
    <?php if ($action === 'list'): ?>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-truck"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Suppliers</h6>
                    <h4 class="mb-0"><?php echo count($suppliers); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Active Suppliers</h6>
                    <h4 class="mb-0"><?php echo count(array_filter($suppliers, fn($s) => $s['status'] === 'active')); ?></h4>
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
                            <th>Supplier Code</th>
                            <th>Supplier Name</th>
                            <th>Contact Person</th>
                            <th>Phone</th>
                            <th>Products</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $s): ?>
                        <tr>
                            <td><strong><?php echo $s['supplier_code']; ?></strong></td>
                            <td><?php echo $s['supplier_name']; ?></td>
                            <td><?php echo $s['contact_person'] ?: '-'; ?></td>
                            <td><?php echo $s['contact_phone'] ?: '-'; ?></td>
                            <td>
                                <span class="badge bg-info"><?php echo $s['product_count']; ?> products</span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $s['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($s['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="?action=view&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?action=edit&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?action=delete&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirmDelete()">
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
                    <h5 class="mb-0"><?php echo $id ? 'Edit' : 'Add New'; ?> Supplier</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="?action=save<?php echo $id ? '&id=' . $id : ''; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Supplier Name *</label>
                                <input type="text" name="supplier_name" class="form-control" value="<?php echo $supplier['supplier_name'] ?? ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Supplier Code</label>
                                <input type="text" name="supplier_code" class="form-control" value="<?php echo $supplier['supplier_code'] ?? ''; ?>" placeholder="Auto-generated if empty">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Person</label>
                                <input type="text" name="contact_person" class="form-control" value="<?php echo $supplier['contact_person'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Phone</label>
                                <input type="text" name="contact_phone" class="form-control" value="<?php echo $supplier['contact_phone'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo $supplier['email'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Terms</label>
                                <input type="text" name="payment_terms" class="form-control" value="<?php echo $supplier['payment_terms'] ?? ''; ?>" placeholder="e.g., Net 30">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"><?php echo $supplier['address'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?php echo ($supplier['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($supplier['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Supplier
                            </button>
                            <a href="suppliers.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($action === 'view' && $supplier): ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Supplier Details</h5>
                </div>
                <div class="card-body text-center">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px; font-size: 2.5rem;">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h4><?php echo $supplier['supplier_name']; ?></h4>
                    <p class="text-muted"><?php echo $supplier['supplier_code']; ?></p>
                    <span class="badge bg-<?php echo $supplier['status'] === 'active' ? 'success' : 'secondary'; ?> fs-6">
                        <?php echo ucfirst($supplier['status']); ?>
                    </span>
                    
                    <hr>
                    
                    <div class="text-start">
                        <p><i class="fas fa-user me-2 text-primary"></i> <?php echo $supplier['contact_person'] ?: 'N/A'; ?></p>
                        <p><i class="fas fa-phone me-2 text-primary"></i> <?php echo $supplier['contact_phone'] ?: 'N/A'; ?></p>
                        <p><i class="fas fa-envelope me-2 text-primary"></i> <?php echo $supplier['email'] ?: 'N/A'; ?></p>
                        <p><i class="fas fa-calendar me-2 text-primary"></i> Terms: <?php echo $supplier['payment_terms'] ?: 'N/A'; ?></p>
                    </div>
                    
                    <div class="d-flex gap-2 justify-content-center mt-3">
                        <a href="?action=edit&id=<?php echo $supplier['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i>Edit
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Address</h5>
                </div>
                <div class="card-body">
                    <p><?php echo nl2br($supplier['address']) ?: 'No address provided'; ?></p>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Products from this Supplier</h5>
                </div>
                <div class="card-body">
                    <p class="mb-0">This supplier provides <strong><?php echo $supplier['product_count']; ?></strong> products.</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
