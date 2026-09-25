@php
    $unreadAlerts = \App\Models\Alert::where('is_resolved', false)->count();

    $pageTitles = [
        'dashboard' => 'Dashboard',
        'analytics' => 'Analytics',
        'alerts' => 'Alerts',
        'reports' => 'Reports',
        'users' => 'Users',
        'dss' => 'Decision Support',
        'settings' => 'Settings',
        'profile.edit' => 'Profile Settings',
        'classifications.index' => 'Classification Logs',
        'classifications.show' => 'Classification Detail',
    ];

    $currentTitle = 'Overview';
    foreach ($pageTitles as $routeName => $label) {
        if (request()->routeIs($routeName)) {
            $currentTitle = $label;
            break;
        }
    }
@endphp

<header class="sw-topbar">

    <div class="d-flex align-items-center gap-3">

        <button class="sw-burger" id="sidebarToggle" type="button" aria-label="Toggle navigation">
            <i class="bi bi-list"></i>
        </button>

        <div>
            <p class="sw-topbar__title">{{ $currentTitle }}</p>
            <p class="sw-topbar__crumb">Smart Waste Monitoring &amp; Decision Support</p>
        </div>

    </div>

    <div class="d-flex align-items-center gap-2 gap-md-3">

        @auth
            <a href="{{ route('alerts') }}" class="sw-bell" aria-label="Notifications">
                <i class="bi bi-bell"></i>
                <span id="notification-badge"
                      class="sw-bell__dot {{ $unreadAlerts > 0 ? '' : 'is-hidden' }}">{{ $unreadAlerts }}</span>
            </a>
        @endauth

        @auth
            <div class="dropdown">
                <button class="sw-user" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="sw-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="d-none d-sm-block text-start">
                        <span class="sw-user__name d-block">{{ auth()->user()->name }}</span>
                        <span class="sw-user__role d-block">{{ auth()->user()->role ?? 'user' }}</span>
                    </span>
                    <i class="bi bi-chevron-down d-none d-sm-block" style="font-size:.7rem;color:var(--sw-slate-500)"></i>
                </button>

                <ul class="dropdown-menu dropdown-menu-end" style="min-width:230px">
                    <li class="px-3 py-2">
                        <div class="fw-semibold">{{ auth()->user()->name }}</div>
                        <div class="text-muted" style="font-size:.78rem">{{ auth()->user()->email }}</div>
                        <span class="sw-pill sw-pill--info mt-2">{{ auth()->user()->role ?? 'user' }}</span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="{{ route('profile.edit') }}">
                            <i class="bi bi-person-gear me-2"></i>Profile Settings
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item text-danger" href="{{ route('logout') }}"
                           onclick="event.preventDefault(); document.getElementById('topbar-logout-form').submit();">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a>
                    </li>
                </ul>

                <form id="topbar-logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                    @csrf
                </form>
            </div>
        @else
            <a href="{{ route('login') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i> Login
            </a>
        @endauth

    </div>

</header>

<script>
    // Mobile sidebar
    (function () {
        var sidebar = document.getElementById('appSidebar');
        var backdrop = document.getElementById('sidebarBackdrop');
        var toggle = document.getElementById('sidebarToggle');
        if (!sidebar || !toggle) return;

        function setOpen(open) {
            sidebar.classList.toggle('is-open', open);
            if (backdrop) backdrop.style.display = open ? 'block' : 'none';
        }

        toggle.addEventListener('click', function () {
            setOpen(!sidebar.classList.contains('is-open'));
        });

        if (backdrop) {
            backdrop.addEventListener('click', function () { setOpen(false); });
        }
    })();

    // Live notification count
    function updateNotificationCount() {
        fetch('{{ route('notifications.unread-count') }}')
            .then(function (response) { return response.json(); })
            .then(function (data) {
                var badge = document.getElementById('notification-badge');
                if (!badge) return;
                badge.textContent = data.count;
                badge.classList.toggle('is-hidden', data.count === 0);
            })
            .catch(function () {});
    }

    setInterval(updateNotificationCount, 30000);
</script>
