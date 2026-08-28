@php
    use App\Support\Ventas\PedidoListadoSupport;
    $conAcciones = $conAcciones ?? false;
    $metaReparto = $metaReparto ?? null;
    $etiqueta = $metaReparto ? PedidoListadoSupport::etiquetaSubtotalReparto($metaReparto) : '';
@endphp
@if ($metaReparto)
<tr class="pedido-subtotal-reparto" bgcolor="#F9E79F"
    style="background-color:#F9E79F !important;font-weight:bold;color:#17202A;">
    <td colspan="4">{{ $etiqueta }}</td>
    <td>{{ PedidoListadoSupport::formatearTotal($metaReparto->caja ?? 0) }}</td>
    <td>{{ PedidoListadoSupport::formatearTotal($metaReparto->pieza ?? 0) }}</td>
    <td>{{ PedidoListadoSupport::formatearTotal($metaReparto->kilo ?? 0) }}</td>
    <td>{{ PedidoListadoSupport::formatearTotal($metaReparto->pesada ?? 0) }}</td>
    <td>{{ $metaReparto->nombretransporte ?? '' }}</td>
    <td></td>
    @if ($conAcciones)
        <td></td>
    @endif
</tr>
@endif
