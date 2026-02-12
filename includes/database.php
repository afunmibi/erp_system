<?php
// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../auth/config.php';

// Database configuration
$host = 'localhost';
$db   = 'erp_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE $db");
    
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Create all tables
function createTables($pdo) {
    // Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'manager', 'staff') DEFAULT 'staff',
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // HMO Patients
    $pdo->exec("CREATE TABLE IF NOT EXISTS hmo_patients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_code VARCHAR(20) UNIQUE NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        date_of_birth DATE NOT NULL,
        gender ENUM('male', 'female', 'other') NOT NULL,
        phone VARCHAR(20),
        email VARCHAR(100),
        address TEXT,
        hmo_provider_id INT,
        policy_number VARCHAR(50),
        enrollment_date DATE,
        status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // HMO Providers
    $pdo->exec("CREATE TABLE IF NOT EXISTS hmo_providers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        provider_code VARCHAR(20) UNIQUE NOT NULL,
        provider_name VARCHAR(100) NOT NULL,
        provider_type ENUM('hospital', 'clinic', 'pharmacy', 'laboratory', 'other') NOT NULL,
        contact_person VARCHAR(100),
        phone VARCHAR(20),
        email VARCHAR(100),
        address TEXT,
        contract_start_date DATE,
        contract_end_date DATE,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // HMO Claims
    $pdo->exec("CREATE TABLE IF NOT EXISTS hmo_claims (
        id INT AUTO_INCREMENT PRIMARY KEY,
        claim_number VARCHAR(30) UNIQUE NOT NULL,
        patient_id INT NOT NULL,
        provider_id INT NOT NULL,
        service_date DATE NOT NULL,
        claim_type ENUM('consultation', 'procedure', 'medication', 'laboratory', 'hospitalization', 'other') NOT NULL,
        description TEXT,
        amount DECIMAL(10,2) NOT NULL,
        approved_amount DECIMAL(10,2) DEFAULT 0,
        status ENUM('pending', 'approved', 'rejected', 'paid') DEFAULT 'pending',
        submitted_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_date TIMESTAMP NULL,
        processed_by INT,
        notes TEXT,
        FOREIGN KEY (patient_id) REFERENCES hmo_patients(id),
        FOREIGN KEY (provider_id) REFERENCES hmo_providers(id)
    )");

    // Inventory Categories
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_name VARCHAR(50) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Inventory Suppliers
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory_suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_code VARCHAR(20) UNIQUE NOT NULL,
        supplier_name VARCHAR(100) NOT NULL,
        contact_person VARCHAR(100),
        phone VARCHAR(20),
        email VARCHAR(100),
        address TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Inventory Products
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sku VARCHAR(30) UNIQUE NOT NULL,
        product_name VARCHAR(100) NOT NULL,
        category_id INT,
        supplier_id INT,
        description TEXT,
        unit_price DECIMAL(10,2) NOT NULL,
        cost_price DECIMAL(10,2) NOT NULL,
        quantity_in_stock INT DEFAULT 0,
        reorder_level INT DEFAULT 10,
        reorder_quantity INT DEFAULT 50,
        unit_of_measure VARCHAR(20) DEFAULT 'pieces',
        location VARCHAR(50),
        status ENUM('active', 'inactive', 'discontinued') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES inventory_categories(id),
        FOREIGN KEY (supplier_id) REFERENCES inventory_suppliers(id)
    )");

    // Inventory Transactions
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        transaction_type ENUM('purchase', 'sale', 'adjustment', 'return', 'transfer') NOT NULL,
        quantity INT NOT NULL,
        unit_cost DECIMAL(10,2),
        total_cost DECIMAL(10,2),
        reference_number VARCHAR(50),
        notes TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES inventory_products(id)
    )");

    // Billing Clients
    $pdo->exec("CREATE TABLE IF NOT EXISTS billing_clients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_code VARCHAR(20) UNIQUE NOT NULL,
        client_name VARCHAR(100) NOT NULL,
        client_type ENUM('individual', 'company', 'government') NOT NULL,
        contact_person VARCHAR(100),
        phone VARCHAR(20),
        email VARCHAR(100),
        address TEXT,
        credit_limit DECIMAL(12,2) DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Billing Invoices
    $pdo->exec("CREATE TABLE IF NOT EXISTS billing_invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_number VARCHAR(30) UNIQUE NOT NULL,
        client_id INT NOT NULL,
        invoice_date DATE NOT NULL,
        due_date DATE NOT NULL,
        subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
        tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
        balance_due DECIMAL(12,2) NOT NULL DEFAULT 0,
        status ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
        notes TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES billing_clients(id)
    )");

    // Billing Invoice Items
    $pdo->exec("CREATE TABLE IF NOT EXISTS billing_invoice_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        product_id INT,
        description TEXT NOT NULL,
        quantity DECIMAL(10,2) NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (invoice_id) REFERENCES billing_invoices(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES inventory_products(id)
    )");

    // Billing Payments
    $pdo->exec("CREATE TABLE IF NOT EXISTS billing_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_number VARCHAR(30) UNIQUE NOT NULL,
        invoice_id INT NOT NULL,
        payment_date DATE NOT NULL,
        payment_method ENUM('cash', 'check', 'bank_transfer', 'credit_card', 'online') NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        reference_number VARCHAR(50),
        notes TEXT,
        received_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (invoice_id) REFERENCES billing_invoices(id)
    )");

    // Payroll Departments
    $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_name VARCHAR(50) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Payroll Positions
    $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_positions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        position_title VARCHAR(50) NOT NULL,
        department_id INT,
        base_salary DECIMAL(12,2) DEFAULT 0,
        description TEXT,
        FOREIGN KEY (department_id) REFERENCES payroll_departments(id)
    )");

    // Payroll Employees
    $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_code VARCHAR(20) UNIQUE NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        email VARCHAR(100) UNIQUE,
        phone VARCHAR(20),
        date_of_birth DATE,
        hire_date DATE NOT NULL,
        department_id INT,
        position_id INT,
        basic_salary DECIMAL(12,2) NOT NULL,
        employment_type ENUM('full_time', 'part_time', 'contract') DEFAULT 'full_time',
        status ENUM('active', 'on_leave', 'terminated', 'resigned') DEFAULT 'active',
        bank_account VARCHAR(50),
        bank_name VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (department_id) REFERENCES payroll_departments(id),
        FOREIGN KEY (position_id) REFERENCES payroll_positions(id)
    )");

    // Payroll Periods
    $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_periods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        period_name VARCHAR(50) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        pay_date DATE NOT NULL,
        status ENUM('open', 'processing', 'closed') DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Payroll Records
    $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        payroll_period_id INT NOT NULL,
        basic_salary DECIMAL(12,2) NOT NULL,
        allowances DECIMAL(12,2) DEFAULT 0,
        overtime DECIMAL(12,2) DEFAULT 0,
        bonuses DECIMAL(12,2) DEFAULT 0,
        gross_salary DECIMAL(12,2) NOT NULL,
        tax_deduction DECIMAL(12,2) DEFAULT 0,
        sss_contribution DECIMAL(12,2) DEFAULT 0,
        philhealth_contribution DECIMAL(12,2) DEFAULT 0,
        pagibig_contribution DECIMAL(12,2) DEFAULT 0,
        other_deductions DECIMAL(12,2) DEFAULT 0,
        total_deductions DECIMAL(12,2) DEFAULT 0,
        net_salary DECIMAL(12,2) NOT NULL,
        status ENUM('draft', 'approved', 'paid') DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES payroll_employees(id),
        FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id)
    )");

    // System Settings
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Activity Logs
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        module VARCHAR(50) NOT NULL,
        description TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
}

