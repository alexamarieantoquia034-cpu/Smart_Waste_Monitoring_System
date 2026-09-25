<div class="col-md-2 bg-dark text-white min-vh-100 p-3 d-flex flex-column" style="max-width: 200px;">

    <h4 class="mb-4">
        Smart Waste Monitoring
    </h4>

    <ul class="nav flex-column">

        <li class="nav-item mb-2">
            <a href="{{ route('dashboard') }}" class="nav-link text-white">
                📊 Dashboard
            </a>
        </li>

        <li class="nav-item mb-2">
            <a href="{{ route('alerts') }}" class="nav-link text-white">
                🚨 Alerts
            </a>
        </li>

        <li class="nav-item mb-2">
            <a href="{{ route('analytics') }}" class="nav-link text-white">
                📈 Analytics
            </a>
        </li>

        <li class="nav-item mb-2">
            <a href="{{ route('reports') }}" class="nav-link text-white">
                📄 Reports
            </a>
        </li>

        <li class="nav-item mb-2">
            <a href="{{ route('users') }}" class="nav-link text-white">
                👥 Users
            </a>
        </li>

        <li class="nav-item mb-2">
            <a href="{{ route('settings') }}" class="nav-link text-white">
                ⚙ Settings
            </a>
        </li>

        <!-- Divider -->
        <li><hr class="text-white opacity-25 my-3"></li>

    </ul>

    <!-- Footer at the bottom -->
    <div class="mt-auto">

        <!-- Logout Button (above footer) -->
        <a href="{{ route('logout') }}" class="nav-link text-danger d-flex align-items-center justify-content-center mb-3"
           onclick="event.preventDefault(); document.getElementById('sidebar-logout-form').submit();">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
        <form id="sidebar-logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
            @csrf
        </form>

        <footer class="bg-dark text-white text-center p-3">

            &copy; 2026 Smart Waste Monitoring System

        </footer>

    </div>

</div>
