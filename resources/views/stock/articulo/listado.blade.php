@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $totalFilas = is_countable($articulos) ? count($articulos) : 0;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect());
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Artículos</title>
	<style>
		body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
		table.data {
			font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
			border-collapse: collapse;
			width: 100%;
			table-layout: fixed;
		}
		table.data td, table.data th {
			border: 1px solid #cccccc;
			text-align: left;
			padding: 4px;
			vertical-align: top;
			word-wrap: break-word;
		}
		table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
		table.data thead tr { background-color: #85C1E9; }
		table.data th {
			font-size: 7px;
			font-weight: bold;
			color: #17202A;
		}
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
					<img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 56px; max-width: 180px; margin-right: 10px; margin-bottom: 4px; vertical-align: middle;">
				@endforeach
			</td>
			<td style="width: 40%; text-align: center;">
				<h2 style="margin: 0; font-size: 20px; font-weight: bold;">Listado de artículos</h2>
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
				<th style="width: 10%;">Código</th>
				<th style="width: 28%;">Descripción</th>
				<th style="width: 10%;">Unidad de Medida</th>
				<th style="width: 12%;">Categoría</th>
				<th style="width: 12%;">Tipo de Artículo</th>
				<th style="width: 10%;">Uso</th>
				<th style="width: 10%;">Facturable</th>
				<th style="width: 8%;">Estado</th>
			</tr>
		</thead>
		<tbody>
			@foreach($articulos as $articulo)
				<tr>
					<td>{{ $articulo->codigoarticulo ?? '' }}</td>
					<td>{{ $articulo->descripcion ?? '' }}</td>
					<td>{{ $articulo->nombreunidadmedida ?? '' }}</td>
					<td>{{ $articulo->nombrecategoria ?? '' }}</td>
					<td>{{ $articulo->nombretipoarticulo ?? '' }}</td>
					<td>{{ $articulo->nombreusoarticulo ?? '' }}</td>
					<td>{{ ($articulo->nofactura == '0' ? 'Facturable' : ($articulo->nofactura == '1' ? 'No facturable' : '' )) }}</td>
					<td>{{ $articulo->estado }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
