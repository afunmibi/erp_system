<?php
require_once '../../includes/header.php';

$payrollStats = fetchOne("SELECT 
    COUNT(*) as total_employees,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_employees,
    (SELECT COUNT(*) FROM payroll_periods) as total_periods,
    (SELECT COALESCE(SUM(net_salary), 0) FROM payroll_records WHERE status = 'paid') as total_paid
    FROM payroll_employees");

$monthlyPayroll = fetchAll("SELECT pp.period_name, SUM(pr.net_salary) as total 
    FROM payroll_records pr 
    JOIN payroll_periods pp ON pr.payroll_period_id = pp.id 
    WHERE pr.status = 'paid'
    GROUP BY pp.id 
    ORDER BY pp.pay_date DESC LIMIT 12");
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-0">Payroll Reports</h2>
                <p class="text-muted mb-0">Employee payroll analytics</p>
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
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Employees</h6>
                    <h3 class="mb-0"><?php echo number_format($payrollStats['total_employees']); ?></h3>
                    <small class="text-success"><?php echo $payrollStats['active_employees']; ?> active</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Paid</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($payrollStats['total_paid']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-calendar"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Payroll Periods</h6>
                    <h3 class="mb-0"><?php echo number_format($payrollStats['total_periods']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon teal">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Avg per Employee</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($payrollStats['total_employees'] > 0 ? $payrollStats['total_paid'] / $payrollStats['total_employees'] : 0); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Monthly Payroll Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="payrollChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Recent Payroll Runs</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Employees</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $periods = fetchAll("SELECT pp.*, COUNT(pr.id) as emp_count 
                                FROM payroll_periods pp 
                                LEFT JOIN payroll_records pr ON pp.id = pr.payroll_period_id 
                                GROUP BY pp.id 
                                ORDER BY pp.start_date DESC LIMIT 10");
                            foreach ($periods as $p):
                            ?>
                            <tr>
                                <td><?php echo $p['period_name']; ?></td>
                                <td><?php echo $p['emp_count']; ?></td>
                                <td>
                                    <?php
                                    $total = fetchOne("SELECT SUM(net_salary) as total FROM payroll_records WHERE payroll_period_id = ?", [$p['id']]);
                                    echo formatCurrency($total['total'] ?: 0);
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $p['status'] === 'closed' ? 'success' : ($p['status'] === 'processing' ? 'warning' : 'info'); ?>">
                                        <?php echo ucfirst($p['status']); ?>
                                    </span>
                                </td>
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
new Chart(document.getElementById('payrollChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($monthlyPayroll, 'period_name')); ?>,
        datasets: [{
            label: 'Total Payroll',
            data: <?php echo json_encode(array_column($monthlyPayroll, 'total')); ?>,
            borderColor: '#8b5cf6',
            backgroundColor: 'rgba(139, 92, 246, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: { responsive: true, scales: { y: { beginAtZero: true } } }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
