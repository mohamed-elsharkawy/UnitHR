# UnitHR — Technical Documentation

## Table of Contents

1. [Architecture](#architecture)
2. [Database Schema](#database-schema)
3. [Authentication Flow](#authentication-flow)
4. [Module System](#module-system)
5. [CRUD Engine](#crud-engine)
6. [NID Deduction](#nid-deduction)
7. [Leave Management](#leave-management)
8. [Notifications](#notifications)
9. [Export System](#export-system)
10. [Security](#security)
11. [Configuration](#configuration)

---

## Architecture

### Front Controller Pattern
`index.php` serves as the front controller:
1. Loads config, DB, helpers, auth, routes
2. Handles language switching, login, logout, OTP setup
3. Routes to modules via `load_module()`

### Module Discovery
Modules are auto-discovered from the `modules/` folder. Each module has:
- `info.php` — Module metadata (name, description, version, enabled, group, order)
- `index.php` — Module logic and rendering

### Module Groups (Sidebar)
Each `info.php` declares the sidebar group the module belongs to:

```php
$moduleInfo = [
    'name'        => 'Employee Seniority',
    'description' => 'Seniority tracking and promotion management.',
    'group'       => 'Employees',  // group identifier
    'order'       => 70,           // position inside the group
    'enabled'     => true,
];
```

`get_routes()` passes `group` and `order` through to `includes/header.php`, which calls `build_module_groups()` in `includes/modules.php`:

- **create if missing** — a group identifier seen for the first time creates a new submenu group;
- **append if existing** — later modules with the same identifier are added to that group;
- modules are sorted by `order`, then by display name;
- an empty or missing `group` falls back to **Other**;
- known groups keep the preferred order, unknown groups follow alphabetically.

Preferred group order: `Dashboard`, `Organization`, `Employees`, `Leaves`, `Administration`, `Fiscal`, `Reports`, `Self-Service – ESS`. Arabic group labels live in the `$groupNamesAr` map in `includes/header.php`. To move a module between groups, change only its `group` value; to reorder it, change `order`.

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
│ │  │  │  │    └── Gender (last 3 digits: even=Male, odd=Female)
│ │  │  │  └─────── Serial number
│ │  │  └────────── Day of birth
│ │  └───────────── Month of birth
│ └──────────────── Year of birth
└────────────────── Century (2=1900s, 3=2000s)
```

Auto-deduced fields:
- **Date of Birth:** Century + YY-MM-DD (validated with checkdate)
- **Gender:** Last 3 digits even=Male, odd=Female (applied on add and edit; the field is derived automatically and cannot be overridden)
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

#### Balance semantics
| Stage | `empleaves.balancebefore` | `empleaves.balanceafter` | `leavesbalances` |
|-------|---------------------------|--------------------------|------------------|
| Submitted (pending) | live remaining balance | `max(0, remaining - days)` (projected) | unchanged |
| Approved | current remaining balance | `max(0, remaining - days)` | `usedbalance += days`, `remainingbalance -= days` |
| Rejected | unchanged | unchanged | unchanged |

Approval re-runs the leave-type rules (fiscal year, `tpel`, `tpey`, workdays,
holidays) and re-checks the balance through `leave_evaluate_approval()` before
updating anything. A conditional `UPDATE … WHERE isapproved = 0` prevents a double
submit from deducting twice.

#### Employee workdays
`uhr_employeesmain` stores `sunworkday`, `monworkday`, `tueworkday`, `wedworkday`,
`thuworkday`, `friworkday`, `satworkday` as `TINYINT(1)` (1 = working day,
0 = not a workday). The employee add/edit form exposes them as checkboxes.

`leave_count_days()` receives the employee's flags through
`leave_employee_workdays()` and skips unchecked days when the leave type does not
include weekends. If the columns are missing (older database), counting falls back
to the legacy Sunday/Saturday weekend. `include weekends` still counts every day.

### Approval Workflow (ESS)
- Employees submit leave requests (status: Pending)
- Superiors see pending requests in ESS
- Approve: re-checks rules and deducts from leave balance, then notifies the employee
- Reject: records rejection reason, who, and when, then notifies the employee

### Employee Seniority & Promotion
- Seniority date calculated based on degree acquired date and interim period
- Excess time in a degree carries over to the new degree upon promotion
- Formula: `seniority_date = promotion_date - (time_in_degree - interim_period)`
- Promotion eligibility: interim period elapsed + vacant next degree exists
- Job groups: Specialized (Higher Education) and Technical & Clerical (Diploma)
- Seniority management module with promotion workflow UI
- Helper functions: `calculate_seniority_date()`, `is_eligible_for_promotion()`

---

## Notifications

Implemented in `includes/notifications.php` (loaded automatically by
`includes/helpers.php`) with the UI in `modules/notifications/`.

### Data model
`uhr_notifications` stores **one row per recipient**, so read state is per user:

| Column | Purpose |
|--------|---------|
| `userid` | recipient account |
| `empid` | related employee (optional) |
| `scope` | `user`, `group` or `global` |
| `scopevalue` | `hr` / `employees` for group scope |
| `title`, `titlear` | bilingual title |
| `message`, `messagear` | bilingual body |
| `link`, `module`, `refid` | deep link and source reference |
| `isread`, `readat` | per-row read state |

### Events
- **Leave submitted** (`notify_leave_submitted`) — notifies the employee's direct
  superior's user account (employee login = NID). If no superior login exists, it
  falls back to the HR group so the request is not lost.
- **Leave approved / rejected** (`notify_leave_decision`) — notifies the employee.
- **Permission request** (`notify_permission_request`) — generic hook for a future
  permission module; no module consumes it yet.

### Audiences
| Scope | Recipients |
|-------|------------|
| `user` | one selected user |
| `group` = `hr` | admins, superadmins, HR-admin and HR-user permissions |
| `group` = `employees` | every active user who is not HR/admin |
| `global` | every active user |

HR/admin users can compose an announcement from the module (`Send Notification`);
employees only see their own inbox. Both layouts render a bell with the unread
count (`notifications_render_bell()`), and every user can search, filter unread,
mark all read, open a linked record, and delete their own notifications.

---

## Export System

### Supported Formats
| Format | Library | Content Type |
|--------|---------|-------------|
| CSV | Built-in (fputcsv) | text/csv |
| XLSX | PhpSpreadsheet + ZipStream | application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| Word (DOCX) | Built-in (ZipWriter / OOXML) | application/vnd.openxmlformats-officedocument.wordprocessingml.document |
| PDF | DomPDF + php-font-lib + php-svg-lib | application/pdf |

All dependencies are bundled offline under `assets/` and registered in `includes/autoload.php`:
`assets/PhpSpreadsheet/`, `assets/zipstream/`, `assets/dompdf/`, `assets/psr/`, `assets/composer-pcre/`, `assets/masterminds/`.

### Export Flow
1. User clicks export button on list page
2. Module's export handler queries data
3. `do_export()` calls `export_clean_buffers()` to discard the layout HTML the front controller buffered
4. The format-specific function sets headers and streams the file
5. `\Throwable` is caught in the XLSX/PDF paths so an unavailable library falls back to the built-in generator instead of returning an HTTP 500

> The buffer cleanup is required: `index.php` includes `header.php` before loading the module, so without it every download would start with `<!DOCTYPE html>` and be corrupted.

### Arabic PDF Support

DomPDF has no Arabic contextual shaping and no Unicode bidi algorithm, so Arabic would otherwise print as disconnected letters, left-to-right. `export_pdf()` now:

1. Runs `uhr_shape_arabic()` to map each letter to its isolated/initial/medial/final presentation form (U+FE70–U+FEFF, present in the bundled DejaVu Sans font), including lam-alef ligatures.
2. Runs `uhr_pdf_arabic()` to reorder mixed Arabic/Latin/number runs for right-to-left display, keeping numbers, dates, and Latin names intact.
3. Reverses the column order and right-aligns the table when the UI language is Arabic.
4. Keeps English exports left-to-right and left-aligned; Arabic cell values are still shaped when they appear.

Other formats are unaffected: Word and Excel store logical Unicode text and perform their own shaping/bidi.

---

## Attendance Permissions Integration

The Attendance module (`attendance`) now supports fixed work schedules that can be
assigned to individual employees or departments/groups via `schedule_assignments`.
The Attendance Permissions module (`attpermissions`) allows employees to submit
permission requests (time-based or full-day) which route through a hierarchical
approval workflow (superior → HR). Approved permissions affect attendance records
without modifying raw biometric punches.

### Schedule Table (`uhr_work_schedules`)
Bilingual schedule names (`name_ar`/`name_en`), start/end times, working days
stored in `uhr_work_schedule_days` (day_of_week 0-6).

### Assignment Table (`uhr_schedule_assignments`)
Supports `employee`, `department`, and `group` assignment types with
`effective_from`/`effective_to` date ranges and `priority` for conflict resolution.
Individual employee assignments take precedence over department/group assignments.

### Permission Tables
- `uhr_att_permission_types` — five configurable types:
  `EARLY_DEPARTURE`, `LATE_ARRIVAL`, `PERSONAL`, `OFFICIAL_BUSINESS`, `WORK_ASSIGNMENT`
- `uhr_att_permission_policies` — configurable limits per type/scope/period
- `uhr_att_permission_requests` — requests with status DRAFT/PENDING/APPROVED/REJECTED/CANCELLED
- `uhr_att_permission_approvals` — per-level approval decisions
- `uhr_att_permission_attendance` — linkage to affected attendance records

---

## Employee Degrees & Groups (`ehr_empdegrees`)

Degree records now belong to an employee group via `employeesgroup_id` foreign
key referencing `uhr_employeesgroup`. This allows different degree ladders for
different employee groups (e.g. Administrative Staff vs Scientific Staff).

All existing degrees are assigned to group 1 (`Administrative Staff`).

### Tables
| Table | Key Fields |
|-------|-----------|
| `uhr_work_schedules` | `name_ar`, `name_en`, `start_time`, `end_time`, `is_active` |
| `uhr_work_schedule_days` | `schedule_id`, `day_of_week`, `is_working_day` |
| `uhr_schedule_assignments` | `assignment_type`, `employee_id`/`depid`/`empgroup`, `schedule_id`, `effective_from`/`effective_to`, `priority` |
| `uhr_att_permission_types` | `code`, `name_ar`, `name_en`, `category` (TIME_BASED/FULL_DAY), `is_active` |
| `uhr_att_permission_policies` | `type_id`, `name_ar`, `name_en`, `scope_type`, `scope_id`, `limit_unit`, `period_type`, limits |
| `uhr_att_permission_requests` | `employee_id`, `type_id`, `start_date`/`end_date`, `start_time`/`end_time`, `reason`, `status` |
| `uhr_att_permission_approvals` | `request_id`, `approver_user_id`, `approval_level`, `decision`, `comments` |
| `uhr_att_permission_attendance` | `request_id`, `attendance_id`, `applied_minutes` |

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
All session values live in a nested, per-application namespace so the server
can host multiple OpenFrame-based applications without session collisions:

```
$_SESSION[UHR_SESSION_KEY][<variable>]
```

The `UHR_SESSION_KEY` constant is derived from the application folder name
(`/UnitHR/` → `unithr`), or it can be overridden by defining it before
`includes/config.php` loads. Direct writes to flat `$_SESSION['key']` variables
must be avoided.

| Variable | Default | Description |
|----------|---------|-------------|
| `dbprefix` | `uhr_` | Table name prefix |
| `dbname` | `unithr` | Database name |
| `lang` | `ar` | Default language |
| `encrypt_key` | (set in config) | Encryption key |
| `devtool_disabled` | `0` | DevTools protection disabled (session) |

On login the namespace holds the authentication state (`user_id`, `user`,
`user_permissions`, `otp_*`); on logout only the application's own namespace
is unset and the session id is regenerated (`session_regenerate_id(true)`),
so co-hosted OpenFrame applications keep their sessions alive.

---

## File Reference

### Core Files
| File | Purpose |
|------|---------|
| `index.php` | Front controller, routing, auth |
| `install.php` | Database installer & seeder |
| `schema.sql` | Database schema (21 tables) |
| `includes/config.php` | Configuration |
| `includes/db.php` | Database connection (PDO) |
| `includes/helpers.php` | Utilities, exporters, field_input() |
| `includes/auth.php` | Authentication functions |
| `includes/leave_rules.php` | Shared leave-rule engine and workday-aware day counting |
| `includes/notifications.php` | Notification engine, scopes, navbar bell |
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