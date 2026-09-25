@php
    $cards = [
        [
            'title' => 'Plastic',
            'value' => $sensorData->plastic_level ?? 0,
            'color' => 'success',
        ],
        [
            'title' => 'Paper',
            'value' => $sensorData->paper_level ?? 0,
            'color' => 'warning',
        ],
        [
            'title' => 'Biodegradable',
            'value' => $sensorData->biodegradable_level ?? 0,
            'color' => 'danger',
        ],
        [
            'title' => 'Reject',
            'value' => $sensorData->reject_level ?? 0,
            'color' => 'dark',
        ],
    ];
@endphp

<div class="row mb-4">

    @foreach ($cards as $card)

        <div class="col-md-3 mb-4">

            <div class="card shadow border-0 h-100">

                <div class="card-body">

                    <h5 class="fw-bold text-{{ $card['color'] }}">
                        {{ $card['title'] }}
                    </h5>

                    <h2 class="mb-3">
                        {{ $card['value'] }}%
                    </h2>

                    <div class="progress mb-2">

                        <div
                            class="progress-bar bg-{{ $card['color'] }}"
                            role="progressbar"
                            style="width: {{ $card['value'] }}%;"
                            aria-valuenow="{{ $card['value'] }}"
                            aria-valuemin="0"
                            aria-valuemax="100">
                        </div>

                    </div>

                    <small class="text-muted">
                        Current Fill Level
                    </small>

                </div>

            </div>

        </div>

    @endforeach

</div>