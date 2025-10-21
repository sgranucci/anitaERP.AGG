<template id="template-renglon-riesgo">
	<tr class="item-riesgo">
		<td>
			<input type="hidden" name="iiriesgos[]" class="form-control iiriesgo" readonly value="1" />
			<input type="hidden" name="riesgo_ids[]" class="form-control riesgo_id" readonly value="0" />
			<input type="hidden" name="creousuario_riesgo_ids[]" class="form-control creousuario_riesgo_id" value="{{ auth()->id() }}" />
			<div class="form-group">
				<input type="text" name="periodos[]" min="2000-01" value="" class="form-control periodo" placeholder="Periodo">
			</div>
		</td>		
		<td>
			<select name="inusualidad_uif_ids[]" data-placeholder="Inusualidad" class="form-control inusualidad_uif" data-fouc>
				<option value="">-- Seleccionar --</option>
				@foreach($inusualidad_uif_query as $key => $value)
					<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
				@endforeach
			</select>
		</td>
		<td>
			<div class="form-group">
				<input type="text" name="riesgos[]" value="" class="form-control riesgo" placeholder="Riesgo asociado">
			</div>
		</td>		
    	<td>
			<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_riesgo tooltipsC">
    			<i class="fa fa-times-circle text-danger"></i>
			</button>
    	</td>
	</tr>
</template>
