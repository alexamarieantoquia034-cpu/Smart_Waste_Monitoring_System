@php
    use Illuminate\Support\Js;
@endphp

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

@if($chartData->count())

<script>

(function () {
    var ctx = document.getElementById('fillChart');
    if (!ctx) return;

    // Match the compartment colours used by the KPI cards
    var palette = {
        plastic: '#3b82f6',
        paper: '#f59e0b',
        bio: '#16a34a',
        reject: '#e11d48'
    };

    function dataset(label, data, color) {
        return {
            label: label,
            data: data,
            borderColor: color,
            backgroundColor: color + '1f',
            borderWidth: 2.5,
            tension: 0.35,
            fill: true,
            pointRadius: 0,
            pointHoverRadius: 5,
            pointHoverBackgroundColor: color,
            pointHoverBorderColor: '#fff',
            pointHoverBorderWidth: 2
        };
    }

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: {{ Js::from($labels) }},
            datasets: [
                dataset('Plastic', {{ Js::from($plasticData) }}, palette.plastic),
                dataset('Paper', {{ Js::from($paperData) }}, palette.paper),
                dataset('Biodegradable', {{ Js::from($bioData) }}, palette.bio),
                dataset('Reject', {{ Js::from($rejectData) }}, palette.reject)
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 7,
                        boxHeight: 7,
                        padding: 16,
                        font: { size: 11, weight: '600' },
                        color: '#64748b'
                    }
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    padding: 11,
                    cornerRadius: 9,
                    titleFont: { size: 12, weight: '600' },
                    bodyFont: { size: 12 },
                    displayColors: true,
                    usePointStyle: true,
                    callbacks: {
                        label: function (item) {
                            return item.dataset.label + ': ' + item.parsed.y + '%';
                        }
                    }
                }
            },
            scales: {
                y: {
                    min: 0,
                    max: 100,
                    border: { display: false },
                    grid: { color: 'rgba(148,163,184,.18)', drawTicks: false },
                    ticks: {
                        stepSize: 25,
                        padding: 8,
                        color: '#94a3b8',
                        font: { size: 11 },
                        callback: function (value) { return value + '%'; }
                    }
                },
                x: {
                    border: { display: false },
                    grid: { display: false },
                    ticks: {
                        padding: 8,
                        color: '#94a3b8',
                        font: { size: 11 },
                        maxRotation: 0,
                        autoSkipPadding: 16
                    }
                }
            }
        }
    });
})();

</script>

@endif
