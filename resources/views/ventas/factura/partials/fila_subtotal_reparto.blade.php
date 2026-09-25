@php
    use App\Support\Ventas\FacturaListadoFiltros;
    use App\Support\Ventas\FacturaListadoSupport;
    use App\Support\Ventas\PedidoListadoSupport;
    use App\Support\Ventas\VentasListadoEtiquetasSupport;
    $conAcciones = $conAcciones ?? false;
    $metaReparto = $metaReparto ?? null;
    $etiqueta = $metaReparto ? FacturaListadoSupport::etiquetaSubtotalReparto($metaReparto) : '';
    $transporteImpresionId = (int) ($metaReparto->transporte_id ?? 0);
    $filtrosImpresion = $filtros ?? [];
    $etiquetaTransporteLc = mb_strtolower(VentasListadoEtiquetasSupport::etiquetaTransporte());
    $claseNum = $claseNum ?? 'text-right';
    $fmt = $fmt ?? static fn ($v) => PedidoListadoSupport::formatearTotal($v);
@endphp
@if ($metaReparto)
<tr class="factura-subtotal-reparto" bgcolor="#F9E79F"
    style="background-color:#F9E79F !important;font-weight:bold;color:#17202A;">
    <td colspan="{{ VentasListadoEtiquetasSupport::colspanAntesMercaderia() }}">{{ $etiqueta }}</td>
    @include('ventas.factura.partials.celdas_cantidades', [
        'totales' => $metaReparto,
        'claseNum' => $claseNum,
        'fmt' => $fmt,
    ])
    <td>{{ $metaReparto->nombretransporte ?? '' }}</td>
    <td></td>
    <td></td>
    @if ($conAcciones)
        <td class="text-nowrap">
            @if (can('listar-factura', false))
                @php
                    $retornoImpresion = FacturaListadoSupport::pathRetornoIndex(
                        FacturaListadoFiltros::paraQueryString($filtrosImpresion)
                    );
                @endphp
                <a href="{{ route('sesion_impresion_reparto', FacturaListadoFiltros::paraImpresionReparto($filtrosImpresion, $transporteImpresionId, false, $retornoImpresion)) }}"
                   class="btn-accion-tabla tooltipsC"
                   title="Imprimir las facturas de este {{ $etiquetaTransporteLc }} (elige copia; respeta el filtro de fechas)">
                    <i class="fa fa-print"></i>
                </a>
                <a href="{{ route('sesion_impresion_reparto', FacturaListadoFiltros::paraImpresionReparto($filtrosImpresion, $transporteImpresionId, true, $retornoImpresion)) }}"
                   class="btn-accion-tabla tooltipsC"
                   title="Imprimir solo copias de este {{ $etiquetaTransporteLc }}, sin original (elige copia; respeta el filtro de fechas)">
                    <i class="fa fa-copy"></i>
                </a>
            @endif
        </td>
    @endif
</tr>
@endif
