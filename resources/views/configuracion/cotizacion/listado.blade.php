<!DOCTYPE html>
<html>
	<title>Cotizaciones</title>
	<head>
		<style>
			table {
				font-family: arial, sans-serif;
				border-collapse: collapse;
				width: 100%;
			}
			td, th {
				boder: 1px solid #dddddd;
				text-align: left;
				padding: 8px;
			}
			tr:nth-child(even) {
				background-color: #dddddd;
			}
		</style>
	</head>
	<body>
		<h2>Cotizaciones</h2>
		<table class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<th class="width20">ID</th>
					<th>Fecha</th>
					<th>Cotizaciones</th>
				</tr>
			</thead>
			<tbody>
			@foreach ($cotizaciones as $data)
				<tr>
					<td>{{$data->id}}</td>
					<td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
					<td>
						<ul>
						@foreach($data->cotizacion_monedas as $moneda)
							<li>{{ $moneda->monedas->nombre }} Venta {{ number_format($moneda->cotizacionventa,4) }} Compra {{ number_format($moneda->cotizacioncompra,4) }}</li>
						@endforeach
						</ul>
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</body>
</html>