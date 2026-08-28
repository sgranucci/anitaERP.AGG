@php
    use App\Support\Ventas\FacturaListadoSupport;
    use App\Support\Ventas\PedidoListadoSupport;
    $conAcciones = $conAcciones ?? false;
    $metaReparto = $metaReparto ?? null;
    $etiqueta = $metaReparto ? FacturaListadoSupport::etiquetaSubtotalReparto($metaReparto) : '';
@endphp
@if ($metaReparto)
<tr class="factura-subtotal-reparto" bgcolor="#F9E79F"
    style="background-color:#F9E79F !important;font-weight:bold;color:#17202A;">
    <td colspan="5">{{ $etiqueta }}</td>
    <td class="text-right">{{ PedidoListadoSupport::formatearTotal($metaReparto->caja ?? 0) }}</td>
    <td class="text-right">{{ PedidoListadoSupport::formatearTotal($metaReparto->pieza ?? 0) }}</td>
    <td class="text-right">{{ PedidoListadoSupport::formatearTotal($metaReparto->kilo ?? 0) }}</td>
    <td>{{ $metaReparto->nombretransporte ?? '' }}</td>
    <td></td>
    @if ($conAcciones)
        <td class="text-nowrap">
            @if (can('listar-factura', false))
                <a href="{{ route('sesion_impresion_reparto', ['transporteId' => (int) ($metaReparto->transporte_id ?? 0)] + ($filtrosQuery ?? [])) }}"
                   class="btn-accion-tabla tooltipsC"
                   title="Imprimir las facturas de este reparto (elegir copia)">
                    <i class="fa fa-print"></i>
                </a>
            @endif
        </td>
    @endif
</tr>
@endif
