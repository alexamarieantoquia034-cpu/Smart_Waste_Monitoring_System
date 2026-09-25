<div class="card shadow h-100">

    <div class="card-header fw-bold">
        Latest Waste Classification
    </div>

    <div class="card-body">

        @if($classification)

            @if($classification->image_path)

                <img
                    src="{{ asset($classification->image_path) }}"
                    class="img-fluid rounded mb-3">

            @endif

            <table class="table table-sm">

                <tr>
                    <th>Waste Type</th>
                    <td>{{ $classification->waste_type }}</td>
                </tr>

                <tr>
                    <th>Confidence</th>
                    <td>{{ $classification->confidence }}%</td>
                </tr>

                <tr>
                    <th>Date</th>
                    <td>{{ $classification->created_at->format('F d, Y') }}</td>
                </tr>

                <tr>
                    <th>Time</th>
                    <td>{{ $classification->created_at->format('h:i A') }}</td>
                </tr>

            </table>

            <div class="text-end mt-3">
                <a href="{{ route('classifications.show', $classification) }}"
                    class="btn btn-sm btn-primary">
                    View Details
                </a>
            </div>
            
        @else

            <div class="text-center py-5 text-muted">
                No classification available.
            </div>

        @endif

    </div>

</div>