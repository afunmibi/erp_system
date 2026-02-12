<?php
require_once '../../includes/header.php';

$billingStats = fetchOne("SELECT 
    COUNT(*) as total_invoices,
    SUM(total_amount) as total_amount,
    SUM(amount_paid) as total_paid,
    SUM(balance_due) as total_balance,
    COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_count,
    SUM(CASE WHEN status = 'overdue' THEN balance_due ELSE 0 END) as overdue_amount
    FROM billing_invoices");

$monthlyBilling = fetchAll("SELECT 
    DATE_FORMAT(invoice_date, '%Y-%m') as month,
    SUM(total_amount) as total,
    SUM(amount_paid) as paid
    FROM billing_invoices 
    WHERE invoice_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
    ORDER BY month");
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-0">Billing Reports</h2>
                <p class="text-muted mb-0">Invoices and payments analytics</p>
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
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Invoices</h6>
                    <h3 class="mb-0"><?php echo number_format($billingStats['total_invoices']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-money-bill"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Collected</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($billingStats['total_paid']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Balance Due</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($billingStats['total_balance']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Overdue</h6>
                    <h3 class="mb-0"><?php echo number_format($billingStats['overdue_count']); ?></h3>
                    <small class="text-danger"><?php echo formatCurrency($billingStats['overdue_amount']); ?></small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Monthly Billing Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="billingChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Invoice Status</h5>
                </div>
                <div class="card-body">
                    <canvas id="statusChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
new Chart(document.getElementById('billingChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($monthlyBilling, 'month')); ?>,
        datasets: [
            {
                label: 'Total',
                data: <?php echo json_encode(array_column($monthlyBilling, 'total')); ?>,
                backgroundColor: '#3b82f6'
            },
            {
                label: 'Paid',
                data: <?php echo json_encode(array_column($monthlyBilling, 'paid')); ?>,
                backgroundColor: '#10b981'
            }
        ]
    },
    options: { responsive: true, scales: { y: { beginAtZero: true } } }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
