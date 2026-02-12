<?php
require_once '../../includes/header.php';

// Get HMO statistics
$patientStats = fetchOne("SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
    COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive
    FROM hmo_patients");

$claimsStats = fetchOne("SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
    SUM(amount) as total_amount,
    SUM(CASE WHEN status = 'approved' THEN approved_amount ELSE 0 END) as approved_amount
    FROM hmo_claims");

$providerStats = fetchOne("SELECT COUNT(*) as total FROM hmo_providers WHERE status = 'active'");

// Monthly claims trend
$monthlyClaims = fetchAll("SELECT 
    DATE_FORMAT(service_date, '%Y-%m') as month,
    COUNT(*) as claim_count,
    SUM(amount) as total_amount
    FROM hmo_claims 
    WHERE service_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(service_date, '%Y-%m')
    ORDER BY month");
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-0">HMO Reports</h2>
                <p class="text-muted mb-0">Healthcare Management Analytics</p>
            </div>
            <button class="btn btn-outline-primary" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print Report
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Patients</h6>
                    <h3 class="mb-0"><?php echo number_format($patientStats['total']); ?></h3>
                    <small class="text-success"><?php echo $patientStats['active']; ?> active</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-file-medical"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Claims</h6>
                    <h3 class="mb-0"><?php echo number_format($claimsStats['total']); ?></h3>
                    <small class="text-warning"><?php echo $claimsStats['pending']; ?> pending</small>
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
                    <h3 class="mb-0"><?php echo formatCurrency($claimsStats['approved_amount']); ?></h3>
                    <small class="text-muted"><?php echo $claimsStats['approved']; ?> approved</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-hospital"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Active Providers</h6>
                    <h3 class="mb-0"><?php echo number_format($providerStats['total']); ?></h3>
                    <small class="text-muted">Network partners</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Monthly Claims Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="claimsTrendChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Claims by Status</h5>
                </div>
                <div class="card-body">
                    <canvas id="claimsStatusChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Claims -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Recent Claims</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Claim #</th>
                                <th>Patient</th>
                                <th>Provider</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recentClaims = fetchAll("SELECT c.*, p.first_name, p.last_name, pr.provider_name 
                                FROM hmo_claims c 
                                LEFT JOIN hmo_patients p ON c.patient_id = p.id 
                                LEFT JOIN hmo_providers pr ON c.provider_id = pr.id 
                                ORDER BY c.submitted_date DESC LIMIT 10");
                            foreach ($recentClaims as $claim): 
                            ?>
                            <tr>
                                <td><strong><?php echo $claim['claim_number']; ?></strong></td>
                                <td><?php echo $claim['first_name'] . ' ' . $claim['last_name']; ?></td>
                                <td><?php echo $claim['provider_name']; ?></td>
                                <td><?php echo formatCurrency($claim['amount']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $claim['status'] === 'approved' ? 'success' : 
                                            ($claim['status'] === 'pending' ? 'warning' : 
                                            ($claim['status'] === 'rejected' ? 'danger' : 'info')); 
                                    ?>">
                                        <?php echo ucfirst($claim['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($claim['submitted_date']); ?></td>
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
const claimsTrendCtx = document.getElementById('claimsTrendChart').getContext('2d');
new Chart(claimsTrendCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($monthlyClaims, 'month')); ?>,
        datasets: [{
            label: 'Claims',
            data: <?php echo json_encode(array_column($monthlyClaims, 'claim_count')); ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        scales: { y: { beginAtZero: true } }
    }
});

const claimsStatusCtx = document.getElementById('claimsStatusChart').getContext('2d');
new Chart(claimsStatusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Pending', 'Approved', 'Rejected'],
        datasets: [{
            data: [<?php echo $claimsStats['pending']; ?>, <?php echo $claimsStats['approved']; ?>, <?php echo $claimsStats['rejected']; ?>],
            backgroundColor: ['#f59e0b', '#10b981', '#ef4444']
        }]
    },
    options: {
        responsive: true
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
