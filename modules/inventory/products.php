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
            'product_code' => $_POST['product_code'] ?: 'PROD-' . strtoupper(substr(uniqid(), -5)),
            'product_name' => $_POST['product_name'],
            'category_id' => $_POST['category_id'] ?: null,
            'supplier_id' => $_POST['supplier_id'] ?: null,
            'unit_price' => $_POST['unit_price'] ?? 0,
            'cost_price' => $_POST['cost_price'] ?? 0,
            'quantity_in_stock' => $_POST['quantity_in_stock'] ?? 0,
            'reorder_level' => $_POST['reorder_level'] ?? 10,
            'expiry_date' => $_POST['expiry_date'] ?? null,
            'status' => $_POST['status'] ?? 'active'
        ];
        
        if ($id) {
            update('inventory_products', $data, 'id = :id', ['id' => $id]);
            logActivity('Update Product', 'inventory', "Updated product: {$data['product_name']}");
            $_SESSION['alert'] = ['message' => 'Product updated successfully', 'type' => 'success'];
        } else {
            $newId = insert('inventory_products', $data);
            logActivity('Create Product', 'inventory', "Created product ID: $newId");
            $_SESSION['alert'] = ['message' => 'Product created successfully', 'type' => 'success'];
        }
        
        header('Location: products.php');
        exit;
    }
}

if ($action === 'delete' && $id) {
    $product = fetchOne("SELECT product_name FROM inventory_products WHERE id = ?", [$id]);
    delete('inventory_products', 'id = :id', ['id' => $id]);
    logActivity('Delete Product', 'inventory', "Deleted product: {$product['product_name']}");
    $_SESSION['alert'] = ['message' => 'Product deleted successfully', 'type' => 'success'];
    header('Location: products.php');
    exit;
}

$product = null;
if ($id && ($action === 'edit' || $action === 'view')) {
    $product = fetchOne("SELECT * FROM inventory_products WHERE id = ?", [$id]);
}

