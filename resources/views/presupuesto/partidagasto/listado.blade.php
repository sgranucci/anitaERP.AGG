@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $partidagasto = $partidagasto ?? collect();
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect($partidagasto));
    $totalFilas = is_countable($partidagasto) ? count($partidagasto) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Partidas de Gastos</title>
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
					<img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] ?? '' }}" style="max-height: 56px; max-width: 180px; margin-right: 10px; margin-bottom: 4px; vertical-align: middle;">
				@endforeach
			</td>
			<td style="width: 40%; text-align: center;">
				<h2 style="margin: 0; font-size: 20px; font-weight: bold;">Partidas de Gastos</h2>
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
				<th>Empresa</th>
				<th>Presupuesto</th>
				<th>Escenario</th>
				<th>Centro de Costo</th>
				<th>Partida</th>
				<th>Detalle</th>
				<th>Articulo</th>
				<th>Proveedor</th>
				<th>Cuenta Contable</th>
				<th style="width: 5%;">Moneda</th>
				<th style="width: 7%;">Monto Total</th>
				<th style="width: 6%;">Estado</th>
				<th style="width: 12%;">Apertura</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($partidagasto as $data)
				@php $montoTotal = 0; @endphp
				@foreach($data->partidagasto_montos as $partida)
					@php $montoTotal += $partida->monto; @endphp
				@endforeach
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
					<td>{{number_format($montoTotal,2)}}</td>
					<td>{{$data->estado}}</td>
					<td>
						@foreach($data->partidagasto_montos as $partida)
							{{$partida->periodo}} {{number_format($partida->monto,2)}}@if(!$loop->last); @endif
						@endforeach
					</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
