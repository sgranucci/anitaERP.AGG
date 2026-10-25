@php
    use App\Support\Caja\TesoreriaCrownMovimientoListadoFiltros;
    use App\Support\Listado\ListadoQbeSupport;
    $camposQbe = TesoreriaCrownMovimientoListadoFiltros::CAMPOS;
    $opsTexto = TesoreriaCrownMovimientoListadoFiltros::OPERADORES_TEXTO;
    $opsFecha = ListadoQbeSupport::OPERADORES_FECHA;
    $camposQbeJson = [];
    foreach ($camposQbe as $k => $m) {
        $camposQbeJson[] = ['key' => $k, 'label' => $m['label'], 'type' => $m['type']];
    }
@endphp
<div class="collapse border-bottom {{ ($qbeAbierto ?? false) ? 'show' : '' }}" id="panel-filtros-tesoreria-crown-movimiento" data-listado-filtros-panel>
    <div id="lw-qbe-panel"
         data-ops-texto='@json($opsTexto)'
         data-ops-entero='@json(["igual" => "Igual a"])'
         data-ops-bool='@json(["igual" => "Igual a"])'
         data-ops-fecha='@json($opsFecha)'
         data-ops-decimal='@json(ListadoQbeSupport::OPERADORES_DECIMAL)'
         data-campos='@json($camposQbeJson)'>
    <input type="hidden" name="sala_id" value="{{ (int) ($sala?->id ?? 0) }}">
    <div class="card-body bg-light py-2 text-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2" style="gap:.35rem;">
            <div>
                <strong><i class="fa fa-filter text-info"></i> Consulta avanzada</strong>
                <div class="small text-muted">Se combina con la sala y con el texto de arriba.</div>
            </div>
            <div class="d-flex" style="gap:.35rem;">
                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-lw-add-criterio">
                    <i class="fa fa-plus"></i> Criterio
                </button>
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fa fa-search"></i> Buscar
                </button>
            </div>
        </div>
        @include('includes.listado.workbench_qbe_grupos', [
            'qbeEstructura' => $filtros['qbe'] ?? [],
            'camposQbe' => $camposQbe,
            'opsTexto' => $opsTexto,
            'opsEntero' => ['igual' => 'Igual a'],
            'opsBool' => ['igual' => 'Igual a'],
            'opsFecha' => $opsFecha,
            'etiquetasColumnas' => [],
        ])
    </div>
    </div>
</div>
