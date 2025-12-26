# Control Tower - Function Reference

Technical reference for developers and advanced users.

---

## Table of Contents

1. [Routes & Endpoints](#1-routes--endpoints)
2. [Controller Methods](#2-controller-methods)
3. [Model Reference](#3-model-reference)
4. [Console Commands](#4-console-commands)
5. [Services](#5-services)
6. [Events & Notifications](#6-events--notifications)

---

## 1. Routes & Endpoints

### Authentication Routes

| Method | Route | Controller | Purpose |
|--------|-------|------------|---------|
| GET | `/login` | AuthController@showLogin | Login form |
| POST | `/login` | AuthController@login | Process login |
| GET | `/register` | AuthController@showRegister | Registration form |
| POST | `/register` | AuthController@register | Process registration |
| POST | `/logout` | AuthController@logout | Logout user |
| GET | `/two-factor/challenge` | TwoFactorController@challenge | 2FA challenge |
| POST | `/two-factor/verify` | TwoFactorController@verify | Verify 2FA code |

### Dashboard & Search

| Method | Route | Controller | Purpose |
|--------|-------|------------|---------|
| GET | `/` | DashboardController@index | Main dashboard |
| GET | `/search` | SearchController@search | Global search |

### Job Routes

| Method | Route | Controller | Purpose |
|--------|-------|------------|---------|
| GET | `/jobs` | JobController@index | List jobs |
| GET | `/jobs/kanban` | JobController@kanban | Kanban view |
| GET | `/jobs/create` | JobController@create | Create form |
| POST | `/jobs` | JobController@store | Store new job |
| GET | `/jobs/{job}` | JobController@show | Job detail |
| GET | `/jobs/{job}/edit` | JobController@edit | Edit form |
| PUT | `/jobs/{job}` | JobController@update | Update job |
| DELETE | `/jobs/{job}` | JobController@destroy | Delete job |
| POST | `/jobs/{job}/remark` | JobController@addRemark | Add remark |
| POST | `/jobs/{job}/mark-invoiced` | JobController@markInvoiced | Mark invoiced |
| PATCH | `/jobs/{job}/order-parts` | JobController@updateOrderParts | Update parts |
| PATCH | `/jobs/{job}/need-part` | JobController@updateNeedPart | Toggle need part |
| PATCH | `/jobs/{job}/work-status` | JobController@updateWorkStatus | Update status |
| POST | `/jobs/bulk-update` | JobController@bulkUpdate | Bulk update |
| GET | `/jobs/{job}/export-pdf` | JobController@exportPdf | Export to PDF |

### Vehicle Routes

| Method | Route | Controller | Purpose |
|--------|-------|------------|---------|
| GET | `/vehicles` | VehicleController@index | List vehicles |
| GET | `/vehicles/create` | VehicleController@create | Create form |
| POST | `/vehicles` | VehicleController@store | Store vehicle |
| GET | `/vehicles/{vehicle}` | VehicleController@show | Vehicle detail |
| GET | `/vehicles/{vehicle}/edit` | VehicleController@edit | Edit form |
| PUT | `/vehicles/{vehicle}` | VehicleController@update | Update vehicle |
| DELETE | `/vehicles/{vehicle}` | VehicleController@destroy | Delete vehicle |
| POST | `/vehicles/{vehicle}/toggle-workshop` | VehicleController@toggleWorkshop | Toggle workshop |
| POST | `/vehicles/bulk-workshop` | VehicleController@bulkUpdateWorkshop | Bulk workshop update |

### Customer Routes

| Method | Route | Controller | Purpose |
|--------|-------|------------|---------|
| GET | `/customers` | CustomerController@index | List customers |
| GET | `/customers/duplicates` | CustomerController@duplicates | View duplicates |
| POST | `/customers/merge` | CustomerController@merge | Merge customers |
| POST | `/customers/merge-batch` | CustomerController@mergeBatch | Batch merge |
| POST | `/customers/dismiss-group` | CustomerController@dismissGroup | Dismiss duplicate |
| GET | `/customers/show` | CustomerController@show | Customer detail |
| GET | `/customers/search` | CustomerController@search | Search autocomplete |

### Report Routes

| Method | Route | Controller | Purpose |
|--------|-------|------------|---------|
| GET | `/reports/uninvoiced` | ReportController@uninvoiced | Uninvoiced report |
| GET | `/reports/invoiced` | ReportController@invoiced | Invoiced report |
| GET | `/reports/needs-parts` | ReportController@needsParts | Needs parts report |
| GET | `/reports/aging` | ReportController@aging | Aging report |
| GET | `/reports/sa-performance` | ReportController@saPerformance | SA performance |
| GET | `/reports/wip-conflicts` | WipConflictReportController@index | WIP conflicts |
| GET | `/reports/orphan-vehicles` | OrphanVehicleReportController@index | Orphan vehicles |
| GET | `/reports/builder` | ReportController@builder | Report builder |
| GET | `/reports/preview` | ReportController@preview | Preview report |
| GET | `/reports/export` | ReportController@export | Export report |
| POST | `/reports/save` | ReportController@saveReport | Save configuration |
| GET | `/reports/{report}/load` | ReportController@loadReport | Load saved |
| DELETE | `/reports/{report}` | ReportController@deleteReport | Delete saved |
| GET | `/reports/export/uninvoiced` | ReportController@exportUninvoiced | Export uninvoiced |
| GET | `/reports/export/invoiced` | ReportController@exportInvoiced | Export invoiced |
| GET | `/reports/export/needs-parts` | ReportController@exportNeedsParts | Export needs parts |

### Import Routes

| Method | Route | Controller | Purpose |
|--------|-------|------------|---------|
| GET | `/imports` | ImportController@index | Import history |
| GET | `/imports/upload` | ImportController@showUploadForm | Upload form |
| POST | `/imports/progress` | ImportController@importProgress | Import progress job |
| POST | `/imports/uninvoiced` | ImportController@importUninvoiced | Import uninvoiced |
| POST | `/imports/invoiced` | ImportController@importInvoiced | Import invoiced |
| GET | `/imports/{import}` | ImportController@show | Import details |

### Admin Routes (prefix: `/admin`)

| Method | Route | Controller | Purpose |
|--------|-------|------------|---------|
| GET | `/admin/users` | UserController@index | User list |
| GET | `/admin/users/{user}/edit` | UserController@edit | Edit user |
| PUT | `/admin/users/{user}` | UserController@update | Update user |
| DELETE | `/admin/users/{user}` | UserController@destroy | Delete user |
| POST | `/admin/users/search-ldap` | UserController@searchLdap | Search LDAP |
| POST | `/admin/users/assign-role` | UserController@assignRole | Assign role |
| GET | `/admin/roles` | RoleController@index | Role list |
| GET | `/admin/roles/{role}/permissions` | RoleController@permissions | Role permissions |
| POST | `/admin/roles/{role}/permissions` | RoleController@updatePermissions | Update permissions |
| GET | `/admin/roles/{role}/fields/{doctype}` | RoleController@fieldPermissions | Field permissions |
| GET | `/admin/backups` | BackupController@index | Backup list |
| POST | `/admin/backups` | BackupController@create | Create backup |
| GET | `/admin/backups/{filename}/download` | BackupController@download | Download backup |
| POST | `/admin/backups/{filename}/restore` | BackupController@restore | Restore backup |
| DELETE | `/admin/backups/{filename}` | BackupController@delete | Delete backup |
| POST | `/admin/backups/schedule` | BackupController@updateSchedule | Update schedule |
| GET | `/admin/data-cleanup` | DataCleanupController@index | Cleanup page |
| POST | `/admin/data-cleanup` | DataCleanupController@cleanup | Execute cleanup |
| GET | `/admin/sessions` | SessionController@index | Session list |
| DELETE | `/admin/sessions/{session}` | SessionController@terminate | Terminate session |
| GET | `/admin/scheduler` | SchedulerController@index | Scheduler settings |
| POST | `/admin/scheduler/run` | SchedulerController@runNow | Run task now |
| GET | `/admin/audit-logs` | AuditLogController@index | Audit logs |
| POST | `/admin/audit-logs/archive` | AuditLogController@archive | Archive logs |

### Customer Portal Routes (prefix: `/customer`)

| Method | Route | Controller | Purpose |
|--------|-------|------------|---------|
| GET | `/customer/login` | CustomerAuthController@showLogin | Customer login |
| POST | `/customer/login` | CustomerAuthController@login | Process login |
| GET | `/customer/` | CustomerDashboardController@dashboard | Dashboard |
| GET | `/customer/jobs` | CustomerDashboardController@jobs | My jobs |
| GET | `/customer/jobs/{job}` | CustomerDashboardController@showJob | Job detail |
| GET | `/customer/vehicles` | CustomerDashboardController@vehicles | My vehicles |
| GET | `/customer/profile` | CustomerDashboardController@profile | Profile |

---

## 2. Controller Methods

### JobController

| Method | Purpose |
|--------|---------|
| `index(Request $request)` | List jobs with filters, pagination |
| `create()` | Show create form |
| `store(StoreJobRequest $request)` | Create new job |
| `show(Job $job)` | Display job detail with timeline, remarks, invoices |
| `edit(Job $job)` | Show edit form |
| `update(UpdateJobRequest $request, Job $job)` | Update job fields |
| `destroy(Job $job)` | Delete job (Admin only) |
| `addRemark(Request $request, Job $job)` | Add timestamped remark |
| `markInvoiced(Request $request, Job $job)` | Mark as invoiced |
| `updateOrderParts(Request $request, Job $job)` | Update Order & Parts section |
| `updateNeedPart(Request $request, Job $job)` | Toggle need_part flag |
| `bulkUpdate(Request $request)` | Bulk status/remark update |
| `kanban(Request $request)` | Kanban board view |
| `updateWorkStatus(Request $request, Job $job)` | AJAX work status update |
| `exportPdf(Job $job)` | Generate PDF export |
| `checkAssignmentAuthorization(Job $job)` | Check SA/Foreman assignment |

### ReportController

| Method | Purpose |
|--------|---------|
| `uninvoiced(Request $request)` | Uninvoiced jobs report |
| `invoiced(Request $request)` | Invoiced jobs report |
| `needsParts(Request $request)` | Needs parts report |
| `aging(Request $request)` | Aging report with color coding |
| `saPerformance(Request $request)` | SA performance metrics |
| `builder()` | Report builder interface |
| `preview(Request $request)` | Preview custom report |
| `export(Request $request)` | Export to Excel/CSV/PDF |
| `saveReport(Request $request)` | Save report configuration |
| `loadReport(SavedReport $report)` | Load saved configuration |
| `exportUninvoiced(Request $request)` | Export uninvoiced to Excel |
| `exportInvoiced(Request $request)` | Export invoiced to Excel |
| `exportNeedsParts(Request $request)` | Export needs parts |
| `getJobColumns()` | Available columns for builder |
| `buildQuery(Request $request)` | Build query from filters |

### ImportController

| Method | Purpose |
|--------|---------|
| `index()` | Import history list |
| `show(Import $import)` | Import details with failed rows |
| `showUploadForm()` | Upload form |
| `importProgress(Request $request)` | Import Progress Job sheet |
| `importUninvoiced(Request $request)` | Import uninvoiced jobs |
| `importInvoiced(Request $request)` | Import invoiced jobs |
| `importBookingSheet(array $rows)` | Process booking sheet |
| `importPdiSheet(array $rows)` | Process PDI sheet |
| `importTowingSheet(array $rows)` | Process towing sheet |
| `sanitizeText(?string $value)` | Clean text values |
| `parseDate(?string $value)` | Parse various date formats |
| `parseAmount(?string $value)` | Parse currency values |
| `cleanupOrphanVehicle(...)` | Handle vehicle plate changes |

### CustomerController

| Method | Purpose |
|--------|---------|
| `index(Request $request)` | List customers from cached summaries |
| `show(Request $request)` | Customer detail with history |
| `search(Request $request)` | Autocomplete search |
| `duplicates()` | View duplicate groups |
| `dismissGroup(Request $request)` | Dismiss false duplicate |
| `merge(Request $request)` | Merge single group |
| `mergeBatch(Request $request)` | Merge multiple groups |
| `detectDuplicateSource(string $name)` | Determine duplicate origin |
| `normalizeForComparison(string $name)` | Normalize for matching |

### DashboardController

| Method | Purpose |
|--------|---------|
| `index()` | Dashboard with cached stats |
| `clearCache()` | Clear dashboard cache |
| `getChartData(...)` | Generate chart datasets |

---

## 3. Model Reference

### Job Model

**Relationships:**
- `vehicle()` - BelongsTo Vehicle
- `remarks()` - HasMany Remark
- `invoices()` - HasMany JobInvoice
- `activities()` - HasMany JobActivity
- `import()` - BelongsTo Import

**Scopes:**
- `scopeUninvoiced($query)` - Where status = uninvoiced
- `scopeInvoiced($query)` - Where status = invoiced
- `scopeNeedsParts($query)` - Where need_part = true

**Accessors:**
- `getTotalInvoiceAmountAttribute()` - Sum of invoice amounts
- `getDepartmentLabelAttribute()` - Human-readable department
- `getTypeSaleLabelAttribute()` - Human-readable type sale
- `getFirstRemarkAttribute()` - Oldest remark
- `getLatestRemarkFromTableAttribute()` - Newest remark

**Methods:**
- `addRemark(string $text, ?string $createdBy, ?int $userId)` - Add remark with notifications
- `markAsInvoiced(string $invoiceNumber)` - Mark job as invoiced
- `notifyAssignedUsers(...)` - Notify SA/Foreman

**Events:**
- Broadcasts `DashboardUpdated` on create/update/delete
- Broadcasts `JobStatusUpdated` on status change
- Broadcasts `RemarkAdded` on new remark

### User Model

**Relationships:**
- `serviceAdvisor()` - HasOne ServiceAdvisor
- `foreman()` - HasOne Foreman
- `sessions()` - HasMany UserSession
- `notifications()` - HasMany Notification
- `roles()` - BelongsToMany Role

**Permission Methods:**
- `canDo(string $doctype, string $action)` - Check permission
- `canRead(string $doctype)` - Shortcut for read
- `canWrite(string $doctype)` - Shortcut for write
- `canReadField(string $doctype, string $field)` - Field read permission
- `canWriteField(string $doctype, string $field)` - Field write permission
- `getRoles()` - Get all user roles
- `hasRole(string $role)` - Check if has role

### Role Model

**Static Methods:**
- `getDocTypes()` - Available DocTypes for permissions
- `getAllPermissions()` - Default permissions matrix

**Methods:**
- `permissions()` - HasMany Permission
- `fieldPermissions()` - HasMany FieldPermission
- `users()` - BelongsToMany User

### Vehicle Model

**Relationships:**
- `jobs()` - HasMany Job

**Scopes:**
- `scopeInWorkshop($query)` - Where is_in_workshop = true

### Notification Model

**Static Methods:**
- `notify(int $userId, string $type, string $title, string $message, ?string $link, string $icon, string $color)` - Create notification

**Types:**
- `TYPE_JOB_ASSIGNED`
- `TYPE_REMARK_ADDED`
- `TYPE_STATUS_CHANGED`
- `TYPE_SYSTEM`

---

## 4. Console Commands

### Job Management

```bash
# Flag stale jobs (jobs over threshold age)
php artisan jobs:flag-stale

# Send stale job notifications
php artisan jobs:notify-stale

# Cleanup old completed jobs
php artisan cleanup:old-jobs --days=365
```

### Customer Data

```bash
# Find duplicate customers (updates duplicate_customer_groups)
php artisan customers:find-duplicates --threshold=80

# Refresh customer summaries cache
php artisan customers:refresh-summaries

# Clean/sanitize customer names
php artisan data:clean-customer-names
```

### Reports

```bash
# Send scheduled reports
php artisan reports:send-scheduled

# Send weekly summary report
php artisan reports:send-weekly
```

### Maintenance

```bash
# Create database backup
php artisan backup:database

# Cleanup inactive sessions
php artisan cleanup:inactive-sessions

# Archive old audit logs
php artisan audit-logs:archive --days=90

# Clean duplicate invoice records
php artisan data:clean-duplicate-invoices
```

---

## 5. Services

### BackupService

Located: `app/Services/BackupService.php`

**Methods:**
- `createBackup()` - Create database backup
- `getBackups()` - List available backups
- `downloadBackup(string $filename)` - Get backup file
- `restoreBackup(string $filename)` - Restore from backup
- `deleteBackup(string $filename)` - Delete backup file
- `pruneOldBackups(int $keepDays)` - Remove old backups

---

## 6. Events & Notifications

### Broadcast Events

| Event | Channel | Purpose |
|-------|---------|---------|
| `DashboardUpdated` | dashboard | Refresh dashboard stats |
| `JobStatusUpdated` | jobs.{id} | Job status changed |
| `RemarkAdded` | jobs.{id} | New remark on job |

### Notification Triggers

| Trigger | Recipients | Type |
|---------|------------|------|
| New remark added | Assigned SA/Foreman | REMARK_ADDED |
| Job assigned | SA/Foreman | JOB_ASSIGNED |
| Status changed | Assigned users | STATUS_CHANGED |
| Stale job detected | SA | SYSTEM |

### Listening for Events (JavaScript)

```javascript
// Dashboard updates
Echo.channel('dashboard')
    .listen('DashboardUpdated', (e) => {
        refreshDashboard();
    });

// Job-specific updates
Echo.channel('jobs.' + jobId)
    .listen('RemarkAdded', (e) => {
        appendRemark(e.remark);
    })
    .listen('JobStatusUpdated', (e) => {
        updateStatus(e.status);
    });
```
