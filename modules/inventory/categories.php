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
            'category_name' => $_POST['category_name'],
            'category_code' => $_POST['category_code'] ?: 'CAT-' . strtoupper(substr(uniqid(), -4)),
            'description' => $_POST['description'] ?? '',
            'status' => $_POST['status'] ?? 'active'
        ];
        
        if ($id) {
            update('inventory_categories', $data, 'id = :id', ['id' => $id]);
            logActivity('Update Category', 'inventory', "Updated category: {$data['category_name']}");
            $_SESSION['alert'] = ['message' => 'Category updated successfully', 'type' => 'success'];
        } else {
            $newId = insert('inventory_categories', $data);
            logActivity('Create Category', 'inventory', "Created category ID: $newId");
            $_SESSION['alert'] = ['message' => 'Category created successfully', 'type' => 'success'];
        }
        
        header('Location: categories.php');
        exit;
    }
}

if ($action === 'delete' && $id) {
    $cat = fetchOne("SELECT category_name FROM inventory_categories WHERE id = ?", [$id]);
    delete('inventory_categories', 'id = :id', ['id' => $id]);
    logActivity('Delete Category', 'inventory', "Deleted category: {$cat['category_name']}");
    $_SESSION['alert'] = ['message' => 'Category deleted successfully', 'type' => 'success'];
    header('Location: categories.php');
    exit;
}

$category = null;
if ($id && ($action === 'edit' || $action === 'view')) {
    $category = fetchOne("SELECT * FROM inventory_categories WHERE id = ?", [$id]);
}

$categories = fetchAll("SELECT c.*, COUNT(p.id) as product_count 
    FROM inventory_categories c 
    LEFT JOIN inventory_products p ON c.id = p.category_id 
    GROUP BY c.id 
    ORDER BY c.category_name");

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-tags me-2"></i>Categories</h2>
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Category
        </a>
        <?php endif; ?>
    </div>
    
    <?php if ($action === 'list'): ?>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-tags"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Categories</h6>
                    <h4 class="mb-0"><?php echo count($categories); ?></h4>
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
                            <th>Category Code</th>
                            <th>Category Name</th>
                            <th>Description</th>
                            <th>Products</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $c): ?>
                        <tr>
                            <td><strong><?php echo $c['category_code']; ?></strong></td>
                            <td><?php echo $c['category_name']; ?></td>
                            <td><?php echo $c['description'] ?: '-'; ?></td>
                            <td>
                                <span class="badge bg-info"><?php echo $c['product_count']; ?> products</span>
                            </td>
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
        <div class="col-md-6 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?php echo $id ? 'Edit' : 'Add New'; ?> Category</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="?action=save<?php echo $id ? '&id=' . $id : ''; ?>">
                        <div class="mb-3">
                            <label class="form-label">Category Name *</label>
                            <input type="text" name="category_name" class="form-control" value="<?php echo $category['category_name'] ?? ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Category Code</label>
                            <input type="text" name="category_code" class="form-control" value="<?php echo $category['category_code'] ?? ''; ?>" placeholder="Auto-generated if empty">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"><?php echo $category['description'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?php echo ($category['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($category['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Category
                            </button>
                            <a href="categories.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($action === 'view' && $category): ?>
    <div class="row">
        <div class="col-md-6 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Category Details</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                            <i class="fas fa-tag"></i>
                        </div>
                        <h4><?php echo $category['category_name']; ?></h4>
                        <p class="text-muted"><?php echo $category['category_code']; ?></p>
                        <span class="badge bg-<?php echo $category['status'] === 'active' ? 'success' : 'secondary'; ?> fs-6">
                            <?php echo ucfirst($category['status']); ?>
                        </span>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <small class="text-muted">Products in Category</small>
                        <h5 class="mb-0"><?php echo $category['product_count']; ?></h5>
                    </div>
                    
                    <?php if ($category['description']): ?>
                    <div class="mb-3">
                        <small class="text-muted">Description</small>
                        <p class="mb-0"><?php echo nl2br($category['description']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex gap-2 justify-content-center mt-3">
                        <a href="?action=edit&id=<?php echo $category['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i>Edit
                        </a>
                        <a href="categories.php" class="btn btn-secondary">Back to List</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
