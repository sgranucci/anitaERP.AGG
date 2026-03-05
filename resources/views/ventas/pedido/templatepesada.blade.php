<template id="template-renglon-pesada">
	<tr class="item-pesada">
    	<td>
			<input type="text" class="form-control numerocajapesada" name="numerocajapesadas[]" value="" readonly>
			<input type="hidden" class="form-control pedido_articulo_id" name="pedido_articulo_ids[]" value="">
        </td>
		<td>
			<input type="hidden" class="articulopesada_id" name="articulopesada_ids[]" value="" >
			<input type="text" style="WIDTH: 120px;HEIGHT: 38px" class="codigoarticulopesada form-control" name="codigoarticulopesadas[]" value="" readonly>
		</td>		
		<td>
			<input type="text" style="WIDTH: 220px; HEIGHT: 38px" class="descripcionarticulopesada form-control" name="descripcionarticulopesadas[]" value="" readonly>
		</td>	
		<td>
			<input type="text" name="unidadmedidapesadas[]" class="form-control unidadmedidapesada" value="" />								
		</td>		
		<td>
			<input type="text" name="lotepesadas[]" class="form-control lotepesada" value="" />
		</td>		
		<td>
			<input type="date" name="fechavencimientopesadas[]" class="form-control fechavencimientopesada" value="" />
		</td>				
		<td>
			<input type="text" name="piezapesadas[]" class="form-control piezapesada" value="" />
		</td>	
		<td>
			<input type="text" name="kilopesadas[]" class="form-control kilopesada" value="" />
		</td>	
        <td>
			<button type="button" title="Elimina esta linea" style="padding:0;" class="btn-accion-tabla eliminarpesada tooltipsC">
        		<i class="fa fa-trash text-danger"></i>
			</button>
			<input type="hidden" name="creousuariopesada_ids[]" class="form-control creousuariopesada_id" value="{{ auth()->id() }}"/>
        </td>
	</tr>
</template>
