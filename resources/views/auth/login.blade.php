<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login &middot; Smart Waste Monitoring</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<div class="sw-auth">

    <aside class="sw-auth__aside">
        <a href="{{ route('home') }}" class="sw-brand" style="color:#fff">
            <span class="sw-brand__mark"><i class="bi bi-recycle"></i></span>
            Smart Waste
        </a>

        <div>
            <h2 style="color:#fff;font-size:2rem;letter-spacing:-.03em;line-height:1.15">
                Every bin,<br>accounted for.
            </h2>
            <p style="color:rgba(255,255,255,.78);max-width:26rem;line-height:1.7;margin-top:1rem">
                Sign in to see live fill levels, threshold alerts, waste classification
                and collection recommendations in real time.
            </p>

            <div class="d-flex flex-wrap gap-2 mt-4">
                <span class="sw-pill" style="background:rgba(255,255,255,.12);color:#fff">
                    <i class="bi bi-speedometer2"></i> Live telemetry
                </span>
                <span class="sw-pill" style="background:rgba(255,255,255,.12);color:#fff">
                    <i class="bi bi-cpu"></i> ML classification
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
                <h1 style="font-size:1.5rem;margin-bottom:.35rem">Welcome back</h1>
                <p class="text-muted mb-0" style="font-size:.88rem">
                    Enter your credentials to access the operations console.
                </p>
            </div>

            @if (session('status'))
                <div class="sw-callout sw-callout--ok mb-3">
                    <i class="bi bi-check-circle-fill"></i>
                    <div class="sw-callout__text">{{ session('status') }}</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="sw-callout sw-callout--danger mb-3">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>
                        <div class="sw-callout__title">Unable to sign in</div>
                        <div class="sw-callout__text">
                            @foreach ($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="mb-3">
                    <label for="email" class="sw-label">Email address</label>
                    <input id="email" type="email"
                           class="sw-input @error('email') is-invalid @enderror"
                           name="email" value="{{ old('email') }}"
                           required autofocus autocomplete="username"
                           placeholder="admin@gmail.com">
                    @error('email')
                        <div style="color:var(--sw-reject);font-size:.75rem;margin-top:.3rem">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="sw-label">Password</label>
                    <input id="password" type="password"
                           class="sw-input @error('password') is-invalid @enderror"
                           name="password" required autocomplete="current-password"
                           placeholder="Enter your password">
                    @error('password')
                        <div style="color:var(--sw-reject);font-size:.75rem;margin-top:.3rem">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="form-check m-0">
                        <input id="remember_me" type="checkbox" class="form-check-input" name="remember">
                        <label for="remember_me" class="form-check-label"
                               style="font-size:.8rem;color:var(--sw-slate-600)">Remember me</label>
                    </div>

                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}"
                           style="font-size:.8rem;font-weight:600;color:var(--sw-teal-700)">
                            Forgot password?
                        </a>
                    @endif
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
                </button>
            </form>

            <hr class="sw-divider my-4">

            <p class="text-center text-muted mb-3" style="font-size:.85rem">
                No account yet?
            </p>
            <a href="{{ route('register') }}" class="btn btn-outline-primary w-100">
                <i class="bi bi-person-plus me-1"></i> Create an account
            </a>

        </div>
    </div>

</div>

</body>
</html>
