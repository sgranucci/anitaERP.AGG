@if (count($filas) === 0)
    <p class="td-vacio">{{ $vacio }}</p>
@else
    <div class="td-chart td-chart-linea"><canvas id="{{ $canvas }}"></canvas></div>
    <div class="table-responsive">
        <table class="table table-sm td-tabla mb-0">
            <thead>
                <tr>
                    <th>Línea</th>
                    <th class="num">Pares</th>
                    <th class="num">% del período</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $fila)
                    <tr>
                        <td>{{ $fila['nombre'] }}</td>
                        <td class="num">{{ $fila['fmt'] }}</td>
                        <td class="num">{{ number_format($fila['pct'], 1, ',', '.') }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="td-nota">Porcentaje sobre el total de pares del período.</p>
@endif
