@foreach ($grupos as $grupo)
    <h5 class="mt-3 mb-1">{{ $grupo['titulo'] }}</h5>
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-1">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>Valor</th>
                    <th class="text-right">Inicial</th>
                    <th class="text-right">Ingresos</th>
                    <th class="text-right">Egresos</th>
                    <th class="text-right">Actual</th>
                    <th class="text-right">Monto actual</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($grupo['filas'] as $fila)
                    @php
                        $valor = $fila['valor'];
                        $den = (float) $valor->denominacion;
                    @endphp
                    <tr>
                        <td>{{ number_format($den, $den == (int) $den ? 0 : 2, ',', '.') }} {{ $valor->tipoValor->codigo ?? '' }}</td>
                        <td class="text-right">{{ number_format($fila['inicial'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($fila['ingresos'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($fila['egresos'], 0, ',', '.') }}</td>
                        <td class="text-right"><strong>{{ number_format($fila['actual'], 0, ',', '.') }}</strong></td>
                        <td class="text-right">{{ number_format($fila['monto_actual'] ?? ($fila['actual'] * $den), 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:#EAF2F8;">
                    <th>Total {{ $grupo['titulo'] }}</th>
                    <th class="text-right">{{ number_format($grupo['totales']['inicial'] ?? 0, 2, ',', '.') }}</th>
                    <th class="text-right">{{ number_format($grupo['totales']['ingresos'] ?? 0, 2, ',', '.') }}</th>
                    <th class="text-right">{{ number_format($grupo['totales']['egresos'] ?? 0, 2, ',', '.') }}</th>
                    <th class="text-right">{{ number_format($grupo['totales']['actual'] ?? 0, 2, ',', '.') }}</th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
@endforeach
