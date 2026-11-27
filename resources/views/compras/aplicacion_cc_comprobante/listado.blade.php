@php
    use App\Support\Compras\AplicacionCcProveedorComprobanteListadoColumnas;
    use App\Support\Compras\AplicacionCcProveedorComprobanteListadoFiltros;
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Listado\ListadoAgrupacionSupport;
    use App\Support\Listado\ListadoExportPresentacionSupport;
    use App\Support\Listado\ListadoPdfRapidoSupport;

    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas ?? collect());
    $etiquetas = $etiquetasColumnas ?? [];
    $campos = AplicacionCcProveedorComprobanteListadoFiltros::camposOrdenables();
    $agrupar = ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos);
    $filasSegmentadas = ListadoAgrupacionSupport::segmentar(
        $datas ?? [],
        $agrupar,
        static fn ($row, string $campo): string => AplicacionCcProveedorComprobanteListadoColumnas::valorCelda($row, $campo),
        $etiquetas
    );
    $lotesPdf = ListadoPdfRapidoSupport::lotes($filasSegmentadas);
    $filtrosPdf = $filtros ?? [];
    $filtrosPdf['orden'] = $filtrosPdf['orden'] ?? ($filtrosPdf['sort'] ?? []);
    $subtitulo = ListadoExportPresentacionSupport::subtitulo($filtrosPdf, $etiquetas, $campos);
    $estado = $filtros['estado'] ?? '';
    $subtitulo = trim('Estado: '.($estado === '' ? 'todos' : (AplicacionCcProveedorComprobanteListadoColumnas::ESTADOS[$estado] ?? $estado))
        .($subtitulo !== '' ? ' · '.$subtitulo : ''));
    $totalFilas = is_countable($datas ?? null) ? count($datas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Comprobantes de aplicación CC proveedores</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; margin: 0; page-break-inside: auto; }
        table.data thead { display: table-header-group; }
        table.data td, table.data th { border: 1px solid #cccccc; text-align: left; padding: 2px 3px; vertical-align: top; }
        table.data tbody tr:nth-child(even) td { background: #f5f5f5; }
        table.data thead tr.columnas > th { background-color: #85C1E9; font-size: 7px; font-weight: bold; color: #17202A; }
        table.data td.num { text-align: right; white-space: nowrap; }
        tr.lw-export-grupo td { background: #D6EAF8 !important; font-weight: bold; color: #1B4F72; }
        tr.lw-export-grupo-1 td { background: #EAF2F8 !important; }
        tr.lw-export-total td { font-weight: bold; background: #EAF2F8 !important; }
        .meta { font-size: 8px; color: #444; margin-top: 3px; }
    </style>
</head>
<body>
    @foreach ($lotesPdf as $indiceLote => $lotePdf)
        <table class="data">
            @include('compras.aplicacion_cc_comprobante.partials.tabla_listado_export', [
                'filasSegmentadas' => $lotePdf,
                'columnasVisibles' => $columnasVisibles ?? null,
                'etiquetasColumnas' => $etiquetas,
                'resumenTotales' => $indiceLote === count($lotesPdf) - 1 ? ($resumen ?? null) : null,
                'cabeceraPdf' => $indiceLote === 0 ? [
                    'titulo' => 'Comprobantes de aplicación CC proveedores',
                    'subtitulo' => $subtitulo,
                    'logosCabecera' => $logosCabecera,
                    'totalFilas' => $totalFilas,
                ] : null,
            ])
        </table>
    @endforeach
</body>
</html>
