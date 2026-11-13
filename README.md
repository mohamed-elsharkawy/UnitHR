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
- ✅ National ID auto-deduction (birth date, retirement date, and gender — last 3 digits: even = Male, odd = Female)
- ✅ Leave balance tracking per leave type per fiscal year
- ✅ Leave approval/rejection workflow by superiors
- ✅ Soft-delete with audit shadow logging
- ✅ Offline exports (CSV, XLSX, Word, PDF) — no internet required
- ✅ Pure-PHP QR code generator for OTP setup
- ✅ DevTools protection with admin toggle
- ✅ Change password for all users
- ✅ Red asterisk on mandatory fields
- ✅ Searchable dropdown menus (Select2, offline) across all modules
- ✅ Permission-based menu loading (sidebar/navbar filtered by user permissions; employees contained to ESS)
- ✅ Employee Degrees linked to Employee Groups (permissions by group)
- ✅ Employees Archives — upload and search employee documents by name, employee, or NID
- ✅ Document Types management
- ✅ Manual Promotions with promotion decision number/date and seniority history
- ✅ File attachments for promotion decisions and archive documents (PDF/JPG/PNG/TIFF)
- ✅ Attendance reports export (PDF, XLSX, Word, CSV)
- ✅ Installer choice: create new database or use existing one
- ✅ REST/JSON API (`/api/`) with Basic/Bearer/API-key auth, LDAP-like bind & search
- ✅ Cron-based module auto-sync (cron.php) for appmodules table
- ✅ Sticky sidebar (full height), sticky navbar with shrink-on-scroll, sticky footer with GitHub link

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
$_SESSION[UHR_SESSION_KEY]['dbhost'] = '127.0.0.1';
$_SESSION[UHR_SESSION_KEY]['dbport'] = 3306;
$_SESSION[UHR_SESSION_KEY]['dbuser'] = 'root';
$_SESSION[UHR_SESSION_KEY]['dbpass'] = 'your_password';
$_SESSION[UHR_SESSION_KEY]['dbname'] = 'unithr';
```

Or use environment variables: `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`

> **Multiple applications on one server:** the server may host several
> OpenFrame-based applications that share PHP's single session cookie per
> host, so every value is stored inside a nested array,
> `$_SESSION[UHR_SESSION_KEY][...]`, instead of flat `$_SESSION['key']`
> variables. The namespace key is derived automatically from the application
> folder (e.g. `/UnitHR/` → `unithr`), so copies of the framework never
> overwrite each other's session data. You can pin your own key by defining
> `UHR_SESSION_KEY` before the framework's `includes/config.php` runs.

### 3. Run Installer
Navigate to `http://localhost/UnitHR/install.php` in your browser. This will:
- Create the database
- Create all tables
- Seed reference data
- Create default SuperAdmin account

### 4. Sync Modules (Optional)
Run `cron.php` to sync all module info.php data to the `appmodules` database table:
```bash
php cron.php
```

### 5. Default Login
- **Username:** `admin`
- **Password:** `admin123`
- OTP setup is required on first login

### 6. (Optional) Remove Installer
After successful installation, you can remove `install.php` for security.

## Project Structure

