@php
    use App\Support\Listado\ListadoQbeSupport;
    $camposQbe = $camposFiltro ?? [];
    $opsTexto = ['contiene' => 'Contiene', 'igual' => 'Igual a', 'empieza' => 'Empieza con'];
    $opsFecha = ListadoQbeSupport::OPERADORES_FECHA;
    $camposQbeJson = [];
    foreach ($camposQbe as $k => $m) {
        $camposQbeJson[] = ['key' => $k, 'label' => $m['label'] ?? $k, 'type' => $m['type'] ?? 'texto'];
    }
@endphp
<div class="lw-qbe collapse {{ ($qbeAbierto ?? false) ? 'show' : '' }}" id="lw-qbe-panel"
     data-ops-texto='@json($opsTexto)'
     data-ops-entero='@json(["igual" => "Igual a"])'
     data-ops-bool='@json(["igual" => "Igual a"])'
     data-ops-fecha='@json($opsFecha)'
     data-ops-decimal='@json(ListadoQbeSupport::OPERADORES_DECIMAL)'
     data-campos='@json($camposQbeJson)'>
    <input type="hidden" name="aplicar_qbe" id="lw-aplicar-qbe" value="">
    <div class="lw-qbe-title">
        <div>
            <h4><i class="fa fa-filter text-info"></i> Consulta avanzada</h4>
            <div class="lw-hint">Se combina con la sala y con el texto de arriba. Los filtros se guardan en la vista abierta.</div>
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
        'opsEntero' => ['igual' => 'Igual a'],
        'opsBool' => ['igual' => 'Igual a'],
        'opsFecha' => $opsFecha,
        'etiquetasColumnas' => $etiquetasColumnas ?? [],
    ])
</div>
