<?php
require_once __DIR__ . '/database.php';
requireAuth();

$currentModule = basename(dirname($_SERVER['PHP_SELF']));
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Detect base path from request URI
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];
$basePath = dirname($scriptName);

// Remove query string from request URI
$requestUri = strtok($requestUri, '?');

// Calculate the project root path correctly
$scriptPath = $_SERVER['SCRIPT_NAME'];
$scriptDir = dirname($scriptPath);

// If we're in a module (modules/ or deeper), go up 2 levels to get project root
// Otherwise use the current directory
if (strpos($scriptDir, '/modules/') !== false) {
    $projectRootPath = dirname(dirname($scriptDir));
} else {
    $projectRootPath = $scriptDir;
}

// Normalize path
$projectRootPath = rtrim($projectRootPath, '/');
if (empty($projectRootPath) || $projectRootPath === '\\') {
    $projectRootPath = '';
}

// Get relative prefix for resources (footer, etc.) - based on current depth
$currentDir = dirname($_SERVER['SCRIPT_NAME']);
$scriptDepth = substr_count($currentDir, '/');
$relativePrefix = $scriptDepth > 0 ? str_repeat('../', $scriptDepth) : '';

// For module links, always use project root-relative paths
// This ensures links work from any location

$user = fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP System - <?php echo ucfirst($currentModule); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <style>
        :root {
            --sidebar-width: 260px;
            --primary-color: #3b82f6;
            --secondary-color: #64748b;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f1f5f9;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            color: white;
            overflow-y: auto;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sidebar-menu {
            padding: 15px 0;
        }
        
        .sidebar-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .sidebar-item:hover,
        .sidebar-item.active {
            background: rgba(59, 130, 246, 0.2);
            color: white;
            border-right: 3px solid var(--primary-color);
        }
        
        .sidebar-item i {
            width: 24px;
            text-align: center;
            font-size: 1.1rem;
        }
        
        .sidebar-submenu {
            background: rgba(0,0,0,0.2);
            display: none;
        }
        
        .sidebar-submenu.show {
            display: block;
        }
        
        .sidebar-submenu .sidebar-item {
            padding-left: 56px;
            font-size: 0.9rem;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        .topbar {
            background: white;
            padding: 15px 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .content-wrapper {
            padding: 25px;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-icon.blue { background: #dbeafe; color: #2563eb; }
        .stat-icon.green { background: #dcfce7; color: #16a34a; }
        .stat-icon.orange { background: #ffedd5; color: #ea580c; }
        .stat-icon.red { background: #fee2e2; color: #dc2626; }
        .stat-icon.purple { background: #f3e8ff; color: #9333ea; }
        .stat-icon.teal { background: #ccfbf1; color: #0d9488; }
        
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background: #2563eb;
            border-color: #2563eb;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="<?php echo $projectRootPath; ?>/index.php" class="sidebar-brand">
                <i class="fas fa-cubes"></i>
                <span>ERP System</span>
            </a>
        </div>
        
        <nav class="sidebar-menu">
            <a href="<?php echo $projectRootPath; ?>/index.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="sidebar-item has-submenu <?php echo $currentModule == 'hmo' ? 'active' : ''; ?>" onclick="toggleSubmenu('hmo-submenu')">
                <i class="fas fa-heartbeat"></i>
                <span>HMO Management</span>
                <i class="fas fa-chevron-down ms-auto"></i>
            </div>
            <div class="sidebar-submenu <?php echo $currentModule == 'hmo' ? 'show' : ''; ?>" id="hmo-submenu">
                <a href="<?php echo $projectRootPath; ?>/modules/hmo/patients.php" class="sidebar-item <?php echo $currentPage == 'patients' ? 'active' : ''; ?>">Patients</a>
                <a href="<?php echo $projectRootPath; ?>/modules/hmo/providers.php" class="sidebar-item <?php echo $currentPage == 'providers' ? 'active' : ''; ?>">Providers</a>
                <a href="<?php echo $projectRootPath; ?>/modules/hmo/claims.php" class="sidebar-item <?php echo $currentPage == 'claims' ? 'active' : ''; ?>">Claims</a>
            </div>
            
            <div class="sidebar-item has-submenu <?php echo $currentModule == 'inventory' ? 'active' : ''; ?>" onclick="toggleSubmenu('inventory-submenu')">
                <i class="fas fa-boxes"></i>
                <span>Inventory</span>
                <i class="fas fa-chevron-down ms-auto"></i>
            </div>
            <div class="sidebar-submenu <?php echo $currentModule == 'inventory' ? 'show' : ''; ?>" id="inventory-submenu">
                <a href="<?php echo $projectRootPath; ?>/modules/inventory/products.php" class="sidebar-item <?php echo $currentPage == 'products' ? 'active' : ''; ?>">Products</a>
                <a href="<?php echo $projectRootPath; ?>/modules/inventory/categories.php" class="sidebar-item <?php echo $currentPage == 'categories' ? 'active' : ''; ?>">Categories</a>
                <a href="<?php echo $projectRootPath; ?>/modules/inventory/suppliers.php" class="sidebar-item <?php echo $currentPage == 'suppliers' ? 'active' : ''; ?>">Suppliers</a>
                <a href="<?php echo $projectRootPath; ?>/modules/inventory/transactions.php" class="sidebar-item <?php echo $currentPage == 'transactions' ? 'active' : ''; ?>">Transactions</a>
            </div>
            
            <div class="sidebar-item has-submenu <?php echo $currentModule == 'billing' ? 'active' : ''; ?>" onclick="toggleSubmenu('billing-submenu')">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Billing</span>
                <i class="fas fa-chevron-down ms-auto"></i>
            </div>
            <div class="sidebar-submenu <?php echo $currentModule == 'billing' ? 'show' : ''; ?>" id="billing-submenu">
                <a href="<?php echo $projectRootPath; ?>/modules/billing/clients.php" class="sidebar-item <?php echo $currentPage == 'clients' ? 'active' : ''; ?>">Clients</a>
                <a href="<?php echo $projectRootPath; ?>/modules/billing/invoices.php" class="sidebar-item <?php echo $currentPage == 'invoices' ? 'active' : ''; ?>">Invoices</a>
                <a href="<?php echo $projectRootPath; ?>/modules/billing/payments.php" class="sidebar-item <?php echo $currentPage == 'payments' ? 'active' : ''; ?>">Payments</a>
            </div>
            
            <div class="sidebar-item has-submenu <?php echo $currentModule == 'payroll' ? 'active' : ''; ?>" onclick="toggleSubmenu('payroll-submenu')">
                <i class="fas fa-money-check-alt"></i>
                <span>Payroll</span>
                <i class="fas fa-chevron-down ms-auto"></i>
            </div>
            <div class="sidebar-submenu <?php echo $currentModule == 'payroll' ? 'show' : ''; ?>" id="payroll-submenu">
                <a href="<?php echo $projectRootPath; ?>/modules/payroll/employees.php" class="sidebar-item <?php echo $currentPage == 'employees' ? 'active' : ''; ?>">Employees</a>
                <a href="<?php echo $projectRootPath; ?>/modules/payroll/departments.php" class="sidebar-item <?php echo $currentPage == 'departments' ? 'active' : ''; ?>">Departments</a>
                <a href="<?php echo $projectRootPath; ?>/modules/payroll/payroll_periods.php" class="sidebar-item <?php echo $currentPage == 'payroll_periods' ? 'active' : ''; ?>">Payroll Periods</a>
                <a href="<?php echo $projectRootPath; ?>/modules/payroll/process.php" class="sidebar-item <?php echo $currentPage == 'process' ? 'active' : ''; ?>">Process Payroll</a>
            </div>
            
            <div class="sidebar-item has-submenu <?php echo $currentModule == 'reports' ? 'active' : ''; ?>" onclick="toggleSubmenu('reports-submenu')">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
                <i class="fas fa-chevron-down ms-auto"></i>
            </div>
            <div class="sidebar-submenu <?php echo $currentModule == 'reports' ? 'show' : ''; ?>" id="reports-submenu">
                <a href="<?php echo $projectRootPath; ?>/modules/reports/hmo.php" class="sidebar-item <?php echo $currentPage == 'hmo' ? 'active' : ''; ?>">HMO Reports</a>
                <a href="<?php echo $projectRootPath; ?>/modules/reports/inventory.php" class="sidebar-item <?php echo $currentPage == 'inventory' ? 'active' : ''; ?>">Inventory Reports</a>
                <a href="<?php echo $projectRootPath; ?>/modules/reports/billing.php" class="sidebar-item <?php echo $currentPage == 'billing' ? 'active' : ''; ?>">Billing Reports</a>
                <a href="<?php echo $projectRootPath; ?>/modules/reports/payroll.php" class="sidebar-item <?php echo $currentPage == 'payroll' ? 'active' : ''; ?>">Payroll Reports</a>
            </div>
            
            <?php if (hasRole('admin')): ?>
            <a href="<?php echo $projectRootPath; ?>/modules/admin/users.php" class="sidebar-item <?php echo $currentModule == 'admin' ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i>
                <span>User Management</span>
            </a>
            <?php endif; ?>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Topbar -->
        <header class="topbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-sm btn-light me-3 d-md-none" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h4 class="mb-0 text-secondary"><?php echo ucfirst($currentModule); ?></h4>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-bell me-1"></i>
                        <span class="badge bg-danger">3</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-exclamation-circle text-warning me-2"></i>Low stock alert: 5 items</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-file-invoice text-info me-2"></i>3 overdue invoices</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-check-circle text-success me-2"></i>Payroll processed</a></li>
                    </ul>
                </div>
                
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                            <i class="fas fa-user"></i>
                        </div>
                        <span class="d-none d-md-inline"><?php echo $user['username']; ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?php echo $projectRootPath; ?>/profile.php"><i class="fas fa-user-circle me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="<?php echo $projectRootPath; ?>/settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?php echo $projectRootPath; ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Content Wrapper -->
        <div class="content-wrapper">
