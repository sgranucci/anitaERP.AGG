<div class="td-panel">
    <h2>{{ $titulo }}</h2>
    <div class="td-cuerpo">
        @if (count($filas) === 0)
            <p class="td-vacio">Sin renglones de artículo en este período.</p>
        @else
            <table class="table table-sm td-tabla mb-0">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Descripción</th>
                        <th class="num">Cantidad</th>
                        <th class="num">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filas as $fila)
                        <tr>
                            <td>{{ $fila['sku'] }}</td>
                            <td>{{ $fila['nombre'] }}</td>
                            <td class="num">{{ number_format($fila['cantidad'], 2, ',', '.') }}</td>
                            <td class="num">$ {{ $fila['monto_fmt'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
