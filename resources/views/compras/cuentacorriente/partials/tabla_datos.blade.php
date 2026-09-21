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
@php
    $paraPdf = ! empty($para_pdf);
    $clsNowrap = $paraPdf ? 'col-nowrap' : '';
    $clsTexto = $paraPdf ? 'col-texto' : '';
@endphp
<thead>
    <tr>
        <th class="{{ $clsNowrap }}" style="width: 5%;">ID</th>
        <th class="{{ $clsTexto }}" style="width: 11%;">Empresa</th>
        <th class="{{ $clsNowrap }}" style="width: 8%;">Fecha</th>
        <th class="{{ $clsNowrap }}" style="width: 8%;">Vencimiento</th>
        <th class="{{ $clsTexto }}" style="width: {{ $enPesos ? '18%' : '22%' }};">Comprobante</th>
        <th class="{{ $clsNowrap }}" style="width: {{ $enPesos ? '8%' : '6%' }};">Moneda</th>
        @if ($modoDeuda)
            <th class="text-right" style="width: 10%; text-align: right;">Importe</th>
            <th class="text-right" style="width: 10%; text-align: right;">Aplicado</th>
            <th class="text-right" style="width: 11%; text-align: right;">Saldo pendiente</th>
            <th class="text-right" style="width: 11%; text-align: right;">{{ CuentacorrienteSaldosPorMoneda::etiquetaColumnaSaldoPendientePesos() }}</th>
        @else
            <th class="text-right" style="width: 10%; text-align: right;">Debe</th>
            <th class="text-right" style="width: 10%; text-align: right;">Haber</th>
            <th class="text-right" style="width: 11%; text-align: right;">Saldo</th>
            <th class="text-right" style="width: 11%; text-align: right;">{{ CuentacorrienteSaldosPorMoneda::etiquetaColumnaSaldoPesos() }}</th>
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
                $modoDeuda
                    ? static fn ($total, $aplicado) => ProveedorCuentacorrienteGrillaSupport::saldoPendiente((float) $total, $aplicado)
                    : static fn ($total, $aplicado) => ProveedorCuentacorrienteGrillaSupport::saldoPendienteAbsoluto((float) $total, $aplicado)
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
            <td class="{{ $clsNowrap }}">{{ $data->id }}</td>
            <td class="{{ $clsTexto }}">{{ $data->empresas->nombre ?? ($data->nombreempresa ?? '') }}</td>
            @php
                $fechaComp = ProveedorCuentacorrienteGrillaSupport::fechaComprobante($data);
                $fechaVto = ProveedorCuentacorrienteGrillaSupport::fechaVencimiento($data);
            @endphp
            <td class="{{ $clsNowrap }}">{{ $fechaComp ? date('d/m/Y', strtotime((string) $fechaComp)) : '' }}</td>
            <td class="{{ $clsNowrap }}">{{ $fechaVto ? date('d/m/Y', strtotime((string) $fechaVto)) : '' }}</td>
            <td class="{{ $clsTexto }}">{{ $etiquetaComprobante }}</td>
            <td class="{{ $clsNowrap }}">{{ $importes['etiqueta_moneda'] }}</td>
            @if ($modoDeuda)
                <td class="text-right" style="text-align: right;">{{ $formatearMonto($totalMostrar, $abreviaturaFila) }}</td>
                <td class="text-right" style="text-align: right;">
                    @if ($aplicadoMostrar != 0)
                        {{ $formatearMonto($aplicadoMostrar, $abreviaturaFila) }}
                    @endif
                </td>
                <td class="text-right" style="text-align: right;">{{ $formatearMonto($saldoPendiente, $data->monedas->abreviatura ?? $abreviaturaFila) }}</td>
                <td class="text-right" style="text-align: right;">{{ $formatearMonto($saldoPendientePesos, CuentacorrienteSaldosPorMoneda::abreviaturaLocal()) }}</td>
            @else
                @php
                    $dh = ProveedorCuentacorrienteGrillaSupport::debeHaberDesdeTotal((float) $totalMostrar);
                @endphp
                <td class="text-right" style="text-align: right;">
                    @if ($dh['debe'] !== null)
                        {{ $formatearMonto($dh['debe'], $abreviaturaFila) }}
                    @endif
                </td>
                <td class="text-right" style="text-align: right;">
                    @if ($dh['haber'] !== null)
                        {{ $formatearMonto($dh['haber'], $abreviaturaFila) }}
                    @endif
                </td>
                <td class="text-right" style="text-align: right;">{{ $formatearMonto($saldoFila, $data->monedas->abreviatura ?? $abreviaturaFila) }}</td>
                <td class="text-right" style="text-align: right;">{{ $formatearMonto($saldoFilaPesos, CuentacorrienteSaldosPorMoneda::abreviaturaLocal()) }}</td>
            @endif
        </tr>
    @endforeach
</tbody>
