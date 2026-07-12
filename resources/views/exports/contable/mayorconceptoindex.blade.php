<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="12" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="12">
                <h2 style="margin: 0; font-size: 18pt; font-weight: bold;">{{ $titulo }}</h2>
                @if (!empty($subtitulo))
                    <div style="font-size: 10pt; color: #555;">{{ $subtitulo }}</div>
                @endif
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
                <td colspan="12"><strong>{{ $tituloResumenExcel }}</strong></td>
            </tr>
            @if ($agrupacionResumen === 'cuenta_concepto')
                <tr>
                    <td><strong>Cuenta</strong></td>
                    <td><strong>Descripción cuenta</strong></td>
                    <td><strong>Concepto</strong></td>
                    <td><strong>Nombre concepto</strong></td>
                    <td colspan="5"></td>
                    <td style="text-align: right;"><strong>Debe</strong></td>
                    <td style="text-align: right;"><strong>Haber</strong></td>
                </tr>
            @else
                <tr>
                    <td><strong>Concepto</strong></td>
                    <td><strong>Nombre</strong></td>
                    <td><strong>Cuenta</strong></td>
                    <td><strong>Descripción cuenta</strong></td>
                    <td colspan="6"></td>
                    <td style="text-align: right;"><strong>Debe</strong></td>
                    <td style="text-align: right;"><strong>Haber</strong></td>
                </tr>
            @endif
            @include('contable.mayor_concepto.partials.resumen_filas', [
                'resumen' => $datosResumenExcel,
                'agrupacion_resumen' => $agrupacionResumen,
                'mostrar_enlaces' => false,
                'colspan_medio' => 5,
                'formatearMonto' => static function ($valor) {
                    if ($valor === null || $valor === '' || (float) $valor === 0.0) {
                        return '';
                    }

                    return number_format((float) $valor, 2, '.', ',');
                },
            ])
            <tr>
                <td colspan="12">&#160;</td>
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
                        <td colspan="12">
                            <strong>Conciliación analítico vs concepto:</strong>
                            {{ (int) ($concExcel['asientos_cuadrados'] ?? 0) }}/{{ (int) ($concExcel['asientos_analizados'] ?? 0) }} asientos
                            ({{ number_format((float) ($concExcel['porcentaje_cuadrado'] ?? 0), 1, ',', '.') }}%)
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
                <td colspan="12"><strong>Detalle de movimientos</strong></td>
            </tr>
        </tbody>
    @endif
    @include('contable.mayor_concepto.partials.tabla_datos', [
        'filas' => $filas,
        'puede_ver_asiento' => false,
        'puede_ver_cuenta' => false,
        'puede_ver_concepto' => false,
    ])
    @if (! empty($totales))
        <tbody>
            <tr style="background-color: #adb5bd; font-weight: bold; border-top: 2px solid #495057;">
                <td colspan="10" style="background-color: #adb5bd;"><strong>Totales</strong></td>
                <td style="text-align: right; background-color: #adb5bd;">{{ number_format((float) ($totales['total_debe'] ?? 0), 2, '.', ',') }}</td>
                <td style="text-align: right; background-color: #adb5bd;">{{ number_format((float) ($totales['total_haber'] ?? 0), 2, '.', ',') }}</td>
            </tr>
        </tbody>
    @endif
</table>
