<?php
require_once '../../includes/header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $invoice_id = $_POST['invoice_id'];
        $payment_date = $_POST['payment_date'];
        $payment_method = $_POST['payment_method'];
        $amount = $_POST['amount'];
        $reference_number = $_POST['reference_number'];
        $notes = $_POST['notes'];
        
        $payment_id = insert('billing_payments', [
            'invoice_id' => $invoice_id,
            'payment_date' => $payment_date,
            'payment_method' => $payment_method,
            'amount' => $amount,
            'reference_number' => $reference_number,
            'notes' => $notes,
            'received_by' => $_SESSION['user_id']
        ]);
        
        // Generate payment number
        $payment_number = 'PAY' . str_pad($payment_id, 6, '0', STR_PAD_LEFT);
        update('billing_payments', ['payment_number' => $payment_number], 'id = ?', [$payment_id]);
        
        // Update invoice balance
        $invoice = fetchOne("SELECT * FROM billing_invoices WHERE id = ?", [$invoice_id]);
        $new_balance = max(0, $invoice['balance_due'] - $amount);
        $new_paid = $invoice['amount_paid'] + $amount;
        
        $status = ($new_balance <= 0) ? 'paid' : $invoice['status'];
        update('billing_invoices', [
            'balance_due' => $new_balance,
            'amount_paid' => $new_paid,
            'status' => $status
        ], 'id = ?', [$invoice_id]);
        
        logActivity('Add Payment', 'billing', "Recorded payment of " . formatCurrency($amount));
        showAlert('Payment recorded successfully');
    }
    
    header('Location: payments.php');
    exit;
}

// Get all payments
$payments = fetchAll("SELECT p.*, i.invoice_number, c.client_name 
    FROM billing_payments p 
    LEFT JOIN billing_invoices i ON p.invoice_id = i.id 
    LEFT JOIN billing_clients c ON i.client_id = c.id 
    ORDER BY p.payment_date DESC");

// Get unpaid or partially paid invoices
$invoices = fetchAll("SELECT * FROM billing_invoices WHERE balance_due > 0 ORDER BY due_date");
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-0">Payments</h2>
                <p class="text-muted mb-0">Record and track payments</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                <i class="fas fa-plus me-2"></i>New Payment
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr>
                            <th>Payment #</th>
                            <th>Date</th>
                            <th>Invoice</th>
                            <th>Client</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><strong><?php echo $p['payment_number']; ?></strong></td>
                            <td><?php echo formatDate($p['payment_date']); ?></td>
                            <td><?php echo $p['invoice_number']; ?></td>
                            <td><?php echo $p['client_name']; ?></td>
                            <td class="text-success fw-bold"><?php echo formatCurrency($p['amount']); ?></td>
                            <td><?php echo ucfirst($p['payment_method']); ?></td>
                            <td><?php echo $p['reference_number'] ?: '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Invoice *</label>
                        <select name="invoice_id" class="form-select" required>
                            <option value="">Select Invoice</option>
                            <?php foreach ($invoices as $inv): ?>
                            <option value="<?php echo $inv['id']; ?>">
                                <?php echo $inv['invoice_number']; ?> - <?php echo $inv['client_name'] ?? ''; ?> (Balance: <?php echo formatCurrency($inv['balance_due']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Date *</label>
                            <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount *</label>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Method *</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="cash">Cash</option>
                            <option value="check">Check</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="online">Online Payment</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reference Number</label>
                        <input type="text" name="reference_number" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
