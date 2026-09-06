@php
    use App\Support\Configuracion\OcArbolTriggerCatalog;
    $ocTriggers = collect(old('oc_trigger_ids') ? [] : (isset($data) ? ($data->oc_triggers ?? collect()) : collect()));
    if (old('oc_trigger_ids')) {
        $ocTriggers = collect(old('oc_trigger_ids'))->map(function ($id, $i) {
            return (object) [
                'id' => $id,
                'nombre' => old('oc_trigger_nombres.'.$i),
                'tipo' => old('oc_trigger_tipos.'.$i),
                'evento' => old('oc_trigger_eventos.'.$i),
                'evaluador' => old('oc_trigger_evaluadores.'.$i),
                'sector_origen_id' => old('oc_trigger_sector_origen_ids.'.$i),
                'sector_destino_id' => old('oc_trigger_sector_destino_ids.'.$i),
                'centrocosto_circuito_id' => old('oc_trigger_centrocosto_ids.'.$i),
                'documento_estado_al_aprobar' => old('oc_trigger_estados.'.$i),
                'accion_final' => old('oc_trigger_acciones.'.$i),
                'accion_final_sector_id' => old('oc_trigger_accion_sector_ids.'.$i),
                'accion_final_estado' => old('oc_trigger_accion_estados.'.$i),
                'prioridad' => old('oc_trigger_prioridades.'.$i, 100),
                'anula_auto_aprobacion' => old('oc_trigger_anula_auto.'.$i, 'N'),
                'reevaluar_en_actualizacion' => old('oc_trigger_reevaluar.'.$i, 'N'),
                'activo' => old('oc_trigger_activos.'.$i, 'S'),
            ];
        });
    }
    $nombreTipoOc = \App\Models\Configuracion\Arbolaprobacion::$enumTipoArbol[array_search('OC', array_column(\App\Models\Configuracion\Arbolaprobacion::$enumTipoArbol, 'valor'))]['nombre'];
    $esOrdenesCompra = (old('tipoarbol', isset($data) ? ($data->tipoarbol ?? '') : '') === $nombreTipoOc);
@endphp
<div id="oc-triggers-panel" class="anita-arbol-panel" style="{{ $esOrdenesCompra ? '' : 'display:none;' }}">
    <div class="anita-arbol-panel-head">
        <div>
            <h2 class="anita-arbol-panel-title">Triggers OC</h2>
            <p class="anita-arbol-panel-hint">
                Eventos (alta, cambio de sector) y condiciones (ej. CAPEX). Prioridad menor = primero.
            </p>
        </div>
        <span class="anita-arbol-chip anita-arbol-chip-warn">Órdenes de compra</span>
    </div>
    <div class="anita-arbol-toolbar mb-2" style="margin-top:0;">
        <span></span>
        <button type="button" class="btn btn-sm anita-arbol-btn-teal" id="agrega_oc_trigger">
            <i class="fa fa-plus"></i> Agregar trigger
        </button>
    </div>
    <div class="anita-arbol-table-wrap">
        <table class="table table-sm mb-0" id="tabla-oc-triggers">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Evento / Evaluador</th>
                    <th>Sector orig.</th>
                    <th>Sector dest.</th>
                    <th>CC circuito</th>
                    <th>Estado al aprobar</th>
                    <th>Acción final</th>
                    <th>Sector/estado acción</th>
                    <th>Prio.</th>
                    <th>Anula auto</th>
                    <th>Reeval.</th>
                    <th>Activo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-oc-triggers">
                @foreach($ocTriggers as $tr)
                    @include('configuracion.arbolaprobacion.partials.oc_trigger_fila', ['tr' => $tr, 'idx' => $loop->index])
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<script type="text/template" id="template-oc-trigger-fila">
@include('configuracion.arbolaprobacion.partials.oc_trigger_fila', ['tr' => null, 'idx' => '__IDX__'])
</script>
