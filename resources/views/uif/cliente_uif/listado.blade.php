@php
    $logoAggPath = public_path('storage/imagenes/logos/AGG.png');
    $logoAguasPath = public_path('storage/imagenes/logos/logoAguas.jpg');
    $logoMime = 'jpeg';
    $logoPath = $logoAguasPath;
    if (config('app.empresa') == 'AGG' && is_file($logoAggPath)) {
        $logoPath = $logoAggPath;
        $logoMime = 'png';
    }
    $logoData = is_file($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Clientes UIF</title>
	<style>
		body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 10px; }
		table {
			font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
			border-collapse: collapse;
			width: 100%;
		}
		td, th {
			border: 1px solid #dddddd;
			text-align: left;
			padding: 6px;
		}
		tr:nth-child(even) {
			background-color: #eeeeee;
		}
		.listado-header { width: 100%; margin-bottom: 12px; border-bottom: 2px solid #444; padding-bottom: 8px; }
		.listado-header td { vertical-align: middle; border: none; }
		th { font-size: 9px; }
	</style>
</head>
<body>
	<table class="listado-header">
		<tr>
			<td style="width: 35%;">
				@if ($logoData)
					<img src="data:image/{{ $logoMime }};base64,{{ $logoData }}" alt="" style="max-width: 220px; max-height: 70px;">
				@endif
			</td>
			<td style="width: 40%; text-align: center;">
				<h2 style="margin: 0;">Clientes UIF</h2>
			</td>
			<td style="width: 25%;"></td>
		</tr>
	</table>
	<table>
		<thead>
			<tr>
				<th>ID</th>
				<th>Nombre</th>
				<th>Tipo</th>
				<th>Número de doc.</th>
				<th>Domicilio</th>
				<th>Localidad</th>
				<th>Provincia</th>
				<th>País</th>
				<th>Teléfono</th>
				<th>Email</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($cliente_uifs as $data)
				<tr>
					<td>{{ $data->id }}</td>
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
