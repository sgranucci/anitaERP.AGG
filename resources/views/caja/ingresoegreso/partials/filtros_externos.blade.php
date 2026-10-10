@php
    use App\Support\Caja\IngresoEgresoListadoFiltros;

    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = 'ingresoegreso';
    $tiposSeleccion = array_map('intval', (array) ($filtros['tipos'] ?? []));
    $periodoActual = (string) ($filtros['periodo'] ?? '');
    $mailActual = (string) ($filtros['mail'] ?? '');

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

    $urlMail = function ($clave) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['mail']);
        if ($clave !== '') {
            $q['mail'] = $clave;
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
        <span class="text-muted small ml-1 mr-1"><i class="fa fa-tags"></i> Tipo:</span>
        <a href="{{ $urlTipo(0) }}"
           class="btn btn-sm {{ $tiposSeleccion === [] ? 'btn-info' : 'btn-outline-info' }}">
            Todos
        </a>
        @foreach (($tiposFichas ?? []) as $tipoFicha)
            <a href="{{ $urlTipo($tipoFicha['id']) }}"
               class="btn btn-sm {{ in_array((int) $tipoFicha['id'], $tiposSeleccion, true) ? 'btn-info' : 'btn-outline-info' }}"
               title="{{ $tipoFicha['nombre'] }}">
                {{ $tipoFicha['abreviatura'] !== '' ? $tipoFicha['abreviatura'] : $tipoFicha['nombre'] }}
            </a>
        @endforeach
        <span class="text-muted small ml-1 mr-1"><i class="fa fa-calendar"></i> Período:</span>
        @foreach (IngresoEgresoListadoFiltros::PERIODOS_FICHA as $clavePeriodo => $etiquetaPeriodo)
            <a href="{{ $urlPeriodo($clavePeriodo) }}"
               class="btn btn-sm {{ $periodoActual === (string) $clavePeriodo ? 'btn-info' : 'btn-outline-info' }}">
                {{ $etiquetaPeriodo }}
            </a>
        @endforeach
        <span class="text-muted small ml-1 mr-1" title="Solo órdenes de pago OPP y OPA"><i class="fa fa-envelope"></i> Mail al proveedor:</span>
        <a href="{{ $urlMail('') }}" class="btn btn-sm {{ $mailActual === '' ? 'btn-info' : 'btn-outline-secondary' }}">Todos</a>
        <a href="{{ $urlMail('enviado') }}" class="btn btn-sm {{ $mailActual === 'enviado' ? 'btn-info' : 'btn-outline-secondary' }}">Enviado</a>
        <a href="{{ $urlMail('no') }}" class="btn btn-sm {{ $mailActual === 'no' ? 'btn-info' : 'btn-outline-secondary' }}">Sin enviar</a>
    </div>
</div>
