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
            'client_id' => $_POST['client_id'],
            'invoice_number' => $_POST['invoice_number'] ?: 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)),
            'invoice_date' => $_POST['invoice_date'],
            'due_date' => $_POST['due_date'],
            'subtotal' => $_POST['subtotal'] ?? 0,
            'tax_rate' => $_POST['tax_rate'] ?? 0,
            'tax_amount' => $_POST['tax_amount'] ?? 0,
            'discount_type' => $_POST['discount_type'] ?? 'none',
            'discount_value' => $_POST['discount_value'] ?? 0,
            'total_amount' => $_POST['total_amount'] ?? 0,
            'notes' => $_POST['notes'] ?? '',
            'status' => $_POST['status'] ?? 'draft'
        ];
        
        if ($id) {
            update('billing_invoices', $data, 'id = :id', ['id' => $id]);
            logActivity('Update Invoice', 'billing', "Updated invoice: {$data['invoice_number']}");
            $_SESSION['alert'] = ['message' => 'Invoice updated successfully', 'type' => 'success'];
        } else {
            $newId = insert('billing_invoices', $data);
            logActivity('Create Invoice', 'billing', "Created invoice ID: $newId");
            $_SESSION['alert'] = ['message' => 'Invoice created successfully', 'type' => 'success'];
        }
        
        header('Location: invoices.php');
        exit;
    }
}

if ($action === 'delete' && $id) {
    $inv = fetchOne("SELECT invoice_number FROM billing_invoices WHERE id = ?", [$id]);
    delete('billing_invoices', 'id = :id', ['id' => $id]);
    logActivity('Delete Invoice', 'billing', "Deleted invoice: {$inv['invoice_number']}");
    $_SESSION['alert'] = ['message' => 'Invoice deleted successfully', 'type' => 'success'];
    header('Location: invoices.php');
    exit;
}

if ($action === 'mark_paid' && $id) {
    update('billing_invoices', ['status' => 'paid', 'paid_date' => date('Y-m-d')], 'id = :id', ['id' => $id]);
    $_SESSION['alert'] = ['message' => 'Invoice marked as paid', 'type' => 'success'];
    header('Location: invoices.php');
    exit;
}

