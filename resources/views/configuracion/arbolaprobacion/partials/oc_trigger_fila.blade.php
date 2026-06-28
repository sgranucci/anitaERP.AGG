@php
    use App\Support\Configuracion\OcArbolTriggerCatalog;
    $idx = $idx ?? 0;
    $tr = $tr ?? null;
@endphp
<tr class="fila-oc-trigger">
    <td>
        <input type="hidden" name="oc_trigger_ids[]" value="{{ $tr->id ?? '' }}">
        <input type="text" name="oc_trigger_nombres[]" class="form-control form-control-sm" value="{{ $tr->nombre ?? '' }}" placeholder="Descripci&oacute;n">
    </td>
    <td>
        <select name="oc_trigger_tipos[]" class="form-control form-control-sm oc-trigger-tipo">
            @foreach(OcArbolTriggerCatalog::tipos() as $tipo)
                <option value="{{ $tipo }}" {{ ($tr->tipo ?? '') === $tipo ? 'selected' : '' }}>{{ OcArbolTriggerCatalog::etiquetaTipo($tipo) }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <select name="oc_trigger_eventos[]" class="form-control form-control-sm oc-trigger-evento mb-1">
            <option value="">—</option>
            @foreach(OcArbolTriggerCatalog::eventos() as $ev)
                <option value="{{ $ev }}" {{ ($tr->evento ?? '') === $ev ? 'selected' : '' }}>{{ OcArbolTriggerCatalog::etiquetaEvento($ev) }}</option>
            @endforeach
        </select>
        <select name="oc_trigger_evaluadores[]" class="form-control form-control-sm oc-trigger-evaluador">
            <option value="">—</option>
            @foreach(OcArbolTriggerCatalog::evaluadores() as $ev)
                <option value="{{ $ev }}" {{ ($tr->evaluador ?? '') === $ev ? 'selected' : '' }}>{{ OcArbolTriggerCatalog::etiquetaEvaluador($ev) }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <select name="oc_trigger_sector_origen_ids[]" class="form-control form-control-sm">
            <option value="">—</option>
            @foreach(($sector_legajocompra_query ?? collect()) as $sector)
                <option value="{{ $sector->id }}" {{ (int)($tr->sector_origen_id ?? 0) === (int)$sector->id ? 'selected' : '' }}>{{ $sector->nombre }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <select name="oc_trigger_sector_destino_ids[]" class="form-control form-control-sm">
            <option value="">—</option>
            @foreach(($sector_legajocompra_query ?? collect()) as $sector)
                <option value="{{ $sector->id }}" {{ (int)($tr->sector_destino_id ?? 0) === (int)$sector->id ? 'selected' : '' }}>{{ $sector->nombre }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <select name="oc_trigger_centrocosto_ids[]" class="form-control form-control-sm">
            <option value="">— CC l&iacute;neas OC —</option>
            @foreach($centrocosto_query as $value)
                <option value="{{ $value->id }}" {{ (int)($tr->centrocosto_circuito_id ?? 0) === (int)$value->id ? 'selected' : '' }}>{{ $value->codigo }} — {{ $value->nombre }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <select name="oc_trigger_estados[]" class="form-control form-control-sm">
            <option value="">— Nivel / default —</option>
            @foreach(($ordencompra_estados_arbol_enum ?? []) as $est)
                <option value="{{ $est['nombre'] }}" {{ ($tr->documento_estado_al_aprobar ?? '') === $est['nombre'] ? 'selected' : '' }}>{{ $est['nombre'] }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <select name="oc_trigger_acciones[]" class="form-control form-control-sm oc-trigger-accion">
            @foreach(OcArbolTriggerCatalog::accionesFinales() as $acc)
                <option value="{{ $acc }}" {{ ($tr->accion_final ?? OcArbolTriggerCatalog::ACCION_NINGUNA) === $acc ? 'selected' : '' }}>{{ OcArbolTriggerCatalog::etiquetaAccionFinal($acc) }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <select name="oc_trigger_accion_sector_ids[]" class="form-control form-control-sm mb-1 oc-trigger-accion-sector">
            <option value="">— sector —</option>
            @foreach(($sector_legajocompra_query ?? collect()) as $sector)
                <option value="{{ $sector->id }}" {{ (int)($tr->accion_final_sector_id ?? 0) === (int)$sector->id ? 'selected' : '' }}>{{ $sector->nombre }}</option>
            @endforeach
        </select>
        <select name="oc_trigger_accion_estados[]" class="form-control form-control-sm oc-trigger-accion-estado">
            <option value="">— estado —</option>
            @foreach(($ordencompra_estados_arbol_enum ?? []) as $est)
                <option value="{{ $est['nombre'] }}" {{ ($tr->accion_final_estado ?? '') === $est['nombre'] ? 'selected' : '' }}>{{ $est['nombre'] }}</option>
            @endforeach
        </select>
    </td>
    <td><input type="number" name="oc_trigger_prioridades[]" class="form-control form-control-sm" min="1" value="{{ $tr->prioridad ?? 100 }}" style="width:70px;"></td>
    <td>
        <select name="oc_trigger_anula_auto[]" class="form-control form-control-sm">
            <option value="N" {{ ($tr->anula_auto_aprobacion ?? 'N') === 'N' ? 'selected' : '' }}>N</option>
            <option value="S" {{ ($tr->anula_auto_aprobacion ?? 'N') === 'S' ? 'selected' : '' }}>S</option>
        </select>
    </td>
    <td>
        <select name="oc_trigger_reevaluar[]" class="form-control form-control-sm">
            <option value="N" {{ ($tr->reevaluar_en_actualizacion ?? 'N') === 'N' ? 'selected' : '' }}>N</option>
            <option value="S" {{ ($tr->reevaluar_en_actualizacion ?? 'N') === 'S' ? 'selected' : '' }}>S</option>
        </select>
    </td>
    <td>
        <select name="oc_trigger_activos[]" class="form-control form-control-sm">
            <option value="S" {{ ($tr->activo ?? 'S') === 'S' ? 'selected' : '' }}>S</option>
            <option value="N" {{ ($tr->activo ?? 'S') === 'N' ? 'selected' : '' }}>N</option>
        </select>
    </td>
    <td><button type="button" class="btn btn-sm btn-danger eliminar_oc_trigger">&times;</button></td>
</tr>
