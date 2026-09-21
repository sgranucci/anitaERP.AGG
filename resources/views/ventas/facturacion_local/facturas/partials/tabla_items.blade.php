<p class="small text-muted mb-2">Ítems incluidos en el comprobante fiscal.</p>
<table class="table table-sm table-striped">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th>SKU</th>
            <th>Detalle</th>
            <th class="text-right">Cant.</th>
            <th class="text-right">Precio</th>
            <th class="text-right">Dto.</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($itemsFacturados as $item)
            <tr>
                <td>
                    @if ((int) ($item->articulo_id ?? 0) > 0 && can('editar-articulos', false))
                        <a href="{{ route('editar_articulo', ['id' => $item->articulo_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           target="_blank" rel="noopener" class="text-primary">{{ $item->codigo }}</a>
                    @else
                        {{ $item->codigo }}
                    @endif
                </td>
                <td>{{ $item->detalle }}</td>
                <td class="text-right">{{ number_format($item->cantidad, 3, ',', '.') }}</td>
                <td class="text-right">{{ number_format($item->precio, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($item->descuento ?? 0, 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-muted">Sin ítems de emisión.</td></tr>
        @endforelse
    </tbody>
</table>
