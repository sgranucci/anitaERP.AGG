@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Sueldos\SancionReporteListadoColumnas;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
    $columnas = $columnasVisibles ?? SancionReporteListadoColumnas::defaultsVisibles();
    $etiquetas = $etiquetasColumnas ?? [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<title>Consultas de sanciones</title>
	<style>
		body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
		table.data { border-collapse: collapse; width: 100%; }
		table.data td, table.data th { border: 1px solid #cccccc; text-align: left; padding: 3px; }
		table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
		table.data thead tr { background-color: #85C1E9; }
		table.data th { font-size: 7px; font-weight: bold; color: #17202A; }
		.listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; }
		.listado-header td { border: none; }
		.meta { font-size: 8px; color: #444; }
	</style>
</head>
<body>
	<table class="listado-header">
		<tr>
			<td style="width: 30%;">
				@foreach ($logosCabecera as $logo)
					<img src="{{ $logo['uri'] }}" alt="" style="max-height: 50px;">
				@endforeach
			</td>
			<td style="width: 45%; text-align: center;">
				<h2 style="margin: 0; font-size: 18px;">Consultas de sanciones</h2>
				<div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
				@if (!empty($subtitulo))
					<div class="meta">{{ $subtitulo }}</div>
				@endif
			</td>
			<td style="width: 25%; text-align: right;">Registros: {{ $totalFilas }}</td>
		</tr>
	</table>
	<table class="data">
		<thead>
			<tr>
				@foreach ($columnas as $key)
					<th>{{ $etiquetas[$key] ?? $key }}</th>
				@endforeach
			</tr>
		</thead>
		<tbody>
			@foreach ($datas as $row)
				<tr>
					@foreach ($columnas as $key)
						<td>{{ SancionReporteListadoColumnas::valorCelda($row, $key) }}</td>
					@endforeach
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
