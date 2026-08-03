@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($cliente_uifs);
    $totalFilas = is_countable($cliente_uifs) ? count($cliente_uifs) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Clientes UIF</title>
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
		table.data tr:nth-child(even) { background-color: #f5f5f5; }
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
				<h2 style="margin: 0; font-size: 20px; font-weight: bold;">Listado de clientes UIF</h2>
				<div class="meta">Generado {{ date('d/m/Y H:i') }}
					@if ($totalFilas > 0)
						— {{ $totalFilas }} registro(s)
					@endif
				</div>
				@if (! empty($subtituloFiltros))
					<div class="meta" style="margin-top: 4px;"><strong>Filtros:</strong> {{ $subtituloFiltros }}</div>
				@endif
			</td>
			<td style="width: 25%;"></td>
		</tr>
	</table>
	<table class="data">
		<thead>
			<tr>
				<th style="width: 4%;">ID</th>
				<th style="width: 8%;">Origen</th>
				<th style="width: 14%;">Nombre</th>
				<th style="width: 5%;">Tipo</th>
				<th style="width: 8%;">N&uacute;mero de doc.</th>
				<th style="width: 14%;">Domicilio</th>
				<th style="width: 11%;">Localidad</th>
				<th style="width: 9%;">Provincia</th>
				<th style="width: 7%;">Pa&iacute;s</th>
				<th style="width: 8%;">Tel&eacute;fono</th>
				<th style="width: 12%;">Email</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($cliente_uifs as $data)
				<tr>
					<td>{{ $data->id }}</td>
					<td>{{ \App\Support\Uif\ClienteUifOrigenPcSupport::labelOrigen((string) ($data->anita_origen ?? '')) }}</td>
					<td>{{ $data->nombre }}</td>
					<td>{{ $data->abreviaturatipodocumento }}</td>
					<td>{{ $data->numerodocumento }}</td>
					<td>{{ $data->domicilio }}</td>
					<td>{{ $data->nombrelocalidad ?? '' }}</td>
					<td>{{ $data->nombreprovincia ?? '' }}</td>
					<td>{{ $data->nombrepais ?? '' }}</td>
					<td>{{ $data->telefono }}</td>
					<td>{{ $data->email }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
