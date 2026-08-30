@php
    use App\Support\Ventas\FacturaListadoFiltros;
    use App\Support\Ventas\FacturaListadoSupport;
    use App\Support\Ventas\PedidoListadoSupport;
    $conAcciones = $conAcciones ?? false;
    $metaReparto = $metaReparto ?? null;
    $etiqueta = $metaReparto ? FacturaListadoSupport::etiquetaSubtotalReparto($metaReparto) : '';
    $transporteImpresionId = (int) ($metaReparto->transporte_id ?? 0);
    $filtrosImpresion = $filtros ?? [];
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
                <a href="{{ route('sesion_impresion_reparto', FacturaListadoFiltros::paraImpresionReparto($filtrosImpresion, $transporteImpresionId)) }}"
                   class="btn-accion-tabla tooltipsC"
                   title="Imprimir las facturas de este reparto (elige copia; respeta el filtro de fechas)">
                    <i class="fa fa-print"></i>
                </a>
                <a href="{{ route('sesion_impresion_reparto', FacturaListadoFiltros::paraImpresionReparto($filtrosImpresion, $transporteImpresionId, true)) }}"
                   class="btn-accion-tabla tooltipsC"
                   title="Imprimir solo copias de este reparto, sin original (elige copia; respeta el filtro de fechas)">
                    <i class="fa fa-copy"></i>
                </a>
            @endif
        </td>
    @endif
</tr>
@endif
