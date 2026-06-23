@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($premios);
    $totalFilas = is_countable($premios) ? count($premios) : 0;
    $nombreCliente = $cliente_uif->nombre ?? '';
    $docCliente = $cliente_uif->numerodocumento ?? '';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Premios del cliente UIF</title>
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
				<h2 style="margin: 0; font-size: 20px; font-weight: bold;">Premios del cliente UIF</h2>
				@if ($nombreCliente !== '')
					<div class="meta">{{ $nombreCliente }}@if ($docCliente !== '') — Doc. {{ $docCliente }}@endif</div>
				@endif
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
				<th style="width: 6%;">ID</th>
				<th style="width: 14%;">Fecha entrega</th>
				<th style="width: 16%;">Sala</th>
				<th style="width: 16%;">Juego</th>
				<th style="width: 12%;">Nro. TITO</th>
				<th style="width: 10%; text-align: right;">Monto</th>
				<th style="width: 10%;">Posici&oacute;n</th>
				<th style="width: 16%;">Forma de pago</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($premios as $data)
				<tr>
					<td>{{ $data->id }}</td>
					<td>
						@if (!empty($data->fechaentrega))
							{{ \Carbon\Carbon::parse($data->fechaentrega)->format('d/m/Y H:i') }}
						@endif
					</td>
					<td>{{ $data->nombresala }}</td>
					<td>{{ $data->nombrejuego }}</td>
					<td>{{ $data->numerotito ?? '' }}</td>
					<td style="text-align: right;">{{ number_format((float) ($data->monto ?? 0), 2, ',', '.') }}</td>
					<td>{{ $data->posicion ?? '' }}</td>
					<td>{{ $data->nombreformapago }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
