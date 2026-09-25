<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Waste Monitoring System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-light">

    @include('layouts.navbar')

    <div class="container-fluid mt-5">
        <div class="row">
            @include('layouts.sidebar')

            <main class="col-md-10 p-4">
                @yield('content')
            </main>
        </div>
    </div>

    @yield('scripts')

</body>
</html>
