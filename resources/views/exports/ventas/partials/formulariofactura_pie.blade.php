<div class="factura-pie-bloque">
    <div class="factura-totales-wrap">
        <table cellpadding="0" cellspacing="0" class="table table-sm table-bordered table-striped tabla-totales-importes">
            <tbody>
                @if ($letra == 'B')
                    @php
                        $lineasTotalesLetraB = $lineasTotalesLetraB ?? \App\Support\Ventas\FacturaBTotalesImpresionSupport::lineas($conceptosTotales);
                    @endphp
                    @foreach ($lineasTotalesLetraB as $lineaTotalB)
                        @if (! empty($lineaTotalB['es_total']))
                            <tr class="fila-total-final">
                                <td style="{{ $facturaPdfCeldaTotales }}"><strong>{{ $lineaTotalB['concepto'] }}</strong></td>
                                <td style="{{ $facturaPdfCeldaTotales }}"></td>
                                <td class="text-right" style="{{ $facturaPdfCeldaTotales }}">
                                    <strong>{{ $venta->monedas->abreviatura }} {{ number_format($lineaTotalB['importe'], 2) }}</strong>
                                </td>
                            </tr>
                        @else
                            <tr>
                                <td>{{ $lineaTotalB['concepto'] }}</td>
                                <td>
                                    @if ($lineaTotalB['tasa'] != 0)
                                        {{ number_format($lineaTotalB['tasa'], 2) }}
                                    @endif
                                </td>
                                <td class="text-right">{{ number_format($lineaTotalB['importe'], 2) }}</td>
                            </tr>
                        @endif
                    @endforeach
                @else
                    @foreach ($conceptosTotales as $itemTotal)
                        @if ($letra == 'A')
                            <tr @if ($itemTotal['concepto'] == 'Total') class="fila-total-final" @endif>
                                <td @if ($itemTotal['concepto'] == 'Total') style="{{ $facturaPdfCeldaTotales }}" @endif>
                                    @if ($itemTotal['concepto'] == 'Total')
                                        <strong>{{ $itemTotal['concepto'] }}</strong>
                                    @else
                                        {{ $itemTotal['concepto'] }}
                                    @endif
                                </td>
                                <td @if ($itemTotal['concepto'] == 'Total') style="{{ $facturaPdfCeldaTotales }}" @endif>
                                    @if ($itemTotal['tasa'] != 0)
                                        {{ number_format($itemTotal['tasa'], 2) }}
                                    @endif
                                </td>
                                <td class="text-right" @if ($itemTotal['concepto'] == 'Total') style="{{ $facturaPdfCeldaTotales }}" @endif>
                                    @if ($itemTotal['concepto'] == 'Total')
                                        <strong>{{ $venta->monedas->abreviatura }} {{ number_format($itemTotal['importe'], 2) }}</strong>
                                    @else
                                        {{ number_format($itemTotal['importe'], 2) }}
                                    @endif
                                </td>
                            </tr>
                        @elseif ($itemTotal['concepto'] == 'Total')
                            <tr class="fila-total-final">
                                <td style="{{ $facturaPdfCeldaTotales }}"><strong>{{ $itemTotal['concepto'] }}</strong></td>
                                <td style="{{ $facturaPdfCeldaTotales }}"></td>
                                <td class="text-right" style="{{ $facturaPdfCeldaTotales }}">
                                    <strong>{{ $venta->monedas->abreviatura }} {{ number_format($itemTotal['importe'], 2) }}</strong>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>
    <table class="table borderless factura-pie-fiscal">
        <tr>
            <td class="factura-pie-qr">
                <img src="{{ $qrDataUri }}" width="80" height="80" alt="Codigo QR">
            </td>
            @if ($facturaPdfPieCentroTieneTexto)
            <td class="factura-pie-leyendas">
                @if ($letra == 'B')
                    RÉGIMEN DE TRANSPARENCIA FISCAL AL CONSUMIDOR (Ley 27.743)<br>
                    IVA Contenido {{ $venta->monedas->abreviatura ?? '' }} {{ number_format($ivaContenido, 2) }}<br>
                    Otros Tributos Nac. que inciden en el precio
                    @if ($impuestoInterno > 0)
                        <br>&nbsp;&nbsp;&nbsp;Impuesto Interno {{ $venta->monedas->abreviatura ?? '' }} {{ number_format($impuestoInterno, 2) }}
                    @endif
                @endif
                @if ($facturaPdfEsElBierzo)
                    @if ($letra == 'B')<br>@endif
                    EMITIR LOS CHEQUES A LA ORDEN DE FRIGORIFICO EL BIERZO<br>
                    CONTROLE EL PESO DE LA MERCADERIA<br>
                    NO SE ACEPTAN RECLAMOS
                @endif
            </td>
            @endif
            <td class="factura-pie-cae">
                CAE: {{ $venta->cae }}<br>
                Fecha Vencimiento CAE: {{ date('d/m/Y', strtotime($venta->fechavencimientocae ?? '')) }}
            </td>
        </tr>
    </table>
    @if (! empty($venta->leyenda) && trim((string) $venta->leyenda) !== '')
        <p class="factura-leyenda"><strong>Leyendas</strong> {{ $venta->leyenda }}</p>
    @endif
</div>