$invoice = null;
if ($id && ($action === 'edit' || $action === 'view')) {
    $invoice = fetchOne("SELECT i.*, c.client_name 
        FROM billing_invoices i 
        JOIN billing_clients c ON i.client_id = c.id 
        WHERE i.id = ?", [$id]);
}

$invoices = fetchAll("SELECT i.*, c.client_name 
    FROM billing_invoices i 
    JOIN billing_clients c ON i.client_id = c.id 
    ORDER BY i.invoice_date DESC");
$clients = fetchAll("SELECT id, client_name FROM billing_clients ORDER BY client_name");

$totals = fetchOne("SELECT 
    COUNT(*) as total_invoices,
    SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as paid_amount,
    SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END) as pending_amount,
    SUM(CASE WHEN status = 'overdue' THEN total_amount ELSE 0 END) as overdue_amount
    FROM billing_invoices");

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-file-invoice me-2"></i>Invoices</h2>
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Create Invoice
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
                    <h6 class="text-muted mb-1">Total Invoices</h6>
                    <h4 class="mb-0"><?php echo count($invoices); ?></h4>
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
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
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
                            <th>Invoice #</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Due Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><strong><?php echo $inv['invoice_number']; ?></strong></td>
                            <td><?php echo $inv['client_name']; ?></td>
                            <td><?php echo formatDate($inv['invoice_date']); ?></td>
                            <td><?php echo formatDate($inv['due_date']); ?></td>
                            <td><strong><?php echo formatCurrency($inv['total_amount']); ?></strong></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $inv['status'] === 'paid' ? 'success' : 
                                        ($inv['status'] === 'cancelled' ? 'danger' : 
                                        ($inv['status'] === 'overdue' ? 'warning' : 'info')); 
                                ?>">
                                    <?php echo ucfirst($inv['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="?action=view&id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?action=edit&id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($inv['status'] === 'pending'): ?>
                                <a href="?action=mark_paid&id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-success" title="Mark as Paid" onclick="return confirm('Mark this invoice as paid?')">
                                    <i class="fas fa-check"></i>
                                </a>
                                <?php endif; ?>
                                <a href="?action=delete&id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirmDelete()">
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
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?php echo $id ? 'Edit' : 'Create New'; ?> Invoice</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="?action=save<?php echo $id ? '&id=' . $id : ''; ?>">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Client *</label>
                                <select name="client_id" class="form-select" required>
                                    <option value="">Select Client</option>
                                    <?php foreach ($clients as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo ($invoice['client_id'] ?? '') == $c['id'] ? 'selected' : ''; ?>>
                                        <?php echo $c['client_name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Invoice Number</label>
                                <input type="text" name="invoice_number" class="form-control" value="<?php echo $invoice['invoice_number'] ?? ''; ?>" placeholder="Auto-generated if empty">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="draft" <?php echo ($invoice['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="pending" <?php echo ($invoice['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="paid" <?php echo ($invoice['status'] ?? '') === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="cancelled" <?php echo ($invoice['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Invoice Date *</label>
                                <input type="date" name="invoice_date" class="form-control" value="<?php echo $invoice['invoice_date'] ?? date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Due Date *</label>
                                <input type="date" name="due_date" class="form-control" value="<?php echo $invoice['due_date'] ?? ''; ?>" required>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Subtotal *</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="subtotal" class="form-control" step="0.01" min="0" value="<?php echo $invoice['subtotal'] ?? 0; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Tax Rate (%)</label>
                                <input type="number" name="tax_rate" class="form-control" step="0.01" min="0" value="<?php echo $invoice['tax_rate'] ?? 0; ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Tax Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="tax_amount" class="form-control" step="0.01" min="0" value="<?php echo $invoice['tax_amount'] ?? 0; ?>">
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Discount</label>
                                <input type="number" name="discount_value" class="form-control" step="0.01" min="0" value="<?php echo $invoice['discount_value'] ?? 0; ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Total Amount *</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="total_amount" class="form-control" step="0.01" min="0" value="<?php echo $invoice['total_amount'] ?? 0; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"><?php echo $invoice['notes'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Invoice
                            </button>
                            <a href="invoices.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($action === 'view' && $invoice): ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Invoice: <?php echo $invoice['invoice_number']; ?></h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <strong>Client:</strong> <?php echo $invoice['client_name']; ?><br>
                            <strong>Invoice Date:</strong> <?php echo formatDate($invoice['invoice_date']); ?><br>
                            <strong>Due Date:</strong> <?php echo formatDate($invoice['due_date']); ?>
                        </div>
                        <div class="col-md-6 text-end">
                            <span class="badge bg-<?php 
                                echo $invoice['status'] === 'paid' ? 'success' : 
                                    ($invoice['status'] === 'cancelled' ? 'danger' : 
                                    ($invoice['status'] === 'overdue' ? 'warning' : 'info')); 
                            ?> fs-6">
                                <?php echo ucfirst($invoice['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <table class="table">
                        <tr>
                            <td><strong>Subtotal:</strong></td>
                            <td class="text-end"><?php echo formatCurrency($invoice['subtotal']); ?></td>
                        </tr>
                        <tr>
                            <td>Tax (<?php echo $invoice['tax_rate']; ?>%):</td>
                            <td class="text-end"><?php echo formatCurrency($invoice['tax_amount']); ?></td>
                        </tr>
                        <tr>
                            <td>Discount:</td>
                            <td class="text-end">-<?php echo formatCurrency($invoice['discount_value']); ?></td>
                        </tr>
                        <tr class="table-active">
                            <td><strong>Total Amount:</strong></td>
                            <td class="text-end"><strong><?php echo formatCurrency($invoice['total_amount']); ?></strong></td>
                        </tr>
                    </table>
                    
                    <?php if ($invoice['notes']): ?>
                    <div class="mt-4">
                        <strong>Notes:</strong>
                        <p><?php echo nl2br($invoice['notes']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex gap-2 mt-4">
                        <?php if ($invoice['status'] === 'pending'): ?>
                        <a href="?action=mark_paid&id=<?php echo $invoice['id']; ?>" class="btn btn-success" onclick="return confirm('Mark as paid?')">
                            <i class="fas fa-check me-2"></i>Mark as Paid
                        </a>
                        <?php endif; ?>
                        <a href="?action=edit&id=<?php echo $invoice['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i>Edit
                        </a>
                        <a href="invoices.php" class="btn btn-secondary">Back to List</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
