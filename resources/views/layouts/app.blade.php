<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#0d6efd">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Control Tower">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Control Tower">
    <meta name="description" content="Track and manage workshop jobs, invoicing, and operations">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icon-192.png') }}">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/icon-16.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/icon-32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/icon-192.png') }}">
    
    <title>@yield('title', 'Control Tower') - Uninvoiced Job Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/custom.css') }}?v={{ filemtime(public_path('css/custom.css')) }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="{{ isset($importantAnnouncements) && $importantAnnouncements->count() > 0 ? 'has-ticker' : '' }}">
    
    @if(isset($importantAnnouncements) && $importantAnnouncements->count() > 0)
    <!-- Important Announcements Ticker -->
    <div class="news-ticker-bar">
        <div class="news-ticker-label">
            <i class="bi bi-megaphone-fill me-2"></i> IMPORTANT
        </div>
        <div class="news-ticker-content-wrapper">
            @for($i = 0; $i < 2; $i++)
            <div class="news-ticker-track">
                @foreach($importantAnnouncements as $announcement)
                <div class="news-ticker-item">
                    <span class="opacity-75 me-2" style="font-size: 0.85em">[{{ $announcement->created_at->format('d/m H:i') }}]</span>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#tickerModal{{ $announcement->id }}">
                        <strong>{{ $announcement->title }}:</strong> {{ \Illuminate\Support\Str::limit(html_entity_decode(strip_tags($announcement->content)), 100) }}
                    </a>
                </div>
                @endforeach
            </div>
            @endfor
        </div>
    </div>
    @endif
    
    <nav class="sidebar d-flex flex-column">
        <div class="brand d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <img src="{{ asset('images/logo.png') }}" alt="Hartono Group" style="height: 36px; width: auto; margin-right: 8px;">
                <span>Control Tower</span>
            </div>
            @auth
            @php
                $unreadNotifications = \App\Models\Notification::where('user_id', auth()->id())->unread()->count();
            @endphp
            <div class="dropdown">
                <button class="btn btn-link text-white p-0 position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
                    <i class="bi bi-bell-fill fs-5"></i>
                    @if($unreadNotifications > 0)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                        {{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}
                    </span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow" style="width: 320px; max-height: 400px; overflow-y: auto;" aria-labelledby="notificationDropdown">
                    <div class="dropdown-header d-flex justify-content-between align-items-center py-2">
                        <span><i class="bi bi-bell me-1"></i>Notifications</span>
                        @if($unreadNotifications > 0)
                        <form action="{{ route('notifications.mark-all-read') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-link btn-sm p-0 text-muted">Mark all read</button>
                        </form>
                        @endif
                        <button id="enablePushBtn" class="btn btn-link btn-sm p-0 text-primary d-none" onclick="enablePushNotifications()">
                            <i class="bi bi-bell-fill me-1"></i>Enable Push
                        </button>
                    </div>
                    <div class="dropdown-divider m-0"></div>
                    <div id="notificationList">
                        @php
                            $recentNotifications = \App\Models\Notification::where('user_id', auth()->id())
                                ->orderBy('created_at', 'desc')
                                ->take(10)
                                ->get();
                        @endphp
                        @forelse($recentNotifications as $notification)
                        <a href="{{ route('notifications.read', $notification) }}" 
                           class="dropdown-item py-2 {{ !$notification->isRead() ? 'bg-light' : '' }}" 
                           onclick="event.preventDefault(); document.getElementById('notif-form-{{ $notification->id }}').submit();">
                            <div class="d-flex align-items-start">
                                <div class="me-2">
                                    <span class="badge bg-{{ $notification->color }} rounded-circle p-2">
                                        <i class="bi bi-{{ $notification->icon }}"></i>
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold small">{{ $notification->title }}</div>
                                    <div class="text-muted small text-truncate" style="max-width: 220px;">{{ $notification->message }}</div>
                                    <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
                                </div>
                                @if(!$notification->isRead())
                                <span class="badge bg-primary rounded-pill">New</span>
                                @endif
                            </div>
                        </a>
                        <form id="notif-form-{{ $notification->id }}" action="{{ route('notifications.read', $notification) }}" method="POST" class="d-none">
                            @csrf
                        </form>
                        @empty
                        <div class="dropdown-item text-center text-muted py-4">
                            <i class="bi bi-bell-slash fs-3 d-block mb-2 opacity-50"></i>
                            No notifications
                        </div>
                        @endforelse
                    </div>
                    @if($recentNotifications->count() > 0)
                    <div class="dropdown-divider m-0"></div>
                    <a href="{{ route('notifications.index') }}" class="dropdown-item text-center text-primary small py-2">
                        View all notifications
                    </a>
                    @endif
                </div>
            </div>
            @endauth
        </div>

        <div class="flex-grow-1 overflow-auto">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                        <i class="bi bi-grid-1x2-fill"></i> Dashboard
                    </a>
                </li>
                
                @php
                    $isJobsActive = request()->routeIs('jobs.*');
                    $isBookingsActive = request()->routeIs('bookings.*') || request()->routeIs('pdi-records.*') || request()->routeIs('towing-records.*');
                    $isReportsActive = request()->routeIs('reports.*');
                    $isImportActive = request()->routeIs('imports.*');
                    $isAdminActive = request()->routeIs('admin.*');
                    $isMasterDataActive = request()->routeIs('service-advisors.*') || request()->routeIs('foremen.*') || request()->routeIs('vehicles.*') || request()->routeIs('customers.*');
                @endphp

                {{-- Jobs Menu --}}
                @php
                    $isJobsActive = request()->routeIs('jobs.*');
                @endphp
                <div class="nav-section {{ $isJobsActive ? '' : 'collapsed' }}" data-bs-toggle="collapse" data-bs-target="#jobsMenu" aria-expanded="{{ $isJobsActive ? 'true' : 'false' }}">
                    <span class="menu-text"><i class="bi bi-briefcase-fill me-2"></i>Jobs</span>
                    <i class="bi bi-chevron-down arr" style="font-size: 0.7em;"></i>
                </div>
                <div class="collapse {{ $isJobsActive ? 'show' : '' }}" id="jobsMenu">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('jobs.index') || request()->routeIs('jobs.show') ? 'active' : '' }}" href="{{ route('jobs.index') }}">
                            <i class="bi bi-card-list"></i> Job Progress
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('jobs.kanban') ? 'active' : '' }}" href="{{ route('jobs.kanban') }}">
                            <i class="bi bi-kanban"></i> Job Kanban
                        </a>
                    </li>
                </div>

                {{-- Bookings Menu --}}
                @auth
                @if(Auth::user()->canManageMasterData())
                <div class="nav-section {{ $isBookingsActive ? '' : 'collapsed' }}" data-bs-toggle="collapse" data-bs-target="#bookingsMenu" aria-expanded="{{ $isBookingsActive ? 'true' : 'false' }}">
                    <span class="menu-text"><i class="bi bi-calendar-check me-2"></i>Bookings</span>
                    <i class="bi bi-chevron-down arr" style="font-size: 0.7em;"></i>
                </div>
                <div class="collapse {{ $isBookingsActive ? 'show' : '' }}" id="bookingsMenu">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('bookings.*') ? 'active' : '' }}" href="{{ route('bookings.index') }}">
                            <i class="bi bi-calendar-event"></i> Booking List
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('pdi-records.*') ? 'active' : '' }}" href="{{ route('pdi-records.index') }}">
                            <i class="bi bi-clipboard-check"></i> PDI Records
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('towing-records.*') ? 'active' : '' }}" href="{{ route('towing-records.index') }}">
                            <i class="bi bi-truck"></i> Towing Records
                        </a>
                    </li>
                </div>
                @endif
                @endauth

                {{-- Parts Tracking Menu (for sparepart, control_tower, manager, or admin) --}}
                @auth
                @if(in_array(Auth::user()->role, ['foreman', 'sparepart', 'control_tower', 'manager', 'admin']))
                @php
                    $isPartsActive = request()->routeIs('parts.*') || request()->routeIs('part-orders.*');
                @endphp
                <div class="nav-section {{ $isPartsActive ? '' : 'collapsed' }}" data-bs-toggle="collapse" data-bs-target="#partsMenu" aria-expanded="{{ $isPartsActive ? 'true' : 'false' }}">
                    <span class="menu-text"><i class="bi bi-box-seam me-2"></i>Parts Tracking</span>
                    <i class="bi bi-chevron-down arr" style="font-size: 0.7em;"></i>
                </div>
                <div class="collapse {{ $isPartsActive ? 'show' : '' }}" id="partsMenu">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('parts.kanban') ? 'active' : '' }}" href="{{ route('parts.kanban') }}">
                            <i class="bi bi-kanban"></i> Parts Kanban
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('part-orders.index') ? 'active' : '' }}" href="{{ route('part-orders.index') }}">
                            <i class="bi bi-list-ul"></i> All Orders
                        </a>
                    </li>
                </div>
                @endif
                @endauth

                {{-- Finance Kanban Menu (for finance, control_tower, manager, or admin) --}}
                @auth
                @if(in_array(Auth::user()->role, ['finance', 'control_tower', 'manager', 'admin']))
                @php
                    $isFinanceActive = request()->routeIs('finance.*');
                @endphp
                <div class="nav-section {{ $isFinanceActive ? '' : 'collapsed' }}" data-bs-toggle="collapse" data-bs-target="#financeMenu" aria-expanded="{{ $isFinanceActive ? 'true' : 'false' }}">
                    <span class="menu-text"><i class="bi bi-cash-coin me-2"></i>Finance</span>
                    <i class="bi bi-chevron-down arr" style="font-size: 0.7em;"></i>
                </div>
                <div class="collapse {{ $isFinanceActive ? 'show' : '' }}" id="financeMenu">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('finance.kanban') ? 'active' : '' }}" href="{{ route('finance.kanban') }}">
                            <i class="bi bi-kanban"></i> Invoice Kanban
                        </a>
                    </li>
                </div>
                @endif
                @endauth

                {{-- Recently Viewed --}}
                @auth
                @php
                    $recentJobs = \App\Models\RecentlyViewed::getRecentForUser(auth()->id(), 5);
                @endphp
                @if($recentJobs->count() > 0)
                <div class="nav-section collapsed" data-bs-toggle="collapse" data-bs-target="#recentMenu" aria-expanded="false">
                    <span class="menu-text"><i class="bi bi-clock-history me-2"></i>Recently Viewed</span>
                    <i class="bi bi-chevron-down arr" style="font-size: 0.7em;"></i>
                </div>
                <div class="collapse" id="recentMenu">
                    @foreach($recentJobs as $recentJob)
                    <li class="nav-item">
                        <a class="nav-link py-2" href="{{ route('jobs.show', $recentJob) }}" title="{{ $recentJob->customer_name }}">
                            <i class="bi bi-file-text text-muted"></i>
                            <span class="d-flex flex-column lh-sm">
                                <span class="small fw-semibold">{{ $recentJob->job_number }}</span>
                                <span class="text-muted" style="font-size: 0.75rem;">{{ Str::limit($recentJob->plate_number, 12) }}</span>
                            </span>
                            @if($recentJob->status == 'invoiced')
                            <span class="badge bg-success ms-auto" style="font-size: 0.65rem;">✓</span>
                            @endif
                        </a>
                    </li>
                    @endforeach
                </div>
                @endif
                @endauth

                {{-- Master Data Menu (MOVED ABOVE IMPORT DATA) --}}
                @auth
                @if(Auth::user()->canManageMasterData())
                <div class="nav-section {{ $isMasterDataActive ? '' : 'collapsed' }}" data-bs-toggle="collapse" data-bs-target="#masterDataMenu" aria-expanded="{{ $isMasterDataActive ? 'true' : 'false' }}">
                    <span class="menu-text"><i class="bi bi-database-fill me-2"></i>Master Data</span>
                    <i class="bi bi-chevron-down arr" style="font-size: 0.7em;"></i>
                </div>
                <div class="collapse {{ $isMasterDataActive ? 'show' : '' }}" id="masterDataMenu">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}" href="{{ route('customers.index') }}">
                            <i class="bi bi-people-fill"></i> Customers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('vehicles.*') ? 'active' : '' }}" href="{{ route('vehicles.index') }}">
                            <i class="bi bi-car-front-fill"></i> Vehicles
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('service-advisors.*') ? 'active' : '' }}" href="{{ route('service-advisors.index') }}">
                            <i class="bi bi-person-badge"></i> Service Advisors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('foremen.*') ? 'active' : '' }}" href="{{ route('foremen.index') }}">
                            <i class="bi bi-person-gear"></i> Foremen
                        </a>
                    </li>
                </div>
                @endif
                @endauth

                {{-- Import Data Menu --}}
                @auth
                @if(Auth::user()->canManageMasterData())
                <div class="nav-section {{ $isImportActive ? '' : 'collapsed' }}" data-bs-toggle="collapse" data-bs-target="#importMenu" aria-expanded="{{ $isImportActive ? 'true' : 'false' }}">
                    <span class="menu-text"><i class="bi bi-cloud-arrow-up me-2"></i>Import Data</span>
                    <i class="bi bi-chevron-down arr" style="font-size: 0.7em;"></i>
                </div>
                <div class="collapse {{ $isImportActive ? 'show' : '' }}" id="importMenu">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('imports.upload') ? 'active' : '' }}" href="{{ route('imports.upload') }}">
                            <i class="bi bi-cloud-arrow-up-fill"></i> Upload File
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('imports.index') ? 'active' : '' }}" href="{{ route('imports.index') }}">
                            <i class="bi bi-clock-history"></i> Import History
                        </a>
                    </li>
                </div>
                @endif

                {{-- Reports Menu --}}
                <div class="nav-section {{ $isReportsActive ? '' : 'collapsed' }}" data-bs-toggle="collapse" data-bs-target="#reportsMenu" aria-expanded="{{ $isReportsActive ? 'true' : 'false' }}">
                    <span class="menu-text"><i class="bi bi-bar-chart-fill me-2"></i>Reports</span>
                    <i class="bi bi-chevron-down arr" style="font-size: 0.7em;"></i>
                </div>
                <div class="collapse {{ $isReportsActive ? 'show' : '' }}" id="reportsMenu">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('reports.uninvoiced') ? 'active' : '' }}" href="{{ route('reports.uninvoiced') }}">
                            <i class="bi bi-exclamation-octagon-fill"></i> Uninvoiced Jobs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('reports.invoiced') ? 'active' : '' }}" href="{{ route('reports.invoiced') }}">
                            <i class="bi bi-check-circle-fill"></i> Invoiced Jobs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('reports.needs-parts') ? 'active' : '' }}" href="{{ route('reports.needs-parts') }}">
                            <i class="bi bi-tools"></i> Needs Parts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('reports.aging') ? 'active' : '' }}" href="{{ route('reports.aging') }}">
                            <i class="bi bi-clock-history"></i> Job Aging
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('reports.sa-performance') ? 'active' : '' }}" href="{{ route('reports.sa-performance') }}">
                            <i class="bi bi-person-badge"></i> SA Performance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('reports.trends') ? 'active' : '' }}" href="{{ route('reports.trends') }}">
                            <i class="bi bi-graph-up-arrow"></i> Trends & Comparisons
                        </a>
                    </li>
                    @if(Auth::user()->canEdit())
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('reports.builder') ? 'active' : '' }}" href="{{ route('reports.builder') }}">
                            <i class="bi bi-file-earmark-bar-graph"></i> Report Builder
                        </a>
                    </li>
                    @endif
                </div>

                @if(Auth::user()->hasAnyRole(['admin', 'manager']))
                <div class="nav-section" data-bs-toggle="collapse" data-bs-target="#adminMenu" aria-expanded="{{ $isAdminActive ? 'true' : 'false' }}">
                    <span class="menu-text"><i class="bi bi-gear-fill me-2"></i>Administration</span>
                    <i class="bi bi-chevron-down arr" style="font-size: 0.7em;"></i>
                </div>
                 <div class="collapse {{ $isAdminActive ? 'show' : '' }}" id="adminMenu">
                    @if(Auth::user()->hasRole('admin'))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                            <i class="bi bi-people-fill"></i> User Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}" href="{{ route('admin.roles.index') }}">
                            <i class="bi bi-shield-lock"></i> Role Permissions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.ldap.*') ? 'active' : '' }}" href="{{ route('admin.ldap.index') }}">
                            <i class="bi bi-hdd-network-fill"></i> LDAP Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.data-cleanup.*') ? 'active' : '' }}" href="{{ route('admin.data-cleanup.index') }}">
                            <i class="bi bi-trash3"></i> Data Cleanup
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.sessions.*') ? 'active' : '' }}" href="{{ route('admin.sessions.index') }}">
                            <i class="bi bi-display"></i> Session Manager
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.dropdowns.*') ? 'active' : '' }}" href="{{ route('admin.dropdowns.index') }}">
                            <i class="bi bi-list-ul"></i> Dropdown Options
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.backups.*') ? 'active' : '' }}" href="{{ route('admin.backups.index') }}">
                            <i class="bi bi-database-check"></i> Database Backups
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.scheduler.*') ? 'active' : '' }}" href="{{ route('admin.scheduler.index') }}">
                            <i class="bi bi-clock-history"></i> Scheduler
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.customer-aliases.*') ? 'active' : '' }}" href="{{ route('admin.customer-aliases.index') }}">
                            <i class="bi bi-link-45deg"></i> Customer Aliases
                        </a>
                    </li>
                    @endif
                    {{-- Announcements: accessible to both admin and manager --}}
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}" href="{{ route('admin.announcements.index') }}">
                            <i class="bi bi-megaphone-fill"></i> Announcements
                        </a>
                    </li>
                    {{-- Scheduled Reports: accessible to both admin and manager --}}
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.scheduled-reports.*') ? 'active' : '' }}" href="{{ route('admin.scheduled-reports.index') }}">
                            <i class="bi bi-envelope-at"></i> Scheduled Reports
                        </a>
                    </li>
                </div>
                @endif

                @if(Auth::user()->hasAnyRole(['admin', 'audit']))
                @php
                    $isAuditActive = request()->routeIs('admin.audit-logs.*') || request()->routeIs('tracker.*');
                @endphp
                <div class="nav-section" data-bs-toggle="collapse" data-bs-target="#auditMenu" aria-expanded="{{ $isAuditActive ? 'true' : 'false' }}">
                    <span class="menu-text"><i class="bi bi-shield-check me-2"></i>Audit</span>
                    <i class="bi bi-chevron-down arr" style="font-size: 0.7em;"></i>
                </div>
                <div class="collapse {{ $isAuditActive ? 'show' : '' }}" id="auditMenu">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}" href="{{ route('admin.audit-logs.index') }}">
                            <i class="bi bi-journal-text"></i> Audit Logs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('tracker.*') ? 'active' : '' }}" href="{{ route('tracker.index') }}">
                            <i class="bi bi-search-heart"></i> Data Tracker
                        </a>
                    </li>
                </div>
                @endif

                <hr class="my-2">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('help.*') ? 'active' : '' }}" href="{{ route('help.index') }}">
                        <i class="bi bi-question-circle"></i> Help Center
                    </a>
                </li>
                <li class="nav-item">
                    <div class="px-3 py-2 text-light small">
                        <i class="bi bi-person-circle me-1"></i>{{ Auth::user()->name }}
                        <span class="badge bg-secondary ms-1">{{ Auth::user()->getRoleDisplayName() }}</span>
                    </div>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.index') }}">
                        <i class="bi bi-person-gear"></i> My Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </li>
                @else
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('login') }}">Login</a>
                </li>
                @endauth
            </ul>
        </div>

        <!-- PWA Install Button & Theme Toggle -->
        <div class="p-3 border-top border-secondary">
            <button class="btn btn-outline-light btn-sm w-100 mb-3" id="pwaInstallBtn">
                <i class="bi bi-download me-2"></i>Install App
            </button>
             <div class="d-flex align-items-center justify-content-between text-white-50">
                <span class="small"><i class="bi bi-moon-stars-fill me-1"></i> Dark Mode</span>
                <div class="theme-switch-wrapper">
                    <label class="theme-switch" for="checkbox">
                        <input type="checkbox" id="checkbox" />
                        <div class="slider"></div>
                    </label>
                </div>
            </div>
            @auth
            <div class="mt-2 text-white-50 small text-center">
                Logged in as <strong class="text-white">{{ Auth::user()->name }}</strong>
                <div class="mt-1">
                    <a href="{{ route('2fa.index') }}" class="text-white-50 small text-decoration-none">
                        <i class="bi bi-shield-lock me-1"></i>Security
                    </a>
                </div>
            </div>
            @endauth
        </div>
    </nav>

    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleMobileSidebar()"></div>
    
    <!-- Mobile Menu Toggle (FAB) -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" onclick="toggleMobileSidebar()" aria-label="Toggle menu">
        <i class="bi bi-list" id="mobileMenuIcon"></i>
    </button>
    


    <main class="main-content">
        <!-- Global Search Bar & Back Button -->
        <div class="global-search-container mb-3 d-flex gap-2">
            <button onclick="history.back()" class="btn btn-light bg-white border shadow-sm" title="Go Back">
                <i class="bi bi-arrow-left"></i>
            </button>
            <div class="position-relative flex-grow-1" id="globalSearchWrapper">
                <div class="input-group">
                    <span class="input-group-text bg-body border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" id="globalSearchInput" 
                           placeholder="Search anything: jobs, vehicles, invoices, parts, bookings, customers... (Ctrl+K)" 
                           autocomplete="off">
                    <span class="input-group-text bg-body text-muted small border-start-0">
                        <kbd class="bg-secondary text-white px-1 rounded">Ctrl</kbd>+<kbd class="bg-secondary text-white px-1 rounded">K</kbd>
                    </span>
                </div>
                <div class="search-results dropdown-menu w-100 shadow-lg" id="searchResults" style="display: none; max-height: 400px; overflow-y: auto;"></div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>{!! session('success') !!}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Keyboard Shortcuts Help Modal -->
    <div class="modal fade" id="shortcutsHelpModal" tabindex="-1" aria-labelledby="shortcutsHelpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="shortcutsHelpModalLabel">
                        <i class="bi bi-keyboard me-2"></i>Keyboard Shortcuts
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Shortcut</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><kbd>N</kbd></td>
                                <td>Create new job</td>
                            </tr>
                            <tr>
                                <td><kbd>S</kbd> or <kbd>Ctrl</kbd>+<kbd>K</kbd></td>
                                <td>Focus search</td>
                            </tr>
                            <tr>
                                <td><kbd>G</kbd> then <kbd>D</kbd></td>
                                <td>Go to Dashboard</td>
                            </tr>
                            <tr>
                                <td><kbd>G</kbd> then <kbd>J</kbd></td>
                                <td>Go to Jobs</td>
                            </tr>
                            <tr>
                                <td><kbd>G</kbd> then <kbd>R</kbd></td>
                                <td>Go to Reports</td>
                            </tr>
                            <tr>
                                <td><kbd>G</kbd> then <kbd>C</kbd></td>
                                <td>Go to Customers</td>
                            </tr>
                            <tr>
                                <td><kbd>?</kbd></td>
                                <td>Show this help</td>
                            </tr>
                            <tr>
                                <td><kbd>Esc</kbd></td>
                                <td>Close modal / search</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="alert alert-info mt-3 mb-0 py-2 small">
                        <i class="bi bi-info-circle me-1"></i>
                        Shortcuts are disabled when typing in text fields.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dark Mode Logic
        const toggleSwitch = document.querySelector('.theme-switch input[type="checkbox"]');
        const currentTheme = localStorage.getItem('theme');

        if (currentTheme) {
            document.documentElement.setAttribute('data-bs-theme', currentTheme);
            if (currentTheme === 'dark') {
                toggleSwitch.checked = true;
            }
        }

        function switchTheme(e) {
            if (e.target.checked) {
                document.documentElement.setAttribute('data-bs-theme', 'dark');
                localStorage.setItem('theme', 'dark');
            } else {
                document.documentElement.setAttribute('data-bs-theme', 'light');
                localStorage.setItem('theme', 'light');
            }
        }

        toggleSwitch.addEventListener('change', switchTheme, false);
        
        // Mobile Sidebar Toggle
        function toggleMobileSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const icon = document.getElementById('mobileMenuIcon');
            
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
            
            if (sidebar.classList.contains('show')) {
                icon.classList.remove('bi-list');
                icon.classList.add('bi-x-lg');
            } else {
                icon.classList.remove('bi-x-lg');
                icon.classList.add('bi-list');
            }
        }
        
        // Make function globally available
        window.toggleMobileSidebar = toggleMobileSidebar;
        
        // Close sidebar when clicking a nav link on mobile
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992) {
                    toggleMobileSidebar();
                }
            });
        });
        
        // Global Search Logic
        const searchInput = document.getElementById('globalSearchInput');
        const searchResults = document.getElementById('searchResults');
        let searchTimeout;
        
        // Ctrl+K shortcut and global keyboard shortcuts
        let gKeyPressed = false;
        let gTimeout = null;
        
        document.addEventListener('keydown', function(e) {
            // Skip if user is typing in an input field
            const activeEl = document.activeElement;
            const isTyping = activeEl && (
                activeEl.tagName === 'INPUT' || 
                activeEl.tagName === 'TEXTAREA' || 
                activeEl.isContentEditable ||
                activeEl.closest('.modal.show')
            );
            
            // Always handle Ctrl+K (even when typing)
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
                return;
            }
            
            // Escape to close results and modals
            if (e.key === 'Escape') {
                searchResults.style.display = 'none';
                searchInput.blur();
                gKeyPressed = false;
                const shortcutsModal = bootstrap.Modal.getInstance(document.getElementById('shortcutsHelpModal'));
                if (shortcutsModal) shortcutsModal.hide();
                return;
            }
            
            // Skip other shortcuts if typing
            if (isTyping) return;
            
            // ? - Show keyboard shortcuts help
            if (e.key === '?' || (e.shiftKey && e.key === '/')) {
                e.preventDefault();
                const modal = new bootstrap.Modal(document.getElementById('shortcutsHelpModal'));
                modal.show();
                return;
            }
            
            // N - New Job
            if (e.key === 'n' || e.key === 'N') {
                e.preventDefault();
                window.location.href = '{{ route("jobs.create") }}';
                return;
            }
            
            // S - Focus Search
            if (e.key === 's' || e.key === 'S') {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
                return;
            }
            
            // G prefix for navigation (G then D = Dashboard, G then J = Jobs)
            if (e.key === 'g' || e.key === 'G') {
                e.preventDefault();
                gKeyPressed = true;
                clearTimeout(gTimeout);
                gTimeout = setTimeout(() => { gKeyPressed = false; }, 1500);
                return;
            }
            
            if (gKeyPressed) {
                gKeyPressed = false;
                clearTimeout(gTimeout);
                
                if (e.key === 'd' || e.key === 'D') {
                    e.preventDefault();
                    window.location.href = '{{ route("dashboard") }}';
                    return;
                }
                if (e.key === 'j' || e.key === 'J') {
                    e.preventDefault();
                    window.location.href = '{{ route("jobs.index") }}';
                    return;
                }
                if (e.key === 'r' || e.key === 'R') {
                    e.preventDefault();
                    window.location.href = '{{ route("reports.uninvoiced") }}';
                    return;
                }
                if (e.key === 'c' || e.key === 'C') {
                    e.preventDefault();
                    window.location.href = '{{ route("customers.index") }}';
                    return;
                }
            }
        });
        
        // Search on input
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetch(`/search?q=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.results.length === 0) {
                            searchResults.innerHTML = '<div class="p-3 text-muted text-center"><i class="bi bi-search me-2"></i>No results found</div>';
                        } else {
                            searchResults.innerHTML = data.results.map(r => `
                                <a href="${r.url}" class="dropdown-item d-flex align-items-center py-2">
                                    <i class="bi ${r.icon} me-3 fs-5 text-${r.type === 'job' ? 'primary' : r.type === 'vehicle' ? 'success' : 'info'}"></i>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold">${r.title}</div>
                                        <small class="text-muted">${r.subtitle}</small>
                                    </div>
                                    ${r.badge ? `<span class="badge ${r.badge_class} ms-2">${r.badge}</span>` : ''}
                                </a>
                            `).join('');
                        }
                        searchResults.style.display = 'block';
                    })
                    .catch(err => {
                        searchResults.innerHTML = '<div class="p-3 text-danger text-center">Search error</div>';
                        searchResults.style.display = 'block';
                    });
            }, 300);
        });
        
        // Close on click outside
        document.addEventListener('click', function(e) {
            if (!document.getElementById('globalSearchWrapper').contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });
        
        // Show results on focus if there's content
        searchInput.addEventListener('focus', function() {
            if (this.value.length >= 2 && searchResults.innerHTML) {
                searchResults.style.display = 'block';
            }
        });
        
        // Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then((registration) => {
                        console.log('SW registered:', registration.scope);
                    })
                    .catch((error) => {
                        console.log('SW registration failed:', error);
                    });
            });
        }
        
        // PWA Install Prompt
        let deferredPrompt = null;
        const installBtn = document.getElementById('pwaInstallBtn');
        
        // Check if already installed (using localStorage as per-device tracking)
        const isPwaInstalled = localStorage.getItem('pwaInstalled') === 'true' || 
                               window.matchMedia('(display-mode: standalone)').matches ||
                               window.navigator.standalone === true;
        
        if (isPwaInstalled && installBtn) {
            console.log('[PWA] Already installed on this device');
            installBtn.style.display = 'none';
        }
        
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('[PWA] beforeinstallprompt fired');
            e.preventDefault();
            deferredPrompt = e;
            
            // Only show if not already installed on THIS device
            if (installBtn && !isPwaInstalled) {
                installBtn.style.display = 'block';
            }
        });
        
        // Handle install button click
        if (installBtn) {
            installBtn.addEventListener('click', async () => {
                if (!deferredPrompt) {
                    console.log('[PWA] No install prompt available');
                    // Try showing browser's native install dialog as fallback
                    alert('To install: tap the menu button (⋮) and select "Add to Home screen" or "Install app"');
                    return;
                }
                
                console.log('[PWA] Showing install prompt');
                installBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Installing...';
                installBtn.disabled = true;
                
                try {
                    deferredPrompt.prompt();
                    const choice = await deferredPrompt.userChoice;
                    console.log('[PWA] User choice:', choice.outcome);
                    
                    if (choice.outcome === 'accepted') {
                        localStorage.setItem('pwaInstalled', 'true');
                        console.log('[PWA] Installed successfully');
                    }
                    deferredPrompt = null;
                    installBtn.style.display = 'none';
                } catch (err) {
                    console.error('[PWA] Install error:', err);
                    installBtn.innerHTML = '<i class="bi bi-download me-2"></i>Install App';
                    installBtn.disabled = false;
                }
            });
        }
        
        // Listen for successful installation
        window.addEventListener('appinstalled', (e) => {
            console.log('[PWA] App installed event fired');
            localStorage.setItem('pwaInstalled', 'true');
            if (installBtn) {
                installBtn.style.display = 'none';
            }
        });
    </script>
    
    @auth
    <!-- Laravel Echo for Real-Time Notifications -->
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
    <script>
    (function() {
        // Reverb/Pusher configuration for frontend (using VITE_* or fallback)
        // VITE_REVERB_* = public URL for browser connection
        // REVERB_* = internal Docker URL for server-side broadcasting
        const reverbConfig = {
            key: '{{ env("VITE_REVERB_APP_KEY", env("REVERB_APP_KEY", "control-tower-key")) }}',
            wsHost: '{{ env("VITE_REVERB_HOST", env("REVERB_HOST", "localhost")) }}',
            wsPort: {{ env("VITE_REVERB_PORT", env("REVERB_PORT", 8080)) }},
            wssPort: {{ env("VITE_REVERB_PORT", env("REVERB_PORT", 443)) }},
            forceTLS: '{{ env("VITE_REVERB_SCHEME", env("REVERB_SCHEME", "http")) }}' === 'https',
            enabledTransports: ['ws', 'wss'],
            disableStats: true,
        };
        
        // Only initialize if Reverb is configured
        if (!reverbConfig.wsHost) {
            console.log('[Echo] Reverb not configured, skipping real-time notifications');
            return;
        }
        
        try {
            window.Echo = new Echo({
                broadcaster: 'reverb',
                ...reverbConfig,
                authEndpoint: '/broadcasting/auth',
            });
            
            const userId = {{ auth()->id() }};
            
            // Subscribe to private notification channel
            window.Echo.private(`notifications.${userId}`)
                .listen('.new-notification', (e) => {
                    console.log('[Echo] New notification received:', e);
                    
                    // Update notification badge
                    const badge = document.querySelector('#notificationDropdown .badge');
                    if (badge) {
                        const current = parseInt(badge.textContent) || 0;
                        badge.textContent = current >= 9 ? '9+' : current + 1;
                        badge.style.display = 'inline-block';
                    } else {
                        // Create badge if it doesn't exist
                        const btn = document.getElementById('notificationDropdown');
                        if (btn) {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                            newBadge.style.fontSize = '0.6rem';
                            newBadge.textContent = '1';
                            btn.appendChild(newBadge);
                        }
                    }
                    
                    // Add notification to dropdown list
                    const list = document.getElementById('notificationList');
                    if (list) {
                        const emptyMsg = list.querySelector('.text-center.text-muted');
                        if (emptyMsg) emptyMsg.remove();
                        
                        const notifHtml = `
                            <a href="${e.notification.link || '#'}" class="dropdown-item py-2 bg-light">
                                <div class="d-flex align-items-start">
                                    <div class="me-2">
                                        <span class="badge bg-${e.notification.color} rounded-circle p-2">
                                            <i class="bi bi-${e.notification.icon}"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold small">${e.notification.title}</div>
                                        <div class="text-muted small text-truncate" style="max-width: 220px;">${e.notification.message}</div>
                                        <small class="text-muted">${e.notification.created_at}</small>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">New</span>
                                </div>
                            </a>
                        `;
                        list.insertAdjacentHTML('afterbegin', notifHtml);
                    }
                    
                    // Show toast notification
                    showNotificationToast(e.notification);
                });
            
            console.log('[Echo] Connected to Reverb, listening for notifications');
            
        } catch (err) {
            console.warn('[Echo] Failed to initialize:', err.message);
        }
        
        // Toast notification function (globally accessible)
        window.showNotificationToast = function(notification) {
            // Create toast container if not exists
            let container = document.getElementById('toastContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toastContainer';
                container.className = 'toast-container position-fixed top-0 end-0 p-3';
                container.style.zIndex = '9999';
                document.body.appendChild(container);
            }
            
            const toastId = 'toast-' + Date.now();
            const toastHtml = `
                <div id="${toastId}" class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header bg-${notification.color} text-white">
                        <i class="bi bi-${notification.icon} me-2"></i>
                        <strong class="me-auto">${notification.title}</strong>
                        <small>just now</small>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        ${notification.message}
                        ${notification.link ? `<div class="mt-2"><a href="${notification.link}" class="btn btn-sm btn-primary">View</a></div>` : ''}
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', toastHtml);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                const toast = document.getElementById(toastId);
                if (toast) {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 300);
                }
            }, 5000);
            
            // Play notification sound (optional)
            try {
                const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2telezeC2telezeC2telezeC');
                audio.volume = 0.3;
                audio.play().catch(() => {});
            } catch (e) {}
        }
    })();
    
    // Push Notification Logic
    window.enablePushNotifications = async function() {
        const btn = document.getElementById('enablePushBtn');
        
        try {
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enabling...';
            }

            // Check if push notifications are supported
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                console.log('[Push] Push notifications not supported');
                alert('Push notifications are not supported by your browser.');
                return;
            }
            
            // Wait for service worker to be ready
            const registration = await navigator.serviceWorker.ready;

            // Request permission
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                console.log('[Push] Permission not granted');
                alert('You need to allow notifications to receive updates.');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-bell-fill me-1"></i>Enable Push';
                }
                return;
            }
            
            // Get VAPID public key from server
            const response = await fetch('/push/vapid-public-key');
            if (!response.ok) {
                throw new Error('Server returned ' + response.status);
            }
            const data = await response.json();
            const publicKey = data.publicKey;
            
            if (!publicKey) {
                console.log('[Push] VAPID key not configured');
                return;
            }
            
            // Convert VAPID key to Uint8Array
            const vapidKey = urlBase64ToUint8Array(publicKey);
            
            // Subscribe to push
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: vapidKey,
            });
            
            console.log('[Push] Subscribed:', subscription.endpoint);
            
            // Send subscription to server
            const saveResponse = await fetch('/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify(subscription.toJSON()),
            });
            
            if (!saveResponse.ok) {
                throw new Error('Failed to save subscription: ' + saveResponse.status);
            }
            
            console.log('[Push] Subscription saved to server');
            
            // Hide button on success
            if (btn) btn.style.display = 'none';
            
            // Show success toast
            showNotificationToast({
                title: 'Notifications Enabled',
                message: 'You will now receive push notifications.',
                icon: 'bell-fill',
                color: 'success'
            });
            
        } catch (err) {
            console.warn('[Push] Subscription failed:', err.message);
            alert('Failed to enable notifications: ' + err.message);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-bell-fill me-1"></i>Enable Push';
            }
        }
    };

    // Check status on load
    (async function() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
        
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            
            // If permissions allow but not subscribed, or permission is default
            // We verify permission status to decide whether to show button
            if (!subscription && Notification.permission === 'default') {
                const btn = document.getElementById('enablePushBtn');
                if (btn) btn.classList.remove('d-none');
            } else if (!subscription && Notification.permission === 'granted') {
                 // Try to silently subscribe if permission is already granted
                 enablePushNotifications();
            }
        } catch (e) {
            console.warn('[Push] Status check failed', e);
        }
    })();
        
    // Helper function to convert base64 to Uint8Array
    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');
        const rawData = window.atob(base64);
        return Uint8Array.from([...rawData].map(char => char.charCodeAt(0)));
    }
    </script>
    @endauth
    @yield('scripts')
    @stack('scripts')

    @if(isset($importantAnnouncements) && $importantAnnouncements->count() > 0)
        @foreach($importantAnnouncements as $announcement)
        <div class="modal fade" id="tickerModal{{ $announcement->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title fs-6">
                            <i class="bi bi-megaphone-fill me-2"></i>{{ $announcement->title }}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex justify-content-between text-muted small mb-3">
                            <span><i class="bi bi-person me-1"></i>{{ $announcement->author->name ?? 'System' }}</span>
                            <span>{{ $announcement->created_at->format('d M Y H:i') }}</span>
                        </div>
                        <div class="announcement-content">
                            {!! $announcement->content !!}
                        </div>
                    </div>
                    <div class="modal-footer">
                         @if(!$announcement->isDismissedBy(auth()->user()))
                        <form action="{{ route('announcements.dismiss', $announcement->id) }}" method="POST">
                            @csrf
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="fetch(this.form.action, {method:'POST', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}}).then(() => { location.reload(); })">
                                <i class="bi bi-x-circle me-1"></i>Dismiss
                            </button>
                        </form>
                        @endif
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    @endif
    <!-- Site Security Modal -->
    @if(!session('site_unlocked'))
    <div class="modal fade show" id="siteSecurityModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="siteSecurityModalLabel" aria-hidden="true" style="display: block; background: rgba(0,0,0,0.8);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title" id="siteSecurityModalLabel">
                        <i class="bi bi-shield-lock-fill me-2"></i>Site Access Required
                    </h5>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted mb-4">Please enter the access code to proceed to Control Tower.</p>
                    <div class="mb-3">
                        <label for="sitePassword" class="form-label fw-bold">Access Code</label>
                        <input type="password" class="form-control form-control-lg" id="sitePassword" placeholder="Enter code..." autofocus>
                        <div class="invalid-feedback" id="sitePasswordError"></div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-primary btn-lg w-100" id="unlockSiteBtn">
                        <i class="bi bi-unlock-fill me-2"></i>Unlock Site
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = new bootstrap.Modal(document.getElementById('siteSecurityModal'), {
                backdrop: 'static',
                keyboard: false
            });
            // Ensure modal cannot be closed
            document.getElementById('siteSecurityModal').addEventListener('hide.bs.modal', function(e) {
                e.preventDefault();
            });
            
            const passwordInput = document.getElementById('sitePassword');
            const unlockBtn = document.getElementById('unlockSiteBtn');
            const errorDiv = document.getElementById('sitePasswordError');

            function attemptUnlock() {
                const password = passwordInput.value;
                if (!password) return;

                unlockBtn.disabled = true;
                unlockBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verifying...';
                passwordInput.classList.remove('is-invalid');

                fetch('{{ route("site-security.unlock") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ password: password })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        passwordInput.classList.add('is-invalid');
                        errorDiv.textContent = 'Incorrect access code';
                        unlockBtn.disabled = false;
                        unlockBtn.innerHTML = '<i class="bi bi-unlock-fill me-2"></i>Unlock Site';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    unlockBtn.disabled = false;
                    unlockBtn.innerHTML = '<i class="bi bi-unlock-fill me-2"></i>Unlock Site';
                });
            }

            unlockBtn.addEventListener('click', attemptUnlock);

            passwordInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    attemptUnlock();
                }
            });
            
            // Focus input
            setTimeout(() => passwordInput.focus(), 500);
        });
    </script>
    @endif
</body>
</html>
