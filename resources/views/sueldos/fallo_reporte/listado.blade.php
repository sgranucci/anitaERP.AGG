<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<title>Cta. cte. fallos</title>
	<style>
		body { font-family: DejaVu Sans, sans-serif; font-size: 8px; }
		table.data { border-collapse: collapse; width: 100%; }
		table.data th, table.data td { border: 1px solid #ccc; padding: 3px 4px; }
		table.data thead th { background: #85C1E9; color: #17202A; }
		table.data tr:nth-child(even) td { background: #f5f5f5; }
		.total { font-weight: bold; background: #e8e8e8 !important; }
		.text-right { text-align: right; }
	</style>
</head>
<body>
	<h2 style="margin:0;font-size:16px;text-align:center;">{{ $titulo }}</h2>
	<div style="text-align:center;">Generado {{ date('d/m/Y H:i') }} · {{ $subtitulo }}</div>
	<div style="text-align:center;margin-bottom:8px;">
		Debe $ {{ number_format($resultado['total_debe'], 2, ',', '.') }} ·
		Haber $ {{ number_format($resultado['total_haber'], 2, ',', '.') }} ·
		Saldo $ {{ number_format($resultado['total_saldo'], 2, ',', '.') }}
	</div>
	<table class="data">
		<thead>
			<tr>
				<th>Legajo</th><th>Empleado</th><th>Fecha</th><th>Concepto</th>
				<th>Debe</th><th>Haber</th><th>Observaci&oacute;n</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($filas as $f)
				<tr class="{{ !empty($f['es_total']) ? 'total' : '' }}">
					<td>{{ $f['legajo'] }}</td>
					<td>{{ $f['nombre'] }}</td>
					<td>{{ $f['fecha_fmt'] ?? '' }}</td>
					<td>{{ $f['concepto'] ?? '' }}</td>
					<td class="text-right">{{ ((float)($f['debe'] ?? 0)) > 0 ? number_format((float)$f['debe'], 2, ',', '.') : '' }}</td>
					<td class="text-right">{{ ((float)($f['haber'] ?? 0)) > 0 ? number_format((float)$f['haber'], 2, ',', '.') : '' }}</td>
					<td>{{ $f['observacion'] ?? '' }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
