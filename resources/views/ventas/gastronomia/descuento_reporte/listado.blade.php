@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Ventas\GastronomiaDescuentoReporteFiltros;
    use App\Support\Ventas\GastronomiaDescuentoReportePdfSupport;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
        ! empty($empresa_nombre)
            ? collect([(object) ['nombreempresa' => $empresa_nombre]])
            : collect()
    );
    $tituloReporte = $titulo ?? 'Reporte descuentos gastronomía';
    $subtituloReporte = $subtitulo ?? '';
    $vistaColumnasPdf = GastronomiaDescuentoReporteFiltros::debeUsarVistaColumnas($filtros ?? [], $resultado ?? null)
        ? GastronomiaDescuentoReportePdfSupport::particionesVistaColumnas($resultado['vista_columnas'] ?? null)
        : [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1a1a1a; line-height: 1.25; }
        table.data {
            border-collapse: collapse;
            width: 100%;
            table-layout: auto;
            margin-bottom: 12px;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 3px 4px;
            vertical-align: top;
            font-size: 7px;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-weight: bold; color: #17202A; }
        table.data tfoot tr { background-color: #e8e8e8; font-weight: bold; }
        table.data tr.grupo-tipo { background-color: #d5e8f5; font-weight: bold; }
        table.data tr.subtotal-tipo { background-color: #f0f0f0; font-weight: bold; }
        table.data.tabla-columnas-pdf {
            table-layout: fixed;
            width: 100%;
            font-size: 6px;
        }
        table.data.tabla-columnas-pdf td,
        table.data.tabla-columnas-pdf th {
            font-size: 6px;
            padding: 2px 3px;
            word-wrap: break-word;
            overflow: hidden;
        }
        table.data.tabla-columnas-pdf .col-art { width: 7%; }
        table.data.tabla-columnas-pdf .col-desc { width: 14%; }
        table.data.tabla-columnas-pdf .col-num { width: 6%; }
        table.data.tabla-columnas-pdf .col-grupo { font-size: 5.5px; line-height: 1.15; }
        .page-break-after { page-break-after: always; }
        .seccion-totales-pdf {
            page-break-before: always;
            page-break-inside: avoid;
        }
        .text-right { text-align: right; white-space: nowrap; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 4px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 3px; }
        .bloque-titulo { font-size: 9px; font-weight: bold; margin: 8px 0 4px; }
        table.totales td, table.totales th {
            border: 1px solid #cccccc;
            padding: 4px 6px;
            font-size: 8px;
        }
        table.totales thead tr { background-color: #85C1E9; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 32%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 48px; max-width: 140px; margin-right: 6px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 46%; text-align: center;">
                <h2 style="margin: 0; font-size: 14px; font-weight: bold;">{{ $tituloReporte }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if ($subtituloReporte !== '')
                    <div class="meta">{{ $subtituloReporte }}</div>
                @endif
            </td>
            <td style="width: 22%; text-align: right; font-size: 8px;">
                @if (! empty($resultado['bloques']))
                    Descuentos: {{ count($resultado['bloques']) }}
                @endif
            </td>
        </tr>
    </table>

    @if ($vistaColumnasPdf !== [])
        @foreach ($vistaColumnasPdf as $particion)
            <div class="{{ ! $loop->last ? 'page-break-after' : '' }}">
                <div class="bloque-titulo">
                    Vista consolidada por columnas · {{ $resultado['periodo_texto'] ?? '' }}
                    @if (($particion['total_partes'] ?? 1) > 1)
                        · Parte {{ $particion['indice'] }}/{{ $particion['total_partes'] }}
                        (máx. {{ GastronomiaDescuentoReportePdfSupport::MAX_GRUPOS_COLUMNAS_POR_TABLA }} grupos por página)
                    @endif
                </div>
                @include('ventas.gastronomia.descuento_reporte.partials.tabla_columnas', [
                    'resultado' => $resultado,
                    'vista_columnas_chunk' => $particion,
                    'puede_ver_articulo' => false,
                    'table_class' => 'data',
                    'sin_wrapper' => true,
                    'modo_pdf' => true,
                ])
            </div>
        @endforeach
    @else
        @foreach ($resultado['bloques'] ?? [] as $bloque)
            <div class="bloque-titulo">
                {{ $bloque['codigo'] ?? '' }} — {{ $bloque['nombre'] ?? '' }}
                · {{ $resultado['periodo_texto'] ?? '' }}
            </div>
            <table class="data">
                @include('ventas.gastronomia.descuento_reporte.partials.tabla_bloque', [
                    'bloque' => $bloque,
                    'puede_ver_articulo' => $puede_ver_articulo ?? false,
                ])
            </table>
        @endforeach
    @endif

    @if (! empty($resultado['totales'] ?? []))
        <div class="seccion-totales-pdf">
            <div class="bloque-titulo" style="font-size: 11px; text-align: center; margin-top: 8px; margin-bottom: 6px;">
                DESCUENTOS — TOTALES · MES: {{ $resultado['mes_etiqueta'] ?? '' }}
            </div>
            <table class="totales" style="width: 60%; margin: 0 auto;">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Sector</th>
                    <th class="text-right">Costo</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($resultado['totales'] as $fila)
                    <tr>
                        <td>{{ $fila['codigo'] ?? '' }}</td>
                        <td>{{ $fila['sector'] ?? '' }}</td>
                        <td class="text-right">{{ number_format((float) ($fila['costo_total'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="text-right">Total general</td>
                    <td class="text-right">{{ number_format((float) ($resultado['gran_total_costo'] ?? 0), 2, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
        </div>
    @endif
</body>
</html>
