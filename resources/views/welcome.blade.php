<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Waste Monitoring System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">
                <i class="bi bi-recycle"></i> Smart Waste Monitoring
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="{{ route('home') }}">Home</a>
                    </li>
                    @if (Route::has('login'))
                        @auth
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-person-fill"></i> Account
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ route('dashboard') }}">
                                        <i class="bi bi-speedometer2"></i> Dashboard
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item text-danger" href="{{ route('logout') }}" 
                                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                            <i class="bi bi-box-arrow-right"></i> Logout
                                        </a>
                                    </li>
                                </ul>
                                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                            </li>
                        @else
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('login') }}">Login</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('register') }}">Register</a>
                            </li>
                        @endif
                    @endif
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1 class="display-4 fw-bold mb-4">
                        Smart Waste <span class="text-white">Monitoring</span> System
                    </h1>
                    <p class="lead mb-4">
                        Monitor and manage waste collection efficiently with our smart monitoring solution. 
                        Track waste levels, receive alerts, and optimize collection routes.
                    </p>
                    <div class="d-flex gap-3 flex-wrap">
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ route('dashboard') }}" class="btn btn-light btn-lg">
                                    <i class="bi bi-speedometer2"></i> Dashboard
                                </a>
                                <a href="{{ route('logout') }}" class="btn btn-outline-light btn-lg" 
                                   onclick="event.preventDefault(); document.getElementById('logout-form-homepage').submit();">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </a>
                                <form id="logout-form-homepage" action="{{ route('logout') }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                            @else
                                <a href="{{ route('login') }}" class="btn btn-light btn-lg">
                                    <i class="bi bi-box-arrow-in-right"></i> Login
                                </a>
                                <a href="{{ route('register') }}" class="btn btn-outline-light btn-lg">
                                    <i class="bi bi-person-plus"></i> Register
                                </a>
                            @endif
                        @endif
                    </div>
                </div>
                <div class="col-lg-5 text-center">
                    <div class="hero-icon">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <style>
        .hero-section {
            background: linear-gradient(135deg, #0d6efd 0%, #198754 100%);
            color: white;
            padding: 80px 0;
            margin-bottom: 60px;
        }
        .hero-section .display-4 {
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .hero-icon {
            font-size: 150px;
            color: rgba(255,255,255,0.8);
        }
    </style>
</body>
</html>
