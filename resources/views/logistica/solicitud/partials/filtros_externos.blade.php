@php
    $alcanceActual = $filtros['alcance'] ?? 'mias';
    $estadoActual = $filtros['estado'] ?? '';
    $plazoActual = $filtros['plazo'] ?? '';
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = 'logistica_solicitud';
    $estados = \App\Support\Logistica\SolicitudLogisticaListadoColumnas::ESTADOS;

    $urlAlcance = function (string $alcance) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['filtro_alcance']);
        if ($alcance === 'todas') {
            $q['filtro_alcance'] = 'todas';
        }

        return route($rutaIndex, $q);
    };

    $urlEstado = function (string $cod) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['filtro_estado']);
        if ($cod !== '') {
            $q['filtro_estado'] = $cod;
        }

        return route($rutaIndex, $q);
    };

    $urlPlazo = function (string $plazo) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['filtro_plazo']);
        if ($plazo === 'vencidas') {
            $q['filtro_plazo'] = 'vencidas';
        }

        return route($rutaIndex, $q);
    };
@endphp
<div class="card-body py-2 border-bottom bg-white">
    <div class="d-flex flex-wrap align-items-center">
        @if ($puedeListarTodas ?? false)
            <div class="mr-4 mb-1">
                <span class="text-muted small mr-2"><i class="fa fa-user"></i> Alcance:</span>
                <div class="btn-group btn-group-sm" role="group" aria-label="Alcance">
                    <a href="{{ $urlAlcance('mias') }}" class="btn {{ $alcanceActual !== 'todas' ? 'btn-primary' : 'btn-outline-primary' }}">Las mías</a>
                    <a href="{{ $urlAlcance('todas') }}" class="btn {{ $alcanceActual === 'todas' ? 'btn-primary' : 'btn-outline-primary' }}">Todas</a>
                </div>
            </div>
        @endif
        <div class="mb-1">
            <span class="text-muted small mr-2"><i class="fa fa-flag"></i> Estado:</span>
            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Estado">
                @foreach ($estados as $cod => $label)
                    <a href="{{ $urlEstado($cod) }}" class="btn {{ $estadoActual === $cod ? 'btn-info' : 'btn-outline-info' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
        <div class="mb-1 ml-lg-3">
            <span class="text-muted small mr-2"><i class="fa fa-clock-o"></i> Plazo:</span>
            <div class="btn-group btn-group-sm" role="group" aria-label="Plazo">
                <a href="{{ $urlPlazo('') }}" class="btn {{ $plazoActual !== 'vencidas' ? 'btn-secondary' : 'btn-outline-secondary' }}">Todos</a>
                <a href="{{ $urlPlazo('vencidas') }}" class="btn {{ $plazoActual === 'vencidas' ? 'btn-danger' : 'btn-outline-danger' }}">Vencidas</a>
            </div>
        </div>
    </div>
</div>
