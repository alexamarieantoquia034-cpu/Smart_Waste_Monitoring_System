<div class="sw-card h-100">

    <div class="sw-card__head">
        <div>
            <h3 class="sw-card__title">
                <i class="bi bi-bell"></i> Active alerts
            </h3>
            <p class="sw-card__sub">Compartments that need attention</p>
        </div>
        <a href="{{ route('alerts') }}" class="sw-pill sw-pill--muted">View all</a>
    </div>

    <div class="sw-card__body p-0">

        @if($alerts->count())

            <div class="sw-scroll-x">
                <table class="sw-table">

                    <thead>
                        <tr>
                            <th>Compartment</th>
                            <th>Status</th>
                            <th>Detected</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($alerts as $alert)
                            <tr>
                                <td style="font-weight:600">{{ ucfirst($alert->compartment) }}</td>
                                <td>
                                    @if($alert->status == 'Full')
                                        <span class="sw-pill sw-pill--danger">Full</span>
                                    @elseif($alert->status == 'Near Full')
                                        <span class="sw-pill sw-pill--warn">Near full</span>
                                    @else
                                        <span class="sw-pill sw-pill--ok">Normal</span>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $alert->created_at->format('M d, h:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>

                </table>
            </div>

        @else

            <div class="sw-empty">
                <i class="bi bi-shield-check"></i>
                All compartments are within capacity.
            </div>

        @endif

    </div>

</div>