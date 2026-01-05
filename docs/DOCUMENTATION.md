# Control Tower - Comprehensive Documentation

**Version:** 2.5  
**Last Updated:** January 2026

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Technology Stack](#2-technology-stack)
3. [User Roles & Permissions](#3-user-roles--permissions)
4. [Core Modules](#4-core-modules)
5. [Data Import/Export](#5-data-importexport)
6. [Reports](#6-reports)
7. [Administration](#7-administration)
8. [Security Features](#8-security-features)
9. [Customer Portal](#9-customer-portal)
10. [Console Commands](#10-console-commands)
11. [Database Schema](#11-database-schema)

---

## 1. System Overview

Control Tower is a workshop management system for tracking vehicle service jobs from entry through invoicing. It integrates with DMS (Dealer Management System) via Excel/ODS data imports and provides comprehensive tracking, reporting, and data quality tools.

### Key Capabilities

| Category | Features |
|----------|----------|
| **Job Operations** | End-to-end tracking, Kanban board, bulk actions, PDF export, print-optimized view |
| **Data Integration** | 5 import types with validation preview for DMS synchronization |
| **Reporting** | 12 report types + custom builder + Trends & Comparisons with Excel/PDF export |
| **Customer Experience** | Self-service portal, vehicle tracking, invoice downloads |
| **Security** | 2FA (TOTP), LDAP, session management, audit logging |
| **Automation** | Scheduled reports with email delivery, stale job alerts, duplicate detection |
| **UX Enhancements** | Keyboard shortcuts, global search, recently viewed jobs, dark mode |

---

## 2. Technology Stack

| Component | Technology |
|-----------|------------|
| **Framework** | Laravel 10+ |
| **Database** | MySQL 5.7+ / MariaDB 10.3+ |
| **Frontend** | Blade + Bootstrap 5 + Bootstrap Icons |
| **Excel** | PhpSpreadsheet |
| **PDF** | Dompdf |
| **Real-time** | Laravel Broadcasting (WebSockets) |
| **Auth** | Local + LDAP support |

### Requirements

- PHP 8.1+ with extensions: `pdo_mysql`, `mbstring`, `xml`, `zip`, `gd`, `bcmath`
- Composer 2.x
- Node.js 18+ & npm

---

## 3. User Roles & Permissions

### Role Hierarchy

| Role | Level | Description |
|------|-------|-------------|
| **Admin** | Highest | Full system access including user management and data cleanup |
| **Manager** | High | All operations, master data, audit access |
| **Control Tower** | Medium | Job management, imports, reports, Booking/PDI/Towing |
| **SA (Service Advisor)** | Limited | View assigned jobs, add remarks |
| **Foreman** | Limited | View assigned jobs, add remarks |
| **Sparepart** | Limited | Edit Order & Parts on jobs needing parts |
| **Audit** | Read-only | Access audit logs and data tracker |

### Permission Matrix

| Feature | Admin | Manager | Control Tower | SA | Foreman | Sparepart | Audit |
|---------|:-----:|:-------:|:-------------:|:--:|:-------:|:---------:|:-----:|
| View Dashboard | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| View Jobs | ✓ | ✓ | ✓ | ✓* | ✓* | ✓* | ✓ |
| Create/Edit Jobs | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |
| Delete Jobs | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Add Remarks | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ |
| Edit Order & Parts | ✓ | ✓ | ✓ | ✗ | ✗ | ✓** | ✗ |
| Data Import | ✓ | ✗ | ✓ | ✗ | ✗ | ✗ | ✗ |
| Report Export | ✓ | ✗ | ✓ | ✗ | ✗ | ✗ | ✗ |
| User Management | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Backups | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Audit Logs | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |

*\* SA/Foreman see only jobs where they are assigned*  
*\*\* Sparepart can only edit jobs with `need_part = true`*

### Dynamic Permissions

The system uses an ERPNext-style permission system with:
- **DocType Permissions**: Read/Write/Create/Delete/Export per model
- **Field Permissions**: Control which fields each role can edit

Configure at: **Administration → Role Permissions**

---

## 4. Core Modules

### 4.1 Dashboard

The main dashboard provides:

| Widget | Description |
|--------|-------------|
| **Stats Cards** | Uninvoiced jobs, Invoiced jobs, Needs Parts, Vehicles in Workshop |
| **Work Status Chart** | Pie chart of job work status distribution |
| **7-Day Trend** | Line chart of new vs invoiced jobs |
| **SA Revenue** | Top 5 Service Advisors by projected revenue |
| **Aging Chart** | Jobs by age (< 3d, 3-7d, 7-14d, 14-30d, > 30d) |
| **Recent Jobs** | Latest 5 uninvoiced jobs quick access |
| **Needs Parts** | Latest 5 jobs flagged for parts |
| **Duplicate Alert** | Warning if duplicate customers detected |

**Features:**
- Dashboard data cached for 5 minutes for performance
- Real-time updates via WebSocket broadcasting
- Global search (Ctrl+K) for quick navigation
- Keyboard shortcuts for common actions
- Recently viewed jobs in sidebar

---

### 4.1.1 Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl+K` or `S` | Focus global search |
| `N` | Create new job |
| `G` → `D` | Go to Dashboard |
| `G` → `J` | Go to Jobs |
| `G` → `R` | Go to Reports |
| `G` → `C` | Go to Customers |
| `?` | Show shortcuts help modal |
| `Esc` | Close modal/search |

*Note: Shortcuts are disabled when typing in input fields.*

---

### 4.2 Job Management

#### Job List
- **Filters**: Status, Search (WIP/Plate/Customer), Date range, SA, Foreman, Work Status
- **Views**: Table view with customizable columns, Kanban board
- **Bulk Actions**: Update work status, Add bulk remarks
- **Export**: PDF with selected jobs

#### Job Detail Page

| Section | Contents |
|---------|----------|
| **Header** | WIP, Plate Number, Status badges, Print & PDF export buttons |
| **Customer Info** | Customer name, address (linked to customer detail) |
| **Job Details** | All job fields organized in cards |
| **Order & Parts** | RQ, Order Part MBINA, Lain-lain (editable by Sparepart) |
| **Timeline** | Activity feed with all job events |
| **Remarks** | Chronological remarks with user/role tags |
| **Invoice History** | All invoice/credit note records |

**Print-Optimized View:**
- Dedicated print styles for A4 paper
- Hidden sidebar, buttons, and interactive elements
- Clean typography and proper page breaks
- Print button for quick access

#### Kanban Board
- Drag-drop cards between work status columns
- Visual work-in-progress management
- Quick access to job details

#### Key Job Fields

| Field | Description |
|-------|-------------|
| `job_number` (WIP) | Unique job identifier |
| `work_order_number` | Work order reference |
| `plate_number` | Vehicle plate |
| `customer_name` | Customer name (normalized) |
| `service_advisor` | Assigned SA |
| `foreman` | Assigned foreman |
| `department` | W (Workshop) or B (Body Paint) |
| `job_date` | Job creation date |
| `promise_date` | Promised completion date |
| `deadline` | Hard deadline |
| `total_sales` | Projected job value |
| `work_status` | Current workflow stage |
| `need_part` | Requires spare parts flag |
| `status` | uninvoiced / invoiced |

---

### 4.3 Vehicle Management

| Feature | Description |
|---------|-------------|
| **Vehicle List** | All vehicles with job counts, searchable |
| **Vehicle Detail** | Full info + service history + stats cards |
| **Workshop Status** | Toggle "In Workshop" flag (Control Tower/Admin) |
| **Bulk Update** | Update workshop status for multiple vehicles |
| **Stats** | Total Jobs, Uninvoiced, Projected Sales, Invoiced Sales |

---

### 4.4 Customer Management

| Feature | Description |
|---------|-------------|
| **Customer List** | Aggregated from jobs/vehicles with counts |
| **Customer Detail** | Stats, vehicles list, job history |
| **Search** | Autocomplete customer search |
| **Duplicate Detection** | Automated similarity matching (>80% match) |
| **Merge Duplicates** | Batch merge with canonical name selection |

#### Duplicate Detection Algorithm
- Levenshtein distance comparison (>80% similarity)
- Similar_text PHP function (>80% match)
- Normalization: PT/PT., commas, extra spaces stripped

#### Source Classification
| Source | Meaning | Action |
|--------|---------|--------|
| DMS Import | From Invoice/Uninvoiced import | Fix in main DMS |
| Job Progress Import | User error during import | Train users |
| Manual Entry | Typed incorrectly | Train users |

---

### 4.5 Bookings

Customer appointment scheduling with:
- Entry date, Booking date
- Customer name, WIP, Vehicle type
- Foreman, Service Advisor assignment
- Type of work, Remarks

---

### 4.6 PDI (Pre-Delivery Inspection)

Track new vehicle inspections:
- Date, Customer name
- Chassis number, Engine number
- Type, Colour
- Foreman, WIP, Status, Remarks

---

### 4.7 Towing Records

Track towing services with:
- Date, Customer name
- Vehicle details
- Towing origin/destination
- Status tracking

---

## 5. Data Import/Export

### Import Types

| Type | Purpose | Source Sheet |
|------|---------|--------------|
| **Job Progress** | Create/update uninvoiced jobs | "Progress Job" sheet |
| **Invoiced** | Mark jobs invoiced, create invoice records | Invoiced data export |
| **Booking** | Import customer bookings | "Booking" sheet |
| **PDI** | Pre-Delivery Inspection records | "PDI" sheet |
| **Towing** | Towing service records | "Towing" sheet |

### Import Features

- **Validation Preview**: Preview data before import with error/warning detection
- **Progress Tracking**: Visual progress bar during import
- **History**: Full log with success/fail counts
- **Error Details**: Failed row logging with error messages
- **Auto Backup**: Database backup before imports
- **Data Sanitization**: Customer name cleanup, date parsing

### Import Process

1. Export data from DMS to Excel/ODS
2. Go to **Operations → Import → Upload**
3. Select file and click **Preview** to validate data
4. Review validation results (valid, warnings, errors)
5. Click **Confirm Import** to proceed
6. Monitor progress
7. Review results (success, failed, skipped)

---

## 6. Reports

### Standard Reports

| Report | Description | Access |
|--------|-------------|--------|
| **Uninvoiced Jobs** | All pending jobs with filters | All users |
| **Invoiced Jobs** | Completed jobs with date range | All users |
| **Needs Parts** | Jobs flagged for spare parts | All users |
| **Aging Report** | Jobs by age with color thresholds | All users |
| **SA Performance** | Metrics per Service Advisor | All users |
| **Trends & Comparisons** | Period comparison, SA trends, aging trends | All users |
| **WIP Conflicts** | Duplicate WIP detection | Control Tower+ |
| **Orphan Vehicles** | Vehicles without jobs | Control Tower+ |

### Trends & Comparisons Report

Management insights dashboard with:

| Section | Description |
|---------|-------------|
| **Period Comparison** | Week/Month/Quarter comparison cards (New Jobs, Invoiced, Revenue, Avg Days) |
| **SA Performance Trends** | Line chart showing SA close rates over 6 months |
| **Aging Trend Analysis** | Stacked bar chart of jobs aged >7, >14, >30 days over 4 weeks |
| **Franchise Comparison** | PC vs CV metrics with bar chart and detailed comparison table |

Key metrics tracked:
- Change indicators (↑/↓ with percentage)
- Close rate = (Invoiced / Total) × 100
- Revenue from invoices
- Avg days to invoice

### Report Builder

Custom report creation with:
- **Column Selection**: 40+ available columns
- **Filters**: Multiple filter conditions
- **Sorting**: Custom sort order
- **Save/Load**: Store report configurations
- **Export**: Excel, CSV, PDF

### Export Formats

| Format | Features |
|--------|----------|
| **Excel (.xlsx)** | Formatted, auto-width columns, headers |
| **CSV** | Plain data for system import |
| **PDF** | Styled, color-coded, ready for printing |

### Scheduled Reports

Automated email delivery with full configuration:

| Feature | Description |
|---------|-------------|
| **Report Types** | Uninvoiced, Invoiced, Needs Parts, Aging, Custom |
| **Schedules** | Daily, Weekly, Monthly with time selection |
| **Recipients** | Multiple email addresses per report |
| **Filters** | Apply saved filters to scheduled reports |
| **Format** | Excel or PDF attachment |
| **SMTP** | Configurable mail server settings |
- Configure recipients
- Set schedule (daily, weekly, monthly)
- Select report type
- SMTP integration

---

## 7. Administration

### 7.1 User Management

- Create local users
- Search and import from LDAP
- Assign roles
- View user activity

### 7.2 LDAP Configuration

- Multiple LDAP servers supported
- Connection testing
- User search and import
- Automatic authentication fallback

### 7.3 Role Management

ERPNext-style permission system:
- Create custom roles
- DocType permissions (Read/Write/Create/Delete/Export)
- Field-level permissions
- Permission inheritance

### 7.4 Dropdown Options

Manage dynamic dropdown values:
- Work status options
- Color-coded labels
- Sort order control

### 7.5 Backups

| Feature | Description |
|---------|-------------|
| Manual Backup | Create backup on-demand |
| Scheduled Backup | Daily automated backups |
| Download | Download backup files |
| Restore | Restore from backup |
| Pruning | Auto-delete old backups |

### 7.6 Data Cleanup

Selective table truncation:
- Jobs, Vehicles, Customers
- Bookings, PDI, Towing
- Imports, Audit Logs
- **Requires backup before cleanup**

### 7.7 Session Management

- View active sessions
- Terminate individual sessions
- Bulk session cleanup
- Session timeout settings

### 7.8 Scheduler Management

Control scheduled tasks:
- Enable/disable tasks
- View execution logs
- Run tasks manually
- Configure intervals

### 7.9 Audit Logs

- All data changes tracked
- User, action, old/new values
- IP address, timestamp
- Archive old logs
- Export archives

---

## 8. Security Features

### 8.1 Two-Factor Authentication

- TOTP support (Google Authenticator, Authy)
- Recovery codes
- Per-user enable/disable
- Challenge on login

### 8.2 Rate Limiting

- Login: 5 attempts/minute
- Registration: 3 attempts/minute

### 8.3 Session Security

- Concurrent session tracking
- Terminate other sessions
- Session timeout configuration
- Device/IP tracking

### 8.4 Audit Trail

All changes to auditable models logged:
- Jobs, Vehicles
- Bookings, PDI, Towing
- Customer merges
- Invoices

---

## 9. Customer Portal

Separate login for customers at `/customer`:

| Feature | Description |
|---------|-------------|
| Dashboard | Overview of customer's vehicles/jobs |
| My Jobs | View all service jobs |
| Job Detail | Track status, view timeline |
| Invoice Download | Download PDF invoices |
| My Vehicles | List all registered vehicles |
| Profile | Update contact information |

---

## 10. Console Commands

### Scheduled Tasks

| Command | Purpose | Schedule |
|---------|---------|----------|
| `data:clean-customer-names` | Sanitize customer names | On-demand |
| `data:clean-duplicate-invoices` | Remove duplicate invoice records | On-demand |
| `cleanup:inactive-sessions` | Clear expired sessions | Daily |
| `cleanup:old-jobs` | Archive old job records | Monthly |
| `backup:database` | Create database backup | Daily |
| `customers:find-duplicates` | Detect duplicate customers | Daily |
| `jobs:flag-stale` | Flag jobs needing attention | Daily |
| `jobs:notify-stale` | Send stale job notifications | Daily |
| `customers:refresh-summaries` | Update customer statistics | Daily |
| `reports:send-scheduled` | Send scheduled reports | Per config |
| `reports:send-weekly` | Weekly summary report | Weekly |
| `audit-logs:archive` | Archive old audit logs | Monthly |

### Running Commands

```bash
# Run manually
php artisan jobs:flag-stale

# With options
php artisan customers:find-duplicates --threshold=80

# View all commands
php artisan list
```

---

## 11. Database Schema

### Core Tables

| Table | Purpose | Key Fields |
|-------|---------|------------|
| `jobs` | Service job records | job_number, plate_number, status, customer_name |
| `vehicles` | Vehicle master | plate_number, chassis_number, is_in_workshop |
| `remarks` | Job comments | job_id, remark_text, user_id, created_by |
| `job_invoices` | Invoice records | job_id, invoice_number, amount |
| `job_activities` | Activity timeline | job_id, activity_type, description |

### Import/Export Tables

| Table | Purpose |
|-------|---------|
| `imports` | Import history log |
| `bookings` | Booking records |
| `pdi_records` | PDI records |
| `towing_records` | Towing records |

### User/Auth Tables

| Table | Purpose |
|-------|---------|
| `users` | User accounts |
| `roles` | Role definitions |
| `permissions` | DocType permissions |
| `field_permissions` | Field-level permissions |
| `user_sessions` | Active sessions |
| `ldap_servers` | LDAP configuration |

### Data Quality Tables

| Table | Purpose |
|-------|---------|
| `customer_merge_logs` | Merge history |
| `customer_merge_suggestions` | Detected duplicates |
| `duplicate_customer_groups` | Grouped duplicates |
| `dismissed_duplicate_groups` | Reviewed non-duplicates |
| `customer_summaries` | Cached statistics |

### Admin Tables

| Table | Purpose |
|-------|---------|
| `audit_logs` | Change tracking |
| `audit_log_archives` | Archived logs |
| `backup_logs` | Backup history |
| `backup_schedules` | Backup settings |
| `scheduler_settings` | Task configuration |
| `scheduler_logs` | Task execution logs |
| `notifications` | In-app notifications |
| `dropdown_options` | Dynamic dropdowns |

---

## Related Documentation

- [Workflow Guide](WORKFLOW_GUIDE.md) - Step-by-step operational workflows
- [Function Reference](FUNCTION_REFERENCE.md) - Technical API reference
- [Role Permissions](ROLE_PERMISSIONS.md) - Permission system details
- [Deployment Guide](DEPLOYMENT_GUIDE.md) - Installation and deployment
- [Portainer Deployment](PORTAINER_DEPLOYMENT.md) - Docker deployment
