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
<html>
	<title>Premios</title>
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
			.listado-header { width: 100%; margin-bottom: 12px; border-bottom: 2px solid #444; padding-bottom: 8px; }
			.listado-header td { vertical-align: middle; }
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
					<h2 style="margin: 0;">Premios UIF</h2>
				</td>
				<td style="width: 25%;"></td>
			</tr>
		</table>
		<table class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<th class="width20">ID</th>
					<th>Nombre</th>
					<th>Sala</th>
					<th>Juego</th>
					<th>Fecha Entrega</th>
					<th style="text-align: right;">Monto</th>
					<th>Posición</th>
					<th>Número TITO</th>
					<th>Forma de Pago</th>
					<th class="width40" data-orderable="false"></th>
				</tr>
			</thead>
			<tbody>
				@foreach ($cliente_premio_uifs as $data)
				<tr>
					<td>{{$data->id}}</td>
					<td>{{$data->nombrecliente}}</td>
					<td>{{$data->nombresala}}</td>
					<td><small>{{$data->nombrejuego}}</small></td>
					<td><small>{{$data->fechaentrega}}</small></td>
					<td style="text-align: right;"><small>{{ number_format((float) ($data->monto ?? 0), 2, ',', '.') }}</small></td>
					<td><small>{{$data->posicion ?? ''}}</small></td>
					<td><small>{{$data->numerotito ?? ''}}</small></td>
					<td><small>{{$data->nombreformapago}}</small></td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</body>
</html>