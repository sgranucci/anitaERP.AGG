@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Imputación contable de conceptos</title>
	<style>
		body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
		table.data { border-collapse: collapse; width: 100%; }
		table.data td, table.data th { border: 1px solid #cccccc; text-align: left; padding: 4px; }
		table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
		table.data thead tr { background-color: #85C1E9; }
		table.data th { font-size: 7px; font-weight: bold; color: #17202A; }
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
					<img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 56px; max-width: 180px; margin-right: 10px;">
				@endforeach
			</td>
			<td style="width: 40%; text-align: center;">
				<h2 style="margin: 0; font-size: 20px; font-weight: bold;">Imputación contable de conceptos</h2>
				<div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
			</td>
			<td style="width: 25%; text-align: right;">
				@if ($totalFilas > 0) Registros: {{ $totalFilas }} @endif
			</td>
		</tr>
	</table>
	<table class="data">
		<thead>
			<tr>
				<th>Empresa</th>
				<th>Alcance</th>
				<th>Clave</th>
				<th>Debe</th>
				<th>Haber</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($datas as $data)
				<tr>
					<td>{{ optional($data->empresa)->nombre }}</td>
					<td>{{ $data->alcanceLabel() }}</td>
					<td>{{ $data->clave_label ?? $data->claveLabel() }}</td>
					<td>
						@if ($data->cuentaDebe)
							{{ $data->cuentaDebe->codigo }} {{ $data->cuentaDebe->nombre }}
						@endif
					</td>
					<td>
						@if ($data->cuentaHaber)
							{{ $data->cuentaHaber->codigo }} {{ $data->cuentaHaber->nombre }}
						@endif
					</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
