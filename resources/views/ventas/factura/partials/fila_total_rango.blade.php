@php
    use App\Support\Ventas\PedidoListadoSupport;
    use App\Support\Ventas\VentasListadoEtiquetasSupport;
    $totalesRango = $totalesRango ?? null;
    $conAcciones = $conAcciones ?? false;
    $claseNum = $claseNum ?? 'text-right';
    $fmt = $fmt ?? static fn ($v) => PedidoListadoSupport::formatearTotal($v);
    $fmtImporte = $fmtImporte ?? static fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp
@if ($totalesRango)
<tr class="factura-total-rango" bgcolor="#D5F5E3"
    style="background-color:#D5F5E3 !important;font-weight:bold;color:#17202A;">
    <td colspan="{{ VentasListadoEtiquetasSupport::colspanAntesMercaderia() }}">
        Total del rango
        @php $nComp = (int) ($totalesRango->cantidad_comprobantes ?? 0); @endphp
        — {{ $nComp === 1 ? '1 comprobante' : $nComp.' comprobantes' }}
        — {{ VentasListadoEtiquetasSupport::formatoCantidadConAbreviatura((float) ($totalesRango->kilo ?? 0)) }}
    </td>
    @include('ventas.factura.partials.celdas_cantidades', [
        'totales' => $totalesRango,
        'claseNum' => $claseNum,
        'fmt' => $fmt,
    ])
    <td></td>
    <td></td>
    <td class="{{ $claseNum }}">{{ $fmtImporte($totalesRango->total ?? 0) }}</td>
    @if ($conAcciones)
        <td></td>
    @endif
</tr>
@endif
