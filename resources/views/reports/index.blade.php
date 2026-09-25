@extends('layouts.app')

@section('content')

<div class="container-fluid">

    <h2 class="mb-4">
        Reports
    </h2>

    <div class="row">

        <!-- Export Reports -->

        <div class="col-lg-4 mb-4">

            <div class="card shadow">

                <div class="card-header">
                    Export Reports
                </div>

                <div class="card-body">

                    <button class="btn btn-success w-100 mb-2" disabled>
                        Export Excel (.xlsx)
                    </button>

                    <button class="btn btn-danger w-100 mb-2" disabled>
                        Generate Weekly PDF
                    </button>

                    <button class="btn btn-primary w-100" disabled>
                        Generate Monthly PDF
                    </button>

                    <small class="text-muted">
                        Report generation is unavailable until sensor data is collected.
                    </small>

                </div>

            </div>

        </div>

        <!-- Sustainability Report -->

        <div class="col-lg-4 mb-4">

            <div class="card shadow">

                <div class="card-header">
                    Sustainability Report
                </div>

                <div class="card-body">

                    <p class="text-muted">

                        Download sustainability reports for university documentation.

                    </p>

                    <button class="btn btn-secondary w-100" disabled>

                        Download Template

                    </button>

                </div>

            </div>

        </div>

        <!-- Report History -->

        <div class="col-lg-4 mb-4">

            <div class="card shadow">

                <div class="card-header">
                    Report History
                </div>

                <div class="card-body">

                    <table class="table table-bordered">

                        <thead>

                            <tr>

                                <th>Date</th>

                                <th>Report</th>

                            </tr>

                        </thead>

                        <tbody>

                            <tr>

                                <td colspan="2" class="text-center text-muted">

                                    No reports available.

                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection