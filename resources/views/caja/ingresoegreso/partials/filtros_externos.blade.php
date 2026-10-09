@php
    use App\Support\Caja\IngresoEgresoListadoFiltros;

    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = 'ingresoegreso';
    $tiposSeleccion = array_map('intval', (array) ($filtros['tipos'] ?? []));
    $periodoActual = (string) ($filtros['periodo'] ?? '');

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

    $urlTipo = function ($id) use ($baseQ, $rutaIndex, $tiposSeleccion) {
        $q = $baseQ;
        unset($q['tipos']);
        $actuales = $tiposSeleccion;
        $id = (int) $id;
        if ($id === 0) {
            return route($rutaIndex, $q);
        }
        if (in_array($id, $actuales, true)) {
            $actuales = array_values(array_filter($actuales, static fn ($x) => $x !== $id));
        } else {
            $actuales[] = $id;
        }
        if ($actuales !== []) {
            $q['tipos'] = $actuales;
        }

        return route($rutaIndex, $q);
    };

    $urlPeriodo = function ($clave) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['filtro_periodo']);
        if ($clave !== '') {
            $q['filtro_periodo'] = $clave;
        }

        return route($rutaIndex, $q);
    };
@endphp
<div class="card-body py-2 border-bottom bg-white">
    @if (($empresa_query ?? collect())->count() > 1)
        <div class="d-flex flex-wrap align-items-center mb-2">
            <span class="text-muted small mr-2"><i class="fa fa-building"></i> Empresa:</span>
            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de empresa">
                @foreach ($empresa_query as $emp)
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
    @endif
    <div class="d-flex flex-wrap align-items-center mb-2">
        <span class="text-muted small mr-2"><i class="fa fa-tags"></i> Tipo:</span>
        <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de tipo de comprobante">
            <a href="{{ $urlTipo(0) }}"
               class="btn {{ $tiposSeleccion === [] ? 'btn-info' : 'btn-outline-info' }}">
                Todos
            </a>
            @foreach (($tiposFichas ?? []) as $tipoFicha)
                <a href="{{ $urlTipo($tipoFicha['id']) }}"
                   class="btn {{ in_array((int) $tipoFicha['id'], $tiposSeleccion, true) ? 'btn-info' : 'btn-outline-info' }}"
                   title="{{ $tipoFicha['nombre'] }}">
                    {{ $tipoFicha['abreviatura'] !== '' ? $tipoFicha['abreviatura'] : $tipoFicha['nombre'] }}
                </a>
            @endforeach
        </div>
    </div>
    <div class="d-flex flex-wrap align-items-center">
        <span class="text-muted small mr-2"><i class="fa fa-calendar"></i> Período:</span>
        <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de período">
            @foreach (IngresoEgresoListadoFiltros::PERIODOS_FICHA as $clavePeriodo => $etiquetaPeriodo)
                <a href="{{ $urlPeriodo($clavePeriodo) }}"
                   class="btn {{ $periodoActual === (string) $clavePeriodo ? 'btn-info' : 'btn-outline-info' }}">
                    {{ $etiquetaPeriodo }}
                </a>
            @endforeach
        </div>
    </div>
</div>
