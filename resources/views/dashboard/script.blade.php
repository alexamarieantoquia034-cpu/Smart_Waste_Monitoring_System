@php
use Illuminate\Support\Js;
@endphp

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

@if($chartData->count())

<script>

const ctx = document.getElementById('fillChart');

new Chart(ctx,{
    type:'line',

    data:{
        labels: {{ Js::from($labels) }},

        datasets:[
            {
                label:'Plastic',
                data: {{ Js::from($plasticData) }},
                borderColor:'green'
            },
            {
                label:'Paper',
                data: {{ Js::from($paperData) }},
                borderColor:'orange'
            },
            {
                label:'Biodegradable',
                data: {{ Js::from($bioData) }},
                borderColor:'red'
            },
            {
                label:'Reject',
                data: {{ Js::from($rejectData) }},
                borderColor:'black'
            }
        ]
    },

    options:{
        responsive:true,
        maintainAspectRatio:false
    }

});

</script>

@endif