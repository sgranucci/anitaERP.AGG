@php
    use App\Support\Compras\TipotransaccionCompraListadoFiltros;
    $camposQbe = [];
    foreach (TipotransaccionCompraListadoFiltros::COLUMNAS_GRILLA as $k => $label) {
        $meta = TipotransaccionCompraListadoFiltros::CAMPOS[$k];
        $meta['label'] = $label;
        $camposQbe[$k] = $meta;
    }
    $opsTexto = TipotransaccionCompraListadoFiltros::OPERADORES_QBE_TEXTO;
    $opsEntero = TipotransaccionCompraListadoFiltros::OPERADORES_QBE_ENTERO;
    $opsBool = [];
    $opsFecha = [];
    $camposQbeJson = [];
    $etiquetasColumnas = [];
    foreach ($camposQbe as $k => $m) {
        $camposQbeJson[] = [
            'key' => $k,
            'label' => $m['label'] ?? $k,
            'type' => $m['type'] ?? 'texto',
        ];
        $etiquetasColumnas[$k] = $m['label'] ?? $k;
    }
    $qbeAbierto = \App\Support\Listado\ListadoQbeSupport::tieneCriterios($filtros['qbe'] ?? []);
@endphp
<div class="lw-qbe collapse {{ $qbeAbierto ? 'show' : '' }}" id="lw-qbe-panel"
     data-ops-texto='@json($opsTexto)'
     data-ops-entero='@json($opsEntero)'
     data-ops-bool='@json($opsBool)'
     data-ops-fecha='@json($opsFecha)'
     data-ops-decimal='@json(\App\Support\Listado\ListadoQbeSupport::OPERADORES_DECIMAL)'
     data-campos='@json($camposQbeJson)'>
    <div class="lw-qbe-title">
        <div>
            <h4><i class="fa fa-filter text-info"></i> Consulta avanzada</h4>
            <div class="lw-hint">Se combina con la búsqueda de arriba. En columnas con etiqueta (operación, signo, estado, retiene) el texto busca por lo que se ve en la grilla.</div>
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
        'camposOrdenables' => TipotransaccionCompraListadoFiltros::camposOrdenables(),
        'orden' => $filtros['sort'] ?? [],
        'etiquetasColumnas' => $etiquetasColumnas,
        'ordenHint' => 'Por defecto: nombre ascendente. También podés ordenar con un clic en el encabezado de la grilla.',
    ])
</div>
