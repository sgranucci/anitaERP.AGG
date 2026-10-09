@php
    use App\Support\Compras\ComprobanteProveedorEstados;
    use App\Support\Compras\ComprobanteProveedorListadoColumnas;
    use App\Support\Compras\ComprobanteProveedorListadoFiltros;
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Listado\ListadoAgrupacionSupport;
    use App\Support\Listado\ListadoExportPresentacionSupport;
    use App\Support\Listado\ListadoPdfRapidoSupport;

    foreach ($datas as $row) {
        if (empty($row->nombreempresa)) {
            $row->nombreempresa = $row->empresas->nombre ?? '';
        }
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas ?? collect());
    $etiquetas = $etiquetasColumnas ?? [];
    $campos = ComprobanteProveedorListadoFiltros::camposOrdenables();
    $agrupar = ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos);
    $filasSegmentadas = ListadoAgrupacionSupport::segmentar(
        $datas ?? [],
        $agrupar,
        static fn ($row, string $campo): string => ComprobanteProveedorListadoColumnas::valorCelda($row, $campo),
        $etiquetas
    );
    $lotesPdf = ListadoPdfRapidoSupport::lotes($filasSegmentadas);
    $filtrosPdf = $filtros ?? [];
    $filtrosPdf['orden'] = $filtrosPdf['orden'] ?? ($filtrosPdf['sort'] ?? []);
    $subtitulo = ListadoExportPresentacionSupport::subtitulo($filtrosPdf, $etiquetas, $campos);
    $estado = (string) ($filtros['estado'] ?? '');
    if ($estado !== '' && $estado !== ComprobanteProveedorEstados::FILTRO_TODOS) {
        $subtitulo = trim('Estado: '.ComprobanteProveedorEstados::etiqueta($estado).($subtitulo !== '' ? ' · '.$subtitulo : ''));
    }
    if (($filtros['empresa_scope'] ?? '') === 'todas') {
        $subtitulo = trim('Todas las empresas asignadas'.($subtitulo !== '' ? ' · '.$subtitulo : ''));
    }
    $totalFilas = is_countable($datas ?? null) ? count($datas) : 0;
    $columnasPdf = $columnasVisibles ?? null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Comprobantes de proveedor</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; margin: 0; page-break-inside: auto; }
        table.data thead { display: table-header-group; }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 2px 3px;
            vertical-align: top;
        }
        table.data thead tr.columnas > th { background-color: #85C1E9; font-size: 7px; font-weight: bold; color: #17202A; }
        tr.lw-export-grupo td { background: #D6EAF8; font-weight: bold; color: #1B4F72; }
        tr.lw-export-grupo-1 td { background: #EAF2F8; }
        .meta { font-size: 8px; color: #444; margin-top: 3px; }
    </style>
</head>
<body>
    @foreach ($lotesPdf as $indiceLote => $lotePdf)
        <table class="data">
            @include('compras.comprobante_proveedor.partials.tabla_listado_export', [
                'datas' => $datas ?? [],
                'filasSegmentadas' => $lotePdf,
                'columnasVisibles' => $columnasPdf,
                'etiquetasColumnas' => $etiquetas,
                'cabeceraPdf' => $indiceLote === 0 ? [
                    'titulo' => 'Comprobantes de proveedor',
                    'subtitulo' => $subtitulo,
                    'logosCabecera' => $logosCabecera,
                    'totalFilas' => $totalFilas,
                ] : null,
            ])
        </table>
    @endforeach
</body>
</html>
