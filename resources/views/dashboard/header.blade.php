<div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4">

    <div>
        <p class="text-muted mb-1" style="font-size:.75rem;letter-spacing:.09em;text-transform:uppercase;font-weight:700">
            {{ now()->format('l, F d, Y') }}
        </p>
        <h2 class="mb-1" style="font-size:1.6rem">
            Welcome back, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}
        </h2>
        <p class="text-muted mb-0">
            Here is the current state of your waste compartments.
        </p>
    </div>

    <div class="sw-card" style="min-width:260px">
        <div class="d-flex align-items-center gap-3 p-3">

            <span class="sw-kpi__icon"
                  style="--kpi: {{ $alerts->count() > 0 ? 'var(--sw-reject)' : 'var(--sw-bio)' }}; width:44px;height:44px;font-size:1.2rem">
                <i class="bi {{ $alerts->count() > 0 ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill' }}"></i>
            </span>

            <div>
                <div style="font-size:1.5rem;font-weight:800;line-height:1">
                    {{ $alerts->count() }}
                </div>
                <div class="text-muted" style="font-size:.75rem">
                    Active alert{{ $alerts->count() === 1 ? '' : 's' }}
                </div>
            </div>

            <div class="ms-auto text-end">
                <div class="text-muted" style="font-size:.68rem">Last reading</div>
                <div style="font-size:.78rem;font-weight:600">
                    {{ $sensorData ? $sensorData->created_at->format('h:i A') : '—' }}
                </div>
            </div>

        </div>
    </div>

</div>