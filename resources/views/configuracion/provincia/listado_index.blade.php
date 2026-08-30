@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Configuracion\ProvinciaListadoFiltros;
    foreach ($datas as $row) {
        $row->nombreempresa = (string) config('app.empresa');
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Provincias</title>
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
			<td style="width: 28%;">
				@foreach ($logosCabecera as $logo)
					<img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 52px; max-width: 160px; margin-right: 8px; margin-bottom: 4px; vertical-align: middle;">
				@endforeach
			</td>
			<td style="width: 44%; text-align: center;">
				<h2 style="margin: 0; font-size: 16px; font-weight: bold;">Listado de provincias</h2>
				<div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
			</td>
			<td style="width: 28%; text-align: right; font-size: 8px;">
				@if ($totalFilas > 0)
					Registros: {{ $totalFilas }}
				@endif
			</td>
		</tr>
	</table>
	<table class="data">
		<thead>
			<tr>
				<th style="width: 5%;">ID</th>
				<th style="width: 12%;">Nombre</th>
				<th style="width: 7%;">Abrev.</th>
				<th style="width: 7%;">Juris.</th>
				<th style="width: 8%;">Código</th>
				<th style="width: 10%;">País</th>
				<th style="width: 8%;">Mín. CM05</th>
				<th style="width: 22%;">Tasas IIBB</th>
				<th style="width: 21%;">Cuentas contables</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($datas as $data)
				<tr>
					<td>{{ $data->id }}</td>
					<td>{{ $data->nombre }}</td>
					<td>{{ $data->abreviatura }}</td>
					<td>{{ $data->jurisdiccion }}</td>
					<td>{{ $data->codigo }}</td>
					<td>{{ $data->paises->nombre ?? '' }}</td>
					<td>{{ $data->minimocoeficientecm05 }}</td>
					<td>{{ ProvinciaListadoFiltros::textoTasas($data) }}</td>
					<td>{{ ProvinciaListadoFiltros::textoCuentas($data) }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
