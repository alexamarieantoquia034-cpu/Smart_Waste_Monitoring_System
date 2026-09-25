<div class="sw-card h-100">

    <div class="sw-card__head">
        <div>
            <h3 class="sw-card__title">
                <i class="bi bi-cpu"></i> Latest classification
            </h3>
            <p class="sw-card__sub">Most recent detected item</p>
        </div>
    </div>

    <div class="sw-card__body">

        @if($classification)

            @if($classification->image_path)
                <div class="rounded-3 overflow-hidden mb-3" style="background:var(--sw-slate-100)">
                    <img src="{{ asset($classification->image_path) }}"
                         class="img-fluid w-100" alt="Classified waste">
                </div>
            @endif

            <div class="d-flex align-items-center gap-3 mb-3">
                <span class="sw-kpi__icon" style="--kpi:var(--sw-teal-600);width:40px;height:40px;font-size:1.1rem">
                    <i class="bi bi-tag"></i>
                </span>
                <div>
                    <div style="font-weight:700;text-transform:capitalize">{{ $classification->waste_type }}</div>
                    <div class="text-muted" style="font-size:.75rem">
                        {{ ucfirst($classification->compartment) }} bin
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="text-muted" style="font-size:.75rem">Confidence</span>
                <div class="sw-meter flex-grow-1" style="margin-top:0">
                    <div class="sw-meter__fill" style="width: {{ min(100, (float) $classification->confidence) }}%"></div>
                </div>
                <span style="font-size:.78rem;font-weight:700">{{ number_format($classification->confidence, 1) }}%</span>
            </div>

            <p class="text-muted mb-3" style="font-size:.75rem">
                <i class="bi bi-clock me-1"></i>
                {{ $classification->created_at->format('M d, Y h:i A') }}
            </p>

            <a href="{{ route('classifications.show', $classification) }}"
               class="btn btn-primary btn-sm w-100">
                View full details
            </a>

        @else

            <div class="sw-empty">
                <i class="bi bi-camera"></i>
                No classification recorded yet.
            </div>

        @endif

    </div>

</div>
