@php
    $filasLista = $filas ?? [];
    if ($filasLista instanceof \Illuminate\Pagination\LengthAwarePaginator) {
        $filasLista = $filasLista->items();
    }
    $fmt = static function ($n) {
        return number_format((float) $n, 3, ',', '.');
    };
@endphp
@if (empty($solo_thead_tbody))
<table class="table table-bordered table-sm mb-0">
@endif
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th>SKU</th>
            <th>Descripción</th>
            <th>Categoría</th>
            <th>Subcategoría</th>
            <th>Uso</th>
            <th>Tipo</th>
            <th class="text-right">Exist. inicial</th>
            <th class="text-right">Recepciones</th>
            <th class="text-right">Devoluciones</th>
            <th class="text-right">Ajustes</th>
            <th class="text-right">Consumos</th>
            <th class="text-right">Otros</th>
            <th class="text-right">Exist. final</th>
            <th class="text-right">Dif.</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filasLista as $fila)
            @php $articuloId = (int) ($fila['articulo_id'] ?? 0); @endphp
            <tr @if (empty($fila['cierra'])) class="table-warning" @endif>
                <td>
                    @if (($puede_ver_articulo ?? false) && $articuloId > 0)
                        <a href="{{ route('editar_articulo', ['id' => $articuloId, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                            class="text-primary" target="_blank" rel="noopener">{{ $fila['sku'] ?? '' }}</a>
                    @else
                        {{ $fila['sku'] ?? '' }}
                    @endif
                </td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                <td>{{ $fila['categoria'] ?? '' }}</td>
                <td>{{ $fila['subcategoria'] ?? '' }}</td>
                <td>{{ $fila['uso'] ?? '' }}</td>
                <td>{{ $fila['tipo'] ?? '' }}</td>
                <td class="text-right">{{ $fmt($fila['existencia_inicial'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($fila['recepciones'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($fila['devoluciones'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($fila['ajustes'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($fila['consumos'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($fila['otros'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($fila['existencia_final'] ?? 0) }}</td>
                <td class="text-right">{{ empty($fila['cierra']) ? $fmt($fila['diferencia'] ?? 0) : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="14" class="text-center text-muted">Sin datos para los filtros</td></tr>
        @endforelse
    </tbody>
    @if (! empty($totales) && empty($omitir_totales))
        <tfoot>
            <tr class="font-weight-bold">
                <td colspan="6">Totales ({{ $totales['total_articulos'] ?? 0 }} art.)</td>
                <td class="text-right">{{ $fmt($totales['existencia_inicial'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($totales['recepciones'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($totales['devoluciones'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($totales['ajustes'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($totales['consumos'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($totales['otros'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($totales['existencia_final'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($totales['diferencia'] ?? 0) }}</td>
            </tr>
        </tfoot>
    @endif
@if (empty($solo_thead_tbody))
</table>
@endif
