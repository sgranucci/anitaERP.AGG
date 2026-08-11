@php
    $totalFilas = is_countable($articulos) ? count($articulos) : 0;
    $filtroEmpresaActivo = \App\Support\Stock\ArticuloListadoFiltros::filtroEmpresaActivo();
    $colspan = $filtroEmpresaActivo ? 9 : 8;
@endphp
<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="{{ $colspan }}"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de artículos</h2></td>
		</tr>
		<tr>
			<td colspan="{{ $colspan }}">Generado {{ date('d/m/Y H:i') }}@if ($totalFilas > 0) — Registros: {{ $totalFilas }}@endif</td>
		</tr>
	</tbody>
	<thead>
	<tr>
		<th>Código</th>
		<th>Descripción</th>
		<th>Unidad de Medida</th>
		<th>Categoría</th>
		<th>Tipo de Artículo</th>
		<th>Uso</th>
		@if ($filtroEmpresaActivo)
			<th>Empresa</th>
		@endif
		<th>Facturable</th>
		<th>Estado</th>
	</tr>
  	</thead>
    <tbody>
		@foreach ($articulos as $articulo)
		<tr>
			<td>{{ $articulo->codigoarticulo ?? '' }}</td>
			<td>{{ $articulo->descripcion ?? '' }}</td>
			<td>{{ $articulo->nombreunidadmedida ?? '' }}</td>
			<td>{{ $articulo->nombrecategoria ?? '' }}</td>
			<td>{{ $articulo->nombretipoarticulo ?? '' }}</td>
			<td>{{ $articulo->nombreusoarticulo ?? '' }}</td>
			@if ($filtroEmpresaActivo)
				<td>{{ $articulo->nombreempresa ?: 'Todas' }}</td>
			@endif
			<td>{{ ($articulo->nofactura == '0' ? 'Facturable' : ($articulo->nofactura == '1' ? 'No facturable' : '' )) }}</td>
			<td>{{ $articulo->estado }}</td>
		</tr>
		@endforeach
	</tbody>
</table>
