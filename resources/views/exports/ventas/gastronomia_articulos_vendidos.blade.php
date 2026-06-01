<table>
    <tbody>
        <tr>
            <td colspan="8"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Artículos vendidos — gastronomía</h2></td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>SKU</th>
            <th>Descripción</th>
            <th>Punto de venta</th>
            <th>Depósito</th>
            <th>Cantidad</th>
            <th>Importe</th>
            <th>Comprob.</th>
            <th>Artículo ID</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $f)
            <tr>
                <td>{{ $f->sku ?? '—' }}</td>
                <td>{{ $f->descripcion ?? '—' }}</td>
                <td>{{ $f->puntoventa_etiqueta !== '' ? $f->puntoventa_etiqueta : '—' }}</td>
                <td>{{ $f->deposito_etiqueta !== '' ? $f->deposito_etiqueta : '—' }}</td>
                <td>{{ number_format((float) ($f->cantidad_total ?? 0), 3, ',', '.') }}</td>
                <td>{{ number_format((float) ($f->importe_total ?? 0), 2, ',', '.') }}</td>
                <td>{{ (int) ($f->cantidad_comprobantes ?? 0) }}</td>
                <td>{{ (int) ($f->articulo_id ?? 0) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