// Create tables
createTables($pdo);

// Create default admin user
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
$stmt->execute();
if (!$stmt->fetch()) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@erp.com', $hash, 'admin']);
}

// Database helper functions
function query($sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function fetchAll($sql, $params = []) {
    return query($sql, $params)->fetchAll();
}

function fetchOne($sql, $params = []) {
    return query($sql, $params)->fetch();
}

function insert($table, $data) {
    global $pdo;
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    return $pdo->lastInsertId();
}

function update($table, $data, $where, $whereParams) {
    global $pdo;
    $set = [];
    foreach ($data as $key => $value) {
        $set[] = "$key = :$key";
    }
    $set = implode(', ', $set);
    $sql = "UPDATE $table SET $set WHERE $where";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($data, $whereParams));
    return $stmt->rowCount();
}

function delete($table, $where, $whereParams) {
    global $pdo;
    $sql = "DELETE FROM $table WHERE $where";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($whereParams);
    return $stmt->rowCount();
}

// Logging function - use PDO version
function logActivity($action, $module, $description = '') {
    global $pdo;
    $userId = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, module, description, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $module, $description, $ip]);
    } catch (Exception $e) {
        // Log silently if table doesn't exist yet
    }
}

// Utility functions
function generateCode($prefix, $id, $length = 6) {
    return $prefix . str_pad($id, $length, '0', STR_PAD_LEFT);
}

function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function showAlert($message, $type = 'success') {
    $_SESSION['alert'] = ['message' => $message, 'type' => $type];
}

function getAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}
?>
