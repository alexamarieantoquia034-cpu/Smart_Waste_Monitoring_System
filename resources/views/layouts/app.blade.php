<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Smart Waste Monitoring System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body {{-- data-live-stream is read by public/js/live-stream.js. The SSE
         endpoint requires a session, so it is only offered to signed-in
         users; guests simply get no live updates. --}}
      data-live-stream="{{ auth()->check() ? route('api.live-stream') : '' }}">

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

    {{-- Real-time updates from the ESP32-CAM. No build step: the file is
         served straight out of public/ like the TF.js runtime. --}}
    <script src="{{ asset('js/live-stream.js') }}"></script>

</body>
</html>
