<div class="card shadow h-100">

    <div class="card-header fw-bold">
        Active Alerts
    </div>

    <div class="card-body">

        @if($alerts->count())

            <table class="table table-bordered table-hover">

                <thead>

                    <tr>

                        <th>Compartment</th>
                        <th>Status</th>
                        <th>Date</th>

                    </tr>

                </thead>

                <tbody>

                    @foreach($alerts as $alert)

                        <tr>

                            <td>{{ ucfirst($alert->compartment) }}</td>

                            <td>

                                @if($alert->status=='Full')

                                    <span class="badge bg-danger">
                                        Full
                                    </span>

                                @elseif($alert->status=='Near Full')

                                    <span class="badge bg-warning text-dark">
                                        Near Full
                                    </span>

                                @else

                                    <span class="badge bg-secondary">
                                        {{ $alert->status }}
                                    </span>

                                @endif

                            </td>

                            <td>
                                {{ $alert->created_at->format('M d, Y h:i A') }}
                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        @else

            <div class="alert alert-success mb-0">

                No active alerts.

            </div>

        @endif

    </div>

</div>