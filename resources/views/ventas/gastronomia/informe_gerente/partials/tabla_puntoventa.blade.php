<table class="table table-sm table-striped table-hover mb-0">
    <thead>
        <tr>
            <th>PV</th>
            <th>Nombre</th>
            <th class="text-right">Facturas</th>
            <th class="text-right">NC</th>
            <th class="text-right">Total neto</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            <tr>
                <td>{{ $fila['codigo'] }}</td>
                <td>{{ $fila['nombre'] }}</td>
                <td class="text-right">{{ $fila['cantidad_facturas'] }}</td>
                <td class="text-right">{{ $fila['cantidad_notas_credito'] }}</td>
                <td class="text-right">${{ number_format($fila['total'], 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted">Sin puntos de venta gastronomía activos.</td></tr>
        @endforelse
    </tbody>
</table>
