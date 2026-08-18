@php
    $cols = $resultado['columnas'] ?? [];
    $filas = $pagina ? $pagina->items() : ($resultado['filas'] ?? []);
    $totales = $resultado['totales'] ?? [];
    $verEmpleado = ($puede_ver_empleado ?? false);
@endphp
<div class="tabla-ancha-grilla" data-tabla-ancha>
    @if ($pagina)
        <div class="tabla-ancha-toolbar d-flex flex-wrap align-items-center justify-content-between">
            <div class="tabla-ancha-paginacion">{{ $pagina->links() }}</div>
            <span class="small text-muted">
                {{ $pagina->firstItem() }}–{{ $pagina->lastItem() }} de {{ $pagina->total() }}
            </span>
        </div>
    @endif
    <div class="tabla-ancha-scroll-top" hidden>
        <div class="tabla-ancha-scroll-top-inner"></div>
    </div>
    <div class="tabla-ancha-wrap">
        <table id="tabla-paginada" class="table table-sm table-bordered table-hover tabla-ancha">
            <thead>
                <tr>
                    <th class="col-fija-1">Legajo</th>
                    <th class="col-fija-2">Nombre / Grupo</th>
                    @foreach ($cols as $col)
                        <th>{{ $col['descripcion'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($filas as $fila)
                    @php
                        $empleadoId = (int) ($fila['empleado_id'] ?? 0);
                        $legajo = $fila['legajo'] ?? '';
                        $nombre = $fila['nombre'] ?? ($fila['grupo_label'] ?? '');
                        $urlEmpleado = ($verEmpleado && $empleadoId > 0)
                            ? route('editar_empleado_sueldos', [
                                'id' => $empleadoId,
                                'origen' => 'modal_consulta',
                                'vista' => 'consulta',
                            ])
                            : null;
                    @endphp
                    <tr>
                        <td class="col-fija-1">
                            @if ($urlEmpleado && (int) $legajo > 0)
                                <a href="{{ $urlEmpleado }}" class="text-primary" target="_blank" rel="noopener">{{ $legajo }}</a>
                            @else
                                {{ $legajo }}
                            @endif
                        </td>
                        <td class="col-fija-2">
                            @if ($urlEmpleado)
                                <a href="{{ $urlEmpleado }}" class="text-primary" target="_blank" rel="noopener">{{ $nombre }}</a>
                            @else
                                {{ $nombre }}
                            @endif
                        </td>
                        @foreach ($cols as $col)
                            @php
                                $val = $fila['c'.$col['nro']] ?? '';
                                $esNum = !empty($col['numerica']);
                            @endphp
                            <td class="{{ $esNum ? 'text-right' : '' }}">
                                @if ($esNum && !empty($puedeDrill) && !empty($col['id']) && !empty($liquidacionId) && !empty($fila['legajo']))
                                    <a href="#" class="text-primary rsd-drill"
                                       data-columna-id="{{ $col['id'] }}"
                                       data-legajo="{{ $fila['legajo'] }}"
                                       data-liquidacion-id="{{ $liquidacionId }}"
                                       data-reporte-id="{{ $reporteId ?? 0 }}">
                                        {{ is_numeric($val) ? number_format((float) $val, 2, ',', '.') : $val }}
                                    </a>
                                @elseif ($esNum && is_numeric($val))
                                    {{ number_format((float) $val, 2, ',', '.') }}
                                @else
                                    {{ $val }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 2 + count($cols) }}" class="text-center text-muted">Sin filas</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($totales !== [] && ($filas ?? []) !== [])
                <tfoot>
                    <tr>
                        <td class="col-fija-1"></td>
                        <td class="col-fija-2">Totales</td>
                        @foreach ($cols as $col)
                            <td class="text-right">
                                @if (!empty($col['numerica']) && isset($totales[$col['nro']]))
                                    {{ number_format((float) $totales[$col['nro']], 2, ',', '.') }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
