@extends("theme.$theme.layout")
@section('titulo')
Grafico de Velas
@endsection

@section("scripts")
	<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
	<script type="text/javascript">
	
	updateStats();
	
	window.setInterval(function () {
		updateStats();
	}, 60000);
		
	function updateStats()
	{
		let f = new Date();
		let fecha = f.getDate() + "-" + (f.getMonth() +1) + "-" + f.getFullYear();
				
		$.get('/anitaERP/public/graficos/leerDatosLecturas/'+fecha+'/'+'1', function(data){
			dibujaGrafico(JSON.parse(data));
		});
	}

	function dibujaGrafico(value)
	{
		var lecturas = value;
		var data01 = [];
		$.each(lecturas, function(data, value) {
			var h = value.hora.toString();
			var rowItem = [h, value.low, value.open, value.close, value.high];
			data01.push(rowItem);
			})

		google.charts.load('current', {
				packages:['corechart']
			}).then(function() {
			drawChart();
			});

		function drawChart() {
			var data = new google.visualization.DataTable();
			data.addColumn('timeofday', 'hora');
			data.addColumn('number', 'low');
			data.addColumn('number', 'open');
			data.addColumn('number', 'close');
			data.addColumn('number', 'high');

			var formattedData = [];
			var separador = ',';
			for (var i = 0; i < data01.length; i++) {
				var fila = data01[i];
				var columnas = fila.toString().split(separador);
				var hh = parseInt(columnas[0].toString().substring(0,2));
				var mm = parseInt(columnas[0].toString().substring(3,5));
				var low = parseFloat(columnas[1]);
				var open = parseFloat(columnas[2]);
				var close = parseFloat(columnas[3]);
				var high = parseFloat(columnas[4]);
				data.addRow([[hh,mm,0], low, open, close, high]);
			}

			var options = {
					legend: 'none',
					candlestick: {
						fallingColor: { strokeWidth: 0, fill: '#a52714' }, // red
						risingColor: { strokeWidth: 0, fill: '#0f9d58' }   // green
					},
					chartArea: { left: 50, top: 20, width: '100%', height: '75%' },
					seriesType: 'candlesticks',
					hAxis: { title: 'Tiempo',
							interval: 1,
							gridlines: { count: -1, },
							format: 'hh:mm' },
					vAxis: {
							format: '#,###',
							gridlines: { count: -1, },
							},
			};

			var chart = new google.visualization.CandlestickChart(document.getElementById('chart_div'));

			chart.draw(data, options);
		}
  	}
    </script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Velas ESU22</h3>
                <div class="card-tools">
                </div>
            </div>
            <div class="card-body table-responsive p-0">
    			<div id="chart_div" style="width: 1100px; height: 600px;"></div>
            </div>
        </div>
    </div>
</div>
@endsection
