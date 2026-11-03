@php
    use App\Support\Solicitudpago\SolicitudpagoListadoFiltros;
    $camposQbe = $camposFiltro ?? SolicitudpagoListadoFiltros::camposQbeDisponibles();
    $opsTexto = SolicitudpagoListadoFiltros::QBE_OPERADORES_TEXTO;
    $opsEntero = SolicitudpagoListadoFiltros::QBE_OPERADORES_ENTERO;
    $opsFecha = SolicitudpagoListadoFiltros::OPERADORES_FECHA;
    $camposQbeJson = [];
    foreach ($camposQbe as $k => $m) {
        $camposQbeJson[] = [
            'key' => $k,
            'label' => $etiquetasColumnas[$k] ?? ($m['label'] ?? $k),
            'type' => $m['type'] ?? 'texto',
        ];
    }
@endphp
<div class="lw-qbe collapse {{ ($qbeAbierto ?? false) ? 'show' : '' }}" id="lw-qbe-panel"
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
            <div class="lw-hint">Grupos Y · O · NOT, fórmulas, orden y agrupación</div>
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
        'opsBool' => ['igual' => 'Es'],
        'etiquetasColumnas' => $etiquetasColumnas ?? [],
    ])
    <div id="lw-orden-qbe-slot">
        <div class="lw-orden-qbe-away d-none" id="lw-orden-qbe-away">
            <i class="fa fa-external-link"></i>
            <div>
                <strong>Orden editándose en Diseñar vista</strong>
                <div class="small">Al cerrar el modal vuelve acá.</div>
            </div>
        </div>
        @include('includes.listado.workbench_orden', [
            'camposOrdenables' => SolicitudpagoListadoFiltros::camposOrdenables(),
            'orden' => $filtros['orden'] ?? [],
            'etiquetasColumnas' => $etiquetasColumnas ?? [],
        ])
    </div>
    <div id="lw-group-qbe-slot">
        <div class="lw-orden-qbe-away d-none" id="lw-group-qbe-away">
            <i class="fa fa-external-link"></i>
            <div>
                <strong>Agrupación editándose en Diseñar vista</strong>
                <div class="small">Al cerrar el modal vuelve acá.</div>
            </div>
        </div>
        @include('includes.listado.workbench_agrupar', [
            'camposAgrupables' => SolicitudpagoListadoFiltros::camposOrdenables(),
            'agrupar' => $filtros['agrupar'] ?? [],
            'etiquetasColumnas' => $etiquetasColumnas ?? [],
        ])
    </div>
</div>
