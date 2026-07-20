@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Models\Sueldos\Liquidacion_Sueldos;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Corridas de liquidación</title>
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
		.text-right { text-align: right; }
		.text-center { text-align: center; }
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
				<h2 style="margin: 0; font-size: 20px; font-weight: bold;">Corridas de liquidación</h2>
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
				<th style="width: 6%;">N&deg;</th>
				<th style="width: 20%;">Empresa</th>
				<th style="width: 26%;">Descripci&oacute;n</th>
				<th style="width: 14%;">Tipo</th>
				<th style="width: 8%;">Per&iacute;odo</th>
				<th style="width: 10%;">Pago</th>
				<th style="width: 8%;">Estado</th>
				<th style="width: 8%;" class="text-right">Neto</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($datas as $data)
				<tr>
					<td>{{ $data->numero }}</td>
					<td>{{ optional($data->empresa)->nombre }}</td>
					<td>{{ $data->descripcion }}{{ $data->simulacion ? ' (Simulación)' : '' }}</td>
					<td>{{ $data->tipoLabel() }}</td>
					<td class="text-center">{{ $data->periodo_mes ? sprintf('%02d/%04d', $data->periodo_mes, $data->periodo_anio) : $data->periodo }}</td>
					<td class="text-center">{{ optional($data->fecha_pago)->format('d/m/Y') }}</td>
					<td>{{ $data->estadoLabel() }}</td>
					<td class="text-right">{{ number_format((float) $data->total_neto, 2, ',', '.') }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
