@php
    use App\Support\Stock\FormulaArticuloListadoFiltros;

    $estadoActual = FormulaArticuloListadoFiltros::normalizarEstado($filtros['estado'] ?? '');
    $articulosActual = FormulaArticuloListadoFiltros::normalizarArticulos($filtros['articulos'] ?? '');
    $opcActual = FormulaArticuloListadoFiltros::normalizarOpcionales($filtros['con_opcionales'] ?? '');
    $baseQ = $filtrosQuery ?? [];
    unset($baseQ['page'], $baseQ['filtro_estado'], $baseQ['filtro_articulos'], $baseQ['con_opcionales']);

    $urlEstado = function (string $cod) use ($baseQ, $articulosActual, $opcActual) {
        $q = $baseQ;
        if ($cod !== '') {
            $q['filtro_estado'] = $cod;
        }
        if ($articulosActual !== '') {
            $q['filtro_articulos'] = $articulosActual;
        }
        if ($opcActual !== '') {
            $q['con_opcionales'] = $opcActual;
        }

        return route('consultar_formula_articulo', $q);
    };
    $urlArticulos = function (string $cod) use ($baseQ, $estadoActual, $opcActual) {
        $q = $baseQ;
        if ($estadoActual !== '') {
            $q['filtro_estado'] = $estadoActual;
        }
        if ($cod !== '') {
            $q['filtro_articulos'] = $cod;
        }
        if ($opcActual !== '') {
            $q['con_opcionales'] = $opcActual;
        }

        return route('consultar_formula_articulo', $q);
    };
    $urlOpc = function (string $cod) use ($baseQ, $estadoActual, $articulosActual) {
        $q = $baseQ;
        if ($estadoActual !== '') {
            $q['filtro_estado'] = $estadoActual;
        }
        if ($articulosActual !== '') {
            $q['filtro_articulos'] = $articulosActual;
        }
        if ($cod !== '') {
            $q['con_opcionales'] = $cod;
        }

        return route('consultar_formula_articulo', $q);
    };
@endphp
<div class="px-3 py-2 border-bottom bg-white d-flex flex-wrap align-items-center" style="gap:.5rem .75rem;">
    <span class="text-muted small"><i class="fa fa-filter"></i> Estado</span>
    <div class="btn-group btn-group-sm" role="group" aria-label="Estado de la fórmula">
        <a href="{{ $urlEstado('') }}" class="btn {{ $estadoActual === '' ? 'btn-primary' : 'btn-outline-primary' }}">Todas</a>
        <a href="{{ $urlEstado('ACTIVA') }}" class="btn {{ $estadoActual === 'ACTIVA' ? 'btn-success' : 'btn-outline-success' }}">Activas</a>
        <a href="{{ $urlEstado('BORRADOR') }}" class="btn {{ $estadoActual === 'BORRADOR' ? 'btn-secondary' : 'btn-outline-secondary' }}">Borrador</a>
        <a href="{{ $urlEstado('INACTIVA') }}" class="btn {{ $estadoActual === 'INACTIVA' ? 'btn-warning' : 'btn-outline-warning' }}">Inactivas</a>
    </div>
    <span class="text-muted small ml-2">Artículos asignados</span>
    <div class="btn-group btn-group-sm" role="group" aria-label="Estado de los artículos de venta asignados">
        <a href="{{ $urlArticulos('') }}" class="btn {{ $articulosActual === '' ? 'btn-primary' : 'btn-outline-primary' }}">Todos</a>
        <a href="{{ $urlArticulos('activos') }}" class="btn {{ $articulosActual === 'activos' ? 'btn-success' : 'btn-outline-success' }}" title="Al menos un artículo de venta asignado está activo">Activos</a>
        <a href="{{ $urlArticulos('inactivos') }}" class="btn {{ $articulosActual === 'inactivos' ? 'btn-warning' : 'btn-outline-warning' }}" title="Tiene artículos de venta y ninguno está activo">Inactivos</a>
        <a href="{{ $urlArticulos('sin_asignar') }}" class="btn {{ $articulosActual === 'sin_asignar' ? 'btn-secondary' : 'btn-outline-secondary' }}" title="Ningún artículo de venta apunta a la fórmula">Sin asignar</a>
    </div>
    @if (! empty($opcionalesHabilitados))
        <span class="text-muted small ml-2">Opcionales</span>
        <div class="btn-group btn-group-sm" role="group" aria-label="Ítems opcionales">
            <a href="{{ $urlOpc('') }}" class="btn {{ $opcActual === '' ? 'btn-primary' : 'btn-outline-primary' }}">Todas</a>
            <a href="{{ $urlOpc('si') }}" class="btn {{ $opcActual === 'si' ? 'btn-info' : 'btn-outline-info' }}">Con opcionales</a>
            <a href="{{ $urlOpc('no') }}" class="btn {{ $opcActual === 'no' ? 'btn-info' : 'btn-outline-info' }}">Sin opcionales</a>
        </div>
    @endif
</div>
