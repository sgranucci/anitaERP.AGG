@php
    use App\Support\Caja\CobranzaListadoFiltros;

    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $estadoActual = (string) ($filtros['estado'] ?? '');
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = 'cobranza';

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

    $urlEstado = function (string $estado) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        // «Todos» va como estado= vacío: así la URL "trae filtros" y no se restaura el estado recordado en sesión.
        $q['estado'] = $estado;

        return route($rutaIndex, $q);
    };
@endphp
<div class="card-body py-2 border-bottom bg-white">
    <div class="d-flex flex-wrap align-items-center" style="gap:.35rem;">
        @if (\App\Support\Configuracion\EmpresaFiltroExternoSupport::mostrarChips())
            <span class="text-muted small mr-1"><i class="fa fa-building"></i> Empresa:</span>
            @foreach (($empresa_query ?? []) as $emp)
                <a href="{{ $urlEmpresa($emp->id) }}"
                   class="btn btn-sm {{ ($empresaScope !== 'todas' && $empresaActual === (int) $emp->id) ? 'btn-info' : 'btn-outline-info' }}">
                    {{ $emp->nombre }}
                </a>
            @endforeach
            <a href="{{ $urlEmpresa('todas') }}"
               class="btn btn-sm {{ $empresaScope === 'todas' ? 'btn-primary' : 'btn-outline-primary' }}">
                Todas mis empresas
            </a>
        @endif
        <span class="text-muted small ml-1 mr-1"><i class="fa fa-flag"></i> Estado:</span>
        <a href="{{ $urlEstado('') }}"
           class="btn btn-sm {{ $estadoActual === '' ? 'btn-info' : 'btn-outline-info' }}">
            Todos
        </a>
        @foreach (CobranzaListadoFiltros::ESTADOS_FICHA as $claveEstado => $etiquetaEstado)
            <a href="{{ $urlEstado($claveEstado) }}"
               class="btn btn-sm {{ $estadoActual === $claveEstado ? 'btn-info' : 'btn-outline-info' }}">
                {{ $etiquetaEstado }}
            </a>
        @endforeach
        <span class="ml-auto" id="exportar-cobranza">
            @include('includes.exportar-tabla-queryparams', [
                'ruta' => 'lista_cobranza',
                'queryparams' => $filtrosQuery ?? [],
                'variant' => 'compact',
            ])
        </span>
    </div>
</div>
