<template id="template-renglon-concepto_ivacompra">
	<tr class="item-concepto_ivacompra">
		<td>
			<input type="text" name="concepto_ivacompras[]" class="form-control iiconcepto_ivacompra" readonly value="1" />
		</td>
		<td>
			<select name="concepto_ivacompra_ids[]" id="concepto_ivacompra_ids" data-placeholder="Centro de costo" class="form-control concepto_ivacompra" data-fouc>
				<option value="">-- Elija concepto iva compra --</option>
				@foreach($concepto_ivacompra_query as $key => $value)
					<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
				@endforeach
			</select>
		</td>
    	<td>
			<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_concepto_ivacompra tooltipsC">
    			<i class="fa fa-times-circle text-danger"></i>
			</button>
    	</td>
	</tr>
</template>
