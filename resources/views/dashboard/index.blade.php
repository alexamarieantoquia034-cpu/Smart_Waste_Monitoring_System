@extends('layouts.app')

@section('content')

<div class="container-fluid">

    @include('dashboard.header')

    @include('dashboard.cards')

    <div class="row mb-4">

        <div class="col-lg-8">
            @include('dashboard.charts')
        </div>

        <div class="col-lg-4">
            @include('dashboard.classification')
        </div>

    </div>

    <div class="row g-4">

        <div class="col-lg-6">
            @include('dashboard.alerts')
        </div>

        <div class="col-lg-6">
            @include('dashboard.dss')
        </div>

    </div>

</div>

@include('dashboard.script')

@endsection