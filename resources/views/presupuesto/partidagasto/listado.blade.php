<!DOCTYPE html>
<html>
	<title>Partidas de Gastos</title>
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
		<h2>Partidas de Gastos</h2>
		<table class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<th class="width20">ID</th>
					<th>Empresa</th>
					<th>Presupuesto</th>
					<th>Escenario</th>
					<th>Centro de Costo</th>
					<th>Partida</th>
					<th>Detalle</th>
					<th>Articulo</th>
					<th>Proveedor</th>
					<th>Cuenta Contable</th>
					<th>Moneda</th>
					<th>Monto Total</th>
					<th>Estado</th>
					<th style="width: 15%;">Apertura</th>
					<th class="width40" data-orderable="false"></th>
				</tr>
			</thead>
			<tbody>
				@foreach ($partidagasto as $data)
					<tr>
						<td>{{$data->id}}</td>
						<td>{{$data->nombreempresa ?? ''}}</td>
						<td>{{$data->nombrepresupuesto ?? ''}}</td>
						<td>{{$data->nombreescenario ?? ''}}</td>
						<td>{{ trim(($data->codigocentrocosto ?? '').' '.($data->nombrecentrocosto ?? '')) }}</td>
						<td>{{$data->codigopartida ?? ''}}</td>
						<td>{{$data->detalle ?? ''}}</td>
						<td>{{$data->descripcionarticulo ?? ''}}</td>
						<td>{{$data->nombreproveedor ?? ''}}</td>
						<td>{{$data->codigocuentacontable}}-{{$data->nombrecuentacontable ?? ''}}</td>
						<td>{{$data->abreviaturamoneda}}</td>
						<td style="text-align: left;">
							@php $montoTotal = 0; @endphp
							@foreach($data->partidagasto_montos as $partida)
								@php $montoTotal += $partida->monto; @endphp
							@endforeach                                
							{{number_format($montoTotal,2)}}
						</td>
						<td>{{$data->estado}}</td>
						<td>
							<ul>
								@foreach($data->partidagasto_montos as $partida)
									<li>{{$partida->periodo}} {{number_format($partida->monto,2)}}</li>
								@endforeach
							</ul>
						</td>
					</tr>
				@endforeach
			</tbody>
		</table>
	</body>
</html>