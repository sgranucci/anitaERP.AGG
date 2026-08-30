@php
    use App\Support\Ventas\PedidoListadoSupport;
    $conAcciones = $conAcciones ?? false;
    $metaReparto = $metaReparto ?? null;
    $etiqueta = $metaReparto ? PedidoListadoSupport::etiquetaSubtotalReparto($metaReparto) : '';
    $accionesReparto = $accionesReparto ?? null;
    $transporteAccionId = (int) ($metaReparto->transporte_id ?? $accionesReparto->transporte_id ?? 0);
    $cantidadVentasReparto = (int) ($accionesReparto->cantidad_ventas ?? 0);
    $cantidadFacturablesReparto = (int) ($accionesReparto->cantidad_facturables ?? 0);
    $retornoImpresion = $retornoIndexPath ?? '';
    $filtrosImpresion = $filtrosQuery ?? [];
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
        <td class="text-nowrap">
            @if (can('listar-factura', false) && $cantidadVentasReparto > 0 && $transporteAccionId > 0)
                <a href="{{ route('sesion_impresion_reparto_pedidos', PedidoListadoSupport::paraImpresionReparto($filtrosImpresion, $transporteAccionId, false, $retornoImpresion)) }}"
                   class="btn-accion-tabla tooltipsC"
                   title="Imprimir las facturas hechas de este reparto">
                    <i class="fa fa-print"></i>
                </a>
                <a href="{{ route('sesion_impresion_reparto_pedidos', PedidoListadoSupport::paraImpresionReparto($filtrosImpresion, $transporteAccionId, true, $retornoImpresion)) }}"
                   class="btn-accion-tabla tooltipsC"
                   title="Imprimir solo copias de las facturas de este reparto">
                    <i class="fa fa-copy"></i>
                </a>
            @endif
            @if (!empty($puedeFacturarReparto) && $cantidadFacturablesReparto > 0 && $transporteAccionId > 0)
                <button type="button"
                        class="btn-accion-tabla tooltipsC btn-facturar-reparto-index"
                        title="Facturar todos los pedidos pesados de este reparto"
                        data-transporte-id="{{ $transporteAccionId }}">
                    <i class="fas fa-file-invoice text-success"></i>
                </button>
            @endif
        </td>
    @endif
</tr>
@endif
