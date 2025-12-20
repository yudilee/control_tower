<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Control Tower') - Uninvoiced Job Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/custom.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body>
    <nav class="sidebar d-flex flex-column">
        <div class="brand">
            <img src="{{ asset('images/logo.png') }}" alt="Hartono Group" style="height: 36px; width: auto; margin-right: 8px;">
            <span>Control Tower</span>
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
                        <a class="nav-link {{ request()->routeIs('jobs.*') ? 'active' : '' }}" href="{{ route('jobs.index') }}">
                            <i class="bi bi-card-list"></i> Job Progress
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
                        <a class="nav-link {{ request()->routeIs('reports.customer-merges') ? 'active' : '' }}" href="{{ route('reports.customer-merges') }}">
                            <i class="bi bi-people"></i> Customer Merges
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
                        <a class="nav-link {{ request()->routeIs('admin.dropdowns.*') ? 'active' : '' }}" href="{{ route('admin.dropdowns.index') }}">
                            <i class="bi bi-list-ul"></i> Dropdown Options
                        </a>
                    </li>
                </div>
                @endif

                @if(Auth::user()->hasAnyRole(['admin', 'audit']))
                @php
                    $isAuditActive = request()->routeIs('audit-logs.*') || request()->routeIs('tracker.*');
                @endphp
                <div class="nav-section" data-bs-toggle="collapse" data-bs-target="#auditMenu" aria-expanded="{{ $isAuditActive ? 'true' : 'false' }}">
                    Audit <i class="bi bi-chevron-down arr" style="font-size: 0.8em;"></i>
                </div>
                <div class="collapse {{ $isAuditActive ? 'show' : '' }}" id="auditMenu">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}" href="{{ route('audit-logs.index') }}">
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
            </div>
            @endauth
        </div>
    </nav>

    <main class="main-content">
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
    </script>
    @stack('scripts')
</body>
</html>
