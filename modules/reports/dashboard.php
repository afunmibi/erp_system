<?php
require_once '../../includes/header.php';

// Get date range filter
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-12 months'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Executive Summary Data
// Total Revenue from billing
$revenueQuery = "SELECT COALESCE(SUM(total_amount), 0) as total FROM billing_invoices WHERE status = 'paid' AND payment_date BETWEEN ? AND ?";
$totalRevenue = fetchOne($revenueQuery, [$startDate, $endDate])['total'] ?? 0;

// Total Payroll Expenses
$payrollQuery = "SELECT COALESCE(SUM(net_salary), 0) as total FROM payroll_records WHERE status = 'approved' AND created_at BETWEEN ? AND ?";
$totalPayroll = fetchOne($payrollQuery, [$startDate, $endDate])['total'] ?? 0;

// Total Inventory Purchases
$purchasesQuery = "SELECT COALESCE(SUM(total_cost), 0) as total FROM inventory_transactions WHERE transaction_type = 'purchase' AND created_at BETWEEN ? AND ?";
$totalPurchases = fetchOne($purchasesQuery, [$startDate, $endDate])['total'] ?? 0;

// Total Expenses
$totalExpenses = $totalPayroll + $totalPurchases;

// Net Profit/Loss
$netProfit = $totalRevenue - $totalExpenses;

// HMO Claims Ratio
$claimsSubmitted = fetchOne("SELECT COUNT(*) as count FROM hmo_claims WHERE created_at BETWEEN ? AND ?", [$startDate, $endDate])['count'] ?? 0;
$claimsApproved = fetchOne("SELECT COUNT(*) as count FROM hmo_claims WHERE status = 'approved' AND created_at BETWEEN ? AND ?", [$startDate, $endDate])['count'] ?? 0;
$claimsRatio = $claimsSubmitted > 0 ? round(($claimsApproved / $claimsSubmitted) * 100, 1) : 0;

// Charts Data - Revenue vs Expenses (12 months)
$months = [];
$revenueData = [];
$expensesData = [];
for ($i = 11; $i >= 0; $i--) {
    $monthStart = date('Y-m-01', strtotime("-$i months"));
    $monthEnd = date('Y-m-t', strtotime("-$i months"));
    $months[] = date('M Y', strtotime("-$i months"));
    
    $rev = fetchOne("SELECT COALESCE(SUM(total_amount), 0) as total FROM billing_invoices WHERE status = 'paid' AND payment_date BETWEEN ? AND ?", [$monthStart, $monthEnd])['total'] ?? 0;
    $pay = fetchOne("SELECT COALESCE(SUM(net_salary), 0) as total FROM payroll_records WHERE status = 'approved' AND created_at BETWEEN ? AND ?", [$monthStart, $monthEnd])['total'] ?? 0;
    $pur = fetchOne("SELECT COALESCE(SUM(total_cost), 0) as total FROM inventory_transactions WHERE transaction_type = 'purchase' AND created_at BETWEEN ? AND ?", [$monthStart, $monthEnd])['total'] ?? 0;
    
    $revenueData[] = $rev;
    $expensesData[] = $pay + $pur;
}

// Department-wise Payroll
$deptPayroll = fetchAll("SELECT d.department_name, COALESCE(SUM(p.net_salary), 0) as total FROM payroll_departments d 
    LEFT JOIN payroll_employees e ON d.id = e.department_id 
    LEFT JOIN payroll_records p ON e.id = p.employee_id AND p.status = 'approved' AND p.created_at BETWEEN ? AND ? 
    GROUP BY d.id ORDER BY total DESC", [$startDate, $endDate]);

// Category-wise Inventory
$categoryInventory = fetchAll("SELECT c.category_name, COUNT(p.id) as product_count, COALESCE(SUM(p.quantity_in_stock * p.unit_price), 0) as value 
    FROM inventory_categories c 
    LEFT JOIN inventory_products p ON c.id = p.category_id AND p.status = 'active' 
    GROUP BY c.id ORDER BY value DESC");

// Monthly Claims Trend
$claimsTrend = fetchAll("SELECT DATE_FORMAT(service_date, '%Y-%m') as month, COUNT(*) as count, SUM(amount) as total 
    FROM hmo_claims 
    WHERE service_date BETWEEN ? AND ? 
    GROUP BY DATE_FORMAT(service_date, '%Y-%m') 
    ORDER BY month", [$startDate, $endDate]);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-chart-line me-2"></i>Reports Dashboard</h2>
        <form method="GET" class="d-flex gap-2">
            <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $startDate; ?>">
            <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $endDate; ?>">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        </form>
    </div>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Revenue</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($totalRevenue); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Expenses</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($totalExpenses); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon <?php echo $netProfit >= 0 ? 'green' : 'red'; ?>">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Net Profit/Loss</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($netProfit); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Claims Approval Rate</h6>
                    <h3 class="mb-0"><?php echo $claimsRatio; ?>%</h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Revenue vs Expenses</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">HMO Claims Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="claimsChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Department Payroll & Inventory -->
    <div class="row mb-4">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Payroll by Department</h5>
                </div>
                <div class="card-body">
                    <canvas id="deptChart" height="200"></canvas>
                    <?php if (empty($deptPayroll)): ?>
                    <p class="text-muted text-center py-3">No payroll data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Inventory by Category</h5>
                </div>
                <div class="card-body">
                    <?php if ($categoryInventory): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Products</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categoryInventory as $cat): ?>
                                <tr>
                                    <td><?php echo $cat['category_name']; ?></td>
                                    <td><?php echo $cat['product_count']; ?></td>
                                    <td><?php echo formatCurrency($cat['value']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-center py-3">No inventory data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="row">
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted">Total Patients</h6>
                    <h3><?php echo fetchOne("SELECT COUNT(*) as count FROM hmo_patients")['count'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted">Total Employees</h6>
                    <h3><?php echo fetchOne("SELECT COUNT(*) as count FROM payroll_employees WHERE status = 'active'")['count'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted">Products</h6>
                    <h3><?php echo fetchOne("SELECT COUNT(*) as count FROM inventory_products WHERE status = 'active'")['count'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted">Clients</h6>
                    <h3><?php echo fetchOne("SELECT COUNT(*) as count FROM billing_clients WHERE status = 'active'")['count'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Revenue vs Expenses Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
new Chart(revenueCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($months); ?>,
        datasets: [
            {
                label: 'Revenue',
                data: <?php echo json_encode($revenueData); ?>,
                backgroundColor: '#10b981'
            },
            {
                label: 'Expenses',
                data: <?php echo json_encode($expensesData); ?>,
                backgroundColor: '#ef4444'
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top' }
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

// Claims Trend Chart
const claimsCtx = document.getElementById('claimsChart').getContext('2d');
new Chart(claimsCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($claimsTrend, 'month')); ?>,
        datasets: [{
            label: 'Claims Count',
            data: <?php echo json_encode(array_column($claimsTrend, 'count')); ?>,
            borderColor: '#8b5cf6',
            backgroundColor: 'rgba(139, 92, 246, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top' }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Department Chart
const deptCtx = document.getElementById('deptChart').getContext('2d');
new Chart(deptCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($deptPayroll, 'department_name')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($deptPayroll, 'total')); ?>,
            backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'right' }
        }
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
