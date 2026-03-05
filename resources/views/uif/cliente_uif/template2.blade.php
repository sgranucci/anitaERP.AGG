<template id="template-renglon-premio">
	<tr class="item-premio">
		<td>
			<input type="hidden" name="premios[]" class="form-control iipremio" readonly value="1" />
			<input type="hidden" name="premio_ids[]" class="form-control premio_id" value="" />
			<input type="datetime" name="fechaentregas[]" class="form-control fechaentrega" value="" />
		</td>
		<td>
			<input type="text" name="salas[]" class="form-control sala" readonly value="" />
		</td>
		<td>
			<input type="text" name="detalles[]" class="form-control detalle" value="" />
		</td>
		<td>
			<input type="text" name="numerotitos[]" class="form-control numerotito" value="" />
		</td>
		<td>
			<input type="text" name="montopremios[]" class="form-control montopremio" value="" />
		</td>
    	<td>
			<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_premio tooltipsC">
    			<i class="fa fa-times-circle text-danger"></i>
			</button>
    	</td>
	</tr>
</template>
