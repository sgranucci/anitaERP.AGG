<template id="template-renglon-factura">
	<tr class="item-factura">
    	<td style="margin : 0; padding : 0; height: 17px;">
       		<input type="hidden" name="items[]" class="form-control item" value="1" readonly>
            <input type="hidden" name="monedas_id_fac[]" class="form-control moneda_id_fac" readonly value="" />
            <input type="hidden" name="incluyeimpuestos_fac[]" class="form-control incluyeimpuesto_fac" readonly value="" />
        	<input type="hidden" style="text-align: right;" name="precios_fac[]" class="form-control precio_fac" value="" readonly/>
			<textarea name="detalle" class="form-control required" rows="3" required placeholder="Detalle ...">{{$data->detalle ?? ''}}</textarea>
        </td>
	</tr>
</template>
