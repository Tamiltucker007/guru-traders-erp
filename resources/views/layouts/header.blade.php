<nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
        <!-- Start navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                    <i class="bi bi-list"></i>
                </a>
            </li>
            {{-- Current page title, not a link home. A permanent "Dashboard"
                 link here sent people back to the landing screen whenever they
                 clicked the top bar while already on another page. --}}
            <li class="nav-item d-none d-md-flex align-items-center">
                @unless (request()->routeIs('dashboard'))
                    <a href="{{ route('dashboard') }}" class="nav-link py-0 text-secondary" title="Dashboard">
                        <i class="bi bi-house-door"></i>
                        <span class="visually-hidden">Dashboard</span>
                    </a>
                    <span class="text-secondary mx-1" aria-hidden="true">/</span>
                @endunless
                <span class="nav-link disabled px-1">{{ $header ?? 'Dashboard' }}</span>
            </li>
        </ul>
        <!-- End navbar links -->

        <!-- Start navbar links (Right) -->
        <ul class="navbar-nav ms-auto">
            <!-- User Dropdown Menu -->
            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=random" class="user-image rounded-circle shadow" alt="User Image">
                    <span class="d-none d-md-inline">{{ Auth::user()->name }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <!-- User image -->
                    <li class="user-header text-bg-primary">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=random" class="rounded-circle shadow" alt="User Image">
                        <p>
                            {{ Auth::user()->name }}
                            <small>Member since {{ Auth::user()->created_at->format('M. Y') }}</small>
                        </p>
                    </li>
                    <!-- Menu Footer-->
                    <li class="user-footer">
                        <a href="{{ route('profile.edit') }}" class="btn btn-default btn-flat">Profile</a>
                        <form method="POST" action="{{ route('logout') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-default btn-flat float-end">Sign out</button>
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
