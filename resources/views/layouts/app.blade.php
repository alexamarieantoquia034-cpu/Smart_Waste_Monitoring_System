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
            {{-- Pages extending this layout use @section/@yield, while the
                 Breeze <x-app-layout> class component passes its content
                 through $slot. Support both so no page renders empty. --}}
            @hasSection('content')
                @yield('content')
            @else
                {{ $slot ?? '' }}
            @endif
        </main>

    </div>

</div>

    @yield('scripts')

</body>
</html>
