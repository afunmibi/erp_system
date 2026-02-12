<?php
require_once '../../includes/header.php';

$inventoryStats = fetchOne("SELECT 
    COUNT(*) as total,
    SUM(quantity_in_stock) as total_items,
    SUM(quantity_in_stock * cost_price) as total_cost,
    SUM(quantity_in_stock * unit_price) as total_value,
    COUNT(CASE WHEN quantity_in_stock <= reorder_level THEN 1 END) as low_stock
    FROM inventory_products WHERE status = 'active'");

$categoryStats = fetchAll("SELECT c.category_name, COUNT(p.id) as product_count 
    FROM inventory_categories c 
    LEFT JOIN inventory_products p ON c.id = p.category_id AND p.status = 'active'
    GROUP BY c.id");
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-0">Inventory Reports</h2>
                <p class="text-muted mb-0">Stock and inventory analytics</p>
            </div>
            <button class="btn btn-outline-primary" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print Report
            </button>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-box"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Products</h6>
                    <h3 class="mb-0"><?php echo number_format($inventoryStats['total']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon teal">
                    <i class="fas fa-coins"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Inventory Value</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($inventoryStats['total_value']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Low Stock Items</h6>
                    <h3 class="mb-0"><?php echo number_format($inventoryStats['low_stock']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-cubes"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Items</h6>
                    <h3 class="mb-0"><?php echo number_format($inventoryStats['total_items']); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Products by Category</h5>
                </div>
                <div class="card-body">
                    <canvas id="categoryChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Low Stock Products</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Stock</th>
                                <th>Reorder Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $lowStock = fetchAll("SELECT product_name, quantity_in_stock, reorder_level 
                                FROM inventory_products 
                                WHERE quantity_in_stock <= reorder_level AND status = 'active'
                                LIMIT 10");
                            foreach ($lowStock as $p):
                            ?>
                            <tr>
                                <td><?php echo $p['product_name']; ?></td>
                                <td class="text-danger fw-bold"><?php echo $p['quantity_in_stock']; ?></td>
                                <td><?php echo $p['reorder_level']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
new Chart(document.getElementById('categoryChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_column($categoryStats, 'category_name')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($categoryStats, 'product_count')); ?>,
            backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6']
        }]
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
