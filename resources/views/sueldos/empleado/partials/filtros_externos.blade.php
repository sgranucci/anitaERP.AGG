@php
    use App\Support\Sueldos\EmpleadoEstados;

    $estadoActual = $filtros['estado'] ?? EmpleadoEstados::ACTIVO;
    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = 'consultar_empleado_sueldos';

    $urlEstado = function ($cod) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['filtro_estado']);
        if ($cod === '') {
            $q['filtro_estado'] = 'TODOS';
        } elseif ($cod !== EmpleadoEstados::ACTIVO) {
            $q['filtro_estado'] = $cod;
        }

        return route($rutaIndex, $q);
    };

    $urlEmpresa = function ($id) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['empresa_id'], $q['empresa_todas']);
        if ($id === 'todas') {
            $q['empresa_todas'] = 1;
        } else {
            $q['empresa_id'] = $id;
        }

        return route($rutaIndex, $q);
    };
@endphp
<div class="card-body py-2 border-bottom bg-white">
    <div class="d-flex flex-wrap align-items-center">
        <div class="mr-4 mb-1">
            <span class="text-muted small mr-2"><i class="fa fa-filter"></i> Estado:</span>
            <div class="btn-group btn-group-sm" role="group" aria-label="Filtro de estado">
                <a href="{{ $urlEstado(EmpleadoEstados::ACTIVO) }}"
                   class="btn {{ $estadoActual === EmpleadoEstados::ACTIVO ? 'btn-success' : 'btn-outline-success' }}">
                    <i class="fa fa-check"></i> Activos
                </a>
                <a href="{{ $urlEstado(EmpleadoEstados::PROVISORIO) }}"
                   class="btn {{ $estadoActual === EmpleadoEstados::PROVISORIO ? 'btn-warning' : 'btn-outline-warning' }}">
                    Provisorios
                </a>
                <a href="{{ $urlEstado(EmpleadoEstados::BAJA) }}"
                   class="btn {{ $estadoActual === EmpleadoEstados::BAJA ? 'btn-danger' : 'btn-outline-danger' }}">
                    Bajas
                </a>
                <a href="{{ $urlEstado('') }}"
                   class="btn {{ $estadoActual === '' ? 'btn-primary' : 'btn-outline-primary' }}">
                    Todos los empleados
                </a>
            </div>
        </div>
        <div class="mb-1">
            <span class="text-muted small mr-2"><i class="fa fa-building"></i> Empresa:</span>
            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de empresa">
                @foreach ($empresa_query ?? [] as $emp)
                    <a href="{{ $urlEmpresa($emp->id) }}"
                       class="btn {{ ($empresaScope !== 'todas' && $empresaActual === (int) $emp->id) ? 'btn-info' : 'btn-outline-info' }}">
                        {{ $emp->nombre }}
                    </a>
                @endforeach
                <a href="{{ $urlEmpresa('todas') }}"
                   class="btn {{ $empresaScope === 'todas' ? 'btn-primary' : 'btn-outline-primary' }}">
                    Todas mis empresas
                </a>
            </div>
        </div>
    </div>
</div>
