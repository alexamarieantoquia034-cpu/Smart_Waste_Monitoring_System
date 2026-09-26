@php
    $unreadAlerts = \App\Models\Alert::where('is_resolved', false)->count();
@endphp

<aside class="sw-sidebar" id="appSidebar">

    <div class="sw-sidebar__brand">
        <div class="sw-sidebar__mark">
            <img src="{{ asset('images/barbie-hero.jpg') }}" alt="Smart Waste">
        </div>
        <div>
            <div class="sw-sidebar__name">Smart Waste</div>
            <div class="sw-sidebar__tag">Monitoring</div>
        </div>
    </div>

    <nav class="sw-sidebar__nav">

        <div class="sw-sidebar__label">Overview</div>

        <a href="{{ route('dashboard') }}"
           class="sw-navlink {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <a href="{{ route('analytics') }}"
           class="sw-navlink {{ request()->routeIs('analytics') ? 'is-active' : '' }}">
            <i class="bi bi-graph-up-arrow"></i> Analytics
        </a>

        <div class="sw-sidebar__label mt-4">Operations</div>

        <a href="{{ route('alerts') }}"
           class="sw-navlink {{ request()->routeIs('alerts') ? 'is-active' : '' }}">
            <i class="bi bi-bell"></i> Alerts
            @if($unreadAlerts > 0)
                <span class="ms-auto sw-pill sw-pill--danger">{{ $unreadAlerts }}</span>
            @endif
        </a>

        <a href="{{ route('classifications.index') }}"
           class="sw-navlink {{ request()->routeIs('classifications.index', 'classifications.show') ? 'is-active' : '' }}">
            <i class="bi bi-cpu"></i> Classification
        </a>

        <a href="{{ route('classifications.live') }}"
           class="sw-navlink {{ request()->routeIs('classifications.live') ? 'is-active' : '' }}">
            <i class="bi bi-camera-video"></i> Live Classification
        </a>

        <a href="{{ route('reports') }}"
           class="sw-navlink {{ request()->routeIs('reports') ? 'is-active' : '' }}">
            <i class="bi bi-file-earmark-text"></i> Reports
        </a>

        <div class="sw-sidebar__label mt-4">Administration</div>

        <a href="{{ route('users') }}"
           class="sw-navlink {{ request()->routeIs('users') ? 'is-active' : '' }}">
            <i class="bi bi-people"></i> Users
        </a>

        <a href="{{ route('dss') }}"
           class="sw-navlink {{ request()->routeIs('dss') ? 'is-active' : '' }}">
            <i class="bi bi-signpost-split"></i> DSS
        </a>

        <a href="{{ route('settings') }}"
           class="sw-navlink {{ request()->routeIs('settings') ? 'is-active' : '' }}">
            <i class="bi bi-sliders"></i> Settings
        </a>

    </nav>

    <div class="sw-sidebar__foot">
        <a href="{{ route('home') }}" class="sw-navlink">
            <i class="bi bi-house"></i> Public site
        </a>
    </div>

</aside>

<div class="d-lg-none" id="sidebarBackdrop"
     style="position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1040;display:none;"></div>
