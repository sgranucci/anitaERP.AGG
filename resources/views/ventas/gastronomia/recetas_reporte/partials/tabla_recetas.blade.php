<thead>
    <tr>
        <th>Código</th>
        <th>Receta</th>
        <th>Artículo de venta</th>
        <th>Estado</th>
        <th>Categoría</th>
        <th>Subcategoría</th>
        <th class="text-right">Porciones</th>
        <th class="text-right">Costo porción</th>
        <th class="text-right">Costo lote</th>
        <th class="text-right">Precio</th>
        <th class="text-right">Precio / costo</th>
        <th class="text-right">Líneas</th>
        <th>Alerta</th>
    </tr>
</thead>
<tbody>
    @forelse ($filas as $fila)
        <tr>
            <td>
                @if (! empty($puede_editar_formula))
                    <a href="{{ route('editar_formula_articulo', ['id' => $fila['formula_id']]) }}">{{ $fila['codigo'] }}</a>
                @else
                    {{ $fila['codigo'] }}
                @endif
            </td>
            <td class="celda-amplia">{{ $fila['nombre'] }}</td>
            <td>
                @if (($fila['sku'] ?? '') !== '')
                    <small class="text-muted">{{ $fila['sku'] }}</small>
                @else
                    <span class="text-muted">—</span>
                @endif
            </td>
            <td>{{ $fila['estado_articulo'] }}</td>
            <td>{{ $fila['categoria'] }}</td>
            <td>{{ $fila['subcategoria'] }}</td>
            <td class="text-right">{{ number_format((float) $fila['porciones'], 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) $fila['costo'], 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) ($fila['costo_lote'] ?? 0), 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) $fila['precio'], 2, ',', '.') }}</td>
            <td class="text-right">{{ $fila['ratio'] === null ? '—' : number_format((float) $fila['ratio'], 2, ',', '.') }}</td>
            <td class="text-right">{{ (int) $fila['lineas'] }}</td>
            <td>
                @if (! empty($fila['alerta']))
                    {{ $fila['alerta'] }}
                    <small class="text-muted">({{ (int) $fila['cant_alertas'] }})</small>
                @endif
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="13" class="text-center text-muted">No hay recetas para los filtros elegidos.</td>
        </tr>
    @endforelse
</tbody>
