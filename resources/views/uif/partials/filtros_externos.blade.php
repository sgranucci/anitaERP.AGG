@php
    use App\Support\Uif\ClienteUifOrigenPcSupport;

    $empresaScope = $filtros['empresa_scope'] ?? 'todas';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = $rutaIndex ?? 'consulta_cliente_uif';

    // Al cambiar empresa: limpia búsqueda/origen para listar toda la sala.
    $urlEmpresa = function ($id) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        foreach ([
            'empresa_id', 'empresa_todas', 'filtro_empresa_id', 'filtro_anita_origen',
            'filtro_valor', 'filtro_modo', 'filtro_campo', 'filtro_operador',
            'filtro_valor_hasta', 'filtro_busqueda_rapida', 'page',
        ] as $key) {
            unset($q[$key]);
        }
        if ($id === 'todas') {
            $q['empresa_todas'] = 1;
        } else {
            $q['empresa_id'] = $id;
        }

        return route($rutaIndex, $q);
    };

    $labelEmpresa = function ($emp) {
        $origen = ClienteUifOrigenPcSupport::origenDesdeEmpresaId((int) $emp->id);

        return $origen
            ? ClienteUifOrigenPcSupport::labelOrigen($origen)
            : $emp->nombre;
    };
@endphp
@if (($empresa_query ?? collect())->count() > 1)
<div class="card-body py-2 border-bottom bg-white">
    <div class="d-flex flex-wrap align-items-center">
        <div class="mb-1">
            <span class="text-muted small mr-2"><i class="fa fa-building"></i> Empresa / sala:</span>
            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de empresa">
                @foreach ($empresa_query as $emp)
                    <a href="{{ $urlEmpresa($emp->id) }}"
                       title="{{ $emp->nombre }}"
                       class="btn {{ ($empresaScope !== 'todas' && $empresaActual === (int) $emp->id) ? 'btn-info' : 'btn-outline-info' }}">
                        {{ $labelEmpresa($emp) }}
                    </a>
                @endforeach
                <a href="{{ $urlEmpresa('todas') }}"
                   class="btn {{ $empresaScope === 'todas' ? 'btn-primary' : 'btn-outline-primary' }}">
                    Todas las salas
                </a>
            </div>
        </div>
    </div>
</div>
@endif
