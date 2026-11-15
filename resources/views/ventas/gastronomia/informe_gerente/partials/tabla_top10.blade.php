<table class="table table-sm table-striped table-hover mb-0">
    <thead>
        <tr>
            <th>#</th>
            <th>SKU</th>
            <th>Artículo</th>
            <th class="text-right">Cantidad</th>
            <th class="text-right">Importe</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $i => $fila)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $fila['sku'] }}</td>
                <td>{{ $fila['descripcion'] }}</td>
                <td class="text-right">{{ number_format((float) ($fila['cantidad'] ?? 0), 2, ',', '.') }}</td>
                <td class="text-right">${{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted">Sin ventas en el período.</td></tr>
        @endforelse
    </tbody>
</table>
