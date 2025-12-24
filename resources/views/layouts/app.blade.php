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
    <link href="{{ asset('css/custom.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body>
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
                    $isOperationsActive = request()->routeIs('jobs.*') || request()->routeIs('vehicles.*') || request()->routeIs('customers.*') || request()->routeIs('bookings.*') || request()->routeIs('pdi-records.*') || request()->routeIs('towing-records.*');
                    $isReportsActive = request()->routeIs('reports.*');
                    $isImportActive = request()->routeIs('imports.*');
                    $isAdminActive = request()->routeIs('admin.*');
                    $isMasterDataActive = request()->routeIs('service-advisors.*') || request()->routeIs('foremen.*');
                @endphp

                <div class="nav-section" data-bs-toggle="collapse" data-bs-target="#operationsMenu" aria-expanded="{{ $isOperationsActive ? 'true' : 'false' }}">
                    Operations <i class="bi bi-chevron-down arr" style="font-size: 0.8em;"></i>
                </div>
                <div class="collapse {{ $isOperationsActive ? 'show' : '' }}" id="operationsMenu">
                <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('jobs.index') || request()->routeIs('jobs.show') ? 'active' : '' }}" href="{{ route('jobs.index') }}">
                            <i class="bi bi-card-list"></i> Job Progress
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('jobs.kanban') ? 'active' : '' }}" href="{{ route('jobs.kanban') }}">
                            <i class="bi bi-kanban"></i> Kanban Board
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('vehicles.*') ? 'active' : '' }}" href="{{ route('vehicles.index') }}">
                            <i class="bi bi-car-front-fill"></i> Vehicles
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}" href="{{ route('customers.index') }}">
                            <i class="bi bi-people-fill"></i> Customers
                        </a>
                    </li>
                    @auth
                    @if(Auth::user()->canManageMasterData())
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('bookings.*') ? 'active' : '' }}" href="{{ route('bookings.index') }}">
                            <i class="bi bi-calendar-check"></i> Bookings
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
                    @endif
                    @endauth
                </div>

                @auth
                @if(Auth::user()->canManageMasterData())
                <div class="nav-section" data-bs-toggle="collapse" data-bs-target="#importMenu" aria-expanded="{{ $isImportActive ? 'true' : 'false' }}">
                    Import Data <i class="bi bi-chevron-down arr" style="font-size: 0.8em;"></i>
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

                <div class="nav-section" data-bs-toggle="collapse" data-bs-target="#reportsMenu" aria-expanded="{{ $isReportsActive ? 'true' : 'false' }}">
                    Reports <i class="bi bi-chevron-down arr" style="font-size: 0.8em;"></i>
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
                    @if(Auth::user()->canEdit())
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('reports.builder') ? 'active' : '' }}" href="{{ route('reports.builder') }}">
                            <i class="bi bi-file-earmark-bar-graph"></i> Report Builder
                        </a>
                    </li>
                    @endif
                </div>

                @if(Auth::user()->canManageMasterData())
                <div class="nav-section" data-bs-toggle="collapse" data-bs-target="#masterDataMenu" aria-expanded="{{ $isMasterDataActive ? 'true' : 'false' }}">
                    Master Data <i class="bi bi-chevron-down arr" style="font-size: 0.8em;"></i>
                </div>
                <div class="collapse {{ $isMasterDataActive ? 'show' : '' }}" id="masterDataMenu">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('service-advisors.*') ? 'active' : '' }}" href="{{ route('service-advisors.index') }}">
                            <i class="bi bi-database-fill"></i> Service Advisors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('foremen.*') ? 'active' : '' }}" href="{{ route('foremen.index') }}">
                            <i class="bi bi-database-fill"></i> Foremen
                        </a>
                    </li>
                </div>
                @endif

                @if(Auth::user()->hasRole('admin'))
                <div class="nav-section" data-bs-toggle="collapse" data-bs-target="#adminMenu" aria-expanded="{{ $isAdminActive ? 'true' : 'false' }}">
                    Administration <i class="bi bi-chevron-down arr" style="font-size: 0.8em;"></i>
                </div>
                 <div class="collapse {{ $isAdminActive ? 'show' : '' }}" id="adminMenu">
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
                        <a class="nav-link {{ request()->routeIs('admin.scheduled-reports.*') ? 'active' : '' }}" href="{{ route('admin.scheduled-reports.index') }}">
                            <i class="bi bi-envelope-at"></i> Scheduled Reports
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
                </div>
                @endif

                @if(Auth::user()->hasAnyRole(['admin', 'audit']))
                @php
                    $isAuditActive = request()->routeIs('admin.audit-logs.*') || request()->routeIs('tracker.*');
                @endphp
                <div class="nav-section" data-bs-toggle="collapse" data-bs-target="#auditMenu" aria-expanded="{{ $isAuditActive ? 'true' : 'false' }}">
                    Audit <i class="bi bi-chevron-down arr" style="font-size: 0.8em;"></i>
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
                    <div class="px-3 py-2 text-muted small">
                        <i class="bi bi-person-circle me-1"></i>{{ Auth::user()->name }}
                        <span class="badge bg-secondary ms-1">{{ Auth::user()->getRoleDisplayName() }}</span>
                    </div>
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

        <!-- Theme Toggle at bottom of sidebar -->
        <div class="p-3 border-top border-secondary">
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
    
    <!-- PWA Install Button -->
    <button class="btn btn-primary rounded-pill shadow" id="pwaInstallBtn">
        <i class="bi bi-download me-2"></i>Install App
    </button>

    <main class="main-content">
        <!-- Global Search Bar -->
        <div class="global-search-container mb-3">
            <div class="position-relative" id="globalSearchWrapper">
                <div class="input-group">
                    <span class="input-group-text bg-body border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" id="globalSearchInput" 
                           placeholder="Search jobs, vehicles, customers... (Ctrl+K)" 
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
        
        // Ctrl+K shortcut
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
            // Escape to close results
            if (e.key === 'Escape') {
                searchResults.style.display = 'none';
                searchInput.blur();
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
        
        // Toast notification function
        function showNotificationToast(notification) {
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
    
    // Push Notification Subscription
    (async function() {
        // Check if push notifications are supported
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.log('[Push] Push notifications not supported');
            return;
        }
        
        // Wait for service worker to be ready
        const registration = await navigator.serviceWorker.ready;
        
        // Check current subscription status
        let subscription = await registration.pushManager.getSubscription();
        
        if (subscription) {
            console.log('[Push] Already subscribed');
            return;
        }
        
        // Check permission
        if (Notification.permission === 'denied') {
            console.log('[Push] Notification permission denied');
            return;
        }
        
        // Request permission if not granted
        if (Notification.permission === 'default') {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                console.log('[Push] Permission not granted');
                return;
            }
        }
        
        try {
            // Get VAPID public key from server
            const response = await fetch('/push/vapid-public-key');
            const { publicKey } = await response.json();
            
            if (!publicKey) {
                console.log('[Push] VAPID key not configured');
                return;
            }
            
            // Convert VAPID key to Uint8Array
            const vapidKey = urlBase64ToUint8Array(publicKey);
            
            // Subscribe to push
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: vapidKey,
            });
            
            console.log('[Push] Subscribed:', subscription.endpoint);
            
            // Send subscription to server
            await fetch('/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify(subscription.toJSON()),
            });
            
            console.log('[Push] Subscription saved to server');
            
        } catch (err) {
            console.warn('[Push] Subscription failed:', err.message);
        }
        
        // Helper function to convert base64 to Uint8Array
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/-/g, '+')
                .replace(/_/g, '/');
            const rawData = window.atob(base64);
            return Uint8Array.from([...rawData].map(char => char.charCodeAt(0)));
        }
    })();
    </script>
    @endauth
    
    @stack('scripts')
</body>
</html>

