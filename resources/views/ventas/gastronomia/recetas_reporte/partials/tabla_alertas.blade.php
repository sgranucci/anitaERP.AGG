<thead>
    <tr>
        <th>Código</th>
        <th>Receta</th>
        <th>Categoría</th>
        <th>Gravedad</th>
        <th>Tipo</th>
        <th>Detalle</th>
    </tr>
</thead>
<tbody>
    @forelse ($filas as $fila)
        <tr>
            <td>{{ $fila['codigo'] }}</td>
            <td>{{ $fila['receta'] }}</td>
            <td>{{ $fila['categoria'] }}</td>
            <td>{{ $fila['gravedad'] }}</td>
            <td>{{ $fila['tipo'] }}</td>
            <td>{{ $fila['detalle'] }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="6" class="text-center text-muted">No hay alertas para los filtros elegidos.</td>
        </tr>
    @endforelse
</tbody>
