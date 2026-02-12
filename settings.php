<?php
require_once 'includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_settings') {
        foreach ($_POST['settings'] as $key => $value) {
            $existing = fetchOne("SELECT id FROM system_settings WHERE setting_key = ?", [$key]);
            if ($existing) {
                update('system_settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
            } else {
                insert('system_settings', ['setting_key' => $key, 'setting_value' => $value]);
            }
        }
        logActivity('Update Settings', 'settings', 'System settings updated');
        showAlert('Settings saved successfully');
    }
    
    header('Location: settings.php');
    exit;
}

// Get current settings
$settings = [];
$db_settings = fetchAll("SELECT setting_key, setting_value FROM system_settings");
foreach ($db_settings as $s) {
    $settings[$s['setting_key']] = $s['setting_value'];
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">System Settings</h2>
            <p class="text-muted mb-0">Configure your ERP system</p>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="update_settings">
        
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-building me-2"></i>Company Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="settings[company_name]" class="form-control" 
                                value="<?php echo sanitize($settings['company_name'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="settings[company_address]" class="form-control" rows="2"><?php echo sanitize($settings['company_address'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="settings[company_phone]" class="form-control" 
                                value="<?php echo sanitize($settings['company_phone'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="settings[company_email]" class="form-control" 
                                value="<?php echo sanitize($settings['company_email'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-money-bill me-2"></i>Financial Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Currency Symbol</label>
                            <input type="text" name="settings[currency_symbol]" class="form-control" 
                                value="<?php echo sanitize($settings['currency_symbol'] ?? '₱'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tax Rate (%)</label>
                            <input type="number" name="settings[tax_rate]" class="form-control" step="0.01" min="0" max="100"
                                value="<?php echo sanitize($settings['tax_rate'] ?? '0'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Invoice Due Days</label>
                            <input type="number" name="settings[invoice_due_days]" class="form-control" min="1"
                                value="<?php echo sanitize($settings['invoice_due_days'] ?? '30'); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-file-invoice me-2"></i>Invoice Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Invoice Prefix</label>
                            <input type="text" name="settings[invoice_prefix]" class="form-control" 
                                value="<?php echo sanitize($settings['invoice_prefix'] ?? 'INV-'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Invoice Footer Text</label>
                            <textarea name="settings[invoice_footer]" class="form-control" rows="2"><?php echo sanitize($settings['invoice_footer'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notification Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Low Stock Threshold</label>
                            <input type="number" name="settings[low_stock_threshold]" class="form-control" min="1"
                                value="<?php echo sanitize($settings['low_stock_threshold'] ?? '10'); ?>">
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="settings[email_notifications]" value="1" class="form-check-input" 
                                    id="emailNotif" <?php echo ($settings['email_notifications'] ?? '') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="emailNotif">Enable Email Notifications</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Settings
                </button>
            </div>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
