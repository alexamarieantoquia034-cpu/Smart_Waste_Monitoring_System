@extends('layouts.app')

@section('content')

<div class="container-fluid">

    <h2 class="mb-4">
        Alert Management
    </h2>

    <div class="card shadow">

        <div class="card-header fw-bold">
            Active Alerts
        </div>

        <div class="card-body">

            @if($alerts->count())

            <table class="table table-bordered table-hover align-middle">
            
            <thead class="table-light">
            
            <tr>
                <th>ID</th>
                <th>Compartment</th>
                <th>Message</th>
                <th>Status</th>
                <th>DSS Recommendation</th>
                <th>Date & Time</th>
            </tr>
            
            </thead>
            
            <tbody>

            @forelse($alerts as $alert)

            <tr>
            
                <td>{{ $alert->id }}</td>

                <td>{{ ucfirst($alert->compartment) }}</td>

                <td>{{ $alert->message }}</td>

                <td>

                    @if($alert->status == 'Full')

                        <span class="badge bg-danger">
                            Full
                        </span>

                    @elseif($alert->status == 'Near Full')

                        <span class="badge bg-warning text-dark">
                            Near Full
                        </span>

                    @else

                        <span class="badge bg-success">
                            Normal
                        </span>

                    @endif

                </td>

                <td>
    
                    @if($alert->status == 'Full')

                        <span class="text-danger fw-bold">
                            Collect Immediately
                        </span>

                    @elseif($alert->status == 'Near Full')

                        <span class="text-warning fw-bold">
                            Schedule Collection
                        </span>

                    @else

                        <span class="text-success fw-bold">
                            Continue Monitoring
                        </span>

                    @endif

                </td>

                <td>
                    {{ $alert->created_at->format('M d, Y h:i A') }}

                </td>

            </tr>

            @empty

            <tr>

            <td colspan="6" class="text-center">

            No Active Alerts

            </td>

            </tr>

            @endforelse

            </tbody>
            
            </table>

            @else

                <div class="alert alert-success mb-0">

                    No active alerts.

                </div>

            @endif

        </div>

    </div>

</div>

@endsection