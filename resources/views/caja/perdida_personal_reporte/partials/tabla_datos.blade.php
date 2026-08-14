@php
    $coleccion = $filas instanceof \Illuminate\Support\Collection || $filas instanceof \Illuminate\Contracts\Pagination\Paginator
        ? $filas
        : collect($filas);
@endphp
<div class="table-responsive">
    <table class="table table-sm table-bordered table-striped mb-0" id="tabla-paginada">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th>Legajo</th>
                <th>Empleado</th>
                <th>Ingreso</th>
                <th>Categor&iacute;a</th>
                <th>Agrupamiento</th>
                <th>Lugar trab.</th>
                <th>Fecha</th>
                <th>Concepto</th>
                <th class="text-right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($coleccion as $f)
                <tr class="{{ !empty($f['es_total']) ? 'font-weight-bold table-secondary' : '' }}">
                    <td>
                        @if (!empty($f['empleado_id']) && empty($f['es_total']) && ($puede_ver_empleado ?? false))
                            <a class="text-primary" target="_blank" rel="noopener"
                               href="{{ route('editar_empleado_sueldos', ['id' => $f['empleado_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                                {{ $f['legajo'] }}
                            </a>
                        @else
                            {{ $f['legajo'] }}
                        @endif
                    </td>
                    <td>{{ $f['nombre'] }}</td>
                    <td>{{ $f['fecha_ingreso'] ?? '' }}</td>
                    <td>{{ $f['categoria'] ?? '' }}</td>
                    <td>{{ $f['agrupamiento'] ?? '' }}</td>
                    <td>{{ $f['lugar_trabajo'] ?? '' }}</td>
                    <td>
                        @if (!empty($f['perdida_id']) && ($puede_ver_perdida ?? false))
                            <a class="text-primary" target="_blank" rel="noopener"
                               href="{{ route('editar_perdida_personal', ['id' => $f['perdida_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                                {{ $f['fecha'] }}
                            </a>
                        @else
                            {{ $f['fecha'] ?? '' }}
                        @endif
                    </td>
                    <td>{{ $f['concepto'] ?? '' }}</td>
                    <td class="text-right">{{ number_format((float)($f['importe'] ?? 0), 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-3">Sin movimientos para el filtro.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
