<template id="template-renglon-aplicacion">
	<tr class="item-aplicacion">
        <td>
            <input type="text" class="id form-control" name="ids[]" value="" >
            <input type="hidden" style="text-align: right;" class="form-control cuentacorriente_id" value="">
            <input type="hidden" style="text-align: right;" class="form-control cobranza_id" value="">
            <input type="hidden" style="text-align: right;" class="form-control ventaaplicado_id value="">
            <input type="hidden" style="text-align: right;" class="form-control aplicado_id value="">
        </td>							
        <td>
            <input type="text" class="fechaaplicacion form-control" name="fechaaplicaciones[]" value="" readonly>
        </td>
        <td>
            <input type="text" class="comprobanteaplicado form-control" name="comprobanteaplicados[]" value="" readonly>
        </td>
        <td>
            <select name="monedaaplicacion_ids[]" data-placeholder="Moneda" class="monedaaplicacion form-control required" required readonly data-fouc>
                <option value="">-- Seleccionar --</option>
                @foreach($moneda_query as $key => $value)
                    <option value="{{ $value->id }}" selected="select">{{ $value->abreviatura }}</option>    
                @endforeach
            </select>
        </td>
        <td>
            <input type="text" style="text-align: right;" name="cotizacionaplicaciones[]" class="form-control cotizacionaplicacion" value="0"  readonly>                            
        </td>
        <td>
            <input type="text" style="text-align: right;" name="montoaplicaciones[]" class="form-control montoaplicacion" value=""  readonly>
        </td>
        <td>
            <input type="text" style="text-align: right;" name="saldoaplicaciones[]" class="form-control saldoaplicacion" value=""  readonly>
        </td>		
		<td>
            <a href="#" class="btn-accion-tabla tooltipsC editarcomprobante" title="Editar este registro">
                <i class="fa fa-edit"></i>
            </a>
        </td>
	</tr>
</template>
