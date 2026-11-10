<!DOCTYPE html>
<html lang="en-US" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'TMS') | Goods Transport Management System</title>

    <!-- Falcon Favicons & Theme Config -->
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/img/favicons/favicon.ico') }}">
    <script src="{{ asset('assets/js/config.js') }}"></script>
    <script src="{{ asset('vendors/simplebar/simplebar.min.js') }}"></script>

    <!-- Falcon Stylesheets -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,500,600,700%7cPoppins:300,400,500,600,700,800,900&amp;display=swap" rel="stylesheet">
    <link href="{{ asset('vendors/simplebar/simplebar.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/theme.min.css') }}" rel="stylesheet" id="style-default">
    <link href="{{ asset('assets/css/user.min.css') }}" rel="stylesheet" id="user-style-default">

    @livewireStyles
    @stack('styles')
</head>

<body>
    <main class="main" id="top">
        <div class="container-fluid" data-layout="container">
            <!-- Sidebar Navigation -->
            <nav class="navbar navbar-light navbar-vertical navbar-expand-xl">
                <div class="d-flex align-items-center">
                    <div class="toggle-icon-wrapper">
                        <button class="btn navbar-toggler-humburger-icon navbar-vertical-toggle" data-bs-toggle="tooltip" data-bs-placement="left" title="Toggle Navigation">
                            <span class="navbar-toggle-icon"><span class="toggle-line"></span></span>
                        </button>
                    </div>
                    <a class="navbar-brand" href="{{ route('dashboard') }}">
                        <div class="d-flex align-items-center py-3">
                            <span class="fas fa-truck-moving text-primary fs-3 me-2"></span>
                            <span class="font-sans-serif fw-bolder fs-2 text-primary">TMS</span>
                        </div>
                    </a>
                </div>

                <div class="collapse navbar-collapse" id="navbarVerticalCollapse">
                    <div class="navbar-vertical-content scrollbar">
                        <ul class="navbar-nav flex-column mb-3" id="navbarVerticalNav">
                            <li class="nav-item">
                                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                    <div class="col-auto navbar-vertical-label">Logistics Operations</div>
                                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                                </div>
                                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-chart-pie"></span></span>
                                        <span class="nav-link-text ps-1">Dashboard</span>
                                    </div>
                                </a>
                            </li>

                            @if(auth()->user()->isSuperAdmin() || auth()->user()->isTenantAdmin() || auth()->user()->hasPermission('view_fleet') || auth()->user()->hasPermission('manage_fleet'))
                            <li class="nav-item">
                                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                    <div class="col-auto navbar-vertical-label">Fleet & Drivers</div>
                                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                                </div>
                                <a class="nav-link {{ request()->routeIs('fleet.vehicles*') ? 'active' : '' }}" href="{{ route('fleet.vehicles') }}">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-truck-moving"></span></span>
                                        <span class="nav-link-text ps-1">Fleet Vehicles</span>
                                    </div>
                                </a>
                                <a class="nav-link {{ request()->routeIs('fleet.owners*') ? 'active' : '' }}" href="{{ route('fleet.owners') }}">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-user-tie"></span></span>
                                        <span class="nav-link-text ps-1">Vehicle Owners</span>
                                    </div>
                                </a>
                                <a class="nav-link {{ request()->routeIs('fleet.drivers*') ? 'active' : '' }}" href="{{ route('fleet.drivers') }}">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-id-card"></span></span>
                                        <span class="nav-link-text ps-1">Commercial Drivers</span>
                                    </div>
                                </a>
                                <a class="nav-link {{ request()->routeIs('fleet.alerts*') ? 'active' : '' }}" href="{{ route('fleet.alerts') }}">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-bell"></span></span>
                                        <span class="nav-link-text ps-1">Document Expiries</span>
                                    </div>
                                </a>
                            </li>
                            @endif

                            @if(auth()->user()->isSuperAdmin())
                            <li class="nav-item">
                                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                    <div class="col-auto navbar-vertical-label text-warning">SaaS Platform Governance</div>
                                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                                </div>
                                <a class="nav-link {{ request()->routeIs('saas.dashboard') ? 'active' : '' }}" href="{{ route('saas.dashboard') }}">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-crown"></span></span>
                                        <span class="nav-link-text ps-1">Platform Dashboard</span>
                                    </div>
                                </a>
                                <a class="nav-link {{ request()->routeIs('saas.tenants') ? 'active' : '' }}" href="{{ route('saas.tenants') }}">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-building"></span></span>
                                        <span class="nav-link-text ps-1">Transport Companies</span>
                                    </div>
                                </a>
                                <a class="nav-link {{ request()->routeIs('saas.plans') ? 'active' : '' }}" href="{{ route('saas.plans') }}">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-layer-group"></span></span>
                                        <span class="nav-link-text ps-1">Subscription Plans</span>
                                    </div>
                                </a>
                                <a class="nav-link {{ request()->routeIs('saas.billing') ? 'active' : '' }}" href="{{ route('saas.billing') }}">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-file-invoice-dollar"></span></span>
                                        <span class="nav-link-text ps-1">SaaS Billing & Receipts</span>
                                    </div>
                                </a>
                            </li>
                            @endif

                            @if(auth()->user()->isSuperAdmin() || auth()->user()->isTenantAdmin() || auth()->user()->hasPermission('view_users'))
                            <li class="nav-item">
                                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                    <div class="col-auto navbar-vertical-label">Company Administration</div>
                                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                                </div>
                                <a class="nav-link {{ request()->routeIs('admin.users') ? 'active' : '' }}" href="{{ route('admin.users') }}">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-users-cog"></span></span>
                                        <span class="nav-link-text ps-1">User & Role Management</span>
                                    </div>
                                </a>
                                @if(auth()->user()->company_id)
                                <a class="nav-link {{ request()->routeIs('settings.company') ? 'active' : '' }}" href="{{ route('settings.company') }}">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-building"></span></span>
                                        <span class="nav-link-text ps-1">Company Profile</span>
                                    </div>
                                </a>
                                <a class="nav-link {{ request()->routeIs('settings.branches') ? 'active' : '' }}" href="{{ route('settings.branches') }}">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-code-branch"></span></span>
                                        <span class="nav-link-text ps-1">Branch Management</span>
                                    </div>
                                </a>
                                <a class="nav-link {{ request()->routeIs('settings.financial') ? 'active' : '' }}" href="{{ route('settings.financial') }}">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-calendar-alt"></span></span>
                                        <span class="nav-link-text ps-1">Fiscal Periods</span>
                                    </div>
                                </a>
                                <a class="nav-link {{ request()->routeIs('settings.master-data') ? 'active' : '' }}" href="{{ route('settings.master-data') }}">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-database"></span></span>
                                        <span class="nav-link-text ps-1">Master Data Lookups</span>
                                    </div>
                                </a>
                                <a class="nav-link {{ request()->routeIs('onboarding') ? 'active' : '' }}" href="{{ route('onboarding') }}">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-magic"></span></span>
                                        <span class="nav-link-text ps-1">Setup Wizard</span>
                                    </div>
                                </a>
                                @endif
                            </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </nav>

            <div class="content">
                <!-- Top Navbar -->
                <nav class="navbar navbar-light navbar-glass navbar-top navbar-expand">
                    <button class="btn navbar-toggler-humburger-icon navbar-toggler me-1 me-sm-3 d-xl-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarVerticalCollapse" aria-controls="navbarVerticalCollapse" aria-expanded="false" aria-label="Toggle Navigation">
                        <span class="navbar-toggle-icon"><span class="toggle-line"></span></span>
                    </button>

                    <!-- Active Tenant / Branch Indicator -->
                    <div class="d-flex align-items-center">
                        @if(auth()->user()->company)
                            <span class="badge bg-soft-primary text-primary px-3 py-2 me-2 fs--1">
                                <i class="fas fa-building me-1"></i> {{ auth()->user()->company->name }}
                            </span>
                        @elseif(auth()->user()->isSuperAdmin())
                            <span class="badge bg-soft-danger text-danger px-3 py-2 me-2 fs--1">
                                <i class="fas fa-shield-alt me-1"></i> Platform Super Administrator
                            </span>
                        @endif

                        <!-- Branch Switcher Form -->
                        @if(!auth()->user()->isSuperAdmin() && isset($accessibleBranches) && $accessibleBranches->count() > 1)
                            <form action="{{ route('branch.switch') }}" method="POST" class="d-inline-flex align-items-center ms-2">
                                @csrf
                                <label class="fs--1 text-600 mb-0 me-1 d-none d-md-inline"><i class="fas fa-map-marker-alt"></i> Branch:</label>
                                <select name="branch_id" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 200px;">
                                    @foreach($accessibleBranches as $b)
                                        <option value="{{ $b->id }}" {{ (session('active_branch_id', auth()->user()->branch_id) == $b->id) ? 'selected' : '' }}>
                                            {{ $b->city }} ({{ $b->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        @elseif(auth()->user()->branch)
                            <span class="badge bg-soft-secondary text-secondary px-2 py-1 fs--1">
                                <i class="fas fa-code-branch me-1"></i> {{ auth()->user()->branch->name }}
                            </span>
                        @endif
                    </div>

                    <ul class="navbar-nav navbar-nav-icons ms-auto flex-row align-items-center">
                        <li class="nav-item dropdown">
                            <a class="nav-link pe-0 ps-2" id="navbarDropdownUser" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <div class="avatar avatar-xl">
                                    <div class="avatar-name rounded-circle bg-primary text-white">
                                        <span>{{ substr(auth()->user()->name, 0, 1) }}</span>
                                    </div>
                                </div>
                            </a>
                            <div class="dropdown-menu dropdown-caret dropdown-menu-end py-0" aria-labelledby="navbarDropdownUser">
                                <div class="bg-white dark__bg-1000 rounded-2 py-2">
                                    <div class="px-3 py-1">
                                        <div class="fw-bold text-dark">{{ auth()->user()->name }}</div>
                                        <div class="fs--2 text-500">{{ auth()->user()->email }}</div>
                                        <div class="fs--2 badge bg-soft-info text-info mt-1">{{ auth()->user()->role?->name ?? (auth()->user()->isSuperAdmin() ? 'Super Admin' : 'User') }}</div>
                                    </div>
                                    <div class="dropdown-divider"></div>
                                    <form action="{{ route('logout') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    </ul>
                </nav>

                <!-- Page Content -->
                <div class="mt-3">
                    @if(session('success') || session('status'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') ?? session('status') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @yield('content')
                </div>

                <!-- Footer -->
                <footer class="footer mt-4">
                    <div class="row g-0 justify-content-between fs--1 mt-4 mb-3">
                        <div class="col-12 col-sm-auto text-center">
                            <p class="mb-0 text-600">Goods Transport Management System &copy; {{ date('Y') }}</p>
                        </div>
                        <div class="col-12 col-sm-auto text-center">
                            <p class="mb-0 text-600">Multi-Tenant SaaS v1.0</p>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    </main>

    <!-- Falcon & Bootstrap Scripts -->
    <script src="{{ asset('vendors/popper/popper.min.js') }}"></script>
    <script src="{{ asset('vendors/bootstrap/bootstrap.min.js') }}"></script>
    <script src="{{ asset('vendors/anchorjs/anchor.min.js') }}"></script>
    <script src="{{ asset('vendors/is/is.min.js') }}"></script>
    <script src="{{ asset('vendors/fontawesome/all.min.js') }}"></script>
    <script src="{{ asset('vendors/lodash/lodash.min.js') }}"></script>
    <script src="{{ asset('assets/js/theme.min.js') }}"></script>

    @livewireScripts
    @stack('scripts')
</body>
</html>

