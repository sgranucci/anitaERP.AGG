@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Sueldos\SiradigTablas;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>SiRADIG F572</title>
	<style>
		body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
		table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
		table.data td, table.data th { border: 1px solid #cccccc; text-align: left; padding: 3px; vertical-align: top; word-wrap: break-word; }
		table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
		table.data thead tr { background-color: #85C1E9; }
		table.data th { font-size: 7px; font-weight: bold; color: #17202A; }
		.listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
		.listado-header td { vertical-align: middle; border: none; }
		.meta { font-size: 8px; color: #444; margin-top: 4px; }
		.text-right { text-align: right; }
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
				<h2 style="margin: 0; font-size: 18px; font-weight: bold;">SiRADIG - F572 (deducciones Ganancias)</h2>
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
				<th style="width: 7%;">Per&iacute;odo</th>
				<th style="width: 6%;">Secci&oacute;n</th>
				<th style="width: 5%;">Nro</th>
				<th style="width: 9%;">Fecha pres.</th>
				<th style="width: 11%;">CUIL</th>
				<th style="width: 22%;">Empleado</th>
				<th style="width: 16%;">Empresa</th>
				<th style="width: 6%;">Vigente</th>
				<th style="width: 12%;" class="text-right">Deducciones ($)</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($datas as $p)
				@php
					$totalDed = $p->conceptos->where('grupo', SiradigTablas::GRUPO_DEDUCCION)->sum('monto_total');
				@endphp
				<tr>
					<td>{{ $p->periodo }}</td>
					<td>{{ $p->seccion }}</td>
					<td>{{ $p->nro_presentacion }}</td>
					<td>{{ optional($p->fecha_presentacion)->format('d/m/Y') }}</td>
					<td>{{ $p->empleado_cuit }}</td>
					<td>{{ trim(($p->empleado_apellido ?? '').' '.($p->empleado_nombre ?? '')) }}</td>
					<td>{{ optional($p->empresa)->nombre }}</td>
					<td>{{ $p->vigente ? 'Sí' : 'No' }}</td>
					<td class="text-right">{{ number_format((float) $totalDed, 2, ',', '.') }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
