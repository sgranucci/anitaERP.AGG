@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Sueldos\EntregaPrendaListadoColumnas;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
    $columnas = $columnasVisibles ?? EntregaPrendaListadoColumnas::defaultsVisibles();
    $etiquetas = $etiquetasColumnas ?? [];
    $totalUnidades = rtrim(rtrim(number_format((float) ($totalCantidad ?? 0), 3, ',', '.'), '0'), ',');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<title>Entregas de indumentaria</title>
	<style>
		body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
		table.data { border-collapse: collapse; width: 100%; }
		table.data td, table.data th { border: 1px solid #cccccc; text-align: left; padding: 3px; }
		table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
		table.data thead tr { background-color: #85C1E9; }
		table.data th { font-size: 7px; font-weight: bold; color: #17202A; }
		table.data td.num { text-align: right; }
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
				<h2 style="margin: 0; font-size: 18px;">Entregas de indumentaria</h2>
				<div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
				@if (!empty($subtitulo))
					<div class="meta">{{ $subtitulo }}</div>
				@endif
			</td>
			<td style="width: 25%; text-align: right;">
				Registros: {{ $totalFilas }}<br>
				Total unidades: {{ $totalUnidades }}
			</td>
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
						<td class="{{ $key === 'cantidad' ? 'num' : '' }}">{{ EntregaPrendaListadoColumnas::valorCelda($row, $key) }}</td>
					@endforeach
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
