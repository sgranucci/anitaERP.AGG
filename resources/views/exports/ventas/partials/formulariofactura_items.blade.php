@php
    $itemsPagina = $itemsPagina ?? [];
    $mostrarPrecios = (bool) ($mostrarPrecios ?? true);
    $mostrarBonificacion = (bool) ($mostrarBonificacion ?? false);
    $mostrarTotalesFila = (bool) ($mostrarTotalesFila ?? false);
    $esRemitoHojaItems = (bool) ($esRemitoHoja ?? false);
    $remitoAgrupadoFerli = (bool) ($remitoAgrupadoFerli ?? false);
    $facturaPdfEsFerli = (bool) ($facturaPdfEsFerli ?? \App\Support\Configuracion\EntornoEmpresaSupport::esFerli());
    $facturaPdfEsLocal = (bool) ($facturaPdfEsLocal ?? \App\Support\Ventas\FacturacionLocal\FacturacionLocalPdfSupport::esVentaLocal($venta ?? null));
    $totalCantidad = 0;
    $totalKiloDescuento = 0;
    $totalPiezasPagina = 0;
    $decCant = (int) config('facturacion.DECIMAL_CANTIDAD');
@endphp
@if ($facturaPdfEsFerli && $esRemitoHojaItems && $remitoAgrupadoFerli)
<table class="table table-sm table-bordered table-striped tabla-items-factura factura-items-ferli factura-remito-ferli-agrupado">
    <tr class="tabla-items-head">
        <td style="width:14%;">ARTICULO</td>
        <td style="width:28%;">DESCRIPCION / COLOR</td>
        <td style="width:44%;">MEDIDAS</td>
        <td class="text-center" style="width:14%;">TOTAL PARES</td>
    </tr>
    @foreach ($itemsPagina as $item)
        @php
            $detalleFerli = trim((string) ($item['detalle'] ?? ''));
            $colorFerli = trim((string) ($item['color'] ?? ''));
            if ($colorFerli !== '' && $detalleFerli !== '' && ! str_contains(mb_strtoupper($detalleFerli), mb_strtoupper($colorFerli))) {
                $detalleFerli .= ' '.$colorFerli;
            } elseif ($detalleFerli === '' && $colorFerli !== '') {
                $detalleFerli = $colorFerli;
            }
            $medidas = is_array($item['medidas'] ?? null) ? $item['medidas'] : [];
            $paresItem = (float) ($item['cantidad'] ?? 0);
        @endphp
        <tr>
            <td>{{ $item['sku'] ?? '' }}</td>
            <td>
                {{ $detalleFerli }}
                @if (! empty($item['leyenda']))
                    <br><small>{{ $item['leyenda'] }}</small>
                @endif
            </td>
            <td>
                @if ($medidas !== [])
                    <table class="tabla-medidas-remito-ferli">
                        <tr>
                            @foreach ($medidas as $m)
                                <th>{{ $m['medida'] ?? '' }}</th>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach ($medidas as $m)
                                <td>{{ number_format((float) ($m['cantidad'] ?? 0), 0) }}</td>
                            @endforeach
                        </tr>
                    </table>
                @else
                    <span class="text-muted">—</span>
                @endif
            </td>
            <td class="text-center"><strong>{{ number_format($paresItem, $decCant) }}</strong></td>
        </tr>
        @php $totalCantidad += $paresItem; @endphp
    @endforeach
    @if ($mostrarTotalesFila)
        <tr class="fila-totales-items">
            <td style="{{ $facturaPdfCeldaTotales }}">&nbsp;</td>
            <td style="{{ $facturaPdfCeldaTotales }}" colspan="2"><strong>TOTAL DE PARES</strong></td>
            <td class="text-center" style="{{ $facturaPdfCeldaTotales }}">
                <strong>{{ number_format($totalesDocumento['cantidad'] ?? $totalCantidad, $decCant) }}</strong>
            </td>
        </tr>
    @endif
