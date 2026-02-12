<?php
/**
 * ERP System Setup Script
 * Run this once to initialize the database and tables
 */

require_once 'includes/database.php';

echo "<h1>ERP System Setup</h1>";

try {
    // Verify connection
    echo "<p>Database connection: OK</p>";
    echo "<p>Database: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "</p>";
    
    // Check existing tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Existing tables: " . count($tables) . "</p>";
    
    if (count($tables) === 0) {
        echo "<p><strong>No tables found. Creating tables...</strong></p>";
        
        // Create tables
        $pdo->exec("
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role ENUM('admin', 'manager', 'staff') DEFAULT 'staff',
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        echo "<p>✓ users table created</p>";
        
        // Create HMO tables
        $pdo->exec("
            CREATE TABLE hmo_patients (
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
            )
        ");
        echo "<p>✓ hmo_patients table created</p>";
        
        $pdo->exec("
            CREATE TABLE hmo_providers (
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
            )
        ");
        echo "<p>✓ hmo_providers table created</p>";
        
        $pdo->exec("
            CREATE TABLE hmo_claims (
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
            )
        ");
        echo "<p>✓ hmo_claims table created</p>";
        
        // Create inventory tables
        $pdo->exec("
            CREATE TABLE inventory_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                category_name VARCHAR(50) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "<p>✓ inventory_categories table created</p>";
        
        $pdo->exec("
            CREATE TABLE inventory_suppliers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                supplier_code VARCHAR(20) UNIQUE NOT NULL,
                supplier_name VARCHAR(100) NOT NULL,
                contact_person VARCHAR(100),
                phone VARCHAR(20),
                email VARCHAR(100),
                address TEXT,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "<p>✓ inventory_suppliers table created</p>";
        
        $pdo->exec("
            CREATE TABLE inventory_products (
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
            )
        ");
        echo "<p>✓ inventory_products table created</p>";
        
        $pdo->exec("
            CREATE TABLE inventory_transactions (
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
            )
        ");
        echo "<p>✓ inventory_transactions table created</p>";
        
        // Create billing tables
        $pdo->exec("
            CREATE TABLE billing_clients (
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
            )
        ");
        echo "<p>✓ billing_clients table created</p>";
        
        $pdo->exec("
            CREATE TABLE billing_invoices (
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
            )
        ");
        echo "<p>✓ billing_invoices table created</p>";
        
        $pdo->exec("
            CREATE TABLE billing_invoice_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                invoice_id INT NOT NULL,
                product_id INT,
                description TEXT NOT NULL,
                quantity DECIMAL(10,2) NOT NULL,
                unit_price DECIMAL(10,2) NOT NULL,
                total_price DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (invoice_id) REFERENCES billing_invoices(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES inventory_products(id)
            )
        ");
        echo "<p>✓ billing_invoice_items table created</p>";
        
        $pdo->exec("
            CREATE TABLE billing_payments (
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
            )
        ");
        echo "<p>✓ billing_payments table created</p>";
        
        // Create payroll tables
        $pdo->exec("
            CREATE TABLE payroll_departments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                department_name VARCHAR(50) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "<p>✓ payroll_departments table created</p>";
        
        $pdo->exec("
            CREATE TABLE payroll_positions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                position_title VARCHAR(50) NOT NULL,
                department_id INT,
                base_salary DECIMAL(12,2) DEFAULT 0,
                description TEXT,
                FOREIGN KEY (department_id) REFERENCES payroll_departments(id)
            )
        ");
        echo "<p>✓ payroll_positions table created</p>";
        
        $pdo->exec("
            CREATE TABLE payroll_employees (
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
            )
        ");
        echo "<p>✓ payroll_employees table created</p>";
        
        $pdo->exec("
            CREATE TABLE payroll_periods (
                id INT AUTO_INCREMENT PRIMARY KEY,
                period_name VARCHAR(50) NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                pay_date DATE NOT NULL,
                status ENUM('open', 'processing', 'closed') DEFAULT 'open',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "<p>✓ payroll_periods table created</p>";
        
        $pdo->exec("
            CREATE TABLE payroll_records (
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
            )
        ");
        echo "<p>✓ payroll_records table created</p>";
        
        // Create activity logs
        $pdo->exec("
            CREATE TABLE activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                action VARCHAR(100) NOT NULL,
                module VARCHAR(50) NOT NULL,
                description TEXT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
        echo "<p>✓ activity_logs table created</p>";
        
        // Create default admin user
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)")
            ->execute(['admin', 'admin@erp.com', $hash, 'admin']);
        echo "<p>✓ Default admin user created (admin / admin123)</p>";
        
        echo "<h2 style='color: green;'>Setup completed successfully!</h2>";
        echo "<p><a href='index.php'>Go to Dashboard</a></p>";
        
    } else {
        echo "<p style='color: green;'><strong>Database is already set up!</strong></p>";
        echo "<p>Tables: " . implode(', ', $tables) . "</p>";
        echo "<p><a href='index.php'>Go to Dashboard</a></p>";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
