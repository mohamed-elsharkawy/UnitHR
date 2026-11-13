# UnitHR JSON API

A lightweight REST/JSON API living in `/api/`. It returns JSON and can be used
like an LDAP directory service: bind (authenticate) then search/query.

## Base URL
```
http://localhost/UnitHR/api/?action=<action>
```

## Authentication
Any of:
- **HTTP Basic Auth**: `Authorization: Basic base64(username:password)`
- **Bearer token**: `Authorization: Bearer <token>` (from `action=login`)
- **API key**: `X-API-Key: <key>` (configure via env `API_KEY`)

## Endpoints

| Method | Action | Description |
|--------|--------|-------------|
| POST | `login` / `bind` / `auth` | Authenticate, returns Bearer token |
| GET  | (none) / `index` | API info & endpoint list |
| GET  | `me` | Current user |
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
| GET  | `notifications` | User inbox (requires user context) |

## Response format
```json
{ "success": true, "message": "OK", "data": [...], "meta": { "page": 1, "total": 100 } }
```

## Example
```bash
# Login
curl -X POST -d "username=admin&password=admin123" "http://localhost/UnitHR/api/?action=login"

# Search employees (Basic auth)
curl -u admin:admin123 "http://localhost/UnitHR/api/?action=employees&search=Ashraf"

# Get employee by id
curl -u admin:admin123 "http://localhost/UnitHR/api/?action=employee&id=1"
```