@extends('layouts.app')

@section('content')

<div class="container-fluid">

    <h2 class="mb-4">
        Classification Logs
    </h2>

    <div class="card shadow">

        <div class="card-header fw-bold">
            Waste Classification History
        </div>

        <div class="card-body">

            @if($classifications->count())

            <div class="table-responsive">

            <table class="table table-bordered table-hover align-middle">

            <thead class="table-light">

            <tr>
                <th>ID</th>
                <th>Image</th>
                <th>Waste Type</th>
                <th>Confidence</th>
                <th>Compartment</th>
                <th>Date & Time</th>
                <th>Action</th>
            </tr>

            </thead>

            <tbody>

            @forelse($classifications as $classification)

            <tr>

                <td>{{ $classification->id }}</td>

                <td>
                    @if($classification->image_path)
                        <img src="{{ asset($classification->image_path) }}"
                             width="48" height="48"
                             class="rounded object-fit-cover"
                             alt="Classification image">
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>

                <td>{{ ucfirst($classification->waste_type) }}</td>

                <td>{{ number_format($classification->confidence, 2) }}%</td>

                <td>{{ ucfirst($classification->compartment) }}</td>

                <td>{{ $classification->created_at->format('M d, Y h:i A') }}</td>

                <td>
                    <a href="{{ route('classifications.show', $classification) }}"
                       class="btn btn-sm btn-outline-primary">
                        View
                    </a>
                </td>

            </tr>

            @empty

            <tr>

            <td colspan="7" class="text-center">

            No classification records yet.

            </td>

            </tr>

            @endforelse

            </tbody>

            </table>

            </div>

            <div>
                {{ $classifications->links() }}
            </div>

            @else

                <div class="alert alert-info mb-0">

                    No classifications have been recorded yet.

                </div>

            @endif

        </div>

    </div>

</div>

@endsection