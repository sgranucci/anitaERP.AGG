@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
    foreach ($datas as $row) {
        $row->nombreempresa = (string) ($row->empresa->nombre ?? config('app.empresa'));
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Numerador fiscal local</title>
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
				<h2 style="margin: 0; font-size: 18px; font-weight: bold;">Numerador fiscal local</h2>
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
				<th>PV</th>
				<th>Nombre</th>
				<th>Modo</th>
				<th>Tipo ARCA</th>
				<th>Serie</th>
				<th>Último</th>
				<th>Piso</th>
				<th>Próximo</th>
				<th>Máx. venta</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($datas as $data)
			<tr>
				<td>{{ $data->puntoventa->codigo ?? $data->puntoventa_id }}</td>
				<td>{{ $data->puntoventa->nombre ?? '' }}</td>
				<td>{{ $data->puntoventa->modofacturacion ?? '' }}</td>
				<td>{{ str_pad((string) $data->codigo_afip, 3, '0', STR_PAD_LEFT) }}</td>
				<td>{{ TipotransaccionCodigoAfipSupport::etiqueta((int) $data->codigo_afip) }}</td>
				<td>{{ (int) $data->ultimo_numero }}</td>
				<td>{{ (int) $data->piso }}</td>
				<td>{{ (int) $data->proximo }}</td>
				<td>{{ $data->max_venta !== null ? (int) $data->max_venta : '' }}</td>
			</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
