@php
    $unreadAlerts = \App\Models\Alert::where('is_resolved', false)->count();
@endphp

<nav class="navbar navbar-dark bg-success fixed-top">

    <div class="container-fluid">

        <!-- Logo/Brand -->
        <a class="navbar-brand" href="{{ route('home') }}">
            <i class="bi bi-recycle me-2"></i> Smart Waste Monitoring
        </a>

        <!-- Right Side: Notifications & User Account -->
        <ul class="navbar-nav flex-row align-items-center">

            <!-- Notification Icon with Badge (real-time) -->
            @auth
                <li class="nav-item me-3">
                    <a class="nav-link position-relative" href="{{ route('alerts') }}">
                        <i class="bi bi-bell-fill fs-5"></i>
                        <span id="notification-badge"
                              class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger text-white pulse-badge {{ $unreadAlerts > 0 ? '' : 'd-none' }}">
                            {{ $unreadAlerts }}
                            <span class="visually-hidden">New alerts</span>
                        </span>
                    </a>
                </li>
            @endauth

            <!-- User Account Dropdown -->
            @auth
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-fill fs-5"></i>
                        <span class="ms-2 d-none d-md-inline">{{ auth()->user()->name }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <div class="dropdown-header text-center py-2">
                                <i class="bi bi-person-circle fs-4 d-block mb-2"></i>
                                <strong class="d-block">{{ auth()->user()->name }}</strong>
                                <small class="text-muted">{{ auth()->user()->email }}</small>
                                <small class="text-muted">
                                    <span class="badge bg-primary">{{ auth()->user()->role ?? 'user' }}</span>
                                </small>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="{{ route('profile.edit') }}">
                                <i class="bi bi-person-gear"></i> Profile Settings
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="{{ route('logout') }}"
                               onclick="event.preventDefault(); document.getElementById('navbar-logout-form').submit();">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                    <form id="navbar-logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </li>
            @else
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('login') }}">
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('register') }}">
                        <i class="bi bi-person-plus"></i> Register
                    </a>
                </li>
            @endauth

        </ul>

    </div>

</nav>

<!-- Notification Pulse Animation -->
<style>
    .pulse-badge {
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
        70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }

    .navbar {
        padding-top: 0.3rem;
        padding-bottom: 0.3rem;
    }
    .navbar-brand {
        padding-top: 0;
        padding-bottom: 0;
    }
    .navbar .nav-link {
        padding-top: 0.35rem;
        padding-bottom: 0.35rem;
        padding-right: 0.5rem;
        padding-left: 0.5rem;
    }
    .navbar .nav-item.dropdown .nav-link {
        padding-top: 0.2rem;
        padding-bottom: 0.2rem;
    }
    .dropdown-menu {
        padding: 0.3rem 0;
    }
    .dropdown-menu .dropdown-item,
    .dropdown-menu .dropdown-header {
        padding: 0.35rem 1rem;
    }
</style>

<!-- Real-time Notification Polling -->
@auth
<script>
    function updateNotificationCount() {
        fetch('{{ route('notifications.unread-count') }}')
            .then(response => response.json())
            .then(data => {
                var badge = document.getElementById('notification-badge');
                if (badge) {
                    var count = data.count;
                    badge.textContent = count;
                    badge.style.display = count > 0 ? 'inline-block' : 'none';
                }
            })
            .catch(function() {});
    }

    setInterval(updateNotificationCount, 30000);
</script>
@endauth
