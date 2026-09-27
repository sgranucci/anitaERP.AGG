@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Stock\ArticuloListadoFiltros;
    $filtroEmpresaActivo = ArticuloListadoFiltros::filtroEmpresaActivo();
    $colspanPdf = $filtroEmpresaActivo ? 9 : 8;
    foreach ($articulos as $row) {
        $row->nombreempresa = $row->nombreempresa ?? '';
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($articulos);
    $totalFilas = is_countable($articulos) ? count($articulos) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Artículos</title>
	<style>
		@include('includes.reportes.estilos_pdf_pagina', [
			'pdf_size' => 'legal landscape',
			'pdf_margin' => '14mm 16mm',
		])
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
		table.data thead { display: table-header-group; }
		table.data thead tr.columnas > th {
			background-color: #85C1E9;
			font-size: 7px;
			font-weight: bold;
			color: #17202A;
		}
	</style>
</head>
<body>
@php
    $filasPdf = [];
    foreach ($articulos as $articulo) {
        $filasPdf[] = ['type' => 'row', 'row' => $articulo];
    }
    $lotesPdf = \App\Support\Listado\ListadoPdfRapidoSupport::lotes($filasPdf);
@endphp
@foreach ($lotesPdf as $indiceLote => $lote)
<table class="data">
    <thead>
        @if ($indiceLote === 0)
            @include('includes.reportes.pdf_thead_cabecera', [
                'titulo' => 'Listado de artículos',
                'subtitulo' => '',
                'colspan' => $colspanPdf,
                'logosCabecera' => $logosCabecera,
                'totalFilas' => $totalFilas,
            ])
        @endif
        <tr class="columnas">
			<th>C&oacute;digo</th>
			<th>Descripci&oacute;n</th>
			<th>UM</th>
			<th>Categor&iacute;a</th>
			<th>Tipo</th>
			<th>Uso</th>
			@if ($filtroEmpresaActivo)
				<th>Empresa</th>
			@endif
			<th>Facturable</th>
			<th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lote as $item)
            @php $articulo = $item['row']; @endphp
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
@endforeach
</body>
</html>
