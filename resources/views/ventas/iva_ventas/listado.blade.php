@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Ventas\IvaVentasListadoFiltros;
    $coleccionLogos = collect($filas ?? [])->map(fn ($f) => ['nombreempresa' => $f['nombreempresa'] ?? '']);
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccionLogos);
    $totalFilas = (int) ($resultado['stats']['ventas'] ?? (is_countable($filas) ? count($filas) : 0));
    $tituloReporte = 'IVA VENTAS';
    $subtitulo = 'Período: '.IvaVentasListadoFiltros::formatearPeriodoTexto($filtros)
        .' · Orden: '.IvaVentasListadoFiltros::formatearOrdenTexto($filtros)
        .' · '.IvaVentasListadoFiltros::formatearSubdiarioTexto($filtros);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; line-height: 1.35; }
        table.data {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 3px 4px;
            vertical-align: top;
            word-wrap: break-word;
            font-size: 7px;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data tbody tr.iva-ventas-resumen-b { background-color: #fef9e7; font-weight: bold; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-size: 7px; font-weight: bold; color: #17202A; }
        .text-right { text-align: right; white-space: nowrap; }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
        h3.seccion { font-size: 10px; margin: 8px 0 4px; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 32%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 52px; max-width: 160px; margin-right: 8px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 46%; text-align: center;">
                <h2 style="margin: 0; font-size: 16px; font-weight: bold;">{{ $tituloReporte }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                <div class="meta">{{ $subtitulo }}</div>
            </td>
            <td style="width: 22%; text-align: right; font-size: 8px;">
                @if ($totalFilas > 0)
                    Comprobantes: {{ $totalFilas }}
                @endif
            </td>
        </tr>
    </table>

    @if (! empty($resultado['conciliacion_contable']['habilitada']))
        @php $conc = $resultado['conciliacion_contable']; $res = $conc['resumen_empresa'] ?? []; @endphp
        <h3 class="seccion">Conciliación contable</h3>
        <table class="data" style="margin-bottom: 10px;">
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th class="text-right">IVA ventas</th>
                    <th class="text-right">Contable</th>
                    <th class="text-right">Diferencia</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($res['lineas'] ?? [] as $linea)
                    <tr>
                        <td>{{ $linea['concepto'] ?? '' }}</td>
                        <td class="text-right">{{ number_format((float) ($linea['erp'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($linea['contable'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($linea['diferencia'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @php $un = $resultado['conciliacion_contable']['por_unidad_negocio'] ?? ['habilitada' => false]; @endphp
    @if (! empty($un['habilitada']) && count($un['unidades'] ?? []) > 0)
        <h3 class="seccion">Conciliación por unidad de negocio</h3>
        <table class="data" style="margin-bottom: 10px;">
            <thead>
                <tr>
                    <th>Unidad de negocio</th>
                    <th class="text-right">Comp.</th>
                    <th class="text-right">Neto gravado</th>
                    <th class="text-right">Imp. interno / kiosco</th>
                    <th class="text-right">IVA</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($un['unidades'] as $unidad)
                    <tr>
                        <td>{{ $unidad['label'] ?? '' }}</td>
                        <td class="text-right">{{ (int) ($unidad['cantidad'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format((float) ($unidad['neto_gravado'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($unidad['imp_interno'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($unidad['iva'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($unidad['total'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                @php $te = $un['total_erp'] ?? []; @endphp
                <tr style="font-weight: bold; background-color: #eef3f8;">
                    <td>TOTAL</td>
                    <td></td>
                    <td class="text-right">{{ number_format((float) ($te['neto_gravado'] ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($te['imp_interno'] ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($te['iva'] ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($te['total'] ?? 0), 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    @if (! empty($resultado['totales_por_puntoventa']))
        <h3 class="seccion">Totales por punto de venta</h3>
        @php $columnas = $resultado['columnas'] ?? []; @endphp
        <table class="data" style="margin-bottom: 10px;">
            <thead>
                <tr>
                    <th>Sección</th>
                    <th>PV</th>
                    <th>Nombre</th>
                    <th class="text-right">Cant.</th>
                    @foreach ($columnas as $col)
                        <th class="text-right">{{ $col['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($resultado['totales_por_puntoventa'] as $tot)
                    <tr>
                        <td>{{ $tot['seccion_label'] ?? '' }}</td>
                        <td>{{ $tot['puntoventa_codigo'] ?? '' }}</td>
                        <td>{{ $tot['puntoventa_nombre'] ?? '' }}</td>
                        <td class="text-right">{{ (int) ($tot['cantidad'] ?? 0) }}</td>
                        @foreach ($columnas as $col)
                            <td class="text-right">{{ number_format((float) ($tot['columnas'][$col['key']] ?? 0), 2, ',', '.') }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="data">
        @include('ventas.iva_ventas.partials.tabla_datos', [
            'resultado' => $resultado,
            'filas' => $filas,
            'clasificar_por_host' => ! empty($filtros['clasificar_por_host']),
            'para_pdf' => true,
            'puede_ver_venta' => false,
            'mostrar_secciones' => true,
        ])
    </table>
</body>
</html>
