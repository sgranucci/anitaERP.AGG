@php
    use App\Support\Compras\ComprobanteProveedorEstados;

    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $estadoActual = (string) ($filtros['estado'] ?? ComprobanteProveedorEstados::FILTRO_TODOS);
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = 'comprobante_proveedor';

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

    $urlEstado = function ($estado) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['estado'], $q['estado_todas']);
        if ($estado === ComprobanteProveedorEstados::FILTRO_TODOS) {
            $q['estado_todas'] = 1;
        } else {
            $q['estado'] = $estado;
        }

        return route($rutaIndex, $q);
    };
@endphp
<div class="card-body py-2 border-bottom bg-white">
    @if (($empresa_query ?? collect())->count() > 1)
    <div class="d-flex flex-wrap align-items-center mb-1">
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
    <div class="d-flex flex-wrap align-items-center">
        <span class="text-muted small mr-2"><i class="fa fa-filter"></i> Estado:</span>
        <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de estado">
            @foreach (ComprobanteProveedorEstados::opcionesFiltroListado() as $codEstado => $etiquetaEstado)
                <a href="{{ $urlEstado($codEstado) }}"
                   class="btn {{ ComprobanteProveedorEstados::filtroBotonClases($codEstado, $estadoActual === $codEstado) }}">
                    {{ $etiquetaEstado }}
                </a>
            @endforeach
            <a href="{{ $urlEstado(ComprobanteProveedorEstados::FILTRO_TODOS) }}"
               class="btn {{ ComprobanteProveedorEstados::filtroBotonClases(ComprobanteProveedorEstados::FILTRO_TODOS, $estadoActual === ComprobanteProveedorEstados::FILTRO_TODOS) }}">
                Todos
            </a>
        </div>
    </div>
</div>
