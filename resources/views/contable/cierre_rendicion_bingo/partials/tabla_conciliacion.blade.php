@php
    $gruposColumnas = $resultado['grupos_columnas'] ?? [];
    $columnas = $resultado['columnas'] ?? [];
    $dias = $resultado['dias'] ?? [];
    $totales = $resultado['totales'] ?? [];
    $tol = (float) ($resultado['tolerancia'] ?? 0.02);
    $modoPantalla = ! empty($modoPantalla);
    $fmtNum = $fmtNum ?? function ($v) {
        return number_format((float) $v, 2, ',', '.');
    };
    $fmtEntero = function ($v) {
        return number_format((int) $v, 0, ',', '.');
    };
    $claseGrupo = function (string $grupo): string {
        return match ($grupo) {
            'flash' => 'conc-bingo-flash',
            default => '',
        };
    };
    $colspanTotal = count($columnas) + ($modoPantalla ? 1 : 0);
@endphp
<table id="tabla-paginada" class="table table-bordered table-hover table-sm mb-0 tabla-conc-bingo" style="font-size: 0.78rem; white-space: nowrap;">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            @foreach ($gruposColumnas as $grupo)
                @php $span = max(1, count($grupo['columnas'] ?? [])); @endphp
                @if ($span > 1)
                    <th class="text-center {{ $claseGrupo((string) ($grupo['grupo'] ?? '')) }}" colspan="{{ $span }}">
                        {{ $grupo['titulo'] ?? '' }}
                    </th>
                @else
                    <th class="text-center {{ $claseGrupo((string) ($grupo['grupo'] ?? '')) }}">
                        {{ $grupo['titulo'] ?? '' }}
                    </th>
                @endif
            @endforeach
            @if ($modoPantalla)
                <th class="text-center" style="width: 6rem;">Acciones</th>
            @endif
        </tr>
        <tr>
            @foreach ($gruposColumnas as $grupo)
                @foreach ($grupo['columnas'] ?? [] as $col)
                    <th class="text-center {{ $claseGrupo((string) ($col['grupo'] ?? '')) }}">
                        {{ $col['subtitulo'] ?? '' }}
                    </th>
                @endforeach
            @endforeach
            @if ($modoPantalla)
                <th></th>
            @endif
        </tr>
    </thead>
    <tbody>
        @forelse ($dias as $dia)
            @php
                $estado = (string) ($dia['estado'] ?? '');
                $valores = is_array($dia['valores'] ?? null) ? $dia['valores'] : [];
                $filaClase = $estado === 'DIF' ? 'table-danger' : '';
            @endphp
            <tr class="{{ $filaClase }}">
                @foreach ($columnas as $col)
                    @php
                        $key = (string) ($col['key'] ?? '');
                        $tipo = (string) ($col['tipo'] ?? 'texto');
                        $grupo = (string) ($col['grupo'] ?? '');
                        $valor = $key === 'fecha_fmt' ? ($dia['fecha_fmt'] ?? '')
                            : ($key === 'estado' ? $estado
                            : ($key === 'cantidad_rendiciones' ? (int) ($dia['cantidad_rendiciones'] ?? 0)
                            : ($key === 'estado_cierre' ? (string) ($dia['estado_cierre'] ?? '')
                            : ($valores[$key] ?? 0))));
                        $esDif = in_array($key, ['dif_venta', 'dif_resultado'], true);
                        $claseNum = $esDif && abs((float) $valor) > $tol ? 'text-danger font-weight-bold' : '';
                    @endphp
                    @if ($tipo === 'numero')
                        <td class="text-right {{ $claseGrupo($grupo) }} {{ $claseNum }}">{{ $fmtNum($valor) }}</td>
                    @elseif ($tipo === 'entero')
                        <td class="text-center {{ $claseGrupo($grupo) }}">{{ $fmtEntero($valor) }}</td>
                    @elseif ($key === 'estado' && $modoPantalla)
                        <td class="text-center">
                            @php
                                $badgeClass = match ($estado) {
                                    'OK' => 'badge-success',
                                    'DIF' => 'badge-danger',
                                    default => 'badge-secondary',
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ $estado !== '' ? $estado : '—' }}</span>
                            @if ((int) ($dia['cantidad_pendiente'] ?? 0) > 0)
                                <br><small class="text-warning">{{ (int) $dia['cantidad_pendiente'] }} sin cierre</small>
                            @endif
                        </td>
                    @elseif ($key === 'estado_cierre' && $modoPantalla)
                        <td class="text-center">
                            @php $ec = (string) ($dia['estado_cierre'] ?? ''); @endphp
                            @if ($ec === 'cerrada')
                                <span class="badge badge-success">Cerrado</span>
                            @elseif ($ec === 'parcial')
                                <span class="badge badge-warning">Parcial</span>
                            @else
                                <span class="badge badge-warning">Pendiente</span>
                            @endif
                        </td>
                    @else
                        <td class="{{ $tipo === 'texto' ? '' : 'text-center' }} {{ $claseGrupo($grupo) }}">{{ $valor }}</td>
                    @endif
                @endforeach
                @if ($modoPantalla)
                    <td class="text-center p-1">
                        @if (can('ejecutar-cierre-rendicion-bingo-contable', false)
                            && (int) ($dia['cantidad_grupos_pendientes'] ?? 0) > 0)
                            <button type="button"
                                    class="btn btn-success btn-sm js-cerrar-jornada-conc"
                                    data-empresa-id="{{ (int) ($resultado['empresa_id'] ?? 0) }}"
                                    data-fecha-dia="{{ $dia['fecha'] ?? '' }}"
                                    data-fecha-fmt="{{ $dia['fecha_fmt'] ?? '' }}"
                                    data-grupos="{{ (int) ($dia['cantidad_grupos_pendientes'] ?? 0) }}"
                                    data-pendientes="{{ (int) ($dia['cantidad_pendiente'] ?? 0) }}">
                                <i class="fa fa-lock"></i> Cerrar
                            </button>
                        @else
                            —
                        @endif
                    </td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="{{ $colspanTotal }}" class="text-center text-muted py-4">
                    Sin rendiciones ni flash en el rango indicado.
                </td>
            </tr>
        @endforelse
        @if (! empty($dias) && $totales !== [])
            <tr class="font-weight-bold" style="background:#f5f5f5;">
                @foreach ($columnas as $col)
                    @php
                        $key = (string) ($col['key'] ?? '');
                        $tipo = (string) ($col['tipo'] ?? 'texto');
                        $grupo = (string) ($col['grupo'] ?? '');
                        $valor = $key === 'fecha_fmt' ? 'Total'
                            : ($key === 'estado' || $key === 'estado_cierre' ? ''
                            : ($totales[$key] ?? ''));
                    @endphp
                    @if ($tipo === 'numero')
                        <td class="text-right {{ $claseGrupo($grupo) }}">{{ $fmtNum($valor) }}</td>
                    @elseif ($tipo === 'entero')
                        <td class="text-center {{ $claseGrupo($grupo) }}">{{ $fmtEntero($valor) }}</td>
                    @else
                        <td>{{ $valor }}</td>
                    @endif
                @endforeach
                @if ($modoPantalla)
                    <td></td>
                @endif
            </tr>
        @endif
    </tbody>
</table>
