# UnitHR - Human Resources Management System

![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-purple)
![License](https://img.shields.io/badge/License-MIT-green)

## Overview

**UnitHR** is a comprehensive Human Resources Management System built on the OpenFrame PHP Framework pattern. It provides complete HR management capabilities including employee records, leave management, department structure, user management, and reporting — all in a bilingual (Arabic/English) interface with RTL support.

## Features

### Core Modules
- **Employee Management** — Main employee records with National ID auto-deduction (birth date, gender, retirement date)
- **Department Structure** — Multi-level department hierarchy with types and managers
- **Leave Management** — Leave types, balances per fiscal year, leave requests with approval workflow
- **User Management** — Role-based permissions, OTP two-factor authentication
- **Employee Self-Service (ESS)** — Employees access their own data via login credentials
- **Reports** — Export to CSV, XLSX, Word (DOCX), and PDF

### Key Features
- ✅ Bilingual interface (Arabic RTL default, English LTR)
- ✅ TOTP two-factor authentication (Google/Microsoft/FreeOTP/Oracle Authenticator)
- ✅ Employee login credentials auto-created on employee addition
- ✅ National ID auto-deduction (Egyptian NID format)
- ✅ Leave balance tracking per leave type per fiscal year
- ✅ Leave approval/rejection workflow by superiors
- ✅ Soft-delete with audit shadow logging
- ✅ Offline exports (CSV, XLSX, Word, PDF) — no internet required
- ✅ Pure-PHP QR code generator for OTP setup
- ✅ DevTools protection with admin toggle
- ✅ Change password for all users
- ✅ Red asterisk on mandatory fields
- ✅ Searchable dropdown menus

## Requirements

- **PHP** 7.4 or higher (8.x recommended)
- **MySQL** 5.7+ or **MariaDB** 10.3+
- **Web Server** (Apache/Nginx)
- **PHP Extensions:** `pdo_mysql`, `mbstring`

## Installation

### 1. Clone the Repository
```bash
git clone https://github.com/your-username/UnitHR.git
```

### 2. Configure Database
Edit `includes/config.php` or set environment variables:
```php
$_SESSION['dbhost'] = '127.0.0.1';
$_SESSION['dbport'] = 3306;
$_SESSION['dbuser'] = 'root';
$_SESSION['dbpass'] = 'your_password';
$_SESSION['dbname'] = 'unithr';
```

Or use environment variables: `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`

### 3. Run Installer
Navigate to `http://localhost/UnitHR/install.php` in your browser. This will:
- Create the database
- Create all 18 tables
- Seed reference data
- Create default SuperAdmin account

### 4. Default Login
- **Username:** `admin`
- **Password:** `admin123`
- OTP setup is required on first login

### 5. (Optional) Remove Installer
After successful installation, you can remove `install.php` for security.

## Project Structure

```
UnitHR/
├── index.php              # Front controller
├── install.php            # Database installer & seeder
├── schema.sql             # MySQL schema (18 tables + audit log)
├── redirect.html          # DevTools redirect page
├── assets/                # Bootstrap CSS/JS, icons, logo
├── includes/              # Core framework files
│   ├── config.php         # App configuration
│   ├── db.php             # Database connection (PDO)
│   ├── helpers.php        # Encryption, TOTP, exporters, utilities
│   ├── auth.php           # Authentication & authorization
│   ├── routes.php         # Module routing
│   ├── header.php         # Shared layout header
│   ├── footer.php         # Shared layout footer
│   ├── login.php          # Login form (two tabs)
│   ├── modules.php        # Module scanner
│   ├── qrcode.php         # Pure-PHP QR code generator
│   └── crud_engine.php    # Generic CRUD engine
├── lang/                  # Translations
│   ├── en.php             # English labels
│   └── ar.php             # Arabic labels
└── modules/               # 19 application modules
    ├── home/              # Dashboard
    ├── employeesmain/     # Employee records (NID deduction)
    ├── leavesbalances/    # Leave balances per type & fiscal year
    ├── empleaves/         # Leave requests & approval
    ├── ess/               # Employee Self-Service
    ├── users/             # User management
    ├── permissions/       # Module permissions
    ├── depstructure/      # Department structure
    ├── deptypes/          # Department types
    ├── reports/           # Data export reports
    └── ...                # Other modules
```

## Modules

| Module | Description |
|--------|-------------|
| **Home** | Dashboard with statistics |
| **Employees Main** | Employee records with NID auto-deduction |
| **Employee Groups/Types/Degrees/Status** | Reference data management |
| **Department Types/Structure** | Organization hierarchy |
| **Leave Types/Balances/Requests** | Leave management with approval workflow |
| **Users & Permissions** | User accounts and module permissions |
| **Access Log** | Login/logout audit trail |
| **Reports** | Export data to CSV/XLSX/Word/PDF |
| **ESS** | Employee Self-Service portal |
| **Fiscal Years** | Fiscal year definitions |
| **Retirement Plan** | Retirement age calculations |

## Default Credentials

| Role | Username | Password |
|------|----------|----------|
| SuperAdmin | `admin` | `admin123` (OTP setup required on first login) |
| Employee | `<National ID>` | `user1234` (auto-created when employee is added) |

## User Roles

| Role | Access |
|------|--------|
| SuperAdmin | Full access to all modules + dashboard + DevTools toggle |
| Admin | Full access to all modules + dashboard + DevTools toggle |
| Employee | ESS only (own data, leave requests, change password) |

## Database Schema

All tables use `uhr_` prefix. Every table includes:
- `id` (BIGINT, auto-increment primary key)
- `rguid` (VARCHAR, unique record GUID)
- `dtadded` (timestamp)
- Audit columns (`addedby*`, `lastedit`, `editedby*`)
- `omitted` (TINYINT, soft-delete flag)

Records are **never hard-deleted** — they are soft-omitted and versioned in `uhr_shadow_log` before every update.

## Exports

All list pages support exporting to:
- **CSV** — Comma-separated values
- **XLSX** — Excel spreadsheet (via PhpSpreadsheet)
- **Word (DOCX)** — Microsoft Word document
- **PDF** — PDF document (via DomPDF)

## Security Features

- Session-based authentication
- TOTP two-factor authentication for admins/HR staff
- Bcrypt password hashing
- Permission-based access control
- Soft-delete with audit trail
- DevTools protection (disable right-click inspect)
- CSRF protection via session tokens
- Duplicate record prevention (NID, Insurance ID, Biometric ID)

## ESS (Employee Self-Service)

Employees can:
- View personal details and department info
- View leave balances per leave type and fiscal year
- View leave history
- Submit leave requests (pending approval)
- Change password
- Logout

ESS is accessible only to logged-in employees. Non-authenticated users are redirected to login.

## Leave Approval Workflow

1. Employee submits leave request via ESS (status: Pending)
2. Superior (employee with subordinates) sees pending requests in ESS
3. Superior can **Approve** (deducts from leave balance) or **Reject** (with reason)
4. Rejected leaves show who rejected and when
5. Approved leaves cannot be edited or deleted

## Configuration

### Environment Variables
| Variable | Default | Description |
|----------|---------|-------------|
| `DB_HOST` | `127.0.0.1` | Database host |
| `DB_PORT` | `3306` | Database port |
| `DB_USER` | `root` | Database user |
| `DB_PASS` | `AdminNewp@ss01` | Database password |

### Application Settings (in `config.php`)
| Setting | Default | Description |
|---------|---------|-------------|
| `dbprefix` | `uhr_` | Table name prefix |
| `dbname` | `unithr` | Database name |
| `lang` | `ar` | Default language (ar/en) |
| `encrypt_key` | (set in config) | Encryption key |

## Technologies Used

- **Backend:** PHP 7.4+, MySQL/MariaDB
- **Frontend:** Bootstrap 5 (offline), Bootstrap Icons
- **Libraries:** PhpSpreadsheet (XLSX), DomPDF (PDF), disable-devtool (security)
- **Authentication:** TOTP (RFC 6238) via hash_hmac (SHA-1)
- **QR Code:** Pure PHP implementation (no GD required)

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License. See `LICENSE` file for details.

## Support

For issues and feature requests, please open an issue on GitHub.

---

**UnitHR** — HR Management Solution  
Built with ❤️ for efficient HR management.