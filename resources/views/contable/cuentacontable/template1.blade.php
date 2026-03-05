<template id="template-renglon-centrocosto">
	<tr class="item-centrocosto">
		<td>
			<select name="centrocosto_ids[]" id="centrocosto_ids" data-placeholder="Centro de costo" class="form-control centrocosto" data-fouc>
				<option value="">-- Elija centro de costo --</option>
				@foreach($centrocosto_query as $key => $value)
					<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
				@endforeach
			</select>
		</td>
    	<td>
			<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_centrocosto tooltipsC">
    			<i class="fa fa-times-circle text-danger"></i>
			</button>
    	</td>
	</tr>
</template>
