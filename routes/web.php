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
    Route::post('login', [AuthController::class, 'login']);
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
});

// 2FA Challenge (separate from auth middleware)
Route::get('two-factor/challenge', [\App\Http\Controllers\TwoFactorController::class, 'challenge'])->name('2fa.challenge');
Route::post('two-factor/verify', [\App\Http\Controllers\TwoFactorController::class, 'verify'])->name('2fa.verify');

Route::post('logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Protected Routes - All Authenticated Users
Route::middleware('auth')->group(function () {
    // Dashboard - Everyone can see
    Route::get('/', function () {
        return view('dashboard');
    })->name('dashboard');
    
    // Global Search
    Route::get('search', [\App\Http\Controllers\SearchController::class, 'search'])->name('search');
    
    // Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [\App\Http\Controllers\NotificationController::class, 'index'])->name('index');
        Route::post('{notification}/read', [\App\Http\Controllers\NotificationController::class, 'markRead'])->name('read');
        Route::post('mark-all-read', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('mark-all-read');
        Route::delete('{notification}', [\App\Http\Controllers\NotificationController::class, 'destroy'])->name('destroy');
        Route::delete('/', [\App\Http\Controllers\NotificationController::class, 'clearAll'])->name('clear-all');
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
    Route::get('jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
    
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

    // Reports - Everyone can view reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('uninvoiced', [ReportController::class, 'uninvoiced'])->name('uninvoiced');
        Route::get('invoiced', [ReportController::class, 'invoiced'])->name('invoiced');
        Route::get('needs-parts', [ReportController::class, 'needsParts'])->name('needs-parts');
        Route::get('aging', [ReportController::class, 'aging'])->name('aging');
        Route::get('sa-performance', [ReportController::class, 'saPerformance'])->name('sa-performance');
        Route::get('customer-merges', [ReportController::class, 'customerMerges'])->name('customer-merges');
        Route::get('customer-merges/export', [ReportController::class, 'exportCustomerMerges'])->name('customer-merges.export');
        Route::get('wip-conflicts', [\App\Http\Controllers\WipConflictReportController::class, 'index'])->name('wip-conflicts');
        Route::post('wip-conflicts/{job}/resolve', [\App\Http\Controllers\WipConflictReportController::class, 'resolve'])->name('wip-conflicts.resolve');
        Route::get('orphan-vehicles', [\App\Http\Controllers\OrphanVehicleReportController::class, 'index'])->name('orphan-vehicles');
        Route::delete('orphan-vehicles/{vehicle}', [\App\Http\Controllers\OrphanVehicleReportController::class, 'destroy'])->name('orphan-vehicles.destroy');
        Route::delete('orphan-vehicles', [\App\Http\Controllers\OrphanVehicleReportController::class, 'bulkDestroy'])->name('orphan-vehicles.bulk-destroy');
    });

    // Add Remarks - SA, Foreman, Sparepart, Control Tower, Manager, Admin
    Route::middleware('role:sa,foreman,sparepart,control_tower,manager,admin')->group(function () {
        Route::post('jobs/{job}/remark', [JobController::class, 'addRemark'])->name('jobs.add-remark');
        Route::post('jobs/bulk-update', [JobController::class, 'bulkUpdate'])->name('jobs.bulk-update');
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
        Route::put('jobs/{job}', [JobController::class, 'update'])->name('jobs.update');
        Route::post('jobs/{job}/mark-invoiced', [JobController::class, 'markInvoiced'])->name('jobs.mark-invoiced');

        // Vehicles CRUD (except destroy)
        Route::get('vehicles/create', [VehicleController::class, 'create'])->name('vehicles.create');
        Route::post('vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
        Route::get('vehicles/{vehicle}/edit', [VehicleController::class, 'edit'])->name('vehicles.edit');
        Route::put('vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
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

    // Sparepart can update need_part field - Sparepart, Control Tower, Manager, Admin
    Route::middleware('role:sparepart,control_tower,manager,admin')->group(function () {
        Route::patch('jobs/{job}/need-part', [JobController::class, 'updateNeedPart'])->name('jobs.update-need-part');
    });

    // Admin Only - User Management, LDAP Settings, DELETE operations
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        // User Management
        Route::get('users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}/edit', [\App\Http\Controllers\Admin\UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('users.update');
        Route::post('users/search-ldap', [\App\Http\Controllers\Admin\UserController::class, 'searchLdap'])->name('users.search-ldap');
        Route::post('users/assign-role', [\App\Http\Controllers\Admin\UserController::class, 'assignRole'])->name('users.assign-role');

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
        Route::delete('/backups/{filename}', [\App\Http\Controllers\Admin\BackupController::class, 'delete'])->name('backups.destroy');
        Route::post('/backups/schedule', [\App\Http\Controllers\Admin\BackupController::class, 'updateSchedule'])->name('backups.schedule');
        Route::put('ldap/{ldapServer}', [\App\Http\Controllers\LdapServerController::class, 'update'])->name('ldap.update');
        Route::delete('ldap/{ldapServer}', [\App\Http\Controllers\LdapServerController::class, 'destroy'])->name('ldap.destroy');
        Route::get('ldap/{ldapServer}/test', [\App\Http\Controllers\LdapServerController::class, 'testConnection'])->name('ldap.test');

        // Data Cleanup
        Route::get('data-cleanup', [\App\Http\Controllers\Admin\DataCleanupController::class, 'index'])->name('data-cleanup.index');
        Route::post('data-cleanup', [\App\Http\Controllers\Admin\DataCleanupController::class, 'cleanup'])->name('data-cleanup.execute');

        // Session Manager
        Route::get('sessions', [\App\Http\Controllers\Admin\SessionController::class, 'index'])->name('sessions.index');
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
