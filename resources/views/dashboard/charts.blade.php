<div class="card shadow h-100">

    <div class="card-header fw-bold">
        Fill Level Trend
    </div>

    <div class="card-body">

        @if($chartData->count())

            <div style="height:350px;">
                <canvas id="fillChart"></canvas>
            </div>

        @else

            <div class="text-center text-muted py-5">
                No sensor data available.
            </div>

        @endif

    </div>

</div>