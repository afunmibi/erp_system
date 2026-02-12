<?php
require_once '../../includes/header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $period_id = insert('payroll_periods', [
            'period_name' => $_POST['period_name'],
            'start_date' => $_POST['start_date'],
            'end_date' => $_POST['end_date'],
            'pay_date' => $_POST['pay_date'],
            'status' => $_POST['status']
        ]);
        logActivity('Add Payroll Period', 'payroll', "Created period: {$_POST['period_name']}");
        showAlert('Payroll period created successfully');
    }
    
    header('Location: payroll_periods.php');
    exit;
}

// Get all payroll periods
$periods = fetchAll("SELECT * FROM payroll_periods ORDER BY start_date DESC");
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-0">Payroll Periods</h2>
                <p class="text-muted mb-0">Manage payroll periods</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPeriodModal">
                <i class="fas fa-plus me-2"></i>New Period
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr>
                            <th>Period Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Pay Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($periods as $p): ?>
                        <tr>
                            <td><strong><?php echo $p['period_name']; ?></strong></td>
                            <td><?php echo formatDate($p['start_date']); ?></td>
                            <td><?php echo formatDate($p['end_date']); ?></td>
                            <td><?php echo formatDate($p['pay_date']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $p['status'] === 'open' ? 'success' : 
                                        ($p['status'] === 'processing' ? 'warning' : 'secondary'); 
                                ?>">
                                    <?php echo ucfirst($p['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="process.php?period_id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-calculator"></i> Process
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Period Modal -->
<div class="modal fade" id="addPeriodModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Payroll Period</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Period Name *</label>
                        <input type="text" name="period_name" class="form-control" placeholder="e.g., January 2024" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Start Date *</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">End Date *</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Pay Date *</label>
                            <input type="date" name="pay_date" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="open">Open</option>
                            <option value="processing">Processing</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Period</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
