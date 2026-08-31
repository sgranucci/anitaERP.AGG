<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<title>P&eacute;rdidas de empleados</title>
	<style>
		body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #222; }
		table.data { border-collapse: collapse; width: 100%; }
		table.data th, table.data td { border: 1px solid #cccccc; padding: 3px 4px; }
		table.data thead th { background: #85C1E9; color: #17202A; }
		table.data tr:nth-child(even) td { background: #f5f5f5; }
		.total { font-weight: bold; background: #e8e8e8 !important; }
		.text-right { text-align: right; }
	</style>
</head>
<body>
	@php
		$logos = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect());
	@endphp
	<table style="width:100%; margin-bottom: 8px;">
		<tr>
			<td style="width:20%;">
				@foreach ($logos as $logo)
					@if (!empty($logo['uri']))
						<img src="{{ $logo['uri'] }}" style="max-height:40px;">
					@endif
				@endforeach
			</td>
			<td style="text-align:center;">
				<h2 style="margin:0;font-size:16px;">{{ $titulo }}</h2>
				<div>Generado {{ date('d/m/Y H:i') }}</div>
				<div>{{ $subtitulo }}</div>
				<div>{{ $resultado['total_empleados'] ?? 0 }} empleados · Total $ {{ number_format($totalImporte, 2, ',', '.') }}</div>
			</td>
			<td style="width:20%;"></td>
		</tr>
	</table>

	<table class="data">
		<thead>
			<tr>
				<th>Legajo</th>
				<th>Empleado</th>
				<th>Ingreso</th>
				<th>Categor&iacute;a</th>
				<th>Agrupamiento</th>
				<th>Lugar</th>
				<th>Fecha</th>
				<th>Concepto</th>
				<th>Importe</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($filas as $f)
				<tr class="{{ !empty($f['es_total']) ? 'total' : '' }}">
					<td>{{ $f['legajo'] }}</td>
					<td>{{ $f['nombre'] }}</td>
					<td>{{ $f['fecha_ingreso'] ?? '' }}</td>
					<td>{{ $f['categoria'] ?? '' }}</td>
					<td>{{ $f['agrupamiento'] ?? '' }}</td>
					<td>{{ $f['lugar_trabajo'] ?? '' }}</td>
					<td>{{ $f['fecha'] ?? '' }}</td>
					<td>{{ $f['concepto'] ?? '' }}</td>
					<td class="text-right">{{ number_format((float)($f['importe'] ?? 0), 2, ',', '.') }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
