@php
    use App\Support\Compras\ProveedorCuentacorrienteGrillaSupport;
    use App\Support\Compras\ProveedorCuentacorrientePreferenciasUsuario;
    use App\Support\Cuentacorriente\CuentacorrienteSaldosPorMoneda;

    $modoDeuda = ($modoVista ?? ProveedorCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE)
        === ProveedorCuentacorrientePreferenciasUsuario::MODO_DEUDA;
    $expresion = CuentacorrienteSaldosPorMoneda::resolverExpresion($expresion ?? null);
    $enPesos = CuentacorrienteSaldosPorMoneda::esExpresionPesos($expresion);
    $paraExcel = ! empty($para_excel);
    $saldosCorridos = $saldosAnterioresPorMoneda ?? [];
    $saldoPesos = (float) ($saldoAnteriorPesos ?? 0);
    // Excel en modo auto: número crudo (adaptable a la región de la PC). CSV/forzado: texto formateado.
    $formatoNumeroExcel = $formato_numero_excel ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumeroExcel);
    $formatearMonto = static function ($valor, $abreviatura = '') use ($paraExcel, $formatoNumeroExcel, $autoExcelNum) {
        if ($paraExcel) {
            if ($autoExcelNum) {
                return number_format((float) $valor, 2, '.', '');
            }

            return \App\Support\Export\ExcelFormatoNumero::formatearTexto((float) $valor, $formatoNumeroExcel, 2);
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
            <th style="width: 10%; text-align: right;">Importe</th>
            <th style="width: 10%; text-align: right;">Aplicado</th>
            <th style="width: 11%; text-align: right;">Saldo pendiente</th>
            <th style="width: 12%; text-align: right;">{{ CuentacorrienteSaldosPorMoneda::etiquetaColumnaSaldoPendientePesos() }}</th>
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
            $etiquetaComprobante = ProveedorCuentacorrienteGrillaSupport::etiquetaComprobante($data);
            $importes = CuentacorrienteSaldosPorMoneda::importesParaGrilla(
                $data,
                $enPesos,
                static fn ($total, $aplicado) => ProveedorCuentacorrienteGrillaSupport::saldoPendienteAbsoluto((float) $total, $aplicado)
            );
            $totalMostrar = $importes['total'];
            $aplicadoMostrar = $importes['aplicado'];
            $saldoPendiente = $importes['saldo_pendiente_origen'];
            $saldoPendientePesos = $importes['saldo_pendiente_pesos'];
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
            <td>{{ $etiquetaComprobante }}</td>
            <td>{{ $importes['etiqueta_moneda'] }}</td>
            @if ($modoDeuda)
                <td class="text-right" style="text-align: right;">{{ $formatearMonto(abs($totalMostrar), $abreviaturaFila) }}</td>
                <td class="text-right" style="text-align: right;">
                    @if ($aplicadoMostrar != 0)
                        {{ $formatearMonto(abs($aplicadoMostrar), $abreviaturaFila) }}
                    @endif
                </td>
                <td class="text-right" style="text-align: right;">{{ $formatearMonto($saldoPendiente, $data->monedas->abreviatura ?? $abreviaturaFila) }}</td>
                <td class="text-right" style="text-align: right;">{{ $formatearMonto($saldoPendientePesos, CuentacorrienteSaldosPorMoneda::abreviaturaLocal()) }}</td>
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
