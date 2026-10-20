@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Listado\ListadoAgrupacionSupport;
    use App\Support\Listado\ListadoExportPresentacionSupport;
    use App\Support\Listado\ListadoPdfRapidoSupport;
    use App\Support\Stock\ArticuloListadoColumnas;
    use App\Support\Stock\ArticuloListadoFiltros;

    foreach ($articulos as $row) {
        $row->nombreempresa = $row->nombreempresa ?? '';
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($articulos);
    $etiquetas = $etiquetasColumnas ?? [];
    $campos = ArticuloListadoFiltros::camposOrdenables();
    $agrupar = ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos);
    $filasSegmentadas = ListadoAgrupacionSupport::segmentar(
        $articulos,
        $agrupar,
        static fn ($row, string $campo): string => ArticuloListadoColumnas::valorCelda($row, $campo),
        $etiquetas
    );
    $lotesPdf = ListadoPdfRapidoSupport::lotes($filasSegmentadas);
    $subtitulo = ListadoExportPresentacionSupport::subtitulo($filtros ?? [], $etiquetas, $campos);
    $totalFilas = is_countable($articulos) ? count($articulos) : 0;
    $columnasPdf = ArticuloListadoColumnas::columnasExportables($columnasVisibles ?? []);
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
@foreach ($lotesPdf as $indiceLote => $lote)
<table class="data">
    @include('stock.articulo.partials.tabla_listado_export', [
        'articulos' => $articulos,
        'filasSegmentadas' => $lote,
        'columnasVisibles' => $columnasPdf,
        'etiquetasColumnas' => $etiquetas,
        'cabeceraPdf' => $indiceLote === 0 ? [
            'titulo' => 'Listado de artículos',
            'subtitulo' => $subtitulo,
            'logosCabecera' => $logosCabecera,
            'totalFilas' => $totalFilas,
        ] : null,
    ])
</table>
@endforeach
</body>
</html>
