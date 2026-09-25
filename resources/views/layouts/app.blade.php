<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Waste Monitoring System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<div class="sw-shell">

    @auth
        @include('layouts.sidebar')
    @endauth

    <div class="sw-main">

        @auth
            @include('layouts.navbar')
        @endauth

        <main class="{{ auth()->check() ? 'sw-content' : 'flex-grow-1' }}">
            @yield('content')
        </main>

    </div>

</div>

    @yield('scripts')

</body>
</html>
