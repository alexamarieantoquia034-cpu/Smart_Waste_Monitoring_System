{{-- Shared recent-detections list, reused by the live page after each save. --}}
<ul class="ml-recent list-unstyled mb-0">
    @foreach ($logs as $log)
        {{-- data-detection-id lets live-stream.js recognise a row that is
             already on the page instead of appending it again. --}}
        <li class="ml-recent__row" data-detection-id="{{ $log->id }}">
            <img src="{{ $log->image_path ? asset($log->image_path) : asset('images/barbie-pattern.jpg') }}"
                 alt="{{ $log->waste_type }}"
                 width="44" height="44"
                 class="ml-recent__thumb"
                 onerror="this.src='{{ asset('images/barbie-pattern.jpg') }}'">
            <div class="ml-recent__meta">
                <div class="ml-recent__title">{{ $log->waste_type }}</div>
                <div class="ml-recent__sub">
                    {{ ucfirst($log->compartment) }} bin
                    &middot; {{ $log->created_at->format('M d, Y h:i A') }}
                </div>
            </div>
            <span class="sw-pill sw-pill--info ms-auto">
                {{ number_format($log->confidence, 1) }}%
            </span>
        </li>
    @endforeach
</ul>
