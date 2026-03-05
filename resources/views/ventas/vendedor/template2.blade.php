<template id="template-renglon-vendedor-asociado">
	<tr class="item-vendedor-asociado">
		<td>
			<div class="form-group row" id="vendedorasociado">
				<input type="hidden" name="vendedorasociado[]" class="form-control iivendedorasociado" readonly value="" />
				<input type="hidden" class="vendedor_id" name="vendedor_ids[]" value="" >
				<input type="hidden" class="vendedor_id_previo" name="vendedor_id_previo[]" value="" >
				<button type="button" title="Consulta Vendedores" style="padding:1;" class="btn-accion-tabla consultavendedor tooltipsC">
						<i class="fa fa-search text-primary"></i>
				</button>
				<input type="text" style="WIDTH: 120px;HEIGHT: 38px" class="codigovendedor form-control" name="codigovendedores[]" value="" >
			</div>
		</td>			
		<td>
			<input type="text" style="WIDTH: 400px; HEIGHT: 38px" class="nombrevendedor form-control" name="nombrevendedores[]" value="" readonly>
		</td>	
		<td>
			<button type="button" class="btn-accion-tabla eliminar_vendedorasociado tooltipsC">
				<i class="fa fa-times-circle text-danger"></i>
			</button>
		</td>
	</tr>
</template>