```
UnitHR/
├── index.php              # Front controller
├── install.php            # Database installer & seeder
├── cron.php               # Module sync cron script
├── schema.sql             # MySQL schema (44 tables + audit log)
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
└── modules/               # 28 application modules
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
| **Employee Seniority** | Seniority tracking & promotion management (with Manual Promotions) |
| **Employee Groups/Types/Degrees/Status** | Reference data management |
| **Employees Archives** | Employee document archive with file upload & search |
| **Document Types** | Manage document types for archives |
| **Manual Promotions** | Manual promotion records with decision number/date & seniority history |
| **Department Types/Structure** | Organization hierarchy |
| **Leave Types/Balances/Requests** | Leave management with approval workflow |
| **Notifications** | Per-user inbox, leave alerts, global/group announcements |
| **Users & Permissions** | User accounts and module permissions |
| **Access Log** | Login/logout audit trail |
| **Reports** | Export data to CSV/XLSX/Word/PDF |
| **ESS** | Employee Self-Service portal |
| **Fiscal Years** | Fiscal year definitions |
| **Retirement Plan** | Retirement age calculations |

### Module Groups & Sidebar

Every module declares its sidebar group in its own `modules/<folder>/info.php`:

```php
$moduleInfo = [
    'name'  => 'Employee Seniority',
    'group' => 'Employees',   // sidebar group identifier
    'order' => 70,            // position inside the group
    // ...
];
```

`includes/header.php` builds the submenu from these identifiers:

- if a group does not exist yet, it is created;
- if it already exists, the module is appended to it;
- modules inside a group are sorted by `order`, then by display name;
- modules without a `group` value fall back to **Other**.

The preferred group order is **Dashboard, Organization, Employees, Leaves, Administration, Fiscal, Reports, Self-Service – ESS**. Any unknown group is displayed afterwards in alphabetical order. Adding a module to an existing group only requires setting `group` and `order` in its `info.php` — no change to `header.php` is needed.

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
| Employee | ESS only (own data, leave requests, own notification inbox, change password) |

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
- **XLSX** — Excel spreadsheet (via PhpSpreadsheet + ZipStream)
- **Word (DOCX)** — Microsoft Word document (built-in OOXML writer)
- **PDF** — PDF document (via DomPDF + php-font-lib + php-svg-lib)

All libraries are bundled offline in `assets/`; no Composer or internet access is required.

Arabic PDF exports are fully supported: letters are converted to their contextual presentation forms and the table is laid out right-to-left (first column on the right, right-aligned text), while numbers, dates, and Latin names keep their natural order. English exports remain left-to-right.

## REST/JSON API

A lightweight JSON API lives in `api/`. It returns JSON and can be used like
an LDAP directory service: bind (authenticate) then search/query.

- Base URL: `http://localhost/UnitHR/api/?action=<action>`
- Auth: HTTP Basic, Bearer token (from `action=login`), or `X-API-Key` header.
- Endpoints: `employees`, `employee`, `departments`, `degrees`, `groups`,
  `statuses`, `leaves`, `attendance`, `archives`, `promotions`,
  `documenttypes`, `notifications`, etc.
- See `api/README.md` for full documentation and examples.

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
- See projected balance immediately after submitting a request
- Read their own notifications in the bell menu and inbox
- Change password
- Logout

ESS is accessible only to logged-in employees. Non-authenticated users are redirected to login.

## Leave Approval Workflow

1. Employee submits leave request via ESS (status: Pending).
2. The request stores the live remaining balance in `balancebefore` and the **projected** balance in `balanceafter` (`max(0, remaining - days)`), so the employee sees the expected result before approval. The leave-type balance in `leavesbalances` is **not** changed at this point.
3. Superior (employee with subordinates) sees pending requests in ESS and receives a notification.
4. Superior can **Approve** or **Reject** (with reason).
5. Approval re-checks the leave-type rules and current balance, then:
   - sets `balancebefore`/`balanceafter` from the current balance,
   - adds the days to `usedbalance` and subtracts them from `remainingbalance` in `leavesbalances`,
   - notifies the employee.
6. Rejection records who rejected and when; the balance is untouched and the employee is notified.
7. Approved leaves cannot be edited or deleted.

The admin **Employee Leaves** module follows the same rules: adding a pending request stores the projected `balanceafter` only, while adding an approved request deducts the balance immediately.

### Employee Workdays

Each employee stores seven boolean workday flags on `uhr_employeesmain`
(`sunworkday` … `satworkday`; `1` = working day, `0` = not a workday).
They are editable on the employee add/edit form and shown on the employee view.

- Leave day counting uses these flags instead of the global weekend whenever the
  columns are present, so employees on different schedules are handled correctly.
- Leave types with **include weekends** enabled count every calendar day and
  ignore the flags.
- Official holidays are still excluded unless the leave type includes holidays.
- Defaults preserve the legacy weekend: Sunday and Saturday off, Monday–Friday working.

### Notifications

The **Notifications** module is available to every logged-in user and also appears
as a bell in the admin and ESS navbars.

- Leave submission notifies the employee's direct superior; if the superior has no
  login account, HR users receive it instead.
- Approval/rejection notifies the employee.
- HR/admin can compose announcements for:
  - **Global** — every active user,
  - **HR Employees** — admins and HR permission holders,
  - **Normal Employees** — users without HR/admin permissions,
  - **Specific User** — one selected account.
- One row is written per recipient, so each user has an independent read state.
- Users can search, filter unread, mark all read, open, and delete their own
  notifications. There is no cross-user access.

### Employee Seniority & Promotion
- Seniority date calculated based on degree acquired date and interim period
- Excess time in a degree carries over to the new degree upon promotion
- Promotion eligibility checked: interim period elapsed + vacant next degree exists
- Job groups: Specialized (Higher Education) and Technical & Clerical (Diploma)
- Seniority management module with promotion workflow UI

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
- **Libraries:** PhpSpreadsheet + ZipStream (XLSX), DomPDF + php-font-lib + php-svg-lib (PDF), disable-devtool (security)
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