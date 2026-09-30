<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="9" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="9"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">{{ $titulo }}</h2></td>
        </tr>
        <tr>
            <td colspan="9">{{ $subtitulo }}</td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>SKU</th>
            <th>Variante</th>
            <th>Combinaci&oacute;n</th>
            <th>Talle</th>
            <th>Stock</th>
            <th>Precio</th>
            <th>Promocional</th>
            <th>Estado</th>
            <th>Detalle</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lineas as $linea)
            <tr>
                <td>{{ $linea->sku }}</td>
                <td>{{ $linea->variante_sku }}</td>
                <td>{{ $linea->combinacion_codigo }}</td>
                <td>{{ $linea->talle }}</td>
                <td>{{ $linea->stock !== null ? (int) $linea->stock : '' }}</td>
                <td>{{ $linea->precio !== null ? number_format((float) $linea->precio, 2, '.', '') : '' }}</td>
                <td>{{ $linea->precio_promocional !== null ? number_format((float) $linea->precio_promocional, 2, '.', '') : '' }}</td>
                <td>{{ \App\Models\Ventas\TiendanubeStockSubidaLinea::etiquetaEstado($linea->estado) }}</td>
                <td>{{ $linea->mensaje }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
