@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Listado\ListadoAgrupacionSupport;
    use App\Support\Listado\ListadoExportPresentacionSupport;
    use App\Support\Listado\ListadoPdfRapidoSupport;
    use App\Support\Ventas\ClienteListadoColumnas;
    use App\Support\Ventas\ClienteListadoFiltros;

    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect());
    $etiquetas = $etiquetasColumnas ?? [];
    $campos = ClienteListadoFiltros::camposOrdenables();
    $agrupar = ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos);
    $filasSegmentadas = ListadoAgrupacionSupport::segmentar(
        $clientes,
        $agrupar,
        static fn ($row, string $campo): string => ClienteListadoColumnas::valorCelda($row, $campo),
        $etiquetas
    );
    $lotesPdf = ListadoPdfRapidoSupport::lotes($filasSegmentadas);
    $subtitulo = ListadoExportPresentacionSupport::subtitulo($filtros ?? [], $etiquetas, $campos);
    $totalFilas = is_countable($clientes) ? count($clientes) : 0;
    $columnasPdf = $columnasVisibles ?? null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Clientes</title>
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
            @include('ventas.cliente.partials.tabla_listado_export', [
                'clientes' => $clientes,
                'filasSegmentadas' => $lotePdf,
                'columnasVisibles' => $columnasPdf,
                'etiquetasColumnas' => $etiquetas,
                'cabeceraPdf' => $indiceLote === 0 ? [
                    'titulo' => 'Listado de clientes',
                    'subtitulo' => $subtitulo,
                    'logosCabecera' => $logosCabecera,
                    'totalFilas' => $totalFilas,
                ] : null,
            ])
        </table>
    @endforeach
</body>
</html>
