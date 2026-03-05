<!DOCTYPE html>
<html>
	<title>Asientos</title>
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
		<h2>Asientos</h2>
		<table class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<th class="width20">ID</th>
					<th>Empresa</th>
					<th>Número</th>
					<th>Fecha</th>
					<th>Tipo de asiento</th>
					<th>Observaciones</th>
					<th>Monto Total</th>
					<th>Cuenta</th>
					<th>Descripción</th>
					<th>Centro de costo</th>
					<th>Debe</th>
					<th>Haber</th>
					<th>Moneda</th>
					<th>Cotizacion</th>
					<th>Detalle</th>
				</tr>
			</thead>
			<tbody>
				@foreach ($asientos as $data)
					@php $flPrimerMovimiento = true; @endphp
					@foreach($data->asiento_movimientos as $movimiento)
						<tr data-entry-id="{{ $data->id }}">
							@if ($flPrimerMovimiento)
								<td>{{$data->id}}</td>
								<td>{{$data->nombreempresa}}</td>
								<td>{{$data->numeroasiento}}</td>
								<td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
								<td>{{$data->nombretipoasiento}}</td>
								<td>{{$data->observacion ?? ''}}</td>
								<td>
									@php $totalAsiento = 0; @endphp
									@foreach($data->asiento_movimientos as $mov)
										@php $totalAsiento += ($mov->monto > 0 ? $mov->monto : 0); @endphp
									@endforeach
									{{number_format($totalAsiento,2)}}
								</td>
								@php $flPrimerMovimiento = false; @endphp
							@else
								<td colspan='7'></td>
							@endif
							<td>{{ $movimiento->cuentacontables->codigo }}</td>
							<td>{{ $movimiento->cuentacontables->nombre }}</td>
							<td>{{ $movimiento->centrocostos->nombre ?? '' }}</td>
							<td>
								@if ($movimiento->monto >= 0)
									{{number_format($movimiento->monto,2)}}
								@endif
							</td>
							<td>
								@if ($movimiento->monto < 0)
									{{number_format(abs($movimiento->monto),2)}}
								@endif
							</td>
							<td>{{ $movimiento->monedas->nombre }}</td>
							<td>{{ $movimiento->cotizacion }}</td>
							<td>{{ $movimiento->observacion }}</td>
						</tr>
					@endforeach
				@endforeach
			</tbody>
		</table>
	</body>
</html>