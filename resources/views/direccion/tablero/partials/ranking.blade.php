<div class="td-panel">
    <h2>{{ $titulo }}</h2>
    <div class="td-cuerpo">
        @if (count($filas) === 0)
            <p class="td-vacio">Sin movimientos en este período.</p>
        @else
            <table class="table table-sm td-tabla mb-0">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th class="num">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filas as $fila)
                        <tr>
                            <td>{{ $fila['nombre'] }}</td>
                            <td class="num">$ {{ $fila['monto_fmt'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
