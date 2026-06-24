@php
    use App\Support\Ventas\ClienteCuentacorrientePreferenciasUsuario;

    $colspan = 9;
    $modoDeuda = ($modoVista ?? ClienteCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE)
        === ClienteCuentacorrientePreferenciasUsuario::MODO_DEUDA;
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
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
                <strong style="font-size: 16pt;">{{ $titulo ?? 'Cuenta corriente de clientes' }}</strong>
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
                Saldo cuenta corriente: {{ $formatear($saldoCuentaCorriente ?? 0) }}
                &middot; Total deuda: {{ $formatear($totalDeuda ?? 0) }}
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
    @include('ventas.cuentacorriente.partials.tabla_datos', [
        'filas' => $filas,
        'modoVista' => $modoVista ?? ClienteCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE,
        'saldoAnterior' => 0,
        'para_pdf' => true,
        'para_excel' => true,
    ])
</table>
