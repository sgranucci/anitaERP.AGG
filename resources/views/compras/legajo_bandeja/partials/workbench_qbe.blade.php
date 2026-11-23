@php
    use App\Support\Compras\OrdencompraLegajoBandejaFiltros;
    $camposQbe = $camposFiltro ?? OrdencompraLegajoBandejaFiltros::camposQbeDisponibles();
    $opsTexto = OrdencompraLegajoBandejaFiltros::OPERADORES_TEXTO_QBE;
    $opsEntero = OrdencompraLegajoBandejaFiltros::OPERADORES_ENTERO_QBE;
    $opsFecha = OrdencompraLegajoBandejaFiltros::OPERADORES_FECHA_QBE;
    $opsDecimal = OrdencompraLegajoBandejaFiltros::OPERADORES_DECIMAL;
    $opsBool = OrdencompraLegajoBandejaFiltros::OPERADORES_BOOLEANO;
    $camposQbeJson = [];
    foreach ($camposQbe as $k => $m) {
        $camposQbeJson[] = [
            'key' => $k,
            'label' => $etiquetasColumnas[$k] ?? ($m['label'] ?? $k),
            'type' => $m['type'] ?? 'texto',
        ];
    }
@endphp
{{-- Solo criterios QBE / orden / agrupar. Colapsado por defecto; se abre con «Consulta avanzada». --}}
<div class="lw-qbe collapse{{ ($qbeAbierto ?? false) ? ' show' : '' }}" id="lw-qbe-panel"
     data-ops-texto='@json($opsTexto)'
     data-ops-entero='@json($opsEntero)'
     data-ops-fecha='@json($opsFecha)'
     data-ops-decimal='@json($opsDecimal)'
     data-ops-bool='@json($opsBool)'
     data-campos='@json($camposQbeJson)'>
    <input type="hidden" name="aplicar_qbe" id="lw-aplicar-qbe" value="">

    <div class="lw-qbe-title">
        <div>
            <h4><i class="fa fa-filter text-info"></i> Criterios QBE</h4>
            <div class="lw-hint">AND con Empresa, Estado, Ámbito, Atajos y números de arriba.</div>
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
            'camposOrdenables' => OrdencompraLegajoBandejaFiltros::camposOrdenables(),
            'orden' => $filtros['sort'] ?? [],
            'etiquetasColumnas' => $etiquetasColumnas ?? [],
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
            'camposAgrupables' => OrdencompraLegajoBandejaFiltros::camposOrdenables(),
            'agrupar' => $filtros['agrupar'] ?? [],
            'etiquetasColumnas' => $etiquetasColumnas ?? [],
        ])
    </div>
</div>
