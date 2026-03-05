<template id="template-renglon-escenario">
	<tr class="item-escenario">
		<td>
			<input type="hidden" name="escenario[]" class="form-control iiescenario" readonly value="1" />
			<input type="text" name="nombres[]" class="form-control" value=""/>
		</td>
		<td>
			<select name="tipos[]" data-placeholder="Tipo de Escenario" class="tipo form-control required" required data-fouc>
				@foreach ($tipo_enum as $value => $tipo)
					<option value="{{ $tipo }}">{{ $tipo }}</option>
				@endforeach
			</select>     
		</td>
		<td>
			<input type="text" name="codigos[]" class="form-control" value="" readonly/>
		</td>                                                      
		<td>
			<button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_escenario tooltipsC">
				<i class="fa fa-times-circle text-danger"></i>
			</button>
			<input type="hidden" name="creousuario_escenario_ids[]" class="form-control creousuario_escenario_id" value="{{ auth()->id() }}"/>
		</td>
	</tr>
</template>