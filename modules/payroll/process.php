<?php
// Process form submissions BEFORE including header
require_once '../../includes/database.php';
require_once '../../auth/config.php';
requireAuth();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'process') {
        $period_id = $_POST['payroll_period_id'];
        $employees = fetchAll("SELECT * FROM payroll_employees WHERE status = 'active'");
        
        foreach ($employees as $emp) {
            $gross_salary = $emp['basic_salary'];
            $deductions = $_POST["deductions_{$emp['id']}"] ?? 0;
            $bonuses = $_POST["bonuses_{$emp['id']}"] ?? 0;
            $net_salary = $gross_salary + $bonuses - $deductions;
            
            $data = [
                'payroll_period_id' => $period_id,
                'employee_id' => $emp['id'],
                'gross_salary' => $gross_salary,
                'bonuses' => $bonuses,
                'deductions' => $deductions,
                'net_salary' => $net_salary,
                'status' => 'processed'
            ];
            insert('payroll_payments', $data);
        }
        
        update('payroll_periods', ['status' => 'closed'], 'id = :id', ['id' => $period_id]);
        logActivity('Process Payroll', 'payroll', "Processed payroll for period ID: $period_id");
        $_SESSION['alert'] = ['message' => 'Payroll processed successfully', 'type' => 'success'];
        header('Location: process.php');
        exit;
    }
}

if ($action === 'delete_period' && $id) {
    $period = fetchOne("SELECT period_name FROM payroll_periods WHERE id = ?", [$id]);
    delete('payroll_periods', 'id = :id', ['id' => $id]);
    logActivity('Delete Payroll Period', 'payroll', "Deleted period: {$period['period_name']}");
    $_SESSION['alert'] = ['message' => 'Payroll period deleted', 'type' => 'success'];
    header('Location: process.php');
    exit;
}

// Handle period creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_period') {
    $data = [
        'period_name' => $_POST['period_name'],
        'start_date' => $_POST['start_date'],
        'end_date' => $_POST['end_date'],
        'status' => 'open'
    ];
    $newId = insert('payroll_periods', $data);
    logActivity('Create Payroll Period', 'payroll', "Created period ID: $newId");
    $_SESSION['alert'] = ['message' => 'Payroll period created', 'type' => 'success'];
    header('Location: process.php');
    exit;
}

$periods = fetchAll("SELECT * FROM payroll_periods ORDER BY start_date DESC");
$activePeriod = fetchOne("SELECT * FROM payroll_periods WHERE status = 'open' ORDER BY created_at DESC LIMIT 1");
$employees = fetchAll("SELECT * FROM payroll_employees WHERE status = 'active' ORDER BY last_name, first_name");

$payments = [];
if ($activePeriod) {
    $payments = fetchAll("SELECT pp.*, e.first_name, e.last_name, e.employee_code 
        FROM payroll_payments pp 
        JOIN payroll_employees e ON pp.employee_id = e.id 
        WHERE pp.payroll_period_id = ?
        ORDER BY e.last_name", [$activePeriod['id']]);
}

$stats = fetchOne("SELECT 
    COUNT(*) as total_periods,
    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_periods
    FROM payroll_periods");

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-money-bill-wave me-2"></i>Payroll Processing</h2>
    </div>
    
    <?php if ($action === 'list'): ?>
    <?php if ($activePeriod): ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="alert alert-info">
                <strong>Active Payroll Period:</strong> 
                <?php echo $activePeriod['period_name']; ?> 
                (<?php echo formatDate($activePeriod['start_date']); ?> - <?php echo formatDate($activePeriod['end_date']); ?>)
                <a href="?action=process&id=<?php echo $activePeriod['id']; ?>" class="btn btn-sm btn-primary ms-3">
                    <i class="fas fa-cog me-1"></i>Process Payroll
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-calendar"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Total Periods</h6>
                    <h4 class="mb-0"><?php echo count($periods); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Active Employees</h6>
                    <h4 class="mb-0"><?php echo count($employees); ?></h4>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create New Period Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Create New Payroll Period</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="process.php">
                <input type="hidden" name="action" value="create_period">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Period Name *</label>
                        <input type="text" name="period_name" class="form-control" placeholder="e.g., January 2025" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Start Date *</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">End Date *</label>
                        <input type="date" name="end_date" class="form-control" required>
                    </div>
                    <div class="col-md-2 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-2"></i>Create
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Payroll Periods List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Payroll Periods</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr>
                            <th>Period Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($periods as $period): ?>
                        <tr>
                            <td><strong><?php echo $period['period_name']; ?></strong></td>
                            <td><?php echo formatDate($period['start_date']); ?></td>
                            <td><?php echo formatDate($period['end_date']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $period['status'] === 'open' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($period['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($period['status'] === 'open'): ?>
                                <a href="?action=process&id=<?php echo $period['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-cog me-1"></i>Process
                                </a>
                                <?php endif; ?>
                                <a href="?action=view&id=<?php echo $period['id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?action=delete_period&id=<?php echo $period['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this period?')">
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
    
    <?php elseif ($action === 'process' && $activePeriod): ?>
    <?php 
    $periodToProcess = fetchOne("SELECT * FROM payroll_periods WHERE id = ?", [$id]);
    $periodEmployees = fetchAll("SELECT * FROM payroll_employees WHERE status = 'active' ORDER BY last_name, first_name");
    ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Process Payroll: <?php echo $periodToProcess['period_name']; ?></h5>
        </div>
        <div class="card-body">
            <form method="POST" action="?action=process">
                <input type="hidden" name="payroll_period_id" value="<?php echo $periodToProcess['id']; ?>">
                
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Code</th>
                                <th>Gross Salary</th>
                                <th>Bonus</th>
                                <th>Deductions</th>
                                <th>Net Salary</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($periodEmployees as $emp): ?>
                            <tr>
                                <td><?php echo $emp['last_name'] . ', ' . $emp['first_name']; ?></td>
                                <td><?php echo $emp['employee_code']; ?></td>
                                <td>
                                    <input type="hidden" name="employee_ids[]" value="<?php echo $emp['id']; ?>">
                                    <?php echo formatCurrency($emp['basic_salary']); ?>
                                    <input type="hidden" name="gross_<?php echo $emp['id']; ?>" value="<?php echo $emp['basic_salary']; ?>">
                                </td>
                                <td>
                                    <input type="number" name="bonuses_<?php echo $emp['id']; ?>" class="form-control" step="0.01" min="0" value="0">
                                </td>
                                <td>
                                    <input type="number" name="deductions_<?php echo $emp['id']; ?>" class="form-control" step="0.01" min="0" value="0">
                                </td>
                                <td>
                                    <strong><?php echo formatCurrency($emp['basic_salary']); ?></strong>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Process Payroll
                    </button>
                    <a href="process.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
