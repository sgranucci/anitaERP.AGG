<template id="template-renglon-arbolaprobacion-nivel">
    <tr class="item-arbolaprobacion-nivel">
        <td>
            <input type="hidden" class="id form-control" name="ids[]" value="">
            <input type="text" name="arbolaprobacion_nivel[]" class="form-control iiarbolaprobacion_nivel" readonly value="1" />
        </td>
        <td>
            <input type="number" min="1" class="nivel form-control" name="niveles[]" required value="">
        </td>
        <td>
            <select name="centrocosto_ids[]" data-placeholder="Centro de Costo" class="centrocosto form-control required" required data-fouc>
                <option value="">-- Elija centro de costo --</option>
                @foreach($centrocosto_query as $key => $value)
                    <option value="{{ $value->id }}">{{ $value->codigo }} - {{ $value->nombre }}</option>    
                @endforeach
            </select>
        </td>                    
        <td>
            <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
                <input type="hidden" class="usuario_id_arbol" name="usuario_ids[]" value="" >
                <input type="hidden" class="usuario_id_previa" name="usuario_id_previa[]" value="" >
                <input type="text" style="flex: 0 0 110px; width: 110px; height: 38px;" class="usuario_codigo_arbol form-control" value="" placeholder="Código usuario" title="Código de login o ID numérico; Tab fuera para cargar el nombre" autocomplete="off">
                <button type="button" title="Consulta usuarios" style="padding:1; flex: 0 0 auto;" class="btn-accion-tabla consultausuario tooltipsC">
                        <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" style="flex: 1 1 auto; min-width: 0; height: 38px; font-size: 14px;" class="nombreusuario form-control" name="nombreusuarios[]" value="" placeholder="(opcional)" >
            </div>
        </td>
        <td>
            <input type="number" class="desdemonto form-control" name="desdemontos[]" value="">
        </td>
        <td>
            <input type="number" class="hastamonto form-control" name="hastamontos[]" value="">
        </td>        
        <td>
            <select name="moneda_ids[]" data-placeholder="Moneda" class="moneda form-control required" required data-fouc>
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
            <input type="checkbox" class="doble_aprobacion_check" value="S" title="Doble aprobación para este centro de costo">
        </td>
        <td>
            <button type="button" style="width: 7%;" title="Elimina esta linea" class="btn-accion-tabla eliminar_arbolaprobacion_nivel tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
