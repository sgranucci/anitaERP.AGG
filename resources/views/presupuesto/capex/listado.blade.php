<!DOCTYPE html>
<html>
	<title>Capex</title>
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
		<h2>Capex</h2>
		<table class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<th class="width20">ID</th>
					<th>Empresa</th>
					<th>Presupuesto</th>
					<th>Centro de Costo</th>
					<th>Nombre</th>
					<th>Detalle</th>
					<th>Codigo de Proyecto</th>
					<th>Nro. de Proyecto</th>
					<th>Estado</th>
					<th style="width: 15%;">Partidas</th>
					<th class="width40" data-orderable="false"></th>
				</tr>
			</thead>
			<tbody>
				@foreach ($capex as $data)
					<tr>
						<td>{{$data->id}}</td>
						<td>{{$data->nombreempresa ?? ''}}</td>
						<td>{{$data->nombrepresupuesto ?? ''}}</td>
						<td>{{$data->nombrecentrocosto ?? '' }}</td>
						<td>{{$data->nombre ?? ''}}</td>
						<td>{{$data->detalle ?? ''}}</td>
						<td>{{$data->codigoproyecto}}</td>
						<td>{{$data->codigo}}</td>
						<td>{{$data->estado}}</td>
						<td>
							<ul>
								@foreach($data->capex_partidas as $partida)

									@php $montoTotal = 0; @endphp
									@foreach($partida->capex_partida_montos as $monto)
										@php $montoTotal += $monto->monto; @endphp
									@endforeach

									<li>Nro.{{$partida->codigo}} {{$partida->nombre}} {{ $partida->monedas->abreviatura ?? ''}} {{number_format($montoTotal,2)}}</li>
								@endforeach
							</ul>
						</td>
					</tr>
				@endforeach
			</tbody>
		</table>
	</body>
</html>