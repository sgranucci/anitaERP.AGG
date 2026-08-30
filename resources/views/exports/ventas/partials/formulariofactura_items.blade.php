@php
    $itemsPagina = $itemsPagina ?? [];
    $mostrarPrecios = (bool) ($mostrarPrecios ?? true);
    $mostrarBonificacion = (bool) ($mostrarBonificacion ?? false);
    $mostrarTotalesFila = (bool) ($mostrarTotalesFila ?? false);
    $esRemitoHojaItems = (bool) ($esRemitoHoja ?? false);
    $totalCantidad = 0;
    $totalKiloDescuento = 0;
    $totalPiezasPagina = 0;
    $decCant = (int) config('facturacion.DECIMAL_CANTIDAD');
@endphp
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
                <td>{{ $item['detalle'] ?? '' }}</td>
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
@if (! $mostrarTotalesFila)
    <p class="factura-continua">Continúa en la página siguiente…</p>
@endif
