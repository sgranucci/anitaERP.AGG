<template id="template-renglon-exclusion">
	<tr class="item-exclusion">
		<td>
			<input type="text" name="exclusiones[]" class="form-control iiexclusion" readonly value="1" />
		</td>
		<td>
			<input type="date" name="desdefechas[]" class="form-control" value="" />
		</td>
		<td>
			<input type="date" name="hastafechas[]" class="form-control" value="" />
		</td>
		<td>
			<select name="tiporetenciones[]" id="formpago_ids" data-placeholder="Forma de pago" class="form-control formapago" data-fouc>
				<option value="">-- Elija tipo de retención --</option>
				@foreach ($tiporetencion_enum as $value => $tiporetencion)
					<option value="{{ $value }}">{{ $tiporetencion }}</option>
				@endforeach
			</select>
		</td>
		<td>
			<input type="number" name="porcentajeexclusiones[]" class="form-control" value="" />
		</td>
		<td>
			<input type="text" name="comentarios[]" class="form-control" value="" />
		</td>
    	<td>
			<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_exclusion tooltipsC">
    			<i class="fa fa-times-circle text-danger"></i>
			</button>
    	</td>
	</tr>
</template>
