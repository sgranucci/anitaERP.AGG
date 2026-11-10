@php
    use App\Support\Listado\ListadoQbeSupport;
    use App\Support\Caja\CajeroCrownListadoFiltros;
    $camposQbe = $camposFiltro ?? [];
    $opsTexto = ['contiene' => 'Contiene', 'igual' => 'Igual a', 'empieza' => 'Empieza con'];
    $opsEntero = ['igual' => 'Igual a', 'mayor' => 'Mayor que', 'menor' => 'Menor que', 'mayor_igual' => 'Desde', 'menor_igual' => 'Hasta', 'entre' => 'Entre'];
    $opsBool = ['igual' => 'Igual a'];
    $camposQbeJson = [];
    foreach ($camposQbe as $k => $m) {
        $camposQbeJson[] = ['key' => $k, 'label' => $m['label'] ?? $k, 'type' => $m['type'] ?? 'texto'];
    }
@endphp
<div class="lw-qbe collapse {{ ($qbeAbierto ?? false) ? 'show' : '' }}" id="lw-qbe-panel"
     data-ops-texto='@json($opsTexto)'
     data-ops-entero='@json($opsEntero)'
     data-ops-bool='@json($opsBool)'
     data-ops-fecha='@json(ListadoQbeSupport::OPERADORES_FECHA)'
     data-ops-decimal='@json(ListadoQbeSupport::OPERADORES_DECIMAL)'
     data-campos='@json($camposQbeJson)'>
    <input type="hidden" name="aplicar_qbe" id="lw-aplicar-qbe" value="">
    <div class="lw-qbe-title">
        <div>
            <h4><i class="fa fa-filter text-info"></i> Consulta avanzada</h4>
            <div class="lw-hint">Se combina con la sala, el estado y el texto de arriba. Los filtros se guardan en la vista abierta.</div>
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
        'opsBool' => $opsBool,
        'opsFecha' => ListadoQbeSupport::OPERADORES_FECHA,
        'etiquetasColumnas' => $etiquetasColumnas ?? [],
    ])
    <div id="lw-orden-qbe-slot">
        @include('includes.listado.workbench_orden', [
            'camposOrdenables' => CajeroCrownListadoFiltros::camposOrdenables(),
            'orden' => $filtros['sort'] ?? [],
            'etiquetasColumnas' => $etiquetasColumnas ?? [],
        ])
    </div>
    <div id="lw-group-qbe-slot">
        @include('includes.listado.workbench_agrupar', [
            'camposAgrupables' => CajeroCrownListadoFiltros::camposOrdenables(),
            'agrupar' => $filtros['agrupar'] ?? [],
            'etiquetasColumnas' => $etiquetasColumnas ?? [],
        ])
    </div>
</div>
