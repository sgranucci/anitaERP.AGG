@php
    use App\Support\Finanzas\FinanzaMovimientoPrecargaListadoFiltros;
    $camposQbe = $camposFiltro ?? FinanzaMovimientoPrecargaListadoFiltros::campos();
    $opsTexto = FinanzaMovimientoPrecargaListadoFiltros::OPERADORES_TEXTO;
    $opsEntero = FinanzaMovimientoPrecargaListadoFiltros::OPERADORES_ENTERO;
    $opsBool = FinanzaMovimientoPrecargaListadoFiltros::OPERADORES_BOOLEANO;
    $opsFecha = FinanzaMovimientoPrecargaListadoFiltros::OPERADORES_FECHA;
    $camposQbeJson = [];
    foreach ($camposQbe as $k => $m) {
        $camposQbeJson[] = [
            'key' => $k,
            'label' => $m['label'] ?? $k,
            'type' => $m['type'] ?? 'texto',
        ];
    }
    $etiquetasColumnas = [];
    foreach ($camposQbe as $k => $m) {
        $etiquetasColumnas[$k] = $m['label'] ?? $k;
    }
@endphp
<div class="lw-qbe collapse" id="lw-qbe-panel"
     data-ops-texto='@json($opsTexto)'
     data-ops-entero='@json($opsEntero)'
     data-ops-bool='@json($opsBool)'
     data-ops-fecha='@json($opsFecha)'
     data-ops-decimal='@json(\App\Support\Listado\ListadoQbeSupport::OPERADORES_DECIMAL)'
     data-campos='@json($camposQbeJson)'>
    <div class="lw-qbe-title">
        <div>
            <h4><i class="fa fa-filter text-info"></i> Consulta avanzada</h4>
            <div class="lw-hint">Se combina con empresa, estado y fechas de arriba.</div>
        </div>
        <div class="d-flex" style="gap:.35rem;">
            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-lw-add-criterio">
                <i class="fa fa-plus"></i> Criterio
            </button>
            <button type="submit" class="btn btn-sm btn-primary">
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
        'opsFecha' => $opsFecha,
        'etiquetasColumnas' => $etiquetasColumnas,
    ])
    @include('includes.listado.workbench_orden', [
        'camposOrdenables' => FinanzaMovimientoPrecargaListadoFiltros::camposOrdenables(),
        'orden' => $filtros['sort'] ?? [],
        'etiquetasColumnas' => $etiquetasColumnas,
    ])
</div>
