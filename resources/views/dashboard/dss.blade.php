<div class="sw-card h-100">

    <div class="sw-card__head">
        <div>
            <h3 class="sw-card__title">
                <i class="bi bi-signpost-split"></i> Collection recommendation
            </h3>
            <p class="sw-card__sub">Highest priority compartment right now</p>
        </div>
    </div>

    <div class="sw-card__body">

        @if($sensorData)

            @php
                $levels = [
                    'Plastic' => $sensorData->plastic_level,
                    'Paper' => $sensorData->paper_level,
                    'Biodegradable' => $sensorData->biodegradable_level,
                    'Reject' => $sensorData->reject_level,
                ];

                arsort($levels);
                $priorityBin = array_key_first($levels);
                $priorityLevel = round((float) current($levels), 1);

                if ($priorityLevel >= 90) {
                    $tone = 'danger'; $icon = 'bi-exclamation-octagon-fill';
                    $title = 'Collect immediately';
                    $advice = 'This compartment is at capacity. Dispatch a collection vehicle now.';
                } elseif ($priorityLevel >= 80) {
                    $tone = 'warn'; $icon = 'bi-exclamation-triangle-fill';
                    $title = 'High priority';
                    $advice = 'Schedule collection within the next collection window.';
                } elseif ($priorityLevel >= 60) {
                    $tone = 'info'; $icon = 'bi-clock-fill';
                    $title = 'Moderate priority';
                    $advice = 'Add this compartment to the next planned collection run.';
                } else {
                    $tone = 'ok'; $icon = 'bi-check-circle-fill';
                    $title = 'Low priority';
                    $advice = 'No action needed. Continue routine monitoring.';
                }
            @endphp

            <div class="d-flex align-items-center gap-3 mb-3">
                <div>
                    <div class="text-muted" style="font-size:.7rem;letter-spacing:.09em;text-transform:uppercase;font-weight:700">
                        Next to collect
                    </div>
                    <div style="font-size:1.35rem;font-weight:800">{{ $priorityBin }}</div>
                </div>
                <div class="ms-auto text-end">
                    <div style="font-size:1.75rem;font-weight:800;line-height:1">{{ $priorityLevel }}<span style="font-size:1rem;color:var(--sw-slate-500)">%</span></div>
                    <div class="text-muted" style="font-size:.72rem">fill level</div>
                </div>
            </div>

            <div class="sw-callout sw-callout--{{ $tone }}">
                <i class="bi {{ $icon }}"></i>
                <div>
                    <div class="sw-callout__title">{{ $title }}</div>
                    <div class="sw-callout__text">{{ $advice }}</div>
                </div>
            </div>

        @else

            <div class="sw-empty">
                <i class="bi bi-hourglass-split"></i>
                Waiting for the first sensor reading.
            </div>

        @endif

    </div>

</div>