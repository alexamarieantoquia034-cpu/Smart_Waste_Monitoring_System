<div class="row mb-4">

    <div class="col-md-8">

        <h2 class="fw-bold">
            Welcome, {{ auth()->user()->name }}
        </h2>

        <p class="text-muted">
            Smart Waste Monitoring and Decision Support System
        </p>

    </div>

    <div class="col-md-4">

        <div class="card border-danger shadow-sm">

            <div class="card-body text-center">

                <h5 class="text-danger fw-bold">
                    Active Alerts
                </h5>

                <h2>{{ $alerts->count() }}</h2>

                <small class="text-muted">

                    Last Updated :

                    {{ $sensorData ? $sensorData->created_at->format('F d, Y h:i A') : 'Waiting for sensor data...' }}

                </small>

            </div>

        </div>

    </div>

</div>