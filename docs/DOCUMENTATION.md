# UnitHR — Technical Documentation

## Table of Contents

1. [Architecture](#architecture)
2. [Database Schema](#database-schema)
3. [Authentication Flow](#authentication-flow)
4. [Module System](#module-system)
5. [CRUD Engine](#crud-engine)
6. [NID Deduction](#nid-deduction)
7. [Leave Management](#leave-management)
8. [Export System](#export-system)
9. [Security](#security)
10. [Configuration](#configuration)

---

## Architecture

### Front Controller Pattern
`index.php` serves as the front controller:
1. Loads config, DB, helpers, auth, routes
2. Handles language switching, login, logout, OTP setup
3. Routes to modules via `load_module()`

### Module Discovery
Modules are auto-discovered from the `modules/` folder. Each module has:
- `info.php` — Module metadata (name, description, version, enabled)
- `index.php` — Module logic and rendering

### Layout System
- **Admin pages:** Sidebar + Navbar + Content + Footer
- **ESS pages:** Simple navbar + Content + Footer
- **Auth pages:** Simple navbar + Content + Footer

---

## Database Schema

### Table Naming
All tables use `uhr_` prefix. Table names are constructed via `table('name')` → `uhr_name`.

### Audit Columns
Every table includes:
- `id` (BIGINT, auto-increment)
- `rguid` (VARCHAR 200, unique)
- `dtadded` (TIMESTAMP)
- `addedbyid`, `addedby`, `addedbyname`, `addedbynamear`
- `lastedit` (TIMESTAMP, auto-update)
- `editedbyid`, `editedby`, `editedbyname`, `editedbynamear`
- `omitted` (TINYINT, soft-delete flag)

### Shadow Log
`uhr_shadow_log` stores snapshots of records before updates/deletes:
- `table_name`, `original_id`, `action`, `changedat`
- `changedbyid`, `changedby`, `changedbyname`, `changedbynamear`
- `snapshot` (JSON of the record before change)

---

## Authentication Flow

### Login Process
1. User submits username/password
2. `attempt_login()` verifies credentials against `uhr_users`
3. If user needs OTP (admin/HR):
   - No OTP secret → redirect to OTP setup
   - OTP secret exists → redirect to OTP verification page
4. If no OTP needed → complete login, redirect to ESS (employees) or Dashboard (admins)

### OTP Verification
- TOTP (RFC 6238) with SHA-1 HMAC
- 30-second time step, ±1 window (90 seconds total)
- QR code provisioning for authenticator apps
- Pure PHP implementation (no openssl required)

### Password Hashing
- New passwords: bcrypt via `password_hash()` / `password_verify()`
- Legacy: RC4 reversible encryption (fallback for migration)

---

## Module System

### CRUD Engine (`crud_engine.php`)
Generic CRUD for simple tables. Config includes:
- `table`, `module`, `pk`, `title`, `title_ar`
- `fields` — field definitions with type, label, required, options
- `order_by`, `search_fields`, `list_cols`
- `extra_select`, `extra_from`, `extra_where`

Actions: list, add, edit, view, del, export

### Custom Modules
Complex modules (employeesmain, empleaves, leavesbalances, depstructure, ess) implement their own logic.

---

## NID Deduction

Egyptian National ID format (14 digits):
```
C YY MM DD SSSS G
│ │  │  │  │    └── Gender (last 3 digits: odd=Male, even=Female)
│ │  │  │  └─────── Serial number
│ │  │  └────────── Day of birth
│ │  └───────────── Month of birth
│ └──────────────── Year of birth
└────────────────── Century (2=1900s, 3=2000s)
```

Auto-deduced fields:
- **Date of Birth:** Century + YY-MM-DD (validated with checkdate)
- **Gender:** Last 3 digits odd=Male, even=Female
- **Retirement Date:** Birth date + service years (from retireplan table)

---

## Leave Management

### Leave Types
Configurable leave types with rules:
- Times per employee life (0=unlimited)
- Times per year (0=unlimited)
- Include weekends (yes/no)
- Include holidays (yes/no)
- Transferable to next year (yes/no)

### Leave Balances
- Per employee, per fiscal year, per leave type
- Fields: carryover, initial, used, remaining
- Auto-calculated: remaining = carryover + initial - used
- Auto-initialized when employee is added (all leave types × all fiscal years)

### Leave Requests
- Employee, leave type, start/end dates
- Fiscal year boundary validation
- Balance validation before approval
- Weekend exclusion based on leave type rules
- Approval deducts from balance
- Rejection tracking (who and when)
- Approved leaves cannot be edited/deleted

### Approval Workflow (ESS)
- Employees submit leave requests (status: Pending)
- Superiors see pending requests in ESS
- Approve: deducts from leave balance
- Reject: records rejection reason, who, and when

---

## Export System

### Supported Formats
| Format | Library | Content Type |
|--------|---------|-------------|
| CSV | Built-in (fputcsv) | text/csv |
| XLSX | PhpSpreadsheet | application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| Word (DOCX) | Built-in (ZipWriter) | application/vnd.openxmlformats-officedocument.wordprocessingml.document |
| PDF | DomPDF | application/pdf |

### Export Flow
1. User clicks export button on list page
2. Module's export handler queries data
3. `do_export()` dispatches to format-specific function
4. File is sent as download with appropriate headers

---

## Security

### Authentication
- Session-based with `require_login()` gate
- Password hashing: bcrypt (`password_hash`/`password_verify`)
- TOTP OTP for admins/HR staff (offline authenticator)
- OTP setup forced on first login for admins

### Authorization
- `user_can($module, $action)` checks permissions
- Superadmins bypass all checks
- Per-module permissions: view, add, edit, delete, admin
- HR admin/user flags for OTP enforcement

### Access Control
- Employees redirected to ESS on login
- Employees cannot access admin modules
- Module-level permission checks in custom modules

### DevTools Protection
- disable-devtool.min.js blocks right-click inspect
- Admin-only toggle button in footer
- Redirects to redirect.html when detected

### Audit Trail
- Shadow log stores record snapshots before updates/deletes
- Access log records all login/logout attempts
- Soft-delete (omitted flag) preserves all records

---

## Configuration

### Environment Variables
| Variable | Default | Description |
|----------|---------|-------------|
| `DB_HOST` | `127.0.0.1` | Database host |
| `DB_PORT` | `3306` | Database port |
| `DB_USER` | `root` | Database user |
| `DB_PASS` | `AdminNewp@ss01` | Database password |

### Session Variables
| Variable | Default | Description |
|----------|---------|-------------|
| `dbprefix` | `uhr_` | Table name prefix |
| `dbname` | `unithr` | Database name |
| `lang` | `ar` | Default language |
| `encrypt_key` | (set in config) | Encryption key |
| `devtool_disabled` | `0` | DevTools protection disabled (session) |

---

## File Reference

### Core Files
| File | Purpose |
|------|---------|
| `index.php` | Front controller, routing, auth |
| `install.php` | Database installer & seeder |
| `schema.sql` | Database schema (18 tables) |
| `includes/config.php` | Configuration |
| `includes/db.php` | Database connection (PDO) |
| `includes/helpers.php` | Utilities, exporters, field_input() |
| `includes/auth.php` | Authentication functions |
| `includes/crud_engine.php` | Generic CRUD engine |
| `includes/header.php` | Layout header (3-row structure) |
| `includes/footer.php` | Layout footer (devtool toggle) |

### Module Files
Each module in `modules/` has:
- `info.php` — Metadata
- `index.php` — Logic and rendering

---

*UnitHR Technical Documentation*  
*Last updated: November 2026*