<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Waste Monitoring System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="sw-landing">

    <nav class="sw-nav">
        <div class="sw-nav__inner">

            <a href="{{ route('home') }}" class="sw-brand">
                <span class="sw-brand__mark"><i class="bi bi-recycle"></i></span>
                Smart Waste
            </a>

            <div class="sw-nav__links">
                @auth
                    <a href="{{ route('dashboard') }}" class="sw-btn sw-btn--dark">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                @else
                    <a href="#features" class="sw-nav__link d-none d-sm-block">Features</a>
                    <a href="{{ route('login') }}" class="sw-nav__link">Login</a>
                    <a href="{{ route('register') }}" class="sw-btn sw-btn--dark">Get started</a>
                @endauth
            </div>

        </div>
    </nav>

    <header class="sw-hero">
        <div class="sw-hero__inner">

            <div>
                <span class="sw-eyebrow">
                    <b>IoT</b> Real-time bin telemetry
                </span>

                <h1 class="sw-hero__title">
                    Know what's filling up <em>before</em> it overflows.
                </h1>

                <p class="sw-hero__lead">
                    Smart sensors read every compartment around the clock. The system turns
                    those readings into clear alerts, waste classification, and collection
                    recommendations — so crews dispatch the right truck, at the right time.
                </p>

                <div class="sw-hero__actions">
                    @auth
                        <a href="{{ route('dashboard') }}" class="sw-btn sw-btn--lime">
                            <i class="bi bi-speedometer2"></i> Open dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="sw-btn sw-btn--lime">
                            <i class="bi bi-box-arrow-in-right"></i> Sign in
                        </a>
                        <a href="{{ route('register') }}" class="sw-btn sw-btn--ghost">
                            <i class="bi bi-person-plus"></i> Create account
                        </a>
                    @endauth
                </div>
            </div>

            <div class="sw-device" aria-hidden="true">
                <div class="sw-device__head">
                    <span class="sw-device__title">Compartment fill levels</span>
                    <span class="sw-device__live">Live</span>
                </div>

                @php
                    $demo = [
                        ['Plastic', 82, '#60a5fa'],
                        ['Paper', 63, '#fbbf24'],
                        ['Biodegradable', 91, '#4ade80'],
                        ['Reject', 47, '#fb7185'],
                    ];
                @endphp

                @foreach($demo as [$label, $value, $color])
                    <div class="sw-device__row">
                        <div class="sw-device__meta">
                            <span>{{ $label }}</span>
                            <b>{{ $value }}%</b>
                        </div>
                        <div class="sw-device__bar">
                            <div class="sw-device__fill"
                                 style="width: {{ $value }}%; background: {{ $color }};"></div>
                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    </header>

    <section class="sw-section" id="features">
        <div class="container">

            <div class="sw-section__head">
                <h2 class="sw-section__title">Everything the crew needs, on one screen</h2>
                <p class="sw-section__lead">
                    From ultrasonic readings to machine-learning classification, the system
                    turns raw sensor data into decisions your team can act on.
                </p>
            </div>

            <div class="row g-4">

                <div class="col-md-6 col-lg-3">
                    <div class="sw-feature">
                        <div class="sw-feature__icon"><i class="bi bi-speedometer2"></i></div>
                        <h3 class="sw-feature__title">Live fill levels</h3>
                        <p class="sw-feature__text">
                            Percentage fill and raw distance for plastic, paper, biodegradable
                            and reject bins, updated continuously.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="sw-feature">
                        <div class="sw-feature__icon"><i class="bi bi-bell"></i></div>
                        <h3 class="sw-feature__title">Threshold alerts</h3>
                        <p class="sw-feature__text">
                            Automatic warnings when a bin nears capacity, with a live
                            notification badge in the top bar.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="sw-feature">
                        <div class="sw-feature__icon"><i class="bi bi-cpu"></i></div>
                        <h3 class="sw-feature__title">Waste classification</h3>
                        <p class="sw-feature__text">
                            Image-based classification with a confidence score and a
                            full history of every detected item.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="sw-feature">
                        <div class="sw-feature__icon"><i class="bi bi-signpost-split"></i></div>
                        <h3 class="sw-feature__title">Decision support</h3>
                        <p class="sw-feature__text">
                            The system ranks compartments by urgency and recommends when to
                            dispatch collection.
                        </p>
                    </div>
                </div>

            </div>

        </div>
    </section>

    <section class="sw-section sw-section--tint">
        <div class="container">
            <div class="sw-cta">
                <h2 class="sw-cta__title">Ready to run smarter collection routes?</h2>
                <p class="sw-cta__text">
                    Sign in to the operations console and see your bins, alerts and
                    recommendations in one place.
                </p>

                @auth
                    <a href="{{ route('dashboard') }}" class="sw-btn sw-btn--lime">
                        <i class="bi bi-speedometer2"></i> Go to dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="sw-btn sw-btn--lime">
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </a>
                @endauth
            </div>
        </div>
    </section>

    <footer class="sw-foot">
        &copy; {{ date('Y') }} Smart Waste Monitoring System
    </footer>

</body>
</html>
