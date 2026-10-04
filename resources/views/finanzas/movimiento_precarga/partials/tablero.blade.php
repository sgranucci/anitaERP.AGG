@php
    $hojas = $tablero['hojas'] ?? [];
    $movimientos = $tablero['movimientos'] ?? [];
    $sinHoja = (int) ($tablero['sin_hoja'] ?? 0);
@endphp
@if ($sinHoja > 0)
    <div class="alert alert-warning py-2">{{ $sinHoja }} movimiento(s) sin banco de la posición (Macro, BMA, BAPRO, Bi Bank o Bind).</div>
@endif
@if ($hojas === [])
    <p class="text-muted mb-2">Todavía no hay importes de precarga para esta fecha en las hojas de bancos.</p>
@endif
@foreach ($hojas as $hoja)
    <div class="precarga-hoja">
        <div class="precarga-hoja-titulo">{{ $hoja['nombre'] }}</div>
        <table class="table table-sm precarga-grid mb-3">
            <thead>
                <tr>
                    <th>Movimiento</th>
                    <th class="text-right">Biyemas</th>
                    <th class="text-right">Kandiko</th>
                    <th class="text-right">Rebisco</th>
                    <th>Nota</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($hoja['filas'] as $fila)
                    @php
                        $vacia = $fila['biy'] === null && $fila['kan'] === null && $fila['reb'] === null;
                    @endphp
                    <tr class="tono-{{ $fila['tono'] }} {{ $vacia ? 'fila-vacia' : '' }}">
                        <td>{{ $fila['etiqueta'] }}</td>
                        <td class="text-right">{{ $fila['biy'] === null ? '' : number_format((float) $fila['biy'], 2, ',', '.') }}</td>
                        <td class="text-right">{{ $fila['kan'] === null ? '' : number_format((float) $fila['kan'], 2, ',', '.') }}</td>
                        <td class="text-right">{{ $fila['reb'] === null ? '' : number_format((float) $fila['reb'], 2, ',', '.') }}</td>
                        <td>{{ $fila['nota'] }}</td>
                    </tr>
                @empty
                    <tr class="fila-vacia">
                        <td colspan="5">Sin movimientos en este banco.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endforeach
@if ($movimientos !== [])
    <div class="precarga-lista">
        <div class="precarga-hoja-titulo">Movimientos del día</div>
        <ul class="list-unstyled mb-0">
            @foreach ($movimientos as $mov)
                <li>
                    <strong>{{ $mov['tipo'] }}</strong>
                    · {{ $mov['rubro'] }}
                    · {{ $mov['empresa'] }}
                    · {{ number_format((float) $mov['monto'], 2, ',', '.') }}
                    <span class="text-muted">{{ $mov['detalle'] }}</span>
                    <span class="badge badge-light">{{ $mov['estado'] }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
