<template id="template-renglon-tasaiibb">
	<tr class="item-tasaiibb">
		<td>
			<select name="condicioniibb_ids[]" data-placeholder="condicioniibb" class="condicioniibb form-control required" required data-fouc>
				<option value="">-- Seleccionar --</option>
				@foreach($condicioniibb_query as $value)
					<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
				@endforeach
			</select>
		</td>
		<td>
			<input type="number" name="tasas[]" value="" class="form-control tasa" placeholder="Tasa de percepción por defecto">
		</td>
		<td>
			<input type="number" name="minimonetos[]" value="" class="form-control minimoneto" placeholder="Mínimo Neto sujeto a Percepión">
		</td>   
		<td>
			<input type="number" name="minimopercepciones[]" value="" class="form-control minimopercepcion" placeholder="Monto Mínimo de Percepión">
		</td>         
		<td>
			<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_tasaiibb tooltipsC">
				<i class="fa fa-times-circle text-danger"></i>
			</button>
			<input type="hidden" name="creousuario_tasa_ids[]" class="form-control creousuario_tasa_id" value="{{ auth()->id() }}"/>			
		</td>
	</tr>
</template>
