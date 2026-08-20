@php
    use App\Support\Compras\ProveedorCuentacorrientePreferenciasUsuario;
    use App\Support\Cuentacorriente\CuentacorrienteSaldosPorMoneda;

    $mostrarSaldoCorrido = (bool) ($mostrarSaldoCorrido ?? false);
    $saldosPorMoneda = $saldosPorMoneda ?? [];
    $equivalentePesos = $equivalentePesos ?? [];
    $expresion = CuentacorrienteSaldosPorMoneda::resolverExpresion($expresion ?? null);
    $enPesos = CuentacorrienteSaldosPorMoneda::esExpresionPesos($expresion);
    $abrevLocal = CuentacorrienteSaldosPorMoneda::abreviaturaLocal();
    $modoDeuda = ($modoVista ?? ProveedorCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE)
        === ProveedorCuentacorrientePreferenciasUsuario::MODO_DEUDA;
    $colspan = 10;
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="{{ $colspan }}">
                <strong style="font-size: 16pt;">{{ $titulo ?? 'Cuenta corriente de proveedores' }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                Generado {{ date('d/m/Y H:i') }}
            </td>
        </tr>
        @if (! empty($subtitulo))
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                    {{ $subtitulo }}
                </td>
            </tr>
        @endif
        <tr>
            <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                Saldo: {{ CuentacorrienteSaldosPorMoneda::formatearResumen($saldosPorMoneda, 'saldo_cc') }}
                &middot; Deuda: {{ CuentacorrienteSaldosPorMoneda::formatearResumen($saldosPorMoneda, 'deuda') }}
                &middot; Equiv. {{ $abrevLocal }} (TC compr.): {{ CuentacorrienteSaldosPorMoneda::formatearMonto((float) ($equivalentePesos['saldo_cc'] ?? 0), $abrevLocal) }}
                @if ($enPesos)
                    &middot; Importes expresados en {{ $abrevLocal }}
                @endif
            </td>
        </tr>
        @if (($totalFilas ?? 0) > 0)
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                    Registros: {{ (int) $totalFilas }}
                </td>
            </tr>
        @endif
    </tbody>
    @include('compras.cuentacorriente.partials.tabla_datos', [
        'filas' => $filas,
        'modoVista' => $modoVista ?? ProveedorCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE,
        'saldoAnterior' => 0,
        'mostrarSaldoCorrido' => $mostrarSaldoCorrido,
        'expresion' => $expresion,
        'para_pdf' => true,
        'para_excel' => true,
        'formato_numero_excel' => $formatoNumeroExcel ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal(),
    ])
</table>
