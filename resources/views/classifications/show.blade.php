@extends('layouts.app')

@section('content')

<div class="container-fluid">

    <h2 class="mb-4">
        Classification Details
    </h2>

    <div class="row g-4">

        <div class="col-lg-5">

            <div class="card shadow h-100">

                <div class="card-header fw-bold">
                    Captured Image
                </div>

                <div class="card-body d-flex align-items-center justify-content-center bg-light">

                    @if($classification->image_path)

                        <img src="{{ asset($classification->image_path) }}"
                             class="img-fluid rounded" alt="Classification image">

                    @else

                        <span class="text-muted">No image captured</span>

                    @endif

                </div>

            </div>

        </div>

        <div class="col-lg-7">

            <div class="card shadow h-100">

                <div class="card-header fw-bold">
                    Classification Information
                </div>

                <div class="card-body">

                    <table class="table">

                        <tr>
                            <th style="width: 35%">Waste Type</th>
                            <td>
                                <span class="badge bg-primary fs-6">
                                    {{ ucfirst($classification->waste_type) }}
                                </span>
                            </td>
                        </tr>

                        <tr>
                            <th>Confidence</th>
                            <td>{{ number_format($classification->confidence, 2) }}%</td>
                        </tr>

                        <tr>
                            <th>Compartment</th>
                            <td>{{ ucfirst($classification->compartment) }}</td>
                        </tr>

                        <tr>
                            <th>Sensor Reading ID</th>
                            <td>{{ $classification->sensor_data_id }}</td>
                        </tr>

                        <tr>
                            <th>Date Captured</th>
                            <td>{{ $classification->created_at->format('M d, Y h:i:s A') }}</td>
                        </tr>

                    </table>

                    <a href="{{ route('classifications.index') }}"
                       class="btn btn-secondary">
                        ← Back to Classifications
                    </a>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection