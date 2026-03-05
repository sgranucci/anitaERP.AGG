<!DOCTYPE html>
<html>
	<title>Artículos</title>
	<head>
		<style>
			table {
				font-family: arial, sans-serif;
				border-collapse: collapse;
				width: 100%;
			}
			td, th {
				boder: 1px solid #dddddd;
				text-align: left;
				padding: 8px;
			}
			tr:nth-child(even) {
				background-color: #dddddd;
			}
		</style>
	</head>
	<body>
		<h2>Artículos</h2>
		<table class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<th>C&oacute;digo</th>
					<th>Descripci&oacute;n</th>
					<th>Unidad de Medida</th>
					<th>Categoría</th>
					<th>Tipo de Artículo</th>
					<th>Uso</th>
					<th>Facturable</th>
					<th>Estado</th>
				</tr>
			</thead>
			<tbody>
				@foreach($articulos as $articulo)
					<tr data-entry-id="{{ $articulo->id }}">
						<td>
							{{ $articulo->codigoarticulo ?? '' }}
						</td>
						<td>
							{{ $articulo->descripcion ?? '' }}
						</td>
						<td>
							{{ $articulo->nombreunidadmedida ?? '' }}
						</td>
						<td>
							{{ $articulo->nombrecategoria ?? '' }}
						</td>
						<td>
							{{ $articulo->nombretipoarticulo ?? '' }}
						</td>
						<td>
							{{ $articulo->nombreusoarticulo ?? '' }}
						</td>
						<td>
							{{ ($articulo->nofactura == '0' ? 'Facturable' : ($articulo->nofactura == '1' ? 'No facturable' : '' )) }}
						</td>
						<td>{{ $articulo->estado }}</td>
					</tr>
				@endforeach
			</tbody>
		</table>
	</body>
</html>