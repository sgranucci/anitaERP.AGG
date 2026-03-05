<template id="template-renglon">
	<tr class="item-ordentrabajo">
    	<td>
       		<input type="text" name="items[]" class="form-control item" value="1" readonly>
        </td>
        <td>
        	<select name="medidas_id[]" data-placeholder="Medida" class="form-control medida" data-fouc>
        </select>
        	<input type="hidden" class="medida_id_previa" name="medida_id_previa[]" value="{{old('medida_id') ?? ''}}" >
        	<input type="hidden" class="desc_medida" name="desc_medida[]" class="desc_medida" value="{{old('desc_medida[]') ?? ''}}" >
        </td>
        <td>
        	<input type="text" id="icantidad" name="cantidades[]" class="form-control cantidad" readonly value="" />
        </td>
        <td>
			<button type="button" title="Elimina esta linea" style="padding:0;" class="btn-accion-tabla eliminar tooltipsC">
        		<i class="fa fa-trash text-danger"></i>
			</button>
        </td>
	</tr>
</template>
