<template id="template-renglon-arbolaprobacion-nivel">
    <tr class="item-arbolaprobacion-nivel">
        <td>
            <input type="hidden" class="id form-control" name="ids[]" value="">
            <input type="text" name="arbolaprobacion_nivel[]" class="form-control form-control-sm iiarbolaprobacion_nivel" readonly value="1" />
        </td>
        <td>
            <input type="number" min="1" class="nivel form-control form-control-sm" name="niveles[]" required value="">
        </td>
        <td class="col-rama-re">
            <select name="ramas[]" class="form-control form-control-sm rama-re" title="Vacío = circuito único">
                <option value="">—</option>
                <option value="A">A</option>
                <option value="B">B</option>
            </select>
        </td>
        <td>
            <select name="centrocosto_ids[]" class="centrocosto form-control form-control-sm required" required data-fouc>
                <option value="">-- CC --</option>
                @foreach($centrocosto_query as $key => $value)
                    <option value="{{ $value->id }}">{{ $value->codigo }} - {{ $value->nombre }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
                <input type="hidden" class="usuario_id_arbol" name="usuario_ids[]" value="" >
                <input type="hidden" class="usuario_id_previa" name="usuario_id_previa[]" value="" >
                <input type="text" style="flex: 0 0 96px; width: 96px;" class="usuario_codigo_arbol form-control form-control-sm" value="" placeholder="Código" title="Login o ID; Tab para cargar nombre" autocomplete="off">
                <button type="button" title="Consulta usuarios" class="btn-accion-tabla consultausuario tooltipsC">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" style="flex: 1 1 auto; min-width: 0;" class="nombreusuario form-control form-control-sm" name="nombreusuarios[]" value="" placeholder="(opcional)" >
            </div>
        </td>
        <td>
            <input type="number" class="desdemonto form-control form-control-sm" name="desdemontos[]" value="">
        </td>
        <td>
            <input type="number" class="hastamonto form-control form-control-sm" name="hastamontos[]" value="">
        </td>
        <td>
            <select name="moneda_ids[]" class="moneda form-control form-control-sm required" required data-fouc>
                @foreach($moneda_query as $key => $value)
                    <option value="{{ $value->id }}">{{ $value->abreviatura }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <select name="documento_estado_al_aprobar[]" class="form-control form-control-sm">
                <option value="">—</option>
                @foreach(($requisicion_estados_arbol_enum ?? []) as $estReq)
                    <option value="{{ $estReq['nombre'] }}">{{ str_replace('_', ' ', $estReq['nombre']) }}</option>
                @endforeach
                @foreach(($requisicion_sala_estados_arbol_enum ?? []) as $estRs)
                    <option value="{{ $estRs['nombre'] }}">{{ str_replace('_', ' ', $estRs['nombre']) }}</option>
                @endforeach
                @foreach(($ordencompra_estados_arbol_enum ?? []) as $estOc)
                    <option value="{{ $estOc['nombre'] }}">{{ $estOc['nombre'] }}</option>
                @endforeach
            </select>
        </td>
        <td class="text-center col-doble-aprobacion">
            <input type="hidden" name="doble_aprobacions[]" class="doble_aprobacion_valor" value="N">
            <input type="checkbox" class="doble_aprobacion_check" value="S" title="Doble aprobación para este CC">
        </td>
        <td>
            <button type="button" title="Eliminar línea" class="btn-accion-tabla eliminar_arbolaprobacion_nivel tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
