<p class="small text-muted mb-2">Ítems de estacionamiento incluidos en el comprobante fiscal.</p>
<table class="table table-sm table-striped">
    <thead>
        <tr>
            <th>Ítem</th>
            <th>Detalle en factura</th>
            <th class="text-right">Cant.</th>
            <th class="text-right">Precio</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($itemsFacturados as $item)
            @php
                $resaltarItem = ($item_filtro_id ?? 0) > 0
                    && (int) ($item->item_estacionamiento_id ?? 0) === (int) $item_filtro_id;
            @endphp
            <tr class="{{ $resaltarItem ? 'table-info' : '' }}">
                <td>
                    @include('caja.estacionamiento.facturas_dia.partials.link_item_estacionamiento', [
                        'itemId' => $item->item_estacionamiento_id,
                        'nombre' => $item->codigo,
                    ])
                </td>
                <td>{{ $item->detalle }}</td>
                <td class="text-right">{{ number_format($item->cantidad, 3, ',', '.') }}</td>
                <td class="text-right">{{ number_format($item->precio, 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-muted">Sin ítems de emisión.</td></tr>
        @endforelse
    </tbody>
</table>
