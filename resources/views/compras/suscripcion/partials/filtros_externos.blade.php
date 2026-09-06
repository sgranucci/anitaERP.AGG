@php
    /**
     * Filtro externo de empresas (botones), estilo Tracking / Cuentas de caja.
     *
     * @var string $rutaNombre  nombre de la ruta del index
     * @var \Illuminate\Support\Collection $empresa_query
     * @var array<string, mixed> $filtrosQuery  query params a preservar
     * @var int|null $empresa_id  empresa seleccionada (0/null = todas)
     * @var bool $mostrarTodas  si se muestra el chip "Todas" (default true)
     */
    $rutaNombre = $rutaNombre ?? 'consultar_suscripcion';
    $empresaActual = (int) ($empresa_id ?? ($filtros['empresa_id'] ?? 0));
    $baseQ = $filtrosQuery ?? [];
    $mostrarTodas = $mostrarTodas ?? true;

    $urlEmpresa = function ($id) use ($baseQ, $rutaNombre) {
        $q = $baseQ;
        unset($q['empresa_id'], $q['page']);
        if ($id !== 'todas' && (int) $id > 0) {
            $q['empresa_id'] = (int) $id;
        }

        return route($rutaNombre, $q);
    };
@endphp
@if (($empresa_query ?? collect())->count() > 1)
<div class="card-body py-2 border-bottom bg-white">
    <div class="d-flex flex-wrap align-items-center">
        <span class="text-muted small mr-2"><i class="fa fa-building"></i> Empresa:</span>
        <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de empresa">
            @foreach ($empresa_query as $emp)
                <a href="{{ $urlEmpresa($emp->id) }}"
                   class="btn {{ $empresaActual === (int) $emp->id ? 'btn-info' : 'btn-outline-info' }}">
                    {{ $emp->nombre }}
                </a>
            @endforeach
            @if ($mostrarTodas)
                <a href="{{ $urlEmpresa('todas') }}"
                   class="btn {{ $empresaActual === 0 ? 'btn-primary' : 'btn-outline-primary' }}">
                    Todas
                </a>
            @endif
        </div>
    </div>
</div>
@endif
