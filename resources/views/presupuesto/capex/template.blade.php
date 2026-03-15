<template id="template-renglon-capex-partida">
    <tr class="item-capex-partida">
        <td>
            <input type="hidden" name="items[]" class="form-control item" readonly value="1" />
            <input type="hidden" name="capex_partida_ids[]" class="form-control capex_partida_id" readonly value="" />
            <input type="hidden" name="creousuario_ids[]" class="creousuario_id" value="{{ auth()->id() }}" />
            <input type="hidden" name="estadopartidas[]" class="estadopartida" value="" />
            <input type="text" name="codigos[]" class="form-control codigopartida" value="" readonly>                                    
        </td>
        <td>
            <input type="text" name="nombres[]" class="form-control nombre" value="">                                    
        </td>
        <td>
            <div class="form-group row">
                <input type="text" class="col-lg-2 proveedor_id form-control" name="proveedor_ids[]" value="" >
                <input type="text" class="col-lg-8 proveedor form-control" name="proveedores[]" value="" readonly>
                <button type="button" title="Consulta proveedores" style="padding:1;" class="btn-accion-tabla consultaproveedor tooltipsC">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="hidden" class="codigoproveedor" name="codigoproveedores[]" value="" >
                <input type="hidden" name="nombreproveedores[]" class="form-control nombreproveedor" value="">
            </div>            
        </td>
        <td>
            <select name="moneda_ids[]" data-placeholder="Moneda" class="form-control required moneda_id" data-fouc readonly required>
                @foreach($moneda_query as $key => $value)
                    <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                @endforeach
            </select>                                    
        </td>
        <td>
            <input type="number" class="form-control montopartida" id="montopartida" name="montopartida" value="" readonly>
        </td>            
        <td>
            <a href="#" class="btn-accion-tabla tooltipsC carga_partida_monto" title="Carga montos mensuales">
                <i class="fa fa-calendar text-success"></i>
            </a>   
            <button style="width: 7%;" type="button" class="btn-accion-tabla eliminar_capex_partida tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>            
        </td>
    </tr>
</template>