<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Auth Routes (Guest only)
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1'); // 5 attempts per minute
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:3,1'); // 3 registrations per minute
});

// 2FA Challenge (separate from auth middleware)
Route::get('two-factor/challenge', [\App\Http\Controllers\TwoFactorController::class, 'challenge'])->name('2fa.challenge');
Route::post('two-factor/verify', [\App\Http\Controllers\TwoFactorController::class, 'verify'])->name('2fa.verify');

Route::post('logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Protected Routes - All Authenticated Users
Route::middleware('auth')->group(function () {
    // Dashboard - Everyone can see (with cached stats)
    Route::get('/', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    
    // Dashboard Customization
    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('customize', [\App\Http\Controllers\DashboardSettingsController::class, 'index'])->name('customize');
        Route::post('customize', [\App\Http\Controllers\DashboardSettingsController::class, 'update'])->name('customize.save');
        Route::post('customize/reset', [\App\Http\Controllers\DashboardSettingsController::class, 'reset'])->name('customize.reset');
        Route::post('widgets/reorder', [\App\Http\Controllers\DashboardSettingsController::class, 'reorder'])->name('widgets.reorder');
    });
    
    // Global Search
    Route::get('search', [\App\Http\Controllers\SearchController::class, 'search'])->name('search');
    
    // Help Center
    Route::prefix('help')->name('help.')->group(function () {
        Route::get('/', [\App\Http\Controllers\HelpController::class, 'index'])->name('index');
        Route::get('{slug}', [\App\Http\Controllers\HelpController::class, 'show'])->name('show');
    });
    
    // Profile / Password Change
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ProfileController::class, 'index'])->name('index');
        Route::put('password', [\App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('password');
    });
    
    // Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [\App\Http\Controllers\NotificationController::class, 'index'])->name('index');
        Route::post('{notification}/read', [\App\Http\Controllers\NotificationController::class, 'markRead'])->name('read');
        Route::post('mark-all-read', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('mark-all-read');
        Route::delete('{notification}', [\App\Http\Controllers\NotificationController::class, 'destroy'])->name('destroy');
        Route::delete('/', [\App\Http\Controllers\NotificationController::class, 'clearAll'])->name('clear-all');
    });
    
    // Announcement Dismiss (for all users)
    Route::post('announcements/{announcement}/dismiss', [\App\Http\Controllers\AnnouncementController::class, 'dismissAjax'])->name('announcements.dismiss');
    
    // Push Subscriptions (PWA)
    Route::prefix('push')->name('push.')->group(function () {
        Route::post('subscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'store'])->name('subscribe');
        Route::delete('unsubscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'destroy'])->name('unsubscribe');
        Route::get('vapid-public-key', [\App\Http\Controllers\PushSubscriptionController::class, 'vapidPublicKey'])->name('vapid');
    });
    
    // Two-Factor Authentication
    Route::prefix('two-factor')->name('2fa.')->group(function () {
        Route::get('/', [\App\Http\Controllers\TwoFactorController::class, 'index'])->name('index');
        Route::get('enable', [\App\Http\Controllers\TwoFactorController::class, 'enable'])->name('enable');
        Route::post('confirm', [\App\Http\Controllers\TwoFactorController::class, 'confirm'])->name('confirm');
        Route::delete('disable', [\App\Http\Controllers\TwoFactorController::class, 'disable'])->name('disable');
        Route::post('regenerate-codes', [\App\Http\Controllers\TwoFactorController::class, 'regenerateCodes'])->name('regenerate-codes');
        Route::delete('sessions/{session}', [\App\Http\Controllers\TwoFactorController::class, 'terminateSession'])->name('terminate-session');
        Route::post('terminate-other-sessions', [\App\Http\Controllers\TwoFactorController::class, 'terminateOtherSessions'])->name('terminate-other-sessions');
    });

    // User Preferences - Everyone can save their own preferences
    Route::post('preferences/columns', [PreferenceController::class, 'storeColumns'])->name('preferences.columns');

    // Jobs - View for everyone, edit restricted by controller
    Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::get('jobs/kanban', [JobController::class, 'kanban'])->name('jobs.kanban');
    Route::get('jobs/{job}', [JobController::class, 'show'])->name('jobs.show')->whereNumber('job');
    Route::patch('jobs/{job}/work-status', [JobController::class, 'updateWorkStatus'])->name('jobs.update-work-status');
    Route::get('jobs/{job}/export-pdf', [JobController::class, 'exportPdf'])->name('jobs.export-pdf');
    
    // Vehicles - View for everyone
    Route::get('vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
    Route::get('vehicles/{vehicle}', [VehicleController::class, 'show'])->name('vehicles.show');

    // Customers - View for everyone
    Route::get('customers', [\App\Http\Controllers\CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/duplicates', [\App\Http\Controllers\CustomerController::class, 'duplicates'])->name('customers.duplicates');
    Route::post('customers/merge', [\App\Http\Controllers\CustomerController::class, 'merge'])->name('customers.merge');
    Route::post('customers/merge-batch', [\App\Http\Controllers\CustomerController::class, 'mergeBatch'])->name('customers.merge-batch');
    Route::post('customers/dismiss-group', [\App\Http\Controllers\CustomerController::class, 'dismissGroup'])->name('customers.dismiss-group');
    Route::get('customers/show', [\App\Http\Controllers\CustomerController::class, 'show'])->name('customers.show');
    Route::get('customers/search', [\App\Http\Controllers\CustomerController::class, 'search'])->name('customers.search');

    // DMS Customers - Direct customer table (not job-based summaries)
    Route::get('dms-customers', [\App\Http\Controllers\DmsCustomerController::class, 'index'])->name('dms-customers.index');
    Route::get('dms-customers/{customer}', [\App\Http\Controllers\DmsCustomerController::class, 'show'])->name('dms-customers.show');

    // Reports - Everyone can view reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('uninvoiced', [ReportController::class, 'uninvoiced'])->name('uninvoiced');
        Route::get('invoiced', [ReportController::class, 'invoiced'])->name('invoiced');
        Route::get('needs-parts', [ReportController::class, 'needsParts'])->name('needs-parts');
        Route::get('aging', [ReportController::class, 'aging'])->name('aging');
        Route::get('sa-performance', [ReportController::class, 'saPerformance'])->name('sa-performance');
        Route::get('trends', [\App\Http\Controllers\TrendsController::class, 'index'])->name('trends');
        Route::get('wip-conflicts', [\App\Http\Controllers\WipConflictReportController::class, 'index'])->name('wip-conflicts');
        Route::post('wip-conflicts/{job}/resolve', [\App\Http\Controllers\WipConflictReportController::class, 'resolve'])->name('wip-conflicts.resolve');
        Route::get('orphan-vehicles', [\App\Http\Controllers\OrphanVehicleReportController::class, 'index'])->name('orphan-vehicles');
        Route::delete('orphan-vehicles/{vehicle}', [\App\Http\Controllers\OrphanVehicleReportController::class, 'destroy'])->name('orphan-vehicles.destroy');
        Route::delete('orphan-vehicles', [\App\Http\Controllers\OrphanVehicleReportController::class, 'bulkDestroy'])->name('orphan-vehicles.bulk-destroy');
    });

    // Add Remarks - SA, Foreman, Sparepart, Control Tower, Manager, Admin
    Route::middleware('role:sa,foreman,sparepart,control_tower,manager,admin')->group(function () {
        Route::post('jobs/{job}/remark', [JobController::class, 'addRemark'])->name('jobs.add-remark');
        Route::delete('remarks/{remark}', [JobController::class, 'deleteRemark'])->name('remarks.destroy');
        Route::post('jobs/bulk-update', [JobController::class, 'bulkUpdate'])->name('jobs.bulk-update');
        Route::get('api/users/search', [JobController::class, 'searchUsers'])->name('api.users.search');
    });

    // Sparepart can update Order & Parts on jobs that need parts
    Route::middleware('role:sparepart,control_tower,manager,admin')->group(function () {
        Route::patch('jobs/{job}/order-parts', [JobController::class, 'updateOrderParts'])->name('jobs.update-order-parts');
    });

    // Edit Operations - Control Tower, Manager, Admin (NO DELETE)
    Route::middleware('role:control_tower,manager,admin')->group(function () {
        // Jobs CRUD (except index/show which are public, and destroy which is admin-only)
        Route::get('jobs/create', [JobController::class, 'create'])->name('jobs.create');
        Route::post('jobs', [JobController::class, 'store'])->name('jobs.store');
        Route::get('jobs/{job}/edit', [JobController::class, 'edit'])->name('jobs.edit');

        Route::post('jobs/{job}/mark-invoiced', [JobController::class, 'markInvoiced'])->name('jobs.mark-invoiced');

        // Vehicles CRUD (except destroy and workshop toggle)
        Route::get('vehicles/create', [VehicleController::class, 'create'])->name('vehicles.create');
        Route::post('vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
        Route::get('vehicles/{vehicle}/edit', [VehicleController::class, 'edit'])->name('vehicles.edit');
        Route::put('vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
    });

    // Workshop Toggle - Control Tower and Admin only (NOT manager)
    Route::middleware('role:control_tower,admin')->group(function () {
        Route::post('vehicles/{vehicle}/toggle-workshop', [VehicleController::class, 'toggleWorkshop'])->name('vehicles.toggle-workshop');
        Route::post('vehicles/bulk-workshop', [VehicleController::class, 'bulkUpdateWorkshop'])->name('vehicles.bulk-workshop');

        // Bookings, PDI, Towing (except destroy)
        Route::resource('bookings', \App\Http\Controllers\BookingController::class)->except(['destroy']);
        Route::resource('pdi-records', \App\Http\Controllers\PdiRecordController::class)->except(['destroy']);
        Route::resource('towing-records', \App\Http\Controllers\TowingRecordController::class)->except(['destroy']);

        // Master Data (except destroy)
        Route::resource('service-advisors', \App\Http\Controllers\ServiceAdvisorController::class)->except(['destroy']);
        Route::resource('foremen', \App\Http\Controllers\ForemanController::class)->except(['destroy']);



        // Imports
        Route::prefix('imports')->name('imports.')->group(function () {
            Route::get('/', [ImportController::class, 'index'])->name('index');
            Route::get('upload', [ImportController::class, 'showUploadForm'])->name('upload');
            Route::post('preview', [ImportController::class, 'preview'])->name('preview');
            Route::post('confirm', [ImportController::class, 'confirmImport'])->name('confirm');
            Route::post('progress', [ImportController::class, 'importProgress'])->name('progress');
            Route::post('uninvoiced', [ImportController::class, 'importUninvoiced'])->name('uninvoiced');
            Route::post('invoiced', [ImportController::class, 'importInvoiced'])->name('invoiced');
            Route::get('{import}', [ImportController::class, 'show'])->name('show'); // Must be last!
        });

        // Report Exports
        Route::get('reports/export/uninvoiced', [ReportController::class, 'exportUninvoiced'])->name('reports.export-uninvoiced');
        Route::get('reports/export/needs-parts', [ReportController::class, 'exportNeedsParts'])->name('reports.export-needs-parts');
        Route::get('reports/export/invoiced', [ReportController::class, 'exportInvoiced'])->name('reports.export-invoiced');
        
        // Report Builder
        Route::get('reports/builder', [ReportController::class, 'builder'])->name('reports.builder');
        Route::get('reports/preview', [ReportController::class, 'preview'])->name('reports.preview');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::post('reports/save', [ReportController::class, 'saveReport'])->name('reports.save');
        Route::get('reports/{report}/load', [ReportController::class, 'loadReport'])->name('reports.load');
        Route::delete('reports/{report}', [ReportController::class, 'deleteReport'])->name('reports.delete');
    });

    // Part Tracking & Job Updates - Foreman, Sparepart, Control Tower, Manager, Admin
    Route::middleware('role:foreman,sparepart,control_tower,manager,admin')->group(function () {
        Route::put('jobs/{job}', [JobController::class, 'update'])->name('jobs.update');
        Route::patch('jobs/{job}/need-part', [JobController::class, 'updateNeedPart'])->name('jobs.update-need-part');

        // Part Orders Management
        Route::prefix('parts')->name('parts.')->group(function () {
            Route::get('kanban', [\App\Http\Controllers\PartOrderController::class, 'kanban'])->name('kanban');
            Route::get('summary', [\App\Http\Controllers\PartOrderController::class, 'summary'])->name('summary');
            Route::get('job/{job}', [\App\Http\Controllers\PartOrderController::class, 'forJob'])->name('for-job');
        });
        Route::resource('part-orders', \App\Http\Controllers\PartOrderController::class);
        Route::post('part-orders/{partOrder}/status', [\App\Http\Controllers\PartOrderController::class, 'updateStatus'])->name('part-orders.update-status');
        Route::post('part-orders/create-from-job', [\App\Http\Controllers\PartOrderController::class, 'createFromJob'])->name('part-orders.create-from-job');
    });

    // Admin Only - User Management, LDAP Settings, DELETE operations
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        // User Management
        Route::get('users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [\App\Http\Controllers\Admin\UserController::class, 'create'])->name('users.create');
        Route::post('users', [\App\Http\Controllers\Admin\UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [\App\Http\Controllers\Admin\UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('users.update');
        Route::post('users/search-ldap', [\App\Http\Controllers\Admin\UserController::class, 'searchLdap'])->name('users.search-ldap');
        Route::post('users/assign-role', [\App\Http\Controllers\Admin\UserController::class, 'assignRole'])->name('users.assign-role');
        Route::delete('users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('users.destroy');
        Route::post('users/{user}/reset-password', [\App\Http\Controllers\Admin\UserController::class, 'resetPassword'])->name('users.reset-password');

        // LDAP Settings
        Route::get('ldap', [\App\Http\Controllers\LdapServerController::class, 'index'])->name('ldap.index');
        Route::get('ldap/create', [\App\Http\Controllers\LdapServerController::class, 'create'])->name('ldap.create');
        Route::post('ldap', [\App\Http\Controllers\LdapServerController::class, 'store'])->name('ldap.store');
        Route::get('ldap/{ldapServer}/edit', [\App\Http\Controllers\LdapServerController::class, 'edit'])->name('ldap.edit');
        
        // Backup routes
        Route::get('/backups', [\App\Http\Controllers\Admin\BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [\App\Http\Controllers\Admin\BackupController::class, 'create'])->name('backups.create');
        Route::get('/backups/{filename}/download', [\App\Http\Controllers\Admin\BackupController::class, 'download'])->name('backups.download');
        Route::post('/backups/{filename}/restore', [\App\Http\Controllers\Admin\BackupController::class, 'restore'])->name('backups.restore');
        Route::post('/backups/restore-file', [\App\Http\Controllers\Admin\BackupController::class, 'restoreFromFile'])->name('backups.restore-file');
        Route::delete('/backups/{filename}', [\App\Http\Controllers\Admin\BackupController::class, 'delete'])->name('backups.destroy');
        Route::post('/backups/schedule', [\App\Http\Controllers\Admin\BackupController::class, 'updateSchedule'])->name('backups.schedule');
        Route::post('/backups/delete-batch', [\App\Http\Controllers\Admin\BackupController::class, 'deleteBatch'])->name('backups.delete-batch');
        Route::post('/backups/prune', [\App\Http\Controllers\Admin\BackupController::class, 'prune'])->name('backups.prune');
        Route::put('ldap/{ldapServer}', [\App\Http\Controllers\LdapServerController::class, 'update'])->name('ldap.update');
        Route::delete('ldap/{ldapServer}', [\App\Http\Controllers\LdapServerController::class, 'destroy'])->name('ldap.destroy');
        Route::get('ldap/{ldapServer}/test', [\App\Http\Controllers\LdapServerController::class, 'testConnection'])->name('ldap.test');

        // Data Cleanup
        Route::get('data-cleanup', [\App\Http\Controllers\Admin\DataCleanupController::class, 'index'])->name('data-cleanup.index');
        Route::post('data-cleanup', [\App\Http\Controllers\Admin\DataCleanupController::class, 'cleanup'])->name('data-cleanup.execute');
        Route::post('data-cleanup/reassign', [\App\Http\Controllers\Admin\DataCleanupController::class, 'reassign'])->name('data-cleanup.reassign');
        Route::post('data-cleanup/sanitize-addresses', [\App\Http\Controllers\Admin\DataCleanupController::class, 'sanitizeAddresses'])->name('data-cleanup.sanitize-addresses');
        Route::get('data-cleanup/sanitize-history', [\App\Http\Controllers\Admin\DataCleanupController::class, 'sanitizeHistory'])->name('data-cleanup.sanitize-history');

        // Session Manager
        Route::get('sessions', [\App\Http\Controllers\Admin\SessionController::class, 'index'])->name('sessions.index');
        Route::post('sessions/settings', [\App\Http\Controllers\Admin\SessionController::class, 'updateSettings'])->name('sessions.settings');
        Route::post('sessions/cleanup', [\App\Http\Controllers\Admin\SessionController::class, 'cleanup'])->name('sessions.cleanup');
        Route::delete('sessions/{session}', [\App\Http\Controllers\Admin\SessionController::class, 'terminate'])->name('sessions.terminate');
        Route::delete('sessions/user/{user}', [\App\Http\Controllers\Admin\SessionController::class, 'terminateUser'])->name('sessions.terminate-user');

        // Dropdown Options Management
        Route::get('dropdowns', [\App\Http\Controllers\DropdownController::class, 'index'])->name('dropdowns.index');
        Route::get('dropdowns/create', [\App\Http\Controllers\DropdownController::class, 'create'])->name('dropdowns.create');
        Route::post('dropdowns', [\App\Http\Controllers\DropdownController::class, 'store'])->name('dropdowns.store');
        Route::get('dropdowns/{dropdown}/edit', [\App\Http\Controllers\DropdownController::class, 'edit'])->name('dropdowns.edit');
        Route::put('dropdowns/{dropdown}', [\App\Http\Controllers\DropdownController::class, 'update'])->name('dropdowns.update');
        Route::delete('dropdowns/{dropdown}', [\App\Http\Controllers\DropdownController::class, 'destroy'])->name('dropdowns.destroy');
        Route::post('dropdowns/order', [\App\Http\Controllers\DropdownController::class, 'updateOrder'])->name('dropdowns.order');

        // Report Settings
        Route::get('report-settings', [\App\Http\Controllers\Admin\ReportSettingsController::class, 'index'])->name('report-settings.index');
        Route::put('report-settings/{report}', [\App\Http\Controllers\Admin\ReportSettingsController::class, 'updateReport'])->name('report-settings.update');
        Route::post('report-settings/{report}/recipients', [\App\Http\Controllers\Admin\ReportSettingsController::class, 'addRecipient'])->name('report-settings.add-recipient');
        Route::post('report-settings/{report}/recipients/remove', [\App\Http\Controllers\Admin\ReportSettingsController::class, 'removeRecipient'])->name('report-settings.remove-recipient');
        Route::post('report-settings/{report}/send', [\App\Http\Controllers\Admin\ReportSettingsController::class, 'sendNow'])->name('report-settings.send-now');
        Route::put('report-settings/smtp', [\App\Http\Controllers\Admin\ReportSettingsController::class, 'updateSmtp'])->name('report-settings.smtp');

        // Announcements (Broadcast)
        Route::get('announcements', [\App\Http\Controllers\AnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('announcements/create', [\App\Http\Controllers\AnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('announcements', [\App\Http\Controllers\AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('announcements/{announcement}/edit', [\App\Http\Controllers\AnnouncementController::class, 'edit'])->name('announcements.edit');
        Route::put('announcements/{announcement}', [\App\Http\Controllers\AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('announcements/{announcement}', [\App\Http\Controllers\AnnouncementController::class, 'destroy'])->name('announcements.destroy');
        Route::post('announcements/{announcement}/resend', [\App\Http\Controllers\AnnouncementController::class, 'resend'])->name('announcements.resend');

        // Scheduled Reports
        Route::resource('scheduled-reports', \App\Http\Controllers\Admin\ScheduledReportController::class);
        Route::patch('scheduled-reports/{scheduledReport}/toggle', [\App\Http\Controllers\Admin\ScheduledReportController::class, 'toggle'])->name('scheduled-reports.toggle');
        Route::post('scheduled-reports/{scheduledReport}/send', [\App\Http\Controllers\Admin\ScheduledReportController::class, 'sendNow'])->name('scheduled-reports.send');
        Route::post('report-settings/smtp/test', [\App\Http\Controllers\Admin\ReportSettingsController::class, 'testSmtp'])->name('report-settings.test-smtp');

        // DMS Import
        Route::get('dms-import', [\App\Http\Controllers\DmsImportController::class, 'index'])->name('dms-import.index');
        Route::post('dms-import/customers', [\App\Http\Controllers\DmsImportController::class, 'importCustomers'])->name('dms-import.customers');
        Route::post('dms-import/vehicles', [\App\Http\Controllers\DmsImportController::class, 'importVehicles'])->name('dms-import.vehicles');

        // Role Management
        Route::get('roles', [\App\Http\Controllers\Admin\RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/create', [\App\Http\Controllers\Admin\RoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [\App\Http\Controllers\Admin\RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}/edit', [\App\Http\Controllers\Admin\RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [\App\Http\Controllers\Admin\RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [\App\Http\Controllers\Admin\RoleController::class, 'destroy'])->name('roles.destroy');
        Route::get('roles/{role}/permissions', [\App\Http\Controllers\Admin\RoleController::class, 'permissions'])->name('roles.permissions');
        Route::post('roles/{role}/permissions', [\App\Http\Controllers\Admin\RoleController::class, 'updatePermissions'])->name('roles.update-permissions');
        Route::get('roles/{role}/fields/{doctype}', [\App\Http\Controllers\Admin\RoleController::class, 'fieldPermissions'])->name('roles.field-permissions');
        Route::post('roles/{role}/fields/{doctype}', [\App\Http\Controllers\Admin\RoleController::class, 'updateFieldPermissions'])->name('roles.update-field-permissions');

        // Scheduler Management
        Route::get('scheduler', [\App\Http\Controllers\Admin\SchedulerController::class, 'index'])->name('scheduler.index');
        Route::post('scheduler/run', [\App\Http\Controllers\Admin\SchedulerController::class, 'runNow'])->name('scheduler.run');
        Route::patch('scheduler/{setting}/toggle', [\App\Http\Controllers\Admin\SchedulerController::class, 'toggle'])->name('scheduler.toggle');
        Route::put('scheduler/{setting}', [\App\Http\Controllers\Admin\SchedulerController::class, 'update'])->name('scheduler.update');
        Route::get('scheduler/logs', [\App\Http\Controllers\Admin\SchedulerController::class, 'logs'])->name('scheduler.logs');
        Route::delete('scheduler/logs/clear', [\App\Http\Controllers\Admin\SchedulerController::class, 'clearLogs'])->name('scheduler.clear-logs');

        // Audit Log Management
        Route::get('audit-logs', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/archives', [\App\Http\Controllers\Admin\AuditLogController::class, 'archives'])->name('audit-logs.archives');
        Route::post('audit-logs/archive', [\App\Http\Controllers\Admin\AuditLogController::class, 'archive'])->name('audit-logs.archive');
        Route::post('audit-logs/settings', [\App\Http\Controllers\Admin\AuditLogController::class, 'updateSettings'])->name('audit-logs.settings');
        Route::post('audit-logs/clear-archives', [\App\Http\Controllers\Admin\AuditLogController::class, 'clearArchives'])->name('audit-logs.clear-archives');
        Route::get('audit-logs/export', [\App\Http\Controllers\Admin\AuditLogController::class, 'exportArchives'])->name('audit-logs.export');

        // Customer Alias Management (for linking job customers to DMS customers)
        Route::get('customer-aliases', [\App\Http\Controllers\Admin\CustomerAliasController::class, 'index'])->name('customer-aliases.index');
        Route::post('customer-aliases', [\App\Http\Controllers\Admin\CustomerAliasController::class, 'store'])->name('customer-aliases.store');
        Route::post('customer-aliases/link-direct', [\App\Http\Controllers\Admin\CustomerAliasController::class, 'linkDirect'])->name('customer-aliases.link-direct');
        Route::post('customer-aliases/bulk-link', [\App\Http\Controllers\Admin\CustomerAliasController::class, 'bulkLink'])->name('customer-aliases.bulk-link');
        Route::delete('customer-aliases/{alias}', [\App\Http\Controllers\Admin\CustomerAliasController::class, 'destroy'])->name('customer-aliases.destroy');
        Route::get('customer-aliases/suggest', [\App\Http\Controllers\Admin\CustomerAliasController::class, 'suggest'])->name('customer-aliases.suggest');

        // DMS Import
        Route::get('dms-import', [\App\Http\Controllers\DmsImportController::class, 'index'])->name('dms-import.index');
        Route::post('dms-import/customers', [\App\Http\Controllers\DmsImportController::class, 'importCustomers'])->name('dms-import.customers');
        Route::post('dms-import/vehicles', [\App\Http\Controllers\DmsImportController::class, 'importVehicles'])->name('dms-import.vehicles');
    });

    // Delete operations - Admin only (outside prefix to keep normal route names)
    Route::middleware('role:admin')->group(function () {
        Route::delete('jobs/{job}', [JobController::class, 'destroy'])->name('jobs.destroy');
        Route::delete('vehicles/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
        Route::delete('bookings/{booking}', [\App\Http\Controllers\BookingController::class, 'destroy'])->name('bookings.destroy');
        Route::delete('pdi-records/{pdi_record}', [\App\Http\Controllers\PdiRecordController::class, 'destroy'])->name('pdi-records.destroy');
        Route::delete('towing-records/{towing_record}', [\App\Http\Controllers\TowingRecordController::class, 'destroy'])->name('towing-records.destroy');
        Route::delete('service-advisors/{service_advisor}', [\App\Http\Controllers\ServiceAdvisorController::class, 'destroy'])->name('service-advisors.destroy');
        Route::delete('foremen/{foreman}', [\App\Http\Controllers\ForemanController::class, 'destroy'])->name('foremen.destroy');
    });


    // Finance Kanban (Invoice-based) - Finance, Control Tower, Manager, Admin
    Route::middleware('role:finance,control_tower,manager,admin')->group(function () {
        Route::get('finance/kanban', [\App\Http\Controllers\FinanceController::class, 'kanban'])->name('finance.kanban');
        Route::post('finance/invoices/{invoice}/status', [\App\Http\Controllers\FinanceController::class, 'updateStatus'])->name('finance.invoice.update-status');
        Route::post('finance/invoices/{invoice}/payment', [\App\Http\Controllers\FinanceController::class, 'recordPayment'])->name('finance.invoice.payment');
    });

    // Audit - Admin and Audit role
    Route::middleware('role:audit,admin')->group(function () {
        Route::get('audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('tracker', [\App\Http\Controllers\TrackerController::class, 'index'])->name('tracker.index');
    });
});

// ============================================
// CUSTOMER PORTAL ROUTES
// ============================================

// Customer Auth (Guest)
Route::prefix('customer')->name('customer.')->group(function () {
    Route::middleware('guest:customer')->group(function () {
        Route::get('login', [\App\Http\Controllers\Customer\CustomerAuthController::class, 'showLogin'])->name('login');
        Route::post('login', [\App\Http\Controllers\Customer\CustomerAuthController::class, 'login']);
        Route::get('register', [\App\Http\Controllers\Customer\CustomerAuthController::class, 'showRegister'])->name('register');
        Route::post('register', [\App\Http\Controllers\Customer\CustomerAuthController::class, 'register']);
    });
    
    Route::post('logout', [\App\Http\Controllers\Customer\CustomerAuthController::class, 'logout'])->name('logout');
    
    // Customer Protected Routes
    Route::middleware('auth:customer')->group(function () {
        Route::get('/', [\App\Http\Controllers\Customer\CustomerDashboardController::class, 'dashboard'])->name('dashboard');
        Route::get('jobs', [\App\Http\Controllers\Customer\CustomerDashboardController::class, 'jobs'])->name('jobs');
        Route::get('jobs/{job}', [\App\Http\Controllers\Customer\CustomerDashboardController::class, 'showJob'])->name('jobs.show');
        Route::get('jobs/{job}/invoice', [\App\Http\Controllers\Customer\CustomerDashboardController::class, 'downloadInvoice'])->name('jobs.invoice');
        Route::get('vehicles', [\App\Http\Controllers\Customer\CustomerDashboardController::class, 'vehicles'])->name('vehicles');
        Route::get('profile', [\App\Http\Controllers\Customer\CustomerDashboardController::class, 'profile'])->name('profile');
        Route::put('profile', [\App\Http\Controllers\Customer\CustomerDashboardController::class, 'updateProfile'])->name('profile.update');
    });
});
