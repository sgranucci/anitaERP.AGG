@php
    $colSpanExcel = (int) ($colSpanExcel ?? (($multiempresa ?? false) ? 18 : 17));
    $colSpanTotalesExcel = $colSpanExcel - 2;
    // Resumen: Concepto/Cuenta + Nombre + Cuenta/Concepto + Desc + Líneas + Debe + Haber = 7 cols
    $colSpanResumenMedio = max(0, $colSpanExcel - 7);
    $formatoExcel = \App\Support\Contable\MayorConceptoExcelFormatoNumero::normalizar(
        $excel_formato_numero
            ?? ($filtros['excel_formato_numero'] ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal())
    );
    $formatearMontoExcel = \App\Support\Contable\MayorConceptoExcelFormatoNumero::formateadorMonto($formatoExcel);
    $formatearCotizacionExcel = \App\Support\Contable\MayorConceptoExcelFormatoNumero::formateadorMonto($formatoExcel, 4);
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="{{ $colSpanExcel }}" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="{{ $colSpanExcel }}">
                <h2 style="margin: 0; font-size: 18pt; font-weight: bold;">{{ $titulo }}</h2>
                @if (!empty($subtitulo))
                    <div style="font-size: 10pt; color: #555;">{{ $subtitulo }}</div>
                @endif
                <div style="font-size: 9pt; color: #777;">
                    Formato n&uacute;meros: {{ \App\Support\Contable\MayorConceptoExcelFormatoNumero::etiqueta($formatoExcel) }}
                </div>
            </td>
        </tr>
    </tbody>
    @if (! empty($resumen) || ! empty($resumenPorCuenta))
        @php
            $agrupacionResumen = $agrupacionResumen ?? 'concepto_cuenta';
            $datosResumenExcel = $agrupacionResumen === 'cuenta_concepto'
                ? ($resumenPorCuenta ?? $resumen ?? [])
                : ($resumen ?? []);
            $tituloResumenExcel = $agrupacionResumen === 'cuenta_concepto'
                ? 'Totales por cuenta y concepto'
                : 'Totales por concepto y cuenta';
        @endphp
        <tbody>
            <tr>
                <td colspan="{{ $colSpanExcel }}"><strong>{{ $tituloResumenExcel }}</strong></td>
            </tr>
            @if ($agrupacionResumen === 'cuenta_concepto')
                <tr style="background-color: #85C1E9; color: #17202A;">
                    <td><strong>Cuenta</strong></td>
                    <td><strong>Descripción cuenta</strong></td>
                    <td><strong>Concepto</strong></td>
                    <td><strong>Nombre concepto</strong></td>
                    <td style="text-align: right;"><strong>Líneas</strong></td>
                    @if ($colSpanResumenMedio > 0)
                        <td colspan="{{ $colSpanResumenMedio }}"></td>
                    @endif
                    <td style="text-align: right;"><strong>Debe</strong></td>
                    <td style="text-align: right;"><strong>Haber</strong></td>
                </tr>
            @else
                <tr style="background-color: #85C1E9; color: #17202A;">
                    <td><strong>Concepto</strong></td>
                    <td><strong>Nombre</strong></td>
                    <td><strong>Cuenta</strong></td>
                    <td><strong>Descripción cuenta</strong></td>
                    <td style="text-align: right;"><strong>Líneas</strong></td>
                    @if ($colSpanResumenMedio > 0)
                        <td colspan="{{ $colSpanResumenMedio }}"></td>
                    @endif
                    <td style="text-align: right;"><strong>Debe</strong></td>
                    <td style="text-align: right;"><strong>Haber</strong></td>
                </tr>
            @endif
            @include('contable.mayor_concepto.partials.resumen_filas', [
                'resumen' => $datosResumenExcel,
                'agrupacion_resumen' => $agrupacionResumen,
                'mostrar_enlaces' => false,
                'colspan_medio' => $colSpanResumenMedio,
                'formatearMonto' => $formatearMontoExcel,
            ])
            <tr>
                <td colspan="{{ $colSpanExcel }}">&#160;</td>
            </tr>
            @php
                $panelAudExcel = $auditoriaPanel ?? null;
            @endphp
            @if (! empty($panelAudExcel))
                @php
                    $concExcel = $panelAudExcel['conciliacion'] ?? null;
                @endphp
                @if (! empty($concExcel))
                    <tr>
                        <td colspan="{{ $colSpanExcel }}">
                            <strong>Conciliación analítico vs concepto:</strong>
                            {{ (int) ($concExcel['asientos_cuadrados'] ?? 0) }}/{{ (int) ($concExcel['asientos_analizados'] ?? 0) }} asientos
                            ({{ \App\Support\Contable\MayorConceptoExcelFormatoNumero::formatear(
                                (float) ($concExcel['porcentaje_cuadrado'] ?? 0),
                                $formatoExcel,
                                1
                            ) }}%)
                            @if (! empty($concExcel['cuadra']))
                                — OK
                            @else
                                — {{ (int) ($concExcel['asientos_descuadrados'] ?? 0) }} divergencia(s)
                            @endif
                        </td>
                    </tr>
                @endif
            @endif
            <tr>
                <td colspan="{{ $colSpanExcel }}"><strong>Detalle de movimientos</strong></td>
            </tr>
        </tbody>
    @endif
    @include('contable.mayor_concepto.partials.tabla_datos', [
        'filas' => $filas,
        'multiempresa' => $multiempresa ?? false,
        'puede_ver_asiento' => $puede_ver_asiento ?? false,
        'puede_ver_cuenta' => $puede_ver_cuenta ?? false,
        'puede_ver_concepto' => $puede_ver_concepto ?? false,
        'puede_ver_ordencompra' => $puede_ver_ordencompra ?? false,
        'formatearMonto' => $formatearMontoExcel,
        'formatearCotizacion' => $formatearCotizacionExcel,
    ])
    @if (! empty($totales))
        <tbody>
            <tr style="background-color: #adb5bd; font-weight: bold; border-top: 2px solid #495057;">
                <td colspan="{{ $colSpanTotalesExcel }}" style="background-color: #adb5bd;"><strong>Totales</strong></td>
                <td style="text-align: right; background-color: #adb5bd;">{{ $formatearMontoExcel($totales['total_debe'] ?? 0) }}</td>
                <td style="text-align: right; background-color: #adb5bd;">{{ $formatearMontoExcel($totales['total_haber'] ?? 0) }}</td>
            </tr>
        </tbody>
    @endif
</table>
