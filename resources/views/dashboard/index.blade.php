@extends('layouts.app')

@section('content')

<div class="container-fluid">

    <h2 class="mb-4">
        Dashboard
    </h2>

    {{-- Fill Levels --}}

    <div class="row">

        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white shadow">
                <div class="card-body">
                    <h5>Plastic</h5>

                    <h2>
                        {{ $sensorData->plastic_lvl ?? '--' }}
                        @if($sensorData)%
                        @endif
                    </h2>

                    <small>
                        {{ $sensorData ? 'Current Fill Level' : 'Waiting for sensor data...' }}
                    </small>

                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card bg-warning shadow">
                <div class="card-body">

                    <h5>Paper</h5>

                    <h2>
                        {{ $sensorData->paper_lvl ?? '--' }}
                        @if($sensorData)%
                        @endif
                    </h2>

                    <small>
                        {{ $sensorData ? 'Current Fill Level' : 'Waiting for sensor data...' }}
                    </small>

                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card bg-danger text-white shadow">
                <div class="card-body">

                    <h5>Biodegradable</h5>

                    <h2>
                        {{ $sensorData->bio_lvl ?? '--' }}
                        @if($sensorData)%
                        @endif
                    </h2>

                    <small>
                        {{ $sensorData ? 'Current Fill Level' : 'Waiting for sensor data...' }}
                    </small>

                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card bg-dark text-white shadow">
                <div class="card-body">

                    <h5>Reject Bin</h5>

                    <h2>
                        {{ $sensorData->unknown_lvl ?? '--' }}
                        @if($sensorData)%
                        @endif
                    </h2>

                    <small>
                        {{ $sensorData ? 'Current Fill Level' : 'Waiting for sensor data...' }}
                    </small>

                </div>
            </div>
        </div>

    </div>



    {{-- Trend Graph --}}

    <div class="row">

        <div class="col-md-8">

            <div class="card shadow">

                <div class="card-header">
                    Waste Fill Level Trend
                </div>

                <div class="card-body">

                    @if($sensorData)

                        <canvas id="fillChart"></canvas>

                    @else

                        <div class="text-center text-muted py-5">

                            No sensor data available.

                        </div>

                    @endif

                </div>

            </div>

        </div>



        {{-- Latest Classification --}}

        <div class="col-md-4">

            <div class="card shadow">

                <div class="card-header">
                    Latest Waste Classification
                </div>

                <div class="card-body">

                    @if($classification)

                        @if($classification->image_url)

                            <img src="{{ asset($classification->image_url) }}"
                                 class="img-fluid rounded">

                            <hr>

                        @endif

                        <h5>{{ $classification->waste_type }}</h5>

                        <p>
                            Confidence :
                            {{ $classification->confidence }}%
                        </p>

                        <p>
                            {{ $classification->timestamp }}
                        </p>

                    @else

                        <div class="text-center text-muted py-5">

                            No classification available.

                        </div>

                    @endif

                </div>

            </div>

        </div>

    </div>



    <br>



    {{-- Alerts + DSS --}}

    <div class="row">

        <div class="col-md-6">

            <div class="card shadow">

                <div class="card-header">

                    Active Alerts

                </div>

                <div class="card-body">

                    @if(count($alerts))

                        <table class="table">

                            <thead>

                                <tr>

                                    <th>Compartment</th>
                                    <th>Fill Level</th>

                                </tr>

                            </thead>

                            <tbody>

                            @foreach($alerts as $alert)

                                <tr>

                                    <td>{{ $alert->compartment }}</td>

                                    <td>{{ $alert->fill_level }}%</td>

                                </tr>

                            @endforeach

                            </tbody>

                        </table>

                    @else

                        <div class="text-center text-muted py-4">

                            No active alerts.

                        </div>

                    @endif

                </div>

            </div>

        </div>



        <div class="col-md-6">

            <div class="card shadow">

                <div class="card-header">

                    DSS Recommendation

                </div>

                <div class="card-body">

                    @if($recommendation)

                        <h5>

                            {{ $recommendation->recommendation }}

                        </h5>

                        <hr>

                        <p>

                            Priority :
                            {{ $recommendation->priority }}

                        </p>

                    @else

                        <div class="text-center text-muted py-4">

                            No recommendation available.

                        </div>

                    @endif

                </div>

            </div>

        </div>

    </div>

</div>

@endsection