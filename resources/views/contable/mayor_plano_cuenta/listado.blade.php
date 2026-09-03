@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);
    $totalFilas = is_countable($filas) ? count($filas) : 0;
    $tot = $totales ?? [];
    $tituloReporte = $titulo ?? 'Mayor analítico por cuenta contable';
    $multiempresa = count($filtros['empresa_ids'] ?? []) > 1
        || empty($filtros['consolidar_empresas']);
    $pdfTotalesVentas = ! empty($filtros['solo_movimientos_ventas']);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: {{ $pdfTotalesVentas ? '11px' : '7px' }}; color: #1a1a1a; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: {{ $pdfTotalesVentas ? '5px 6px' : '3px 4px' }};
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-size: {{ $pdfTotalesVentas ? '10px' : '6.5px' }}; font-weight: bold; color: #17202A; }
        table.data td { font-size: {{ $pdfTotalesVentas ? '11px' : '7px' }}; }
        .text-right { text-align: right; white-space: nowrap; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: {{ $pdfTotalesVentas ? '10px' : '7px' }}; color: #444; margin-top: 3px; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 32%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 52px; max-width: 160px; margin-right: 8px; margin-bottom: 4px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 46%; text-align: center;">
                <h2 style="margin: 0; font-size: {{ $pdfTotalesVentas ? '18px' : '16px' }}; font-weight: bold;">{{ $tituloReporte }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if (!empty($subtitulo))
                    <div class="meta">{{ $subtitulo }}</div>
                @endif
            </td>
            <td style="width: 22%; text-align: right; font-size: {{ $pdfTotalesVentas ? '10px' : '7px' }};">
                @if ($totalFilas > 0)
                    Registros: {{ $totalFilas }}
                @endif
            </td>
        </tr>
    </table>

    @if (! empty($filtros['solo_movimientos_ventas']) && ! empty($resumen))
        @php
            $mostrarCcPdf = collect($resumen)->contains(fn ($row) => array_key_exists('centrocosto_codigo', $row));
            $cuadrePdf = $cuadreCobroVentas ?? $cuadre_cobro_ventas ?? null;
        @endphp
        <table class="data">
            <thead>
                <tr>
                    <th>Cuenta</th>
                    <th>Nombre</th>
                    @if ($mostrarCcPdf)
                        <th>Centro de costo</th>
                    @endif
                    <th class="text-right">Saldo inicial</th>
                    <th class="text-right">Debe</th>
                    <th class="text-right">Haber</th>
                    <th class="text-right">Neto (H-D)</th>
                    <th class="text-right">Saldo</th>
                    <th class="text-right">Líneas</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($resumen as $row)
                    <tr>
                        <td>{{ $row['cuenta_codigo'] ?? '' }}</td>
                        <td>{{ $row['cuenta_nombre'] ?? '' }}</td>
                        @if ($mostrarCcPdf)
                            <td>
                                {{ ($row['centrocosto_codigo'] ?? '') !== '' ? $row['centrocosto_codigo'] : 'Sin CC' }}
                                @if (! empty($row['centrocosto_nombre']))
                                    — {{ $row['centrocosto_nombre'] }}
                                @endif
                            </td>
                        @endif
                        <td class="text-right">{{ number_format((float) ($row['saldo_inicial'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($row['total_debe'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($row['total_haber'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($row['total_haber'] ?? 0) - (float) ($row['total_debe'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($row['saldo_inicial'] ?? 0) + (float) ($row['total_debe'] ?? 0) - (float) ($row['total_haber'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ (int) ($row['cantidad_lineas'] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if (! empty($cuadrePdf))
            <p class="meta" style="margin-top: 10px; font-weight: bold;">Cuadre total comprobantes (listado IVA ventas / subdiario)</p>
            <table class="data" style="width: 60%;">
                <thead>
                    <tr>
                        <th>Concepto</th>
                        <th class="text-right">Neto Debe (D-H)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Deudores por ventas ({{ $cuadrePdf['deudores_codigo'] ?: '113100' }})</td>
                        <td class="text-right">{{ number_format((float) ($cuadrePdf['deudores'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>Caja contado ({{ $cuadrePdf['caja_codigo'] ?: '111100' }})</td>
                        <td class="text-right">{{ number_format((float) ($cuadrePdf['caja'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>Total cobro (= columna Total del listado)</td>
                        <td class="text-right">{{ number_format((float) ($cuadrePdf['total_cobro'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        @endif
    @else
    <table class="data">
        @include('contable.mayor_plano_cuenta.partials.tabla_datos', [
            'filas' => $filas,
            'puede_ver_asiento' => false,
            'puede_ver_cuenta' => false,
            'puede_ver_ordencompra' => false,
            'multiempresa' => $multiempresa,
        ])
    </table>
    @endif

    @if (! empty($tot))
        <p class="meta" style="margin-top: 8px;">
            Totales: {{ (int) ($tot['cantidad_cuentas'] ?? 0) }} cuentas,
            {{ (int) ($tot['cantidad_filas'] ?? 0) }} líneas,
            Debe {{ number_format((float) ($tot['total_debe'] ?? 0), 2, ',', '.') }},
            Haber {{ number_format((float) ($tot['total_haber'] ?? 0), 2, ',', '.') }}
        </p>
    @endif
</body>
</html>
