<?php
require_once 'includes/header.php';

// Fetch dashboard statistics
$hmoStats = fetchOne("SELECT 
    COUNT(*) as total_patients,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_patients,
    (SELECT COUNT(*) FROM hmo_claims WHERE status = 'pending') as pending_claims,
    (SELECT COALESCE(SUM(amount), 0) FROM hmo_claims WHERE status = 'approved') as approved_claims_amount
FROM hmo_patients");

$inventoryStats = fetchOne("SELECT 
    COUNT(*) as total_products,
    COUNT(CASE WHEN quantity_in_stock <= reorder_level THEN 1 END) as low_stock,
    COALESCE(SUM(quantity_in_stock * unit_price), 0) as inventory_value
FROM inventory_products WHERE status = 'active'");

$billingStats = fetchOne("SELECT 
    COUNT(*) as total_invoices,
    COALESCE(SUM(CASE WHEN status = 'overdue' THEN balance_due END), 0) as overdue_amount,
    COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount END), 0) as collected_amount
FROM billing_invoices");

$payrollStats = fetchOne("SELECT 
    COUNT(*) as total_employees,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_employees,
    (SELECT COALESCE(SUM(net_salary), 0) FROM payroll_records WHERE payroll_period_id = (SELECT id FROM payroll_periods ORDER BY id DESC LIMIT 1)) as current_payroll
FROM payroll_employees");

// Recent activities
$recentActivities = fetchAll("SELECT al.*, u.username 
    FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC 
    LIMIT 10");

// Monthly data for charts
$monthlyRevenue = fetchAll("SELECT 
    DATE_FORMAT(invoice_date, '%Y-%m') as month,
    SUM(total_amount) as revenue
FROM billing_invoices 
WHERE invoice_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
ORDER BY month");

$monthlyClaims = fetchAll("SELECT 
    DATE_FORMAT(service_date, '%Y-%m') as month,
    COUNT(*) as claim_count,
    SUM(amount) as total_amount
FROM hmo_claims 
WHERE service_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
GROUP BY DATE_FORMAT(service_date, '%Y-%m')
ORDER BY month");

// Get provider count
$providerCount = fetchOne("SELECT COUNT(*) as count FROM hmo_providers");
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-3">Dashboard Overview</h2>
            <p class="text-muted">Welcome back, <?php echo $user['username']; ?>! Here's what's happening across your ERP system.</p>
        </div>
    </div>
    
    <!-- Stats Row 1: HMO -->
    <h5 class="mb-3 text-primary"><i class="fas fa-heartbeat me-2"></i>HMO Management</h5>
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Patients</h6>
                    <h3 class="mb-0"><?php echo number_format($hmoStats['total_patients'] ?? 0); ?></h3>
                    <small class="text-success"><i class="fas fa-arrow-up"></i> Active: <?php echo $hmoStats['active_patients'] ?? 0; ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-file-medical"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Pending Claims</h6>
                    <h3 class="mb-0"><?php echo number_format($hmoStats['pending_claims'] ?? 0); ?></h3>
                    <small class="text-warning">Awaiting approval</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Approved Claims</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($hmoStats['approved_claims_amount'] ?? 0); ?></h3>
                    <small class="text-success">Total value</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-hospital"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">HMO Providers</h6>
                    <h3 class="mb-0"><?php echo number_format($providerCount['count'] ?? 0); ?></h3>
                    <small class="text-muted">Network partners</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats Row 2: Inventory & Billing -->
    <h5 class="mb-3 text-success"><i class="fas fa-boxes me-2"></i>Inventory & Billing</h5>
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon teal">
                    <i class="fas fa-box"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Inventory Value</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($inventoryStats['inventory_value'] ?? 0); ?></h3>
                    <small class="text-muted"><?php echo $inventoryStats['total_products'] ?? 0; ?> products</small>
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
                    <h3 class="mb-0"><?php echo number_format($inventoryStats['low_stock'] ?? 0); ?></h3>
                    <small class="text-danger">Need reordering</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Collected</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($billingStats['collected_amount'] ?? 0); ?></h3>
                    <small class="text-success">From paid invoices</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Overdue Amount</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($billingStats['overdue_amount'] ?? 0); ?></h3>
                    <small class="text-warning">Needs attention</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats Row 3: Payroll -->
    <h5 class="mb-3 text-info"><i class="fas fa-money-check-alt me-2"></i>Payroll</h5>
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Employees</h6>
                    <h3 class="mb-0"><?php echo number_format($payrollStats['total_employees'] ?? 0); ?></h3>
                    <small class="text-success"><?php echo $payrollStats['active_employees'] ?? 0; ?> active</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Current Payroll</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($payrollStats['current_payroll'] ?? 0); ?></h3>
                    <small class="text-muted">Latest period</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Payroll Periods</h6>
                    <h3 class="mb-0"><?php echo number_format(fetchOne("SELECT COUNT(*) as count FROM payroll_periods")['count'] ?? 0); ?></h3>
                    <small class="text-muted">Processed</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Section -->
    <div class="row mb-4">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Monthly Revenue</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Monthly Claims</h5>
                </div>
                <div class="card-body">
                    <canvas id="claimsChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="row">
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5>
                    <a href="<?php echo $projectRootPath; ?>/modules/reports/dashboard.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Module</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentActivities as $activity): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 12px;">
                                                <?php echo strtoupper(substr($activity['username'] ?? 'S', 0, 1)); ?>
                                            </div>
                                            <?php echo $activity['username'] ?? 'System'; ?>
                                        </div>
                                    </td>
                                    <td><?php echo $activity['action']; ?></td>
                                    <td><span class="badge bg-secondary"><?php echo ucfirst($activity['module']); ?></span></td>
                                    <td><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?php echo $projectRootPath; ?>/modules/hmo/claims.php?action=new" class="btn btn-outline-primary">
                            <i class="fas fa-plus me-2"></i>New HMO Claim
                        </a>
                        <a href="<?php echo $projectRootPath; ?>/modules/billing/invoices.php?action=new" class="btn btn-outline-success">
                            <i class="fas fa-plus me-2"></i>Create Invoice
                        </a>
                        <a href="<?php echo $projectRootPath; ?>/modules/inventory/products.php?action=add" class="btn btn-outline-info">
                            <i class="fas fa-plus me-2"></i>Add Product
                        </a>
                        <a href="<?php echo $projectRootPath; ?>/modules/payroll/process.php" class="btn btn-outline-warning">
                            <i class="fas fa-calculator me-2"></i>Process Payroll
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Revenue Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($monthlyRevenue, 'month')); ?>,
        datasets: [{
            label: 'Revenue (₱)',
            data: <?php echo json_encode(array_column($monthlyRevenue, 'revenue')); ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₱' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Claims Chart
const claimsCtx = document.getElementById('claimsChart').getContext('2d');
new Chart(claimsCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($monthlyClaims, 'month')); ?>,
        datasets: [{
            label: 'Claims',
            data: <?php echo json_encode(array_column($monthlyClaims, 'claim_count')); ?>,
            backgroundColor: '#10b981'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
