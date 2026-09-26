@php
    $compartments = [
        [
            'key' => 'plastic',
            'label' => 'Plastic',
            'icon' => 'bi-cup-straw',
            'level' => $sensorData->plastic_level ?? 0,
            'distance' => $sensorData->plastic_distance ?? null,
        ],
        [
            'key' => 'paper',
            'label' => 'Paper',
            'icon' => 'bi-file-earmark-text',
            'level' => $sensorData->paper_level ?? 0,
            'distance' => $sensorData->paper_distance ?? null,
        ],
        [
            'key' => 'bio',
            'label' => 'Biodegradable',
            'icon' => 'bi-leaf',
            'level' => $sensorData->biodegradable_level ?? 0,
            'distance' => $sensorData->biodegradable_distance ?? null,
        ],
        [
            'key' => 'reject',
            'label' => 'Reject',
            'icon' => 'bi-x-octagon',
            'level' => $sensorData->reject_level ?? 0,
            'distance' => $sensorData->reject_distance ?? null,
        ],
    ];
@endphp

<div class="row g-3 mb-4">

    @foreach($compartments as $bin)
        @php
            $value = round((float) $bin['level'], 1);
            $width = max(0, min(100, $value));
        @endphp

        <div class="col-6 col-xl-3">
            {{-- liveLevel-* and the data-live-* hooks are what
                 public/js/live-stream.js updates when the ESP32-CAM reports. --}}
            <div class="sw-kpi sw-kpi--{{ $bin['key'] }}" id="liveLevel-{{ $bin['key'] }}">

                <div class="sw-kpi__top">
                    <span class="sw-kpi__label">{{ $bin['label'] }}</span>
                    <span class="sw-kpi__icon"><i class="bi {{ $bin['icon'] }}"></i></span>
                </div>

                <div class="sw-kpi__value" data-live-value>
                    {{ $value }}<span>%</span>
                </div>

                <div class="sw-kpi__meta">
                    <span data-live-hint>
                        @if($value >= 90)
                            <i class="bi bi-exclamation-triangle-fill" style="color:var(--sw-reject)"></i>
                            <span>Nearly full</span>
                        @elseif($value >= 75)
                            <i class="bi bi-clock-fill" style="color:var(--sw-paper)"></i>
                            <span>Schedule pickup</span>
                        @else
                            <i class="bi bi-check-circle-fill" style="color:var(--sw-bio)"></i>
                            <span>Within capacity</span>
                        @endif
                    </span>

                    @if($bin['distance'] !== null)
                        <span class="ms-auto">{{ round((float) $bin['distance'], 0) }} cm</span>
                    @endif
                </div>

                <div class="sw-meter">
                    <div class="sw-meter__fill" style="width: {{ $width }}%"></div>
                </div>

            </div>
        </div>

    @endforeach

</div>