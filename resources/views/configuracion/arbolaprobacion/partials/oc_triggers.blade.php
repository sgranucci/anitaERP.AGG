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
<div id="oc-triggers-panel" class="row" style="{{ $esOrdenesCompra ? '' : 'display:none;' }}">
    <div class="col-sm-12">
        <div class="alert alert-secondary" role="alert" style="margin-top: 10px;">
            <strong>Triggers OC</strong> — eventos (alta, cambio de sector) y condiciones (ej. CAPEX mensual excedido).
            Prioridad menor = se eval&uacute;a primero. Gastronom&iacute;a: evento <em>Cambio de sector</em> hacia GASTRONOMIA, CC del circuito y acci&oacute;n final a Cuentas a pagar.
        </div>
    </div>
    <div class="col-sm-12">
        <button type="button" class="btn btn-sm btn-primary" id="agrega_oc_trigger">Agregar trigger</button>
    </div>
    <div class="col-sm-12" style="margin-top: 10px; overflow-x: auto;">
        <table class="table table-bordered table-sm" id="tabla-oc-triggers">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Evento / Evaluador</th>
                    <th>Sector orig.</th>
                    <th>Sector dest.</th>
                    <th>CC circuito</th>
                    <th>Estado al aprobar</th>
                    <th>Acci&oacute;n final</th>
                    <th>Sector/estado acci&oacute;n</th>
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
