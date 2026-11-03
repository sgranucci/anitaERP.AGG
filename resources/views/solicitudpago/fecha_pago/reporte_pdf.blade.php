@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);
    $fmtFecha = function ($fecha) {
        if (! $fecha) {
            return '';
        }

        return \Carbon\Carbon::parse($fecha)->format('d/m/Y');
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Impagas por fecha de pago</title>
	<style>
		body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1a1a1a; }
		table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
		table.data td, table.data th { border: 1px solid #cccccc; text-align: left; padding: 3px; vertical-align: top; word-wrap: break-word; }
		table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
		table.data thead tr { background-color: #85C1E9; }
		table.data th { font-size: 6.5px; font-weight: bold; color: #17202A; }
		.listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 6px; }
		.listado-header td { vertical-align: middle; border: none; }
		.meta { font-size: 7px; color: #444; margin-top: 4px; }
		.text-right { text-align: right; }
		.total td { font-weight: bold; background-color: #eaf2f8; }
	</style>
</head>
<body>
	<table class="listado-header">
		<tr>
			<td style="width: 28%;">
				@foreach ($logosCabecera as $logo)
					<img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 50px; max-width: 160px; margin-right: 8px; margin-bottom: 4px; vertical-align: middle;">
				@endforeach
			</td>
			<td style="width: 48%; text-align: center;">
				<h2 style="margin: 0; font-size: 16px; font-weight: bold;">Informe de solicitudes de pago</h2>
				<div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
				<div class="meta">{{ $subtitulo }}</div>
			</td>
			<td style="width: 24%; text-align: right; font-size: 7px;">
				Registros: {{ (int) ($totales['registros'] ?? 0) }}<br>
				Importe: {{ number_format((float) ($totales['monto'] ?? 0), 2, ',', '.') }}
			</td>
		</tr>
	</table>
	<table class="data">
		<thead>
			<tr>
				<th>Empresa</th>
				<th>N&uacute;mero</th>
				<th>Fecha</th>
				<th>Vence</th>
				<th>Fecha pago</th>
				<th>Tratamiento</th>
				<th>Sector</th>
				<th>Concepto</th>
				<th>Forma pago</th>
				<th class="text-right">N.Pro.</th>
				<th>Proveedor</th>
				<th>Mon</th>
				<th class="text-right">Importe</th>
				<th>Estado</th>
				<th>Refer.</th>
				<th>Observaci&oacute;n</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($filas as $fila)
				<tr class="{{ ! empty($fila->es_total) ? 'total' : '' }}">
					<td>{{ \Illuminate\Support\Str::limit((string) $fila->nombreempresa, 18) }}</td>
					<td>{{ $fila->codigo }}</td>
					<td>{{ $fmtFecha($fila->fecha ?? null) }}</td>
					<td>{{ $fmtFecha($fila->fecha_vencimiento ?? null) }}</td>
					<td>{{ $fmtFecha($fila->fecha_pago ?? null) }}</td>
					<td>{{ $fila->tratamiento_label }}</td>
					<td>{{ \Illuminate\Support\Str::limit(trim(($fila->sector_codigo ? $fila->sector_codigo.' ' : '').($fila->sector_nombre ?? '')), 16) }}</td>
					<td>{{ \Illuminate\Support\Str::limit((string) $fila->concepto_nombre, 22) }}</td>
					<td>{{ \Illuminate\Support\Str::limit((string) $fila->forma_pago_nombre, 14) }}</td>
					<td class="text-right">{{ $fila->proveedor_codigo }}</td>
					<td>{{ \Illuminate\Support\Str::limit((string) $fila->proveedor_nombre, 18) }}</td>
					<td>{{ $fila->moneda }}</td>
					<td class="text-right">{{ number_format((float) $fila->monto, 2, ',', '.') }}</td>
					<td>{{ $fila->estado_label }}</td>
					<td>{{ $fila->referencia }}</td>
					<td>{{ \Illuminate\Support\Str::limit((string) ($fila->observacion ?? ''), 22) }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
