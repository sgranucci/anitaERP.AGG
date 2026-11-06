@php
    use App\Support\Listado\ListadoQbeSupport;
    use App\Support\Caja\TesoreriaCrownResultadoOnlineListadoFiltros;
    $camposQbe = $camposFiltro ?? [];
    $opsTexto = ['contiene' => 'Contiene', 'igual' => 'Igual a', 'empieza' => 'Empieza con'];
    $opsEntero = ['igual' => 'Igual a', 'mayor' => 'Mayor que', 'menor' => 'Menor que', 'mayor_igual' => 'Desde', 'menor_igual' => 'Hasta', 'entre' => 'Entre'];
    $opsFecha = ListadoQbeSupport::OPERADORES_FECHA;
    $camposQbeJson = [];
    foreach ($camposQbe as $k => $m) {
        $camposQbeJson[] = ['key' => $k, 'label' => $m['label'] ?? $k, 'type' => $m['type'] ?? 'texto'];
    }
@endphp
<div class="lw-qbe collapse {{ ($qbeAbierto ?? false) ? 'show' : '' }}" id="lw-qbe-panel"
     data-ops-texto='@json($opsTexto)'
     data-ops-entero='@json($opsEntero)'
     data-ops-bool='@json(["igual" => "Igual a"])'
     data-ops-fecha='@json($opsFecha)'
     data-ops-decimal='@json(ListadoQbeSupport::OPERADORES_DECIMAL)'
     data-campos='@json($camposQbeJson)'>
    <input type="hidden" name="aplicar_qbe" id="lw-aplicar-qbe" value="">
    <div class="lw-qbe-title">
        <div>
            <h4><i class="fa fa-filter text-info"></i> Consulta avanzada</h4>
            <div class="lw-hint">Se combina con la sala, las fechas y el texto de arriba. Los filtros se guardan en la vista abierta.</div>
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
        'opsBool' => ['igual' => 'Igual a'],
        'opsFecha' => $opsFecha,
        'etiquetasColumnas' => $etiquetasColumnas ?? [],
    ])
    <div id="lw-orden-qbe-slot">
        @include('includes.listado.workbench_orden', [
            'camposOrdenables' => TesoreriaCrownResultadoOnlineListadoFiltros::camposOrdenables(),
            'orden' => $filtros['sort'] ?? [],
            'etiquetasColumnas' => $etiquetasColumnas ?? [],
        ])
    </div>
    <div id="lw-group-qbe-slot">
        @include('includes.listado.workbench_agrupar', [
            'camposAgrupables' => TesoreriaCrownResultadoOnlineListadoFiltros::camposOrdenables(),
            'agrupar' => $filtros['agrupar'] ?? [],
            'etiquetasColumnas' => $etiquetasColumnas ?? [],
        ])
    </div>
</div>