</table>
@elseif ($facturaPdfEsFerli)
<table class="table table-sm table-bordered table-striped tabla-items-factura factura-items-ferli">
    <tr class="tabla-items-head">
        <td>ARTICULO</td>
        <td>DESCRIPCION COLOR</td>
        <td class="text-center">{{ $esRemitoHojaItems ? 'PARES' : 'CANTIDAD' }}</td>
        @if ($mostrarPrecios)
            <td class="text-right">PRECIO UNITARIO</td>
            <td class="text-right">IMPORTE BRUTO</td>
        @endif
    </tr>
    @foreach ($itemsPagina as $item)
        @php
            $detalleFerli = trim((string) ($item['detalle'] ?? ''));
            $colorFerli = trim((string) ($item['color'] ?? ''));
            if ($colorFerli !== '' && $detalleFerli !== '' && ! str_contains(mb_strtoupper($detalleFerli), mb_strtoupper($colorFerli))) {
                $detalleFerli .= ' '.$colorFerli;
            } elseif ($detalleFerli === '' && $colorFerli !== '') {
                $detalleFerli = $colorFerli;
            }
            if ($facturaPdfEsLocal) {
                $talleFerli = trim((string) ($item['medida'] ?? $item['talle_nombre'] ?? $item['talle_codigo'] ?? ''));
                if ($talleFerli !== '' && ! str_contains(mb_strtoupper($detalleFerli), mb_strtoupper('TALLE '.$talleFerli))) {
                    $detalleFerli = trim($detalleFerli.' Talle '.$talleFerli);
                }
            }
            $importeBruto = round((float) ($item['preciosindescuento'] ?? $item['precio'] ?? 0), 2)
                * round((float) ($item['cantidad'] ?? 0), 2);
        @endphp
        <tr>
            <td>{{ $item['sku'] ?? '' }}</td>
            <td>
                {{ $detalleFerli }}
                @if (! empty($item['leyenda']))
                    <br><small>{{ $item['leyenda'] }}</small>
                @endif
            </td>
            <td class="text-center">{{ number_format((float) ($item['cantidad'] ?? 0), $decCant) }}</td>
            @if ($mostrarPrecios)
                <td class="text-right">{{ number_format((float) ($item['precio'] ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format($importeBruto, 2) }}</td>
            @endif
        </tr>
        @php
            $totalCantidad += $item['cantidad'] ?? 0;
        @endphp
    @endforeach
    @if ($mostrarTotalesFila)
        <tr class="fila-totales-items">
            <td style="{{ $facturaPdfCeldaTotales }}">&nbsp;</td>
            <td style="{{ $facturaPdfCeldaTotales }}"><strong>TOTAL DE PARES</strong></td>
            <td class="text-center" style="{{ $facturaPdfCeldaTotales }}">
                <strong>{{ number_format($totalesDocumento['cantidad'] ?? $totalCantidad, $decCant) }}</strong>
            </td>
            @if ($mostrarPrecios)
                <td style="{{ $facturaPdfCeldaTotales }}">&nbsp;</td>
                <td style="{{ $facturaPdfCeldaTotales }}">&nbsp;</td>
            @endif
        </tr>
    @endif
</table>
@else
<table class="table table-sm table-bordered table-striped tabla-items-factura {{ $facturaPdfRemitoDebajoCliente && $mostrarPrecios ? 'factura-items-debajo-remito' : 'factura-items-debajo-cliente' }}">
    <tr class="tabla-items-head">
        <td>Artículo</td>
        <td>Descripción</td>
        @if ($esRemitoHojaItems)
            <td class="text-center">Piezas</td>
        @endif
        <td class="text-center">Cantidad</td>
        @if ($mostrarBonificacion)
            <td class="text-center">Bonificación</td>
        @endif
        @if ($mostrarPrecios)
            <td class="text-right">Precio</td>
            <td class="text-right">Total Item</td>
        @endif
    </tr>
        @foreach ($itemsPagina as $item)
            <tr>
                <td>{{ $item['sku'] ?? '' }}</td>
                <td>
                    {{ $item['detalle'] ?? '' }}
                    @if (! empty($item['leyenda']))
                        <br><small>{{ $item['leyenda'] }}</small>
                    @endif
                </td>
                @if ($esRemitoHojaItems)
                    <td class="text-center">{{ number_format((float) ($item['pieza'] ?? 0), $decCant) }}</td>
                @endif
                <td class="text-center">{{ number_format($item['cantidad'], $decCant) }}</td>
                @if ($mostrarBonificacion)
                    <td class="text-center">{{ number_format($item['kilodescuento'] ?? 0, $decCant) }}</td>
                @endif
                @if ($mostrarPrecios)
                    @if ($facturaPdfEsElBierzo)
                        <td class="text-right">{{ number_format($item['preciosindescuento'], 2) }}</td>
                        <td class="text-right">{{ number_format($item['preciosindescuento'] * $item['cantidad'], 2) }}</td>
                    @else
                        <td class="text-right">{{ number_format($item['precio'], 2) }}</td>
                        <td class="text-right">{{ number_format(round($item['preciosindescuento'], 2) * round($item['cantidad'], 2), 2) }}</td>
                    @endif
                @endif
            </tr>
            @php
                $totalCantidad += $item['cantidad'] ?? 0;
                $totalKiloDescuento += $item['kilodescuento'] ?? 0;
                $totalPiezasPagina += (float) ($item['pieza'] ?? 0);
            @endphp
        @endforeach
        @if ($mostrarTotalesFila && $esRemitoHojaItems)
            <tr class="fila-totales-items">
                <td style="{{ $facturaPdfCeldaTotales }}">&nbsp;</td>
                <td style="{{ $facturaPdfCeldaTotales }}"><strong>TOTALES</strong></td>
                <td class="text-center" style="{{ $facturaPdfCeldaTotales }}">
                    <strong>{{ number_format($totalPiezasRemito ?? $totalPiezasPagina, $decCant) }}</strong>
                </td>
                <td class="text-center" style="{{ $facturaPdfCeldaTotales }}">
                    <strong>{{ number_format($totalesDocumento['cantidad'] ?? $totalCantidad, $decCant) }}</strong>
                </td>
            </tr>
        @elseif ($mostrarTotalesFila)
            <tr class="fila-totales-items">
                <td style="{{ $facturaPdfCeldaTotales }}">&nbsp;</td>
                <td style="{{ $facturaPdfCeldaTotales }}"><strong>TOTALES</strong></td>
                <td class="text-center" style="{{ $facturaPdfCeldaTotales }}"><strong>{{ number_format($totalesDocumento['cantidad'] ?? $totalCantidad, $decCant) }}</strong></td>
                @if ($mostrarBonificacion)
                    <td class="text-center" style="{{ $facturaPdfCeldaTotales }}"><strong>{{ number_format($totalesDocumento['kilodescuento'] ?? $totalKiloDescuento, $decCant) }}</strong></td>
                @endif
                @if ($mostrarPrecios)
                    <td style="{{ $facturaPdfCeldaTotales }}">&nbsp;</td>
                    <td style="{{ $facturaPdfCeldaTotales }}">&nbsp;</td>
                @endif
            </tr>
        @endif
</table>
@endif
