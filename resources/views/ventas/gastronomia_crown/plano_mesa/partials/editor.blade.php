@php
    $layoutEditor = $layout_editor ?? ['version' => 1, 'width' => 1200, 'height' => 800, 'grid' => 20, 'nodes' => []];
    $mesasEditor = $mesas_editor ?? [];
    $esEdicion = $esEdicion ?? false;
@endphp
<div class="card card-outline card-info mb-0 gcpl-editor-card">
    <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between">
        <h3 class="card-title mb-0"><i class="fa fa-drafting-compass"></i> Editor del plano</h3>
        <div class="gcpl-toolbar">
            <div class="btn-group btn-group-sm mr-2" role="group" aria-label="Forma de la mesa">
                <button type="button" class="btn btn-outline-secondary gcpl-shape active" data-shape="rect" title="Rectangular">
                    <i class="fa fa-square"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary gcpl-shape" data-shape="round" title="Redonda">
                    <i class="fa fa-circle"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary gcpl-shape" data-shape="oval" title="Ovalada">
                    <i class="fa fa-ellipsis-h"></i>
                </button>
            </div>
            <div class="btn-group btn-group-sm mr-2" role="group" aria-label="Zoom">
                <button type="button" class="btn btn-outline-secondary" id="gcpl-zoom-out" title="Alejar"><i class="fa fa-search-minus"></i></button>
                <button type="button" class="btn btn-outline-secondary" id="gcpl-zoom-fit" title="Ajustar a la pantalla"><i class="fa fa-expand"></i></button>
                <button type="button" class="btn btn-outline-secondary" id="gcpl-zoom-in" title="Acercar"><i class="fa fa-search-plus"></i></button>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary mr-2" id="gcpl-rotar" title="Rotar 15°">
                <i class="fa fa-redo"></i> Rotar
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger mr-2" id="gcpl-borrar" title="Quitar del plano (Supr)">
                <i class="fa fa-trash"></i> Quitar
            </button>
            @if ($esEdicion && can('actualizar-plano-mesa-gastronomia-crown', false))
                <button type="button" class="btn btn-sm btn-info" id="gcpl-guardar-layout" title="Guardar solo el plano, sin salir">
                    <i class="fa fa-save"></i> Guardar plano
                </button>
            @endif
        </div>
    </div>
    <div class="card-body p-0">
        <div class="gcpl-wrap">
            <aside class="gcpl-palette" id="gcpl-palette">
                <div class="gcpl-palette-head">
                    <span><i class="fa fa-chair"></i> Mesas sin ubicar</span>
                    <span class="badge badge-light" id="gcpl-palette-count">0</span>
                </div>
                <input type="text" class="form-control form-control-sm gcpl-palette-buscar" id="gcpl-palette-buscar"
                       placeholder="Buscar mesa…" autocomplete="off">
                <div class="gcpl-palette-list" id="gcpl-palette-list">
                    <div class="text-muted small p-2">Elija empresa y sala.</div>
                </div>
                <div class="gcpl-palette-foot text-muted small">
                    Clic en una mesa para agregarla al centro del plano. Arrastre para ubicarla; el tirador rota.
                </div>
            </aside>
            <div class="gcpl-stage-wrap">
                <div id="gcpl-stage" class="gcpl-stage"></div>
                <div class="gcpl-status" id="gcpl-status">
                    <span id="gcpl-status-texto">Sin mesas ubicadas.</span>
                </div>
            </div>
        </div>
    </div>
</div>

<input type="hidden" name="layout_json" id="layout_json" value="{{ old('layout_json', json_encode($layoutEditor, JSON_UNESCAPED_UNICODE)) }}">

<script>
    window.GastroPlanoEditorCfg = {
        layout: @json($layoutEditor),
        mesas: @json($mesasEditor),
        esEdicion: @json($esEdicion),
        planoId: {{ (int) ($data->id ?? 0) }},
        urls: {
            mesasSala: @json(route('gastronomia_crown_plano_api_mesas_sala')),
            guardarLayout: @json(url('ventas/gastronomia-crown/plano-mesa')),
        },
    };
</script>
