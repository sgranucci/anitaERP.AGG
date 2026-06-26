@php
    $tot = $bloque['totales'] ?? [];
@endphp
<thead>
    <tr>
        <th>Artículo</th>
        <th>Descripción</th>
        <th class="text-right">Unidades</th>
        <th class="text-right">Costo unit.</th>
        <th class="text-right">Costo total</th>
        <th class="text-right">Precio vta.</th>
        <th class="text-right">Total venta</th>
    </tr>
</thead>
<tbody>
    @forelse ($bloque['filas'] ?? [] as $fila)
        <tr>
            <td class="text-nowrap">
                @if (($puede_ver_articulo ?? false) && (int) ($fila['articulo_id'] ?? 0) > 0)
                    <a href="{{ route('editar_articulo', ['id' => $fila['articulo_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ $fila['sku'] ?? '—' }}
                    </a>
                @else
                    {{ $fila['sku'] ?? '—' }}
                @endif
            </td>
            <td>{{ $fila['descripcion'] ?? '—' }}</td>
            <td class="text-right">{{ number_format((float) ($fila['unidades'] ?? 0), 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) ($fila['costo_unitario'] ?? 0), 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) ($fila['costo_total'] ?? 0), 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) ($fila['precio_venta'] ?? 0), 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) ($fila['total_venta'] ?? 0), 2, ',', '.') }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="7" class="text-center text-muted">Sin ventas con este descuento en el período.</td>
        </tr>
    @endforelse
</tbody>
<tfoot>
    <tr class="table-active font-weight-bold">
        <td colspan="2">Total final</td>
        <td class="text-right">{{ number_format((float) ($tot['unidades'] ?? 0), 0, ',', '.') }}</td>
        <td></td>
        <td class="text-right">{{ number_format((float) ($tot['costo_total'] ?? 0), 2, ',', '.') }}</td>
        <td></td>
        <td class="text-right">{{ number_format((float) ($tot['total_venta'] ?? 0), 2, ',', '.') }}</td>
    </tr>
</tfoot>
