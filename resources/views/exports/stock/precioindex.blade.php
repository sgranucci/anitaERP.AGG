<table>
    <thead>
        <tr>
            <th colspan="8">Precios vigentes al {{ date('d/m/Y', strtotime($fechaReferencia)) }}</th>
        </tr>
        <tr>
            <th>ID</th>
            <th>SKU</th>
            <th>Artículo</th>
            <th>Lista de precios</th>
            <th>Fecha vigencia</th>
            <th>Moneda</th>
            <th>Precio</th>
            <th>Precio anterior</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($precios as $precio)
        <tr>
            <td>{{ $precio->id }}</td>
            <td>{{ $precio->sku }}</td>
            <td>{{ $precio->articulo_descripcion }}</td>
            <td>{{ $precio->listaprecio_nombre }}</td>
            <td>{{ $precio->fechavigencia ? date('d/m/Y', strtotime($precio->fechavigencia)) : '' }}</td>
            <td>{{ $precio->moneda_nombre }}</td>
            <td>{{ (float) $precio->precio }}</td>
            <td>{{ (float) $precio->precioanterior }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
