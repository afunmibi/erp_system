<?php
require_once '../../includes/header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $product_id = $_POST['product_id'];
        $transaction_type = $_POST['transaction_type'];
        $quantity = $_POST['quantity'];
        $unit_cost = $_POST['unit_cost'];
        $reference_number = $_POST['reference_number'];
        $notes = $_POST['notes'];
        
        $total_cost = $quantity * $unit_cost;
        
        $transaction_id = insert('inventory_transactions', [
            'product_id' => $product_id,
            'transaction_type' => $transaction_type,
            'quantity' => $quantity,
            'unit_cost' => $unit_cost,
            'total_cost' => $total_cost,
            'reference_number' => $reference_number,
            'notes' => $notes,
            'created_by' => $_SESSION['user_id']
        ]);
        
        // Update product stock
        $adjustment = ($transaction_type === 'purchase' || $transaction_type === 'return') ? $quantity : -$quantity;
        update('inventory_products', ['quantity_in_stock' => "quantity_in_stock + $adjustment"], 'id = ?', [$product_id]);
        
        logActivity('Add Transaction', 'inventory', "Added $transaction_type transaction");
        showAlert('Transaction added successfully');
    }
    
    header('Location: transactions.php');
    exit;
}

// Get all transactions
$transactions = fetchAll("SELECT t.*, p.product_name, p.sku 
    FROM inventory_transactions t 
    LEFT JOIN inventory_products p ON t.product_id = p.id 
    ORDER BY t.created_at DESC");

// Get all products for dropdown
$products = fetchAll("SELECT id, product_name, sku, quantity_in_stock FROM inventory_products WHERE status = 'active'");
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-0">Inventory Transactions</h2>
                <p class="text-muted mb-0">Track all inventory movements</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                <i class="fas fa-plus me-2"></i>New Transaction
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Unit Cost</th>
                            <th>Total Cost</th>
                            <th>Reference</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td><?php echo formatDate($t['created_at']); ?></td>
                            <td><?php echo $t['product_name']; ?> <small class="text-muted">(<?php echo $t['sku']; ?>)</small></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $t['transaction_type'] === 'purchase' ? 'success' : 
                                        ($t['transaction_type'] === 'sale' ? 'primary' : 
                                        ($t['transaction_type'] === 'return' ? 'info' : 'warning')); 
                                ?>">
                                    <?php echo ucfirst($t['transaction_type']); ?>
                                </span>
                            </td>
                            <td class="<?php echo ($t['transaction_type'] === 'purchase' || $t['transaction_type'] === 'return') ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($t['transaction_type'] === 'purchase' || $t['transaction_type'] === 'return') ? '+' : '-'; ?><?php echo $t['quantity']; ?>
                            </td>
                            <td><?php echo formatCurrency($t['unit_cost']); ?></td>
                            <td><?php echo formatCurrency($t['total_cost']); ?></td>
                            <td><?php echo $t['reference_number'] ?: '-'; ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-info" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Transaction Modal -->
<div class="modal fade" id="addTransactionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Inventory Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>">
                                <?php echo $p['product_name']; ?> (<?php echo $p['sku']; ?>) - Stock: <?php echo $p['quantity_in_stock']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Transaction Type</label>
                        <select name="transaction_type" class="form-select" required>
                            <option value="purchase">Purchase</option>
                            <option value="sale">Sale</option>
                            <option value="adjustment">Adjustment</option>
                            <option value="return">Return</option>
                            <option value="transfer">Transfer</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" class="form-control" min="1" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Unit Cost</label>
                            <input type="number" name="unit_cost" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reference Number</label>
                        <input type="text" name="reference_number" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
