<thead>
    <tr>
        <th>Categoría</th>
        <th>Subcategoría</th>
        <th class="text-right">Fórmulas</th>
        <th class="text-right">Con alerta Alta</th>
        <th class="text-right">Con alerta Media</th>
    </tr>
</thead>
<tbody>
    @forelse ($filas as $fila)
        <tr>
            <td class="celda-amplia">{{ $fila['categoria'] }}</td>
            <td class="celda-amplia">{{ $fila['subcategoria'] }}</td>
            <td class="text-right">{{ (int) $fila['recetas'] }}</td>
            <td class="text-right">{{ (int) $fila['alta'] }}</td>
            <td class="text-right">{{ (int) $fila['media'] }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="5" class="text-center text-muted">No hay fórmulas para los filtros elegidos.</td>
        </tr>
    @endforelse
</tbody>
@if (! empty($filas))
<tfoot>
    <tr>
        <th>Total</th>
        <th></th>
        <th class="text-right">{{ (int) ($totales['recetas'] ?? 0) }}</th>
        <th class="text-right">{{ (int) ($totales['alta'] ?? 0) }}</th>
        <th class="text-right">{{ (int) ($totales['media'] ?? 0) }}</th>
    </tr>
</tfoot>
@endif
