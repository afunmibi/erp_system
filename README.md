# ERP System - Healthcare & Business Management

A comprehensive Enterprise Resource Planning (ERP) system designed for healthcare organizations and businesses, combining HMO management, inventory control, billing, payroll, and advanced reporting capabilities.

## 🏥 Core Modules

### HMO Management
- **Patients**: Complete patient records with HMO policy integration
- **Providers**: Network provider management (hospitals, clinics, pharmacies)
- **Claims**: Insurance claims processing with approval workflow

### Inventory Management
- **Products**: Stock management with reorder levels
- **Categories**: Product organization and classification
- **Suppliers**: Vendor management and purchase history
- **Transactions**: Real-time stock tracking and adjustments

### Billing & Invoicing
- **Clients**: Customer management with credit limits
- **Invoices**: Professional invoice generation with line items
- **Payments**: Multi-method payment processing and reconciliation

### Payroll System
- **Employees**: Complete employee records with bank details
- **Departments**: Organizational structure management
- **Positions**: Job roles and salary grades
- **Payroll Processing**: Automated salary calculations with statutory deductions

### Reports & Analytics
- **Dashboard**: Executive KPIs and real-time metrics
- **Financial Reports**: Revenue, expenses, and profitability analysis
- **HMO Analytics**: Claims trends and provider performance
- **Inventory Reports**: Stock valuation and movement tracking

## 🚀 Technology Stack

- **Backend**: PHP 8.x with MySQLi
- **Frontend**: Bootstrap 5 + Tailwind CSS
- **Database**: MySQL with comprehensive relational schema
- **Charts**: Chart.js for data visualization
- **Tables**: DataTables for advanced data management

## 📊 Key Features

### Security & Access Control
- Role-based authentication (Admin, Manager, Staff)
- Activity logging and audit trails
- Password hashing and secure sessions

### Data Management
- CRUD operations for all modules
- Data validation and sanitization
- Foreign key relationships for data integrity
- Auto-generated unique codes and references

### User Experience
- Responsive design for all devices
- Interactive dashboards with real-time updates
- Professional invoice and report layouts
- Quick search and filtering capabilities

## 🔧 Installation

1. **Setup Database**
   ```sql
   -- Import the database schema from includes/database.php
   -- Tables are created automatically on first load
   ```

2. **Configure**
   - Update database credentials in `includes/database.php`
   - Set correct file permissions for uploads directory

3. **Access**
   - Default login: admin / admin123
   - Navigate to `/login.php` to begin

## 🏗️ File Structure

```
erp_system/
├── includes/
│   ├── database.php          # Database connection and functions
│   ├── header.php            # Layout and navigation
│   └── footer.php            # JavaScript and closing tags
├── modules/
│   ├── hmo/                  # Health insurance management
│   ├── inventory/            # Stock control
│   ├── billing/              # Invoice and payment
│   ├── payroll/              # Employee management
│   ├── reports/              # Analytics and dashboards
│   └── admin/                # System administration
├── assets/                   # CSS, JS, images
└── uploads/                  # File uploads
```

## 💡 Highlights

### Professional Dashboard
- Executive summary with KPI cards
- Interactive charts and graphs
- Recent activity monitoring
- Quick action shortcuts

### Advanced Inventory
- Stock level monitoring with alerts
- Supplier management with purchase history
- Product categorization and search
- Transaction logging for audit trails

### Comprehensive Payroll
- Automatic statutory deductions (SSS, PhilHealth, Pag-IBIG)
- Multiple employment types support
- Payslip generation
- Department-wise cost analysis

### Healthcare-Specific Features
- HMO policy integration
- Provider network management
- Claims approval workflow
- Medical billing support

## 🎯 Business Impact

This ERP system provides:
- **Efficiency**: Automated workflows and reduced manual processes
- **Visibility**: Real-time insights into all business operations
- **Compliance**: Proper audit trails and reporting capabilities
- **Scalability**: Modular design supporting business growth

## 📈 Performance Metrics

- **Patient Management**: Track HMO enrollment and claims
- **Inventory Optimization**: Minimize stockouts and overstocking
- **Financial Control**: Monitor revenue, expenses, and profitability
- **HR Efficiency**: Streamline payroll and employee management

---

**Portfolio Quality**: This is a production-ready ERP system demonstrating advanced PHP development, database design, and full-stack web development capabilities. Perfect for showcasing enterprise-level programming skills.