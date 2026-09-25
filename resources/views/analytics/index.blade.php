@extends('layouts.app')

@section('content')

<div class="container-fluid">

    <h2 class="mb-4">
        Historical Analytics
    </h2>

    <div class="row">

        <!-- Historical Fill Level Trend -->
        <div class="col-lg-12 mb-4">

            <div class="card shadow">

                <div class="card-header">
                    Historical Fill Level Trends
                </div>

                <div class="card-body">

                    <canvas id="fillLevelChart" height="100"></canvas>

                    <div class="text-center text-muted mt-3">
                        No historical fill level data available.
                    </div>

                </div>

            </div>

        </div>

        <!-- Waste Generation Trend -->
        <div class="col-lg-6 mb-4">

            <div class="card shadow">

                <div class="card-header">
                    Waste Generation Trend
                </div>

                <div class="card-body">

                    <canvas id="wasteTrendChart"></canvas>

                    <div class="text-center text-muted mt-3">
                        No waste generation records available.
                    </div>

                </div>

            </div>

        </div>

        <!-- Peak Disposal Time -->
        <div class="col-lg-6 mb-4">

            <div class="card shadow">

                <div class="card-header">
                    Peak Disposal Time
                </div>

                <div class="card-body">

                    <canvas id="peakTimeChart"></canvas>

                    <div class="text-center text-muted mt-3">
                        No disposal activity recorded.
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection

@section('scripts')

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

// Historical Fill Level
new Chart(document.getElementById('fillLevelChart'),{

    type:'line',

    data:{
        labels:[],
        datasets:[]
    },

    options:{
        responsive:true,
        plugins:{
            legend:{
                display:false
            }
        }
    }

});

// Waste Generation
new Chart(document.getElementById('wasteTrendChart'),{

    type:'bar',

    data:{
        labels:[],
        datasets:[]
    },

    options:{
        responsive:true,
        plugins:{
            legend:{
                display:false
            }
        }
    }

});

// Peak Disposal Time
new Chart(document.getElementById('peakTimeChart'),{

    type:'bar',

    data:{
        labels:[],
        datasets:[]
    },

    options:{
        responsive:true,
        plugins:{
            legend:{
                display:false
            }
        }
    }

});

</script>

@endsection