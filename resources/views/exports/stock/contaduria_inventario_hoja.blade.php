<table>
    <tr>
        <td colspan="6"><strong>{{ $datos['encabezado_titulo'] ?? '' }}</strong></td>
    </tr>
    <tr>
        <td colspan="6">{{ $datos['encabezado_datos'] ?? '' }}</td>
    </tr>
    <tr>
        <td colspan="6">{{ $datos['encabezado_pie'] ?? '' }}</td>
    </tr>
    <tr>
        <th>SKU</th>
        <th>Descripción</th>
        <th>UM</th>
        <th>Contado</th>
        <th>Costo u/c</th>
        <th>Valor contado</th>
    </tr>
    @foreach ($datos['filas'] ?? [] as $fila)
        <tr>
            <td>{{ $fila['sku'] ?? '' }}</td>
            <td>{{ $fila['descripcion'] ?? '' }}</td>
            <td>{{ $fila['um'] ?? '' }}</td>
            <td>{{ \App\Exports\Stock\ContaduriaInventarioHojaExport::cantidadVisible($fila['saldo'] ?? 0) }}</td>
            <td>{{ number_format((float) ($fila['precio'] ?? 0), 2, '.', '') }}</td>
            <td>{{ number_format((float) ($fila['valor'] ?? 0), 2, '.', '') }}</td>
        </tr>
    @endforeach
    <tr>
        <td colspan="5"><strong>Total general (costo u/c)</strong></td>
        <td><strong>{{ number_format((float) ($datos['total_valor'] ?? 0), 2, '.', '') }}</strong></td>
    </tr>
</table>
