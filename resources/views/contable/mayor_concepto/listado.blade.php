@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);
    $totalFilas = is_countable($filas) ? count($filas) : 0;
    $tot = $totales ?? [];
    $tituloReporte = $titulo ?? 'Mayor por concepto';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1a1a1a; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
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
        }
        table.data tbody tr:nth-child(even):not(.fila-total-cuenta):not(.fila-total-concepto):not(.fila-total-general) {
            background-color: #f5f5f5;
        }
        table.data thead tr { background-color: #85C1E9; }
        table.data th {
            font-size: 6.5px;
            font-weight: bold;
            color: #17202A;
        }
        .fila-total-cuenta td {
            background-color: #e9ecef;
            font-weight: bold;
            border-top: 1px solid #adb5bd;
        }
        .fila-total-concepto td {
            background-color: #ced4da;
            font-weight: bold;
            border-top: 2px solid #6c757d;
        }
        .fila-total-general th,
        .fila-total-general td {
            background-color: #adb5bd;
            font-weight: bold;
            border-top: 2px solid #495057;
        }
        .text-right { text-align: right; white-space: nowrap; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 7px; color: #444; margin-top: 3px; }
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
                <h2 style="margin: 0; font-size: 16px; font-weight: bold;">{{ $tituloReporte }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if (!empty($subtitulo))
                    <div class="meta">{{ $subtitulo }}</div>
                @endif
            </td>
            <td style="width: 22%; text-align: right; font-size: 7px;">
                @if ($totalFilas > 0)
                    Registros: {{ $totalFilas }}
                @endif
            </td>
        </tr>
    </table>

    @if (! empty($resumen) || ! empty($resumenPorCuenta))
        @php
            $agrupacionResumen = $agrupacionResumen ?? 'concepto_cuenta';
            $datosResumenPdf = $agrupacionResumen === 'cuenta_concepto'
                ? ($resumenPorCuenta ?? $resumen ?? [])
                : ($resumen ?? []);
            $tituloResumen = $agrupacionResumen === 'cuenta_concepto'
                ? 'Totales por cuenta y concepto'
                : 'Totales por concepto y cuenta';
        @endphp
        <h3 style="font-size: 11px; margin: 0 0 6px 0;">{{ $tituloResumen }}</h3>
        <table class="data" style="margin-bottom: 12px;">
            <thead>
                @if ($agrupacionResumen === 'cuenta_concepto')
                    <tr>
                        <th style="width: 8%;">Cuenta</th>
                        <th style="width: 18%;">Descripción cuenta</th>
                        <th style="width: 6%;">Concepto</th>
                        <th style="width: 14%;">Nombre concepto</th>
                        <th style="width: 5%;" class="text-right">Lín.</th>
                        <th style="width: 8%;" class="text-right">Debe</th>
                        <th style="width: 8%;" class="text-right">Haber</th>
                    </tr>
                @else
                    <tr>
                        <th style="width: 6%;">Concepto</th>
                        <th style="width: 14%;">Nombre</th>
                        <th style="width: 8%;">Cuenta</th>
                        <th style="width: 18%;">Descripción cuenta</th>
                        <th style="width: 5%;" class="text-right">Lín.</th>
                        <th style="width: 8%;" class="text-right">Debe</th>
                        <th style="width: 8%;" class="text-right">Haber</th>
                    </tr>
                @endif
            </thead>
            <tbody>
                @include('contable.mayor_concepto.partials.resumen_filas', [
                    'resumen' => $datosResumenPdf,
                    'agrupacion_resumen' => $agrupacionResumen,
                    'mostrar_enlaces' => false,
                ])
            </tbody>
        </table>
    @endif

    @php
        $panelAud = $auditoriaPanel ?? null;
        $audDisp = $panelAud['disponibilidad'] ?? ($auditoria ?? null);
        $audContra = $panelAud['contrapartidas'] ?? ($auditoriaContrapartidas ?? null);
    @endphp
    @if (! empty($audDisp) || ! empty($audContra))
        <h3 style="font-size: 11px; margin: 0 0 6px 0;">Auditoría vs mayor plano</h3>
        <p class="meta" style="margin: 0 0 8px 0;">
            Caja/banco: {{ ! empty($audDisp['cuadra']) ? 'cuadra' : 'con diferencias' }}
            @if (! empty($audContra))
                · Contrapartidas (op. disp.): {{ ! empty($audContra['cuadra']) ? 'cuadra' : 'con diferencias ('.((int) ($audContra['cuentas_descuadradas'] ?? 0)).' cuentas)' }}
            @endif
        </p>
        @if (! empty($audDisp['filas']))
            <table class="data" style="margin-bottom: 8px;">
                <thead>
                    <tr>
                        <th>Cuenta disp.</th>
                        <th>Descripción</th>
                        <th class="text-right">Plano D</th>
                        <th class="text-right">Plano H</th>
                        <th class="text-right">Imput. D</th>
                        <th class="text-right">Imput. H</th>
                        <th class="text-right">Δ D</th>
                        <th class="text-right">Δ H</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($audDisp['filas'] as $fila)
                        <tr @if (empty($fila['cuadra'])) style="background-color: #fff3cd;" @endif>
                            <td>{{ $fila['cuenta_codigo'] ?? '' }}</td>
                            <td>{{ $fila['cuenta_nombre'] ?? '' }}</td>
                            <td class="text-right">{{ number_format((float) ($fila['plano_debe'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) ($fila['plano_haber'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) ($fila['imputado_debe'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) ($fila['imputado_haber'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) ($fila['diferencia_debe'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) ($fila['diferencia_haber'] ?? 0), 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        @php
            $diffsContraPdf = array_values(array_filter(
                $audContra['filas'] ?? [],
                fn ($f) => empty($f['cuadra']),
            ));
        @endphp
        @if ($diffsContraPdf !== [])
            <table class="data" style="margin-bottom: 12px;">
                <thead>
                    <tr>
                        <th>Contrapartida</th>
                        <th>Descripción</th>
                        <th class="text-right">Plano D</th>
                        <th class="text-right">Plano H</th>
                        <th class="text-right">Imput. D</th>
                        <th class="text-right">Imput. H</th>
                        <th class="text-right">Δ D</th>
                        <th class="text-right">Δ H</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (array_slice($diffsContraPdf, 0, 20) as $fila)
                        <tr style="background-color: #fff3cd;">
                            <td>{{ $fila['cuenta_codigo'] ?? '' }}</td>
                            <td>{{ $fila['cuenta_nombre'] ?? '' }}</td>
                            <td class="text-right">{{ number_format((float) ($fila['plano_debe'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) ($fila['plano_haber'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) ($fila['imputado_debe'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) ($fila['imputado_haber'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) ($fila['diferencia_debe'] ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) ($fila['diferencia_haber'] ?? 0), 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    <h3 style="font-size: 11px; margin: 0 0 6px 0;">Detalle de movimientos</h3>
    <table class="data">
        @include('contable.mayor_concepto.partials.tabla_datos', [
            'filas' => $filas,
            'puede_ver_asiento' => false,
            'puede_ver_cuenta' => false,
            'puede_ver_concepto' => false,
        ])
        @if (! empty($tot))
            <tfoot>
                <tr class="fila-total-general">
                    <th colspan="10">Totales</th>
                    <th class="text-right">{{ number_format((float) ($tot['total_debe'] ?? 0), 2, ',', '.') }}</th>
                    <th class="text-right">{{ number_format((float) ($tot['total_haber'] ?? 0), 2, ',', '.') }}</th>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
