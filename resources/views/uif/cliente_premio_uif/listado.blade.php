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
	<title>Premios UIF</title>
		<style>
			body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; }
			table {
				font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
				border-collapse: collapse;
				width: 100%;
			}
			td, th {
				border: 1px solid #dddddd;
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
					<div style="font-size: 8px; color: #444; margin-top: 4px;">
						Generado {{ date('d/m/Y H:i') }}
						@if (is_countable($cliente_premio_uifs ?? null))
							— {{ count($cliente_premio_uifs) }} registro(s)
						@endif
					</div>
					@if (! empty($subtituloFiltros))
						<div style="font-size: 8px; color: #444; margin-top: 4px;"><strong>Filtros:</strong> {{ $subtituloFiltros }}</div>
					@endif
				</td>
				<td style="width: 25%;"></td>
			</tr>
		</table>
		<table class="data" style="width:100%; border-collapse:collapse; font-size:8px; font-family:DejaVu Sans, sans-serif;">
			<thead>
				<tr style="background:#85C1E9;color:#17202A;">
					<th style="border:1px solid #cccccc;padding:4px;">ID</th>
					<th style="border:1px solid #cccccc;padding:4px;">Origen</th>
					<th style="border:1px solid #cccccc;padding:4px;">Nombre</th>
					<th style="border:1px solid #cccccc;padding:4px;">Sala</th>
					<th style="border:1px solid #cccccc;padding:4px;">Juego</th>
					<th style="border:1px solid #cccccc;padding:4px;">Fecha Entrega</th>
					<th style="border:1px solid #cccccc;padding:4px;text-align:right;">Monto</th>
					<th style="border:1px solid #cccccc;padding:4px;">Posición</th>
					<th style="border:1px solid #cccccc;padding:4px;">Número TITO</th>
					<th style="border:1px solid #cccccc;padding:4px;">Forma de Pago</th>
				</tr>
			</thead>
			<tbody>
				@foreach ($cliente_premio_uifs as $data)
				<tr style="{{ $loop->even ? 'background:#f5f5f5;' : '' }}">
					<td style="border:1px solid #cccccc;padding:4px;">{{$data->id}}</td>
					<td style="border:1px solid #cccccc;padding:4px;">{{ \App\Support\Uif\ClienteUifOrigenPcSupport::labelOrigen((string) ($data->anita_origen ?? '')) }}</td>
					<td style="border:1px solid #cccccc;padding:4px;">{{$data->nombrecliente}}</td>
					<td style="border:1px solid #cccccc;padding:4px;">{{$data->nombresala}}</td>
					<td style="border:1px solid #cccccc;padding:4px;">{{$data->nombrejuego}}</td>
					<td style="border:1px solid #cccccc;padding:4px;">{{$data->fechaentrega}}</td>
					<td style="border:1px solid #cccccc;padding:4px;text-align:right;">{{ number_format((float) ($data->monto ?? 0), 2, ',', '.') }}</td>
					<td style="border:1px solid #cccccc;padding:4px;">{{$data->posicion ?? ''}}</td>
					<td style="border:1px solid #cccccc;padding:4px;">{{$data->numerotito ?? ''}}</td>
					<td style="border:1px solid #cccccc;padding:4px;">{{$data->nombreformapago}}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</body>
</html>