<template id="template-renglon-premio">
	<tr class="item-premio">
		<td>
			<input type="hidden" class="form-control iipremio" readonly value="1" />
			<input type="hidden" class="form-control premio_id" value="" />
			<input type="datetime" class="form-control fechaentrega" readonly value="" />
		</td>
		<td>
			<input type="text" class="form-control sala" readonly value="" />
		</td>
		<td>
			<input type="text" class="form-control detalle" readonly value="" />
		</td>
		<td>
			<input type="text" class="form-control numerotito" readonly value="" />
		</td>
		<td>
			<input type="text" class="form-control montopremio" readonly style="text-align: right;" value="" />
		</td>
		<td class="text-center align-middle premio-foto-preview">
			<span class="text-muted">—</span>
		</td>
    	<td>
			<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_premio tooltipsC">
    			<i class="fa fa-times-circle text-danger"></i>
			</button>
    	</td>
	</tr>
</template>
