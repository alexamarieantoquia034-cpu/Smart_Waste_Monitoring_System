<div class="sw-card h-100">

    <div class="sw-card__head">
        <div>
            <h3 class="sw-card__title">
                <i class="bi bi-graph-up"></i> Fill level trend
            </h3>
            <p class="sw-card__sub">Last {{ $chartData->count() }} sensor readings</p>
        </div>
        <span class="sw-pill sw-pill--info">Live data</span>
    </div>

    <div class="sw-card__body">

        @if($chartData->count())

            <div style="height:340px">
                <canvas id="fillChart"></canvas>
            </div>

        @else

            <div class="sw-empty">
                <i class="bi bi-activity"></i>
                No sensor data available yet.
            </div>

        @endif

    </div>

</div>