$products = fetchAll("SELECT p.*, c.category_name, s.supplier_name 
    FROM inventory_products p 
    LEFT JOIN inventory_categories c ON p.category_id = c.id 
    LEFT JOIN inventory_suppliers s ON p.supplier_id = s.id 
    ORDER BY p.product_name");
$categories = fetchAll("SELECT id, category_name FROM inventory_categories ORDER BY category_name");
$suppliers = fetchAll("SELECT id, supplier_name FROM inventory_suppliers ORDER BY supplier_name");

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-box me-2"></i>Products</h2>
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Product
        </a>
        <?php endif; ?>
    </div>
    
    <?php if ($action === 'list'): ?>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-box"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Products</h6>
                    <h4 class="mb-0"><?php echo count($products); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Low Stock</h6>
                    <h4 class="mb-0"><?php echo count(array_filter($products, fn($p) => $p['quantity_in_stock'] <= $p['reorder_level'])); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-money-bill"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Value</h6>
                    <h4 class="mb-0"><?php echo formatCurrency(array_sum(array_map(fn($p) => $p['quantity_in_stock'] * $p['cost_price'], $products))); ?></h4>
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
                            <th>Product Code</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Supplier</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                        <tr class="<?php echo $p['quantity_in_stock'] <= $p['reorder_level'] ? 'table-warning' : ''; ?>">
                            <td><strong><?php echo $p['product_code']; ?></strong></td>
                            <td><?php echo $p['product_name']; ?></td>
                            <td><?php echo $p['category_name'] ?: '-'; ?></td>
                            <td><?php echo $p['supplier_name'] ?: '-'; ?></td>
                            <td><?php echo formatCurrency($p['unit_price']); ?></td>
                            <td>
                                <?php echo $p['quantity_in_stock']; ?>
                                <?php if ($p['quantity_in_stock'] <= $p['reorder_level']): ?>
                                <i class="fas fa-exclamation-circle text-warning ms-1" title="Low Stock"></i>
                                <?php endif; ?>
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
                    <h5 class="mb-0"><?php echo $id ? 'Edit' : 'Add New'; ?> Product</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="?action=save<?php echo $id ? '&id=' . $id : ''; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Name *</label>
                                <input type="text" name="product_name" class="form-control" value="<?php echo $product['product_name'] ?? ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Code</label>
                                <input type="text" name="product_code" class="form-control" value="<?php echo $product['product_code'] ?? ''; ?>" placeholder="Auto-generated if empty">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category</label>
                                <select name="category_id" class="form-select">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo ($product['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo $cat['category_name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Supplier</label>
                                <select name="supplier_id" class="form-select">
                                    <option value="">Select Supplier</option>
                                    <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?php echo $sup['id']; ?>" <?php echo ($product['supplier_id'] ?? '') == $sup['id'] ? 'selected' : ''; ?>>
                                        <?php echo $sup['supplier_name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Cost Price *</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="cost_price" class="form-control" step="0.01" min="0" value="<?php echo $product['cost_price'] ?? 0; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Unit Price *</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="unit_price" class="form-control" step="0.01" min="0" value="<?php echo $product['unit_price'] ?? 0; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Reorder Level</label>
                                <input type="number" name="reorder_level" class="form-control" min="0" value="<?php echo $product['reorder_level'] ?? 10; ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Initial Quantity</label>
                                <input type="number" name="quantity_in_stock" class="form-control" min="0" value="<?php echo $product['quantity_in_stock'] ?? 0; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Expiry Date</label>
                                <input type="date" name="expiry_date" class="form-control" value="<?php echo $product['expiry_date'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?php echo ($product['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($product['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="discontinued" <?php echo ($product['status'] ?? '') === 'discontinued' ? 'selected' : ''; ?>>Discontinued</option>
                            </select>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Product
                            </button>
                            <a href="products.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($action === 'view' && $product): ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Product Details</h5>
                </div>
                <div class="card-body text-center">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px; font-size: 2.5rem;">
                        <i class="fas fa-box"></i>
                    </div>
                    <h4><?php echo $product['product_name']; ?></h4>
                    <p class="text-muted"><?php echo $product['product_code']; ?></p>
                    <span class="badge bg-<?php echo $product['status'] === 'active' ? 'success' : 'secondary'; ?> fs-6">
                        <?php echo ucfirst($product['status']); ?>
                    </span>
                    
                    <hr>
                    
                    <div class="text-start">
                        <p><i class="fas fa-tag me-2 text-primary"></i> Category: <?php echo $product['category_name'] ?: 'N/A'; ?></p>
                        <p><i class="fas fa-truck me-2 text-primary"></i> Supplier: <?php echo $product['supplier_name'] ?: 'N/A'; ?></p>
                        <p><i class="fas fa-money-bill me-2 text-primary"></i> Cost: <?php echo formatCurrency($product['cost_price']); ?></p>
                        <p><i class="fas fa-tag me-2 text-primary"></i> Price: <?php echo formatCurrency($product['unit_price']); ?></p>
                    </div>
                    
                    <div class="d-flex gap-2 justify-content-center mt-3">
                        <a href="?action=edit&id=<?php echo $product['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i>Edit
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Stock Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <h3 class="mb-0 <?php echo $product['quantity_in_stock'] <= $product['reorder_level'] ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo $product['quantity_in_stock']; ?>
                                </h3>
                                <small class="text-muted">In Stock</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h3 class="mb-0"><?php echo $product['reorder_level']; ?></h3>
                                <small class="text-muted">Reorder Level</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h3 class="mb-0"><?php echo $product['expiry_date'] ? formatDate($product['expiry_date']) : 'N/A'; ?></h3>
                                <small class="text-muted">Expiry Date</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Value Analysis</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Stock Value (Cost):</strong> <?php echo formatCurrency($product['quantity_in_stock'] * $product['cost_price']); ?></p>
                            <p><strong>Stock Value (Price):</strong> <?php echo formatCurrency($product['quantity_in_stock'] * $product['unit_price']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Potential Profit:</strong> <?php echo formatCurrency($product['quantity_in_stock'] * ($product['unit_price'] - $product['cost_price'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
