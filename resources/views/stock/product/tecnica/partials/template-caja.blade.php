<template id="template-renglon-caja">
    <tr class="item-caja">
		<td>
			<select name="cajas_id[]" class="form-control">
       			<option value="">-- Elija caja --</option>
                	@foreach ($caja_query as $caja)
                		<option value="{{ $caja->id }}"> {{ $caja->nombre }} </option>
                	@endforeach
            </select>
		</td>
		<td>
		</td>
		<td>
			<input type="text" id="desdenro" name="desdenros[]" class="form-control desdenro" value="" />
		</td>
		<td>
			<input type="text" id="hastanro" name="hastanros[]" class="form-control hastanro" value="" />
		</td>
		<td>
			<button type="button" title="Elimina esta linea" style="padding:0;" class="btn-accion-tabla eliminarCaja tooltipsC">
              	<i class="fa fa-trash text-danger"></i>
			</button>
		</td>
	</tr>
</template>
