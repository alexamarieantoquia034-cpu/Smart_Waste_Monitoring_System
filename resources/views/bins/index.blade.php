@extends('layouts.app')

@section('content')

<div class="container-fluid">

    <h2 class="mb-4">Waste Bins</h2>

    <div class="card shadow">

        <div class="card-header">
            Bin Monitoring
        </div>

        <div class="card-body">

            <table class="table table-bordered">

                <thead>

                    <tr>
                        <th>Bin Code</th>
                        <th>Waste Type</th>
                        <th>Fill Level</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                    </tr>

                </thead>

                <tbody>

                @forelse($bins as $bin)

                    <tr>

                        <td>{{ $bin->bin_code }}</td>

                        <td>{{ $bin->waste_type }}</td>

                        <td>{{ $bin->fill_level }}%</td>

                        <td>{{ $bin->status }}</td>

                        <td>{{ $bin->last_updated ?? '--' }}</td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="5" class="text-center">

                            No waste bins available.

                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

@endsection