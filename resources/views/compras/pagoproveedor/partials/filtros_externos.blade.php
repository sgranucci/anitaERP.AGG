@php
    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = 'pagoproveedor';

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
    $mailActual = (string) ($filtros['mail'] ?? '');
    $urlMail = function (string $valor) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['mail']);
        if ($valor !== '') {
            $q['mail'] = $valor;
        }

        return route($rutaIndex, $q);
    };
@endphp
<div class="card-body py-2 border-bottom bg-white">
    <div class="d-flex flex-wrap align-items-center" style="gap:.35rem;">
        @if (($empresa_query ?? collect())->count() > 1)
            <span class="text-muted small mr-1"><i class="fa fa-building"></i> Empresa:</span>
            @foreach ($empresa_query as $emp)
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
        <span class="text-muted small ml-1 mr-1"><i class="fa fa-calendar"></i> Período:</span>
        @foreach (\App\Support\Compras\PagoproveedorListadoFiltros::PERIODOS_FICHA as $clavePeriodo => $etiquetaPeriodo)
            @php
                $qPeriodo = $baseQ;
                unset($qPeriodo['filtro_periodo']);
                if ($clavePeriodo !== '') {
                    $qPeriodo['filtro_periodo'] = $clavePeriodo;
                }
            @endphp
            <a href="{{ route($rutaIndex, $qPeriodo) }}"
               class="btn btn-sm {{ (string) ($filtros['periodo'] ?? '') === (string) $clavePeriodo ? 'btn-info' : 'btn-outline-info' }}">
                {{ $etiquetaPeriodo }}
            </a>
        @endforeach
        <span class="text-muted small ml-1 mr-1" title="Filtra las órdenes de pago según si se enviaron por correo"><i class="fa fa-envelope"></i> Mail:</span>
        <a href="{{ $urlMail('') }}" class="btn btn-sm btn-outline-secondary {{ $mailActual === '' ? 'active' : '' }}">Todos</a>
        <a href="{{ $urlMail('enviado') }}" class="btn btn-sm btn-outline-secondary {{ $mailActual === 'enviado' ? 'active' : '' }}">Enviado</a>
        <a href="{{ $urlMail('no') }}" class="btn btn-sm btn-outline-secondary {{ $mailActual === 'no' ? 'active' : '' }}">Sin enviar</a>
    </div>
</div>
