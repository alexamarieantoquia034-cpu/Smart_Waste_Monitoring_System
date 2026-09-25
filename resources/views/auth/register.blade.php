<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register &middot; Smart Waste Monitoring</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<div class="sw-auth">

    <aside class="sw-auth__aside">
        <a href="{{ route('home') }}" class="sw-brand" style="color:#fff">
            <span class="sw-brand__mark">
                <img src="{{ asset('images/barbie-hero.jpg') }}" alt="Smart Waste">
            </span>
            Smart Waste
        </a>

        <div>
            <h2 style="color:#fff;font-size:2rem;letter-spacing:-.03em;line-height:1.15">
                Join the<br>collection team.
            </h2>
            <p style="color:rgba(255,255,255,.78);max-width:26rem;line-height:1.7;margin-top:1rem">
                Create an account to monitor bin levels, respond to alerts and keep
                collection routes efficient across the city.
            </p>

            <div class="d-flex flex-wrap gap-2 mt-4">
                <span class="sw-pill" style="background:rgba(255,255,255,.12);color:#fff">
                    <i class="bi bi-bell"></i> Instant alerts
                </span>
                <span class="sw-pill" style="background:rgba(255,255,255,.12);color:#fff">
                    <i class="bi bi-signpost-split"></i> Smart routing
                </span>
            </div>
        </div>

        <p style="color:rgba(255,255,255,.45);font-size:.78rem;margin:0">
            &copy; {{ date('Y') }} Smart Waste Monitoring System
        </p>
    </aside>

    <div class="sw-auth__form">
        <div class="sw-auth__card">

            <div class="mb-4">
                <h1 style="font-size:1.5rem;margin-bottom:.35rem">Create your account</h1>
                <p class="text-muted mb-0" style="font-size:.88rem">
                    It takes less than a minute to get started.
                </p>
            </div>

            @if ($errors->any())
                <div class="sw-callout sw-callout--danger mb-3">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>
                        <div class="sw-callout__title">Please fix the following</div>
                        <div class="sw-callout__text">
                            @foreach ($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}">
                @csrf

                <div class="mb-3">
                    <label for="name" class="sw-label">Full name</label>
                    <input id="name" type="text" class="sw-input" name="name"
                           value="{{ old('name') }}" required autofocus
                           autocomplete="name" placeholder="Juan Dela Cruz">
                </div>

                <div class="mb-3">
                    <label for="email" class="sw-label">Email address</label>
                    <input id="email" type="email" class="sw-input" name="email"
                           value="{{ old('email') }}" required
                           autocomplete="username" placeholder="you@example.com">
                </div>

                <div class="mb-3">
                    <label for="password" class="sw-label">Password</label>
                    <input id="password" type="password" class="sw-input" name="password"
                           required autocomplete="new-password" placeholder="At least 8 characters">
                </div>

                <div class="mb-4">
                    <label for="password_confirmation" class="sw-label">Confirm password</label>
                    <input id="password_confirmation" type="password" class="sw-input"
                           name="password_confirmation" required autocomplete="new-password"
                           placeholder="Repeat your password">
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-person-plus me-1"></i> Create account
                </button>
            </form>

            <hr class="sw-divider my-4">

            <p class="text-center text-muted mb-3" style="font-size:.85rem">
                Already have an account?
            </p>
            <a href="{{ route('login') }}" class="btn btn-outline-primary w-100">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
            </a>

        </div>
    </div>

</div>

</body>
</html>
