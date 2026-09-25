<div class="card shadow h-100">

    <div class="card-header fw-bold">
        Decision Support Recommendation
    </div>

    <div class="card-body">

        @if($sensorData)

            @php

                $levels = [

                    'Plastic' => $sensorData->plastic_level,

                    'Paper' => $sensorData->paper_level,

                    'Biodegradable' => $sensorData->biodegradable_level,

                    'Reject' => $sensorData->reject_level

                ];

                arsort($levels);

                $priorityBin = array_key_first($levels);

                $priorityLevel = current($levels);

            @endphp

            <table class="table">

                <tr>

                    <th>Highest Priority</th>

                    <td>{{ $priorityBin }}</td>

                </tr>

                <tr>

                    <th>Fill Level</th>

                    <td>{{ $priorityLevel }}%</td>

                </tr>

            </table>

            @if($priorityLevel >= 90)

                <div class="alert alert-danger">

                    <strong>Collect Immediately</strong>

                </div>

            @elseif($priorityLevel >= 80)

                <div class="alert alert-warning">

                    <strong>High Priority</strong>

                </div>

            @elseif($priorityLevel >= 60)

                <div class="alert alert-info">

                    <strong>Moderate Priority</strong>

                </div>

            @else

                <div class="alert alert-success">

                    <strong>Low Priority</strong>

                </div>

            @endif

        @else

            <div class="text-center py-5 text-muted">

                Waiting for sensor data...

            </div>

        @endif

    </div>

</div>