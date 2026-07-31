@php
    use App\Support\Configuracion\CotizacionListadoColumnas;
    use App\Support\Configuracion\EmpresaLogoArchivo;
    foreach ($datas as $row) {
        $row->nombreempresa = (string) config('app.empresa');
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
    $monedasColumnas = $monedasColumnas ?? collect();
    $cantidadMonedas = $monedasColumnas->count();
    $anchoFijo = 12;
    $anchoMoneda = $cantidadMonedas > 0 ? max(8, (100 - $anchoFijo) / ($cantidadMonedas * 2)) : 10;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Cotizaciones</title>
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
			padding: 3px;
			vertical-align: middle;
			word-wrap: break-word;
		}
		table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
		table.data thead tr { background-color: #85C1E9; }
		table.data th {
			font-size: 7px;
			font-weight: bold;
			color: #17202A;
			text-align: center;
		}
		table.data td.num { text-align: right; }
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
				<h2 style="margin: 0; font-size: 18px; font-weight: bold;">Listado de cotizaciones</h2>
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
				<th style="width: 5%;" rowspan="2">ID</th>
				<th style="width: 7%;" rowspan="2">Fecha</th>
				@foreach ($monedasColumnas as $moneda)
					<th colspan="2">{{ $moneda->nombre }}</th>
				@endforeach
			</tr>
			<tr>
				@foreach ($monedasColumnas as $moneda)
					<th style="width: {{ number_format($anchoMoneda, 1) }}%;">Compra</th>
					<th style="width: {{ number_format($anchoMoneda, 1) }}%;">Venta</th>
				@endforeach
			</tr>
		</thead>
		<tbody>
			@foreach ($datas as $data)
				@php
					$mapa = CotizacionListadoColumnas::mapaPorMoneda($data);
				@endphp
				<tr>
					<td>{{ $data->id }}</td>
					<td>{{ $data->fecha ? \Illuminate\Support\Carbon::parse($data->fecha)->format('d/m/Y') : '' }}</td>
					@foreach ($monedasColumnas as $moneda)
						@php
							$vals = $mapa[(int) $moneda->id] ?? ['compra' => null, 'venta' => null];
						@endphp
						<td class="num">{{ CotizacionListadoColumnas::formatear($vals['compra']) }}</td>
						<td class="num">{{ CotizacionListadoColumnas::formatear($vals['venta']) }}</td>
					@endforeach
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
