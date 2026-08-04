@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($asientos);
    $totalFilas = is_countable($asientos) ? count($asientos) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Asientos contables</title>
	<style>
		body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
		table.data {
			font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
			border-collapse: collapse;
			width: 100%;
			table-layout: fixed;
		}
		table.data td, table.data th {
			border: 1px solid #cccccc;
			text-align: left;
			padding: 4px;
			vertical-align: top;
			word-wrap: break-word;
		}
		table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
		table.data thead tr { background-color: #85C1E9; }
		table.data th {
			font-size: 7px;
			font-weight: bold;
			color: #17202A;
		}
		.listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
		.listado-header td { vertical-align: middle; border: none; }
		.meta { font-size: 8px; color: #444; margin-top: 4px; }
	</style>
</head>
<body>
	<table class="listado-header">
		<tr>
			<td style="width: 35%;">
				@foreach ($logosCabecera as $logo)
					<img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 56px; max-width: 180px; margin-right: 10px; margin-bottom: 4px; vertical-align: middle;">
				@endforeach
			</td>
			<td style="width: 40%; text-align: center;">
				<h2 style="margin: 0; font-size: 18px; font-weight: bold;">Listado de asientos contables</h2>
				<div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
			</td>
			<td style="width: 25%; text-align: right; font-size: 8px;">
				@if ($totalFilas > 0)
					Registros: {{ $totalFilas }}
				@endif
			</td>
		</tr>
	</table>
	<table class="data">
		<thead>
			<tr>
				<th style="width: 4%;">ID</th>
				<th style="width: 10%;">Empresa</th>
				<th style="width: 6%;">Número</th>
				<th style="width: 6%;">Fecha</th>
				<th style="width: 9%;">Tipo</th>
				<th style="width: 12%;">Observaciones</th>
				<th style="width: 6%;">Monto</th>
				<th style="width: 7%;">Cuenta</th>
				<th style="width: 12%;">Descripción</th>
				<th style="width: 8%;">C. costo</th>
				<th style="width: 5%;">Debe</th>
				<th style="width: 5%;">Haber</th>
				<th style="width: 5%;">Moneda</th>
				<th style="width: 5%;">Cotiz.</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($asientos as $data)
				@php $flPrimerMovimiento = true; @endphp
				@foreach($data->asiento_movimientos as $movimiento)
					<tr>
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
							<td></td><td></td><td></td><td></td><td></td><td></td><td></td>
						@endif
						<td>{{ $movimiento->cuentacontables->codigo ?? '' }}</td>
						<td>{{ $movimiento->cuentacontables->nombre ?? '' }}</td>
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
						<td>{{ $movimiento->monedas->nombre ?? '' }}</td>
						<td>{{ $movimiento->cotizacion }}</td>
					</tr>
				@endforeach
			@endforeach
		</tbody>
	</table>
</body>
</html>
