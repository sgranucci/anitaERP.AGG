@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    foreach ($datas as $row) {
        $row->nombreempresa = $row->empresa->nombre ?? config('app.empresa');
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Programas de impresión</title>
	<style>
		body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
		table.data { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; border-collapse: collapse; width: 100%; }
		table.data td, table.data th { border: 1px solid #cccccc; text-align: left; padding: 4px; vertical-align: top; }
		table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
		table.data thead tr { background-color: #85C1E9; }
		table.data th { font-size: 8px; font-weight: bold; color: #17202A; }
		.listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
		.listado-header td { vertical-align: middle; border: none; }
		.meta { font-size: 8px; color: #444; margin-top: 4px; }
	</style>
</head>
<body>
	<table class="listado-header">
		<tr>
			<td style="width: 30%;">
				@foreach ($logosCabecera as $logo)
					@if (!empty($logo['existe']))
						<img src="{{ $logo['ruta'] }}" style="max-height: 42px; max-width: 140px;">
					@endif
				@endforeach
			</td>
			<td>
				<strong style="font-size: 14px;">Programas de impresión</strong>
				<div class="meta">Generado {{ date('d/m/Y H:i') }} — {{ $totalFilas }} registros</div>
			</td>
		</tr>
	</table>
	<table class="data">
		<thead>
			<tr>
				<th>ID</th>
				<th>Código</th>
				<th>Nombre</th>
				<th>Empresa</th>
				<th>Formularios</th>
				<th>Reglas</th>
				<th>Disparo al grabar</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($datas as $data)
			<tr>
				<td>{{ $data->id }}</td>
				<td>{{ $data->codigo }}</td>
				<td>{{ $data->nombre }}</td>
				<td>{{ $data->empresa->nombre ?? 'Todas' }}</td>
				<td>{{ $data->formularios_count ?? $data->formularios->count() }}</td>
				<td>{{ $data->reglas_count ?? $data->reglas->count() }}</td>
				<td>{{ $data->permite_disparo_al_grabar ? 'Sí' : 'No' }}</td>
			</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
