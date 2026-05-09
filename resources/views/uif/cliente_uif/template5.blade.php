<template id="template-renglon-archivo">
	<tr class="item-archivo">
    	<td>
            <input type="file" name="nombrearchivos[]" class="form-control nombrearchivos" onchange="actualizaArchivo(this)">
       	</td>
    	<td>
			<button type="button" title="Elimina esta línea" class="btn-accion-tabla eliminararchivo tooltipsC">
    			<i class="fa fa-times-circle text-danger"></i>
			</button>
    	</td>
	</tr>
</template>
