# UnitHR JSON API

A lightweight REST/JSON API living in `/api/`. It returns JSON and can be used
like an LDAP directory service: bind (authenticate) then search/query.

## Base URL
```
http://localhost/UnitHR/api/?action=<action>
```

## Authentication

Every account has a persistent API token in `uhr_users.api_token`. The first
token is the account `rguid`; it can be rotated at any time from **My Profile**
(`?module=userprofile`) or with `action=regenerate_token`. Rotating the token
immediately invalidates the old token and all short-lived bearer tokens issued
before the rotation.

Any of:
- **API token (recommended)**: `X-API-Token: <api_token>`
- **Bearer**: `Authorization: Bearer <api_token>` or a signed token from `action=login`
- **HTTP Basic**: `Authorization: Basic base64(username:password)` (legacy clients)
- **Service API key**: `X-API-Key: <key>` (optional; configure via env `API_KEY`)

Passwords are verified with `password_hash()` / `password_verify()` only; the
old plaintext fallback has been removed.

## Endpoints

| Method | Action | Description |
|--------|--------|-------------|
| POST | `login` / `bind` / `auth` / `ldap_bind` | Authenticate; returns a signed bearer token, the persistent `api_token`, and a DN for bind/ldap_bind |
| GET  | (none) / `index` | API info, authentication methods & endpoint list |
| GET  | `me` | Current user |
| GET  | `profile` | Current user profile plus the linked employee record |
| POST | `change_password` | `current_password`, `new_password` (min 8 characters) |
| POST | `regenerate_token` | Rotate the caller's API token and return the new value |
| GET  | `employees` | List/search employees (`search`, `dept`, `status`, `page`, `limit`) |
| GET  | `employee` | Single employee (`id`) |
| GET  | `departments` | List departments |
| GET  | `deptypes` | List department types |
| GET  | `degrees` | List employee degrees |
| GET  | `groups` | List employee groups |
| GET  | `statuses` | List job statuses |
| GET  | `jobgroups` | List job groups |
| GET  | `types` | List employee types |
| GET  | `leaves` | Leave balances & requests (`empid`) |
| GET  | `attendance` | Attendance records (`empid`, `from`, `to`) |
| GET  | `archives` | Employee archives (`search`, `empid`) |
| GET  | `promotions` | Promotion history (`empid`) |
| GET  | `documenttypes` | List document types |
| GET  | `payroll_types`, `payroll_type` | Payroll payabilities/deductions (`id` for one row) |
| GET  | `payroll_employee_setup` | Employee payroll settings (`empid`) |
| GET  | `payroll_runs`, `payroll_run` | Payroll run summaries and details (`year`, `status`, `id`) |
| GET  | `payroll_tax_years`, `payroll_tax_brackets` | Tax schedules (`year` for brackets) |
| GET  | `payroll_insurance_rules` | Social insurance rules by year |
| GET  | `payroll_year_end` | Year-end reconciliation from finalized runs (`year`) |
| GET  | `search` | LDAP-like search (`base`, `filter`, `limit`) over `users` or `employees` |
| GET  | `whoami` | Current user |
| GET  | `notifications` | User inbox (requires user context) |

## Response format
```json
{ "success": true, "message": "OK", "data": [...], "meta": { "page": 1, "total": 100 } }
```

## Examples
```bash
# Login (returns token + api_token)
curl -X POST -d "username=admin&password=admin123" "http://localhost/UnitHR/api/?action=login"

# Call an endpoint with the persistent API token
curl -H "X-API-Token: <api_token>" "http://localhost/UnitHR/api/?action=me"

# Bearer with the persistent API token also works
curl -H "Authorization: Bearer <api_token>" "http://localhost/UnitHR/api/?action=employees&limit=5"

# LDAP-style bind (returns a DN as well)
curl -X POST -H "Content-Type: application/json" --data-binary '{"username":"admin","password":"admin123"}' \
     "http://localhost/UnitHR/api/?action=ldap_bind"

# Search users
curl -H "X-API-Token: <api_token>" "http://localhost/UnitHR/api/?action=search&base=users&filter=admin"

# Change password (authenticated with X-API-Token)
curl -X POST -H "X-API-Token: <api_token>" -d "current_password=old&new_password=newpassword" \
     "http://localhost/UnitHR/api/?action=change_password"

# Rotate the API token (the old token stops working immediately)
curl -X POST -H "X-API-Token: <api_token>" "http://localhost/UnitHR/api/?action=regenerate_token"
```

## Security notes
- Use the API token over HTTPS in production.
- Do not store tokens in URLs; send them in `X-API-Token` or `Authorization`.
- Rotate a token immediately if it may have been exposed.
- The optional `X-API-Key` is a service-wide key with no user context; it cannot
  access user-specific endpoints such as `profile` or `notifications`.
