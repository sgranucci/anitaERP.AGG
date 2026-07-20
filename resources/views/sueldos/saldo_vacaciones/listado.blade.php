@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Sueldos\SaldoVacacionesReporteConsulta;
    use App\Support\Sueldos\EmpleadoEstados;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
    $anioFiltro = $filtros['anio'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Saldos de vacaciones</title>
	<style>
		body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
		table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
		table.data td, table.data th { border: 1px solid #cccccc; text-align: left; padding: 4px; vertical-align: top; word-wrap: break-word; }
		table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
		table.data thead tr { background-color: #85C1E9; }
		table.data th { font-size: 7px; font-weight: bold; color: #17202A; }
		.listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
		.listado-header td { vertical-align: middle; border: none; }
		.meta { font-size: 8px; color: #444; margin-top: 4px; }
		.text-center { text-align: center; }
		.text-right { text-align: right; }
		tfoot td { font-weight: bold; background-color: #eaf2f8; }
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
				<h2 style="margin: 0; font-size: 20px; font-weight: bold;">Saldos de vacaciones</h2>
				<div class="meta">Generado {{ date('d/m/Y H:i') }} @if($anioFiltro) &middot; Per&iacute;odo {{ $anioFiltro }} @endif</div>
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
				<th style="width: 22%;">Empresa</th>
				<th style="width: 8%;" class="text-right">Legajo</th>
				<th style="width: 26%;">Empleado</th>
				<th style="width: 10%;" class="text-center">Estado</th>
				<th style="width: 10%;" class="text-center">Ingreso</th>
				<th style="width: 8%;" class="text-right">Deveng.</th>
				<th style="width: 8%;" class="text-right">Consum.</th>
				<th style="width: 8%;" class="text-right">Saldo</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($datas as $data)
				<tr>
					<td>{{ $data->empresa_nombre }}</td>
					<td class="text-right">{{ $data->legajo }}</td>
					<td>{{ $data->nombre }}</td>
					<td class="text-center">{{ EmpleadoEstados::label($data->estado) }}</td>
					<td class="text-center">{{ $data->fecha_ingreso ? \Carbon\Carbon::parse($data->fecha_ingreso)->format('d/m/Y') : '—' }}</td>
					<td class="text-right">{{ number_format((float) $data->devengado, 2, ',', '.') }}</td>
					<td class="text-right">{{ number_format((float) $data->consumido, 2, ',', '.') }}</td>
					<td class="text-right">{{ number_format((float) $data->saldo, 2, ',', '.') }}</td>
				</tr>
			@endforeach
		</tbody>
		<tfoot>
			<tr>
				<td colspan="5" class="text-right">Totales ({{ $totales['empleados'] ?? $totalFilas }} empleados)</td>
				<td class="text-right">{{ number_format($totales['devengado'] ?? 0, 2, ',', '.') }}</td>
				<td class="text-right">{{ number_format($totales['consumido'] ?? 0, 2, ',', '.') }}</td>
				<td class="text-right">{{ number_format($totales['saldo'] ?? 0, 2, ',', '.') }}</td>
			</tr>
		</tfoot>
	</table>
</body>
</html>
