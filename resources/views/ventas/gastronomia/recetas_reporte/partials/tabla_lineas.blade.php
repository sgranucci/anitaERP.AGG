<thead>
    <tr>
        <th>Receta</th>
        <th>Tipo</th>
        <th>SKU / subreceta</th>
        <th>Descripción</th>
        <th>UM</th>
        <th class="text-right">Cantidad</th>
        <th class="text-right">Factor</th>
        <th class="text-right">Cant. c/ desp.</th>
        <th class="text-right">Costo unit.</th>
        <th class="text-right">Costo línea</th>
        <th>Opcional</th>
        <th>Entra al costo</th>
    </tr>
</thead>
<tbody>
    @forelse ($filas as $fila)
        <tr>
            <td>{{ $fila['receta'] }} <small class="text-muted">{{ $fila['codigo_receta'] }}</small></td>
            <td>{{ $fila['tipo'] }}</td>
            <td>{{ $fila['codigo_linea'] }}</td>
            <td>{{ $fila['descripcion'] }}</td>
            <td>{{ $fila['um'] }}</td>
            <td class="text-right">{{ number_format((float) $fila['cantidad'], 4, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) $fila['factor'], 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) $fila['cant_desp'], 4, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) $fila['costo_unitario'], 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) $fila['costo_linea'], 2, ',', '.') }}</td>
            <td>{{ $fila['opcional'] }}</td>
            <td>{{ $fila['entra_costo'] }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="12" class="text-center text-muted">No hay líneas para los filtros elegidos.</td>
        </tr>
    @endforelse
</tbody>
