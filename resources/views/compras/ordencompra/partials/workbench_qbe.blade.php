@php
    use App\Support\Compras\OrdencompraListadoFiltros;
    use App\Support\Listado\ListadoQbeSupport;
    $camposQbe = $camposFiltro ?? OrdencompraListadoFiltros::camposQbeDisponibles();
    $opsTexto = OrdencompraListadoFiltros::OPERADORES_TEXTO_QBE;
    $opsEntero = OrdencompraListadoFiltros::OPERADORES_ENTERO_QBE;
    $opsFecha = OrdencompraListadoFiltros::OPERADORES_FECHA_QBE;
    $opsDecimal = OrdencompraListadoFiltros::OPERADORES_DECIMAL;
    $opsBool = OrdencompraListadoFiltros::OPERADORES_BOOLEANO;
    $camposQbeJson = [];
    foreach ($camposQbe as $k => $m) {
        $camposQbeJson[] = [
            'key' => $k,
            'label' => $etiquetasColumnas[$k] ?? ($m['label'] ?? $k),
            'type' => $m['type'] ?? 'texto',
        ];
    }
@endphp
<div class="lw-qbe collapse" id="lw-qbe-panel"
     data-ops-texto='@json($opsTexto)'
     data-ops-entero='@json($opsEntero)'
     data-ops-fecha='@json($opsFecha)'
     data-ops-decimal='@json($opsDecimal)'
     data-ops-bool='@json($opsBool)'
     data-campos='@json($camposQbeJson)'>
    <input type="hidden" name="aplicar_qbe" id="lw-aplicar-qbe" value="">
    <div class="lw-qbe-title">
        <div>
            <h4><i class="fa fa-filter text-info"></i> Consulta avanzada</h4>
            <div class="lw-hint">Se combina con empresa, sala, presupuesto y estado de arriba. Nº OC, proveedor, solicitante, fechas y circuito (recepción / factura / OP).</div>
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
        'opsDecimal' => $opsDecimal,
        'opsBool' => $opsBool,
        'etiquetasColumnas' => $etiquetasColumnas ?? [],
    ])
    <div id="lw-orden-qbe-slot">
        <div class="lw-orden-qbe-away d-none" id="lw-orden-qbe-away">
            <i class="fa fa-external-link"></i>
            <div>
                <strong>Orden editándose en Diseñar vista</strong>
                <div class="small">Al cerrar el modal vuelve acá. También podés aplicar desde la pestaña Ordenar.</div>
            </div>
        </div>
        @include('includes.listado.workbench_orden', [
            'camposOrdenables' => OrdencompraListadoFiltros::camposOrdenables(),
            'orden' => $filtros['sort'] ?? [],
            'etiquetasColumnas' => $etiquetasColumnas ?? [],
            'ordenHint' => 'Por defecto: fecha IVA, más reciente primero. Si elegís otro campo, queda recordado para tu usuario y marcado en la barra.',
        ])
    </div>
    <div id="lw-group-qbe-slot">
        <div class="lw-orden-qbe-away d-none" id="lw-group-qbe-away">
            <i class="fa fa-external-link"></i>
            <div>
                <strong>Agrupación editándose en Diseñar vista</strong>
                <div class="small">Al cerrar el modal vuelve acá. También podés aplicar desde la pestaña Agrupar.</div>
            </div>
        </div>
        @include('includes.listado.workbench_agrupar', [
            'camposAgrupables' => OrdencompraListadoFiltros::camposOrdenables(),
            'agrupar' => $filtros['agrupar'] ?? [],
            'etiquetasColumnas' => $etiquetasColumnas ?? [],
        ])
    </div>
</div>
