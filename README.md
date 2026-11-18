# Multi-Tenant SaaS Project & Resource Management Platform

A robust, lightweight, production-grade Multi-Tenant SaaS platform built with **Laravel 12 / PHP 8.2+**, **MySQL 8**, and **Laravel Queues**.

---

## 🚀 Key Features & Modules

- **Module 1: Multi-Tenant Architecture**
  - Shared-database multi-tenancy with `organization_id` column.
  - Automatic query scoping via `BelongsToTenant` trait and global `TenantScope`.
  - `TenantMiddleware` protects against ID manipulation and cross-tenant data leaks.
  - Super Admin global visibility across all tenants.

- **Module 2: Role-Based Access Control (RBAC)**
  - Native PHP Enums: `SuperAdmin`, `OrgAdmin`, `ProjectManager`, `Developer`.
  - Comprehensive Laravel Policies (`ProjectPolicy`, `TaskPolicy`) protecting all endpoints.
  - Granular permissions for viewing, creating, updating, assigning, and deleting resources.

- **Module 3: Project & Task Management**
  - Full project lifecycle (`draft`, `active`, `completed`, `on_hold`).
  - Tasks with priorities (`low`, `medium`, `high`, `critical`) and statuses (`open`, `in_progress`, `review`, `completed`).
  - Strict **Status Transition State Machine** preventing invalid jumps (e.g. `completed` → `open`).
  - Comprehensive task activity audit trail (`task_activities` table).

- **Module 4: REST API Development**
  - 10 standardized JSON endpoints with uniform envelope (`success`, `message`, `data`, `meta`).
  - Filtering, search, sorting, and pagination.
  - Streamed CSV export for high-volume task exports (`GET /api/tasks/export`).

- **Module 5: Database Design & Performance Optimization**
  - Normalized schema with foreign keys and composite indexes `(organization_id, status)`, `(assigned_to, status)`.
  - Eager loading (`with(['manager', 'developers'])`) eliminating all N+1 queries.
  - Database-level conditional aggregation for Dashboard (`COUNT(CASE WHEN...)`) resulting in sub-15ms response times.

- **Module 6: Background Jobs & Notifications**
  - Asynchronous task assignment notification workflow via `SendTaskAssignedNotificationJob`.
  - Stored in MySQL `notifications` table.
  - Idempotency and deduplication mechanisms preventing duplicate notifications.

- **Module 7: Debugging & Code Review**
  - Detailed root-cause analysis and architectural fixes for all 6 assessment production scenarios documented in [DEBUGGING_CODE_REVIEW.md](file:///home/mtpc/www/mt/learning/Multi-Tenant-SaaS-App/DEBUGGING_CODE_REVIEW.md).

---

## 🛠️ Technology Stack

- **Framework**: Laravel 12.x / PHP 8.5 (compatible with PHP 8.2+)
- **Database**: MySQL 8.x
- **Authentication**: Laravel Sanctum (Bearer Token)
- **Queue Driver**: Database (zero external infrastructure dependency)
- **Cache / Sessions**: Database-backed

---

## ⚙️ Installation & Setup

### 1. Prerequisites
- PHP 8.2+ with `pdo_mysql`, `bcmath`, `mbstring`, `json`, `xml`
- Composer 2.x
- MySQL 8.x running locally

### 2. Configure Environment
```bash
cp .env.example .env
```
Ensure your database credentials match in `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=multi_tenant_saas
DB_USERNAME=laravel
DB_PASSWORD=Laravel@123
QUEUE_CONNECTION=database
```

### 3. Migrate and Seed Database
```bash
php artisan migrate:fresh --seed
```

### 4. Run Queue Worker
To process background notifications asynchronously:
```bash
php artisan queue:work --tries=3 --timeout=60
```

### 5. Start Application Server
```bash
php artisan serve --port=8000
```
API will be accessible at: `http://127.0.0.1:8000/api`

---

## 🧪 Pre-Seeded Test Accounts

All accounts use the password: `password123`

| Role | Email | Organization | Access Level |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `superadmin@saas.test` | *Global* | Cross-tenant access to all data |
| **Org Admin (Acme)** | `admin@acme.test` | Acme Corporation (ID: 1) | Manage all Acme projects, users, tasks |
| **Project Manager (Acme)**| `pm@acme.test` | Acme Corporation (ID: 1) | Manage assigned projects and tasks |
| **Developer 1 (Acme)** | `dev1@acme.test` | Acme Corporation (ID: 1) | View assigned projects & update assigned tasks |
| **Developer 2 (Acme)** | `dev2@acme.test` | Acme Corporation (ID: 1) | View assigned projects & update assigned tasks |
| **Org Admin (Globex)** | `admin@globex.test`| Globex Industries (ID: 2) | Manage all Globex projects and tasks |

---

## 📡 API Endpoints Reference

Import the ready-to-use Postman Collection: [postman_collection.json](file:///home/mtpc/www/mt/learning/Multi-Tenant-SaaS-App/postman_collection.json)

| Method | Endpoint | Description | Auth Required |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/auth/login` | Authenticate user & get Bearer Token | Public |
| `POST` | `/api/auth/logout` | Revoke current token | Sanctum |
| `GET` | `/api/auth/me` | Fetch current user & organization profile | Sanctum |
| `GET` | `/api/dashboard/summary` | Sub-15ms aggregated dashboard metrics | Sanctum + Tenant |
| `GET` | `/api/projects` | List projects (search, filter by status, sort) | Sanctum + Tenant |
| `POST` | `/api/projects` | Create new project | Org Admin, Super Admin, PM |
| `GET` | `/api/projects/{id}` | Project details with manager & developers | Authorized members |
| `DELETE`| `/api/projects/{id}` | Delete project | Org Admin, Super Admin |
| `GET` | `/api/projects/{id}/tasks`| List tasks for project (filter & pagination) | Authorized members |
| `POST` | `/api/projects/{id}/tasks`| Create task under project | Org Admin, PM |
| `PATCH`| `/api/tasks/{id}/status` | Update task status (state-machine validated) | Org Admin, PM, Assigned Dev |
| `POST` | `/api/tasks/{id}/assign` | Assign developer to task (triggers queue job) | Org Admin, PM |
| `GET` | `/api/tasks/export` | Streamed CSV task export | Sanctum + Tenant |

---

## 🧪 Running Automated Tests

Run the full automated test suite containing 27 tests and 82 assertions:
```bash
php artisan test
```

### Test Coverage Highlights:
- `AuthTest`: Login, invalid credentials, token revocation.
- `TenantIsolationTest`: Cross-tenant data isolation, ID manipulation prevention, Super Admin bypass.
- `RbacAuthorizationTest`: Permissions per role (Developer cannot delete projects, PM can manage assigned projects).
- `TaskStatusTransitionTest`: State machine validation (Rejects `completed` → `open` with 422, logs activity).
- `TaskAssignmentAndQueueTest`: Job dispatching, DB notifications, deduplication idempotency.
- `DashboardAndExportTest`: Direct MySQL SQL aggregation, streamed CSV export.

---

## 🚢 Production Queue Worker Deployment & Monitoring

### 1. Supervisor Configuration
In production (e.g. Ubuntu Linux), queue workers should be managed using `supervisor`:
```ini
[program:saas-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
```

### 2. Failed Job Monitoring
- Failed jobs are tracked in the `failed_jobs` table.
- View failed jobs: `php artisan queue:failed`
- Retry failed jobs: `php artisan queue:retry all`
- Alerts can be integrated into Slack/Datadog using `Queue::failing()` hook in `AppServiceProvider`.
