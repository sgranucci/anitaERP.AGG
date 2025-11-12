<template id="template-renglon-seguimiento">
	<tr class="item-seguimiento">
    	<td>
			<input type="hidden" name="seguimientos[]" class="form-control iiseguimiento" readonly value="1" />
			<input type="date" name="fechas[]" class="form-control"
				value="" />
		</td>
		<td>
			<input type="text" name="observaciones[]" value="" class="form-control observacion" placeholder="Observación">
		</td>
		<td>
			<!-- textarea -->
			<div class="form-group">
				<textarea name="leyendas[]" class="form-control" rows="3" placeholder="Leyenda ..."></textarea>
			</div>								
		</td>			
		<td>
			<input type="hidden" name="creousuario_ids[]" class="form-control creousuario_id" value="{{ auth()->id() }}"/>
			<input type="text" name="creousuarios[]" class="form-control creousuario" value="{{ auth()->user()->name }}" readonly/>
		</td>						
    	<td>
			<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_seguimiento tooltipsC">
    			<i class="fa fa-times-circle text-danger"></i>
			</button>
    	</td>
	</tr>
</template>
