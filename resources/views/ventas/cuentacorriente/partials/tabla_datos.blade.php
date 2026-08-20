@php
    use App\Support\Cuentacorriente\CuentacorrienteSaldosPorMoneda;
    use App\Support\Ventas\ClienteCuentacorrienteGrillaSupport;
    use App\Support\Ventas\ClienteCuentacorrientePreferenciasUsuario;

    $modoDeuda = ($modoVista ?? ClienteCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE)
        === ClienteCuentacorrientePreferenciasUsuario::MODO_DEUDA;
    $expresion = CuentacorrienteSaldosPorMoneda::resolverExpresion($expresion ?? null);
    $enPesos = CuentacorrienteSaldosPorMoneda::esExpresionPesos($expresion);
    $saldosCorridos = $saldosAnterioresPorMoneda ?? [];
    $saldoPesos = (float) ($saldoAnteriorPesos ?? 0);
    $separadorEntrega = ! empty($para_pdf) ? '<br>' : "\n";
    $paraExcel = ! empty($para_excel);
    $formatearMonto = static function ($valor, $abreviatura = '') use ($paraExcel) {
        if ($paraExcel) {
            return $valor;
        }

        return CuentacorrienteSaldosPorMoneda::formatearMonto((float) $valor, (string) $abreviatura);
    };
@endphp
<thead>
    <tr>
        <th style="width: 5%;">ID</th>
        <th style="width: 12%;">Empresa</th>
        <th style="width: 9%;">Fecha</th>
        <th style="width: 9%;">Vencimiento</th>
        <th style="width: {{ $enPesos ? '20%' : '24%' }};">Comprobante</th>
        <th style="width: {{ $enPesos ? '10%' : '6%' }};">Moneda</th>
        @if ($modoDeuda)
            <th style="width: 11%; text-align: right;">Importe</th>
            <th style="width: 11%; text-align: right;">Aplicado</th>
            <th style="width: 13%; text-align: right;">Saldo pendiente</th>
        @else
            <th style="width: 10%; text-align: right;">Debe</th>
            <th style="width: 10%; text-align: right;">Haber</th>
            <th style="width: 11%; text-align: right;">Saldo</th>
            <th style="width: 12%; text-align: right;">{{ CuentacorrienteSaldosPorMoneda::etiquetaColumnaSaldoPesos() }}</th>
        @endif
    </tr>
</thead>
<tbody>
    @foreach ($filas as $data)
        @php
            $etiquetaComprobante = ClienteCuentacorrienteGrillaSupport::etiquetaComprobante($data);
            $importes = CuentacorrienteSaldosPorMoneda::importesParaGrilla(
                $data,
                $enPesos,
                static fn ($total, $aplicado) => ClienteCuentacorrienteGrillaSupport::saldoPendienteAbsoluto((float) $total, $aplicado)
            );
            $totalMostrar = $importes['total'];
            $aplicadoMostrar = $importes['aplicado'];
            $saldoPendiente = $importes['saldo_pendiente'];
            $abreviaturaFila = $importes['abreviatura'];
            $monedaFilaId = $importes['moneda_id'];
            $saldoFila = 0.0;
            $saldoFilaPesos = 0.0;
            if (! $modoDeuda) {
                $saldosCorridos = CuentacorrienteSaldosPorMoneda::acumularSaldoCorrido(
                    $saldosCorridos,
                    $monedaFilaId,
                    (float) $data->total
                );
                $saldoFila = $saldosCorridos[$monedaFilaId] ?? 0.0;
                $saldoPesos = CuentacorrienteSaldosPorMoneda::acumularSaldoCorridoPesos(
                    $saldoPesos,
                    $data,
                    (float) $data->total
                );
                $saldoFilaPesos = $saldoPesos;
            }
        @endphp
        <tr>
            <td>{{ $data->id }}</td>
            <td>{{ $data->empresas->nombre ?? ($data->nombreempresa ?? '') }}</td>
            <td>{{ date('d/m/Y', strtotime($data->fecha ?? '')) }}</td>
            <td>{{ date('d/m/Y', strtotime($data->fechavencimiento ?? '')) }}</td>
            <td>
                {{ $etiquetaComprobante }}
                @if ($data->venta_id > 0 && ! empty($data->ventas?->lugarentrega))
                    {!! $separadorEntrega !!}<small>Entrega: {{ $data->ventas->lugarentrega }}</small>
                @endif
            </td>
            <td>{{ $importes['etiqueta_moneda'] }}</td>
            @if ($modoDeuda)
                <td class="text-right" style="text-align: right;">{{ $formatearMonto(abs($totalMostrar), $abreviaturaFila) }}</td>
                <td class="text-right" style="text-align: right;">
                    @if ($aplicadoMostrar != 0)
                        {{ $formatearMonto(abs($aplicadoMostrar), $abreviaturaFila) }}
                    @endif
                </td>
                <td class="text-right" style="text-align: right;">{{ $formatearMonto($saldoPendiente, $abreviaturaFila) }}</td>
            @else
                <td class="text-right" style="text-align: right;">
                    @if ($totalMostrar >= 0)
                        {{ $formatearMonto($totalMostrar, $abreviaturaFila) }}
                    @endif
                </td>
                <td class="text-right" style="text-align: right;">
                    @if ($totalMostrar < 0)
                        {{ $formatearMonto(abs($totalMostrar), $abreviaturaFila) }}
                    @endif
                </td>
                <td class="text-right" style="text-align: right;">{{ $formatearMonto($saldoFila, $data->monedas->abreviatura ?? $abreviaturaFila) }}</td>
                <td class="text-right" style="text-align: right;">{{ $formatearMonto($saldoFilaPesos, CuentacorrienteSaldosPorMoneda::abreviaturaLocal()) }}</td>
            @endif
        </tr>
    @endforeach
</tbody>
