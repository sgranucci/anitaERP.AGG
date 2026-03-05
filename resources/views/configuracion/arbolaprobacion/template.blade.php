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
                    <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                @endforeach
            </select>
        </td>                    
        <td>
            <div class="form-group row" id="usuario">
                <input type="text" style="WIDTH: 40px;HEIGHT: 38px" class="usuario_id" name="usuario_ids[]" value="" >
                <input type="hidden" class="usuario_id_previa" name="usuario_id_previa[]" value="" >
                <button type="button" title="Consulta usuarios" style="padding:1;" class="btn-accion-tabla consultausuario tooltipsC">
                        <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" style="font-size: 16px; WIDTH: 300px;HEIGHT: 38px" class="nombreusuario form-control" name="nombreusuarios[]" value="" >
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
            <button type="button" style="width: 7%;" title="Elimina esta linea" class="btn-accion-tabla eliminar_arbolaprobacion_nivel tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>