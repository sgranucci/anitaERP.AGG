@php
    use App\Support\Configuracion\ReArbolTriggerCatalog;
    $reTriggers = collect();
    if (old('re_trigger_ids')) {
        $reTriggers = collect(old('re_trigger_ids'))->map(function ($id, $i) {
            return (object) [
                'id' => $id,
                'nombre' => old('re_trigger_nombres.'.$i),
                'evaluador' => old('re_trigger_evaluadores.'.$i),
                'centrocosto_id' => old('re_trigger_centrocosto_ids.'.$i),
                'accion_rama' => old('re_trigger_acciones.'.$i),
                'param_monto' => old('re_trigger_param_montos.'.$i),
                'param_moneda_id' => old('re_trigger_param_moneda_ids.'.$i),
                'param_cuentacontable_id' => old('re_trigger_param_cuentacontable_ids.'.$i),
                'param_cuenta_codigo' => old('re_trigger_param_cuenta_codigos.'.$i),
                'param_cuenta_nombre' => old('re_trigger_param_cuenta_nombres.'.$i),
                'vigencia_desde' => old('re_trigger_vigencia_desdes.'.$i),
                'vigencia_hasta' => old('re_trigger_vigencia_hastas.'.$i),
                'observacion' => old('re_trigger_observaciones.'.$i),
                'prioridad' => old('re_trigger_prioridades.'.$i, 100),
                'activo' => old('re_trigger_activos.'.$i, 'S'),
            ];
        });
    } elseif (isset($data) && ($data->re_triggers ?? null)) {
        $reTriggers = $data->re_triggers;
    }
    $nombreTipoRe = \App\Models\Configuracion\Arbolaprobacion::$enumTipoArbol[array_search('RE', array_column(\App\Models\Configuracion\Arbolaprobacion::$enumTipoArbol, 'valor'))]['nombre'];
    $esRequisicionesTriggers = (old('tipoarbol', isset($data) ? ($data->tipoarbol ?? '') : '') === $nombreTipoRe);
    $nested = ! empty($nested);
    $cantTriggers = $reTriggers->count();
    $cantActivos = $reTriggers->filter(fn ($tr) => strtoupper((string) ($tr->activo ?? 'S')) !== 'N')->count();
    $triggersAbiertos = old('re_trigger_ids') !== null;
@endphp
@if($nested)
<div id="re-triggers-panel" class="anita-arbol-subsection anita-arbol-collapse{{ $triggersAbiertos ? ' is-open' : '' }}" data-anita-collapse="re-triggers">
@else
<div id="re-triggers-panel" class="anita-arbol-panel anita-arbol-collapse{{ $triggersAbiertos ? ' is-open' : '' }}" style="{{ $esRequisicionesTriggers ? '' : 'display:none;' }}" data-anita-collapse="re-triggers">
@endif
    <button type="button" class="anita-arbol-collapse-toggle" id="toggle-re-triggers" aria-expanded="{{ $triggersAbiertos ? 'true' : 'false' }}" aria-controls="re-triggers-collapse-body">
        <span class="anita-arbol-collapse-toggle-main">
            <i class="fa fa-chevron-right anita-arbol-collapse-caret" aria-hidden="true"></i>
            <span>
                <span class="anita-arbol-subsection-title d-block">2. Triggers avanzados (opcional)</span>
                <span class="anita-arbol-panel-hint mb-0 d-block">
                    Políticas con prioridad, vigencia y parámetros (monto, cuenta). Para el día a día alcanza el paso 1.
                </span>
            </span>
        </span>
        <span class="anita-arbol-collapse-toggle-meta">
            <span class="anita-arbol-chip anita-arbol-chip-ok" id="re-triggers-activos-chip">{{ $cantActivos }} activa{{ $cantActivos === 1 ? '' : 's' }}</span>
            <span class="anita-arbol-chip anita-arbol-chip-navy" id="re-triggers-count-chip">{{ $cantTriggers }} regla{{ $cantTriggers === 1 ? '' : 's' }}</span>
            <span class="anita-arbol-chip anita-arbol-chip-teal">Avanzado</span>
        </span>
    </button>
    <div id="re-triggers-collapse-body" class="anita-arbol-collapse-body" @if(! $triggersAbiertos) hidden @endif>
        <div class="anita-arbol-toolbar mb-2" style="margin-top:0.35rem;">
            <span class="text-muted small">Menor prioridad = primero. Evaluadores de allowlist leen la lista del paso 1.</span>
            <button type="button" class="btn btn-sm anita-arbol-btn-teal" id="agrega_re_trigger">
                <i class="fa fa-plus"></i> Agregar trigger
            </button>
        </div>
        <div class="anita-arbol-table-wrap">
            <table class="table table-sm mb-0" id="tabla-re-triggers">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Evaluador / params</th>
                        <th>CC + vigencia</th>
                        <th>Acción rama</th>
                        <th>Prio.</th>
                        <th>Activo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tbody-re-triggers">
                    @foreach($reTriggers as $tr)
                        @include('configuracion.arbolaprobacion.partials.re_trigger_fila', ['tr' => $tr])
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
<script type="text/template" id="template-re-trigger-fila">
@include('configuracion.arbolaprobacion.partials.re_trigger_fila', ['tr' => null])
</script>
