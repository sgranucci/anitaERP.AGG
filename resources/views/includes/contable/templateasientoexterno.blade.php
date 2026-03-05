<template id="template-renglon-cuenta-asiento">
    <tr class="item-cuenta-asiento">
        <td>
            <div class="form-group row" id="cuentacontable">
                <input type="hidden" name="cuenta[]" class="form-control iicuentacontable" readonly value="1" />
                <input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="" >
                <input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="" >
                <button type="button" title="Consulta cuentas" style="padding:1;" class="btn-accion-tabla consultacuenta tooltipsC">
                        <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" style="WIDTH: 100px;HEIGHT: 38px" class="codigoasiento form-control" name="codigoasientos[]" value="" >
                <input type="hidden" class="codigo_previo_cuentacontable" name="codigo_previo_cuentacontables[]" value="" >
                <input type="hidden" class="carga_cuentacontable_manual" name="carga_cuentacontable_manuales[]" value="1" >
            </div>
        </td>							
        <td>
            <input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="nombrecuentacontable form-control" name="nombrecuentacontables[]" value="" readonly >
        </td>
        <td>
            <select name="centrocostoasiento_ids[]" data-placeholder="Centro de costo" class="centrocostoasiento form-control" data-fouc>
            </select>
        </td>
        <td>
            <select name="monedaasiento_ids[]" data-placeholder="Moneda" class="monedaasiento form-control" required data-fouc>
                <option value="">-- Seleccionar --</option>
                    @foreach($moneda_query as $key => $value)
                        @if( (int) $value->id == (int) old('monedaasiento_ids[]', $cuenta->moneda_id ?? ''))
                            <option value="{{ $value->id }}" selected="select">{{ $value->abreviatura }}</option>    
                        @else
                            <option value="{{ $value->id }}">{{ $value->abreviatura }}</option>    
                        @endif
                    @endforeach
            </select>
            <input type="hidden" class="monedaasiento_id_previo" name="monedaasiento_id_previo[]" value="" >
        </td>
        <td>
            <input type="number" name="debeasientos[]" class="form-control debeasiento" value="">
        </td>
        <td>
            <input type="number" name="haberasientos[]" class="form-control haberasiento" value="">
        </td>
        <td>
            <input type="number" name="cotizacionasientos[]" class="form-control cotizacionasiento" value="0">
        </td>
        <td>
            <input type="text" name="observacionasientos[]" class="form-control observacionasiento" value="">
        </td>
        <td>
            <button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cuenta_asiento tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>