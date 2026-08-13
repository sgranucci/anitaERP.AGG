@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Certificados sanitarios SENASA</title>
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
				<h2 style="margin: 0; font-size: 20px; font-weight: bold;">Listado certificados sanitarios SENASA</h2>
				<div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
			</td>
            <td style="width: 25%; text-align: right; font-size: 8px;">
				@if ($totalFilas > 0)
					Registros: {{ $totalFilas }}<br>
				@endif
				@if (!empty($totalesListado))
					Kilos: {{ number_format((float) $totalesListado['kilos'], 2, ',', '.') }}<br>
					Cajas: {{ number_format((float) $totalesListado['cajas'], 2, ',', '.') }}
				@endif
			</td>
		</tr>
	</table>
	<table class="data">
		<thead>
			<tr>
				<th style="width: 5%;">ID</th>
				<th style="width: 10%;">Nro</th>
				<th style="width: 9%;">Fecha</th>
				<th style="width: 11%;">Cami&oacute;n</th>
				<th style="width: 14%;">Reparto</th>
				<th style="width: 10%;">Precinto</th>
				<th style="width: 7%;">Est. destino</th>
				<th style="width: 9%;">Kilos</th>
				<th style="width: 8%;">Cajas</th>
				<th style="width: 9%;">Nro. interno</th>
				<th style="width: 8%;">Nro. patag&oacute;nico</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($datas as $data)
				<tr>
					<td>{{ $data->id }}</td>
					<td>{{ $data->etiqueta }}</td>
					<td>{{ optional($data->fecha)->format('d/m/Y') }}</td>
					<td>{{ $data->camion->dominio ?? '' }}</td>
					<td>{{ $data->transporte->nombre ?? '' }}</td>
					<td>{{ $data->precinto }}</td>
					<td>{{ $data->establecimiento_destino ?: '' }}</td>
					<td class="num" style="text-align:right;">{{ number_format((float) ($data->kilos_total ?? 0), 2, ',', '.') }}</td>
					<td class="num" style="text-align:right;">{{ number_format((float) ($data->cajas_total ?? 0), 2, ',', '.') }}</td>
					<td>{{ $data->nro_cert_interno ?: '' }}</td>
					<td>{{ $data->nro_cert_patagonico ?: '' }}</td>
				</tr>
			@endforeach
			@if (!empty($totalesListado))
				<tr>
					<td colspan="7" style="font-weight:bold;">TOTAL</td>
					<td style="text-align:right;font-weight:bold;">{{ number_format((float) $totalesListado['kilos'], 2, ',', '.') }}</td>
					<td style="text-align:right;font-weight:bold;">{{ number_format((float) $totalesListado['cajas'], 2, ',', '.') }}</td>
					<td></td>
					<td></td>
				</tr>
			@endif
		</tbody>
	</table>
</body>
</html>
