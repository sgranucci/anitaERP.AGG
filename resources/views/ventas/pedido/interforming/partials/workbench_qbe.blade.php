@php
    use App\Support\Ventas\PedidoInterformingListadoFiltros;
    $camposQbe = $camposFiltro ?? PedidoInterformingListadoFiltros::camposQbeDisponibles();
    $opsTexto = PedidoInterformingListadoFiltros::OPERADORES_QBE_TEXTO;
    $opsEntero = PedidoInterformingListadoFiltros::OPERADORES_QBE_ENTERO;
    $opsFecha = \App\Support\Listado\ListadoQbeSupport::OPERADORES_FECHA;
    $camposQbeJson = [];
    foreach ($camposQbe as $k => $m) {
        $camposQbeJson[] = [
            'key' => $k,
            'label' => ($etiquetasColumnas[$k] ?? null) ?: ($m['label'] ?? $k),
            'type' => $m['type'] ?? 'texto',
        ];
    }
    $qbeAbiertoPanel = \App\Support\Listado\ListadoQbeSupport::tieneCriterios($filtros['qbe'] ?? []);
@endphp
<div class="lw-qbe collapse {{ $qbeAbiertoPanel ? 'show' : '' }}" id="lw-qbe-panel"
     data-ops-texto='@json($opsTexto)'
     data-ops-entero='@json($opsEntero)'
     data-ops-bool='@json(["igual" => "Es"])'
     data-ops-fecha='@json($opsFecha)'
     data-ops-decimal='@json(\App\Support\Listado\ListadoQbeSupport::OPERADORES_DECIMAL)'
     data-campos='@json($camposQbeJson)'>
    <input type="hidden" name="aplicar_qbe" id="lw-aplicar-qbe" value="">
    <div class="lw-qbe-title">
        <div>
            <h4><i class="fa fa-filter text-info"></i> Consulta avanzada</h4>
            <div class="lw-hint">Se combina con el tipo PED / PEX de arriba.</div>
        </div>
        <div class="d-flex" style="gap:.35rem;">
            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-lw-add-criterio">
                <i class="fa fa-plus"></i> Criterio
            </button>
            <button type="submit" id="btn-lw-aplicar-qbe" class="btn btn-sm btn-primary">
                <i class="fa fa-search"></i> Buscar
            </button>
            <button type="button" class="btn btn-sm btn-link text-muted" data-toggle="collapse" data-target="#lw-qbe-panel">
                Ocultar
            </button>
        </div>
    </div>
    @include('includes.listado.workbench_qbe_grupos', [
        'qbeEstructura' => $filtros['qbe'] ?? [],
        'camposQbe' => $camposQbe,
        'opsTexto' => $opsTexto,
        'opsEntero' => $opsEntero,
        'opsFecha' => $opsFecha,
        'etiquetasColumnas' => $etiquetasColumnas ?? [],
    ])
    <div id="lw-orden-qbe-slot">
        @include('includes.listado.workbench_orden', [
            'camposOrdenables' => PedidoInterformingListadoFiltros::camposOrdenables(),
            'orden' => $filtros['sort'] ?? [],
            'etiquetasColumnas' => $etiquetasColumnas ?? [],
        ])
    </div>
    <div id="lw-group-qbe-slot">
        @include('includes.listado.workbench_agrupar', [
            'camposAgrupables' => PedidoInterformingListadoFiltros::camposOrdenables(),
            'agrupar' => $filtros['agrupar'] ?? [],
            'etiquetasColumnas' => $etiquetasColumnas ?? [],
        ])
    </div>
</div>
