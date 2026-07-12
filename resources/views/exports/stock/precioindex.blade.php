<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="10" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="10"><strong style="font-size: 16pt;">Precios de venta</strong></td>
        </tr>
        <tr>
            <td colspan="10">Generado {{ date('d/m/Y H:i') }} · Vigencia al {{ date('d/m/Y', strtotime($fechaReferencia)) }}</td>
        </tr>
        @if (!empty($subtituloFiltros))
            <tr>
                <td colspan="10">{{ $subtituloFiltros }}</td>
            </tr>
        @endif
        @if (($totalFilas ?? 0) > 0)
            <tr>
                <td colspan="10">Registros: {{ $totalFilas }}</td>
            </tr>
        @endif
    </tbody>
    <thead>
        <tr>
            <th>ID</th>
            <th>SKU</th>
            <th>Descripci&oacute;n</th>
            <th>Categor&iacute;a</th>
            <th>Lista</th>
            <th>Vigencia</th>
            <th>Moneda</th>
            <th>Precio</th>
            <th>Prec. ant.</th>
            <th>Usuario</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($precios as $precio)
            <tr>
                <td>{{ $precio->id }}</td>
                <td>{{ $precio->sku }}</td>
                <td>{{ $precio->articulo_descripcion }}</td>
                <td>{{ $precio->categoria_nombre }}</td>
                <td>{{ $precio->listaprecio_nombre }}</td>
                <td>{{ $precio->fechavigencia ? date('d/m/Y', strtotime($precio->fechavigencia)) : '' }}</td>
                <td>{{ $precio->moneda_nombre }}</td>
                <td>{{ number_format((float) $precio->precio, 2, '.', '') }}</td>
                <td>{{ number_format((float) $precio->precioanterior, 2, '.', '') }}</td>
                <td>{{ $precio->usuario_nombre }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
