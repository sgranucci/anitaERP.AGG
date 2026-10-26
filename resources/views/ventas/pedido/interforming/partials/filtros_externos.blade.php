@php
    $tipoActual = strtoupper(trim((string) ($filtros['tipo'] ?? 'TODOS')));
    if (! in_array($tipoActual, ['PED', 'PEX'], true)) {
        $tipoActual = 'TODOS';
    }
    $baseQ = $filtrosQuery ?? [];
    $urlTipo = function (string $tipo) use ($baseQ) {
        $q = $baseQ;
        unset($q['tipo']);
        if ($tipo !== 'TODOS') {
            $q['tipo'] = $tipo;
        }

        return route('pedido', $q);
    };
@endphp
<div class="card-body py-2 border-bottom bg-white">
    <div class="d-flex flex-wrap align-items-center">
        <span class="text-muted small mr-2"><i class="fa fa-filter"></i> Tipo:</span>
        <div class="btn-group btn-group-sm" role="group" aria-label="Filtro PED o PEX">
            <a href="{{ $urlTipo('TODOS') }}" class="btn {{ $tipoActual === 'TODOS' ? 'btn-primary' : 'btn-outline-primary' }}">Todos</a>
            <a href="{{ $urlTipo('PED') }}" class="btn {{ $tipoActual === 'PED' ? 'btn-info' : 'btn-outline-info' }}">PED venta</a>
            <a href="{{ $urlTipo('PEX') }}" class="btn {{ $tipoActual === 'PEX' ? 'btn-info' : 'btn-outline-info' }}">PEX exportación</a>
        </div>
        @if (! empty($exportRuta))
        <div class="mb-1 ml-auto">
            @include('includes.exportar-tabla-queryparams', [
                'ruta' => $exportRuta,
                'queryparams' => $exportQueryparams ?? $filtrosQuery ?? [],
                'variant' => 'compact',
            ])
        </div>
        @endif
    </div>
</div>
