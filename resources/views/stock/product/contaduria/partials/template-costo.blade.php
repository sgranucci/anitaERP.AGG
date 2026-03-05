<template id="template-renglon-costo">
    <tr class="item-costo">
		<td>
			<select name="tareas_id[]" class="form-control">
       			<option value="">-- Elija tarea --</option>
                	@foreach ($tarea_query as $tarea)
                		<option value="{{ $tarea->id }}"> {{ $tarea->nombre }} </option>
                	@endforeach
            </select>
		</td>
		<td>
			<input type="number" name="costos[]" class="form-control costo" value="" required/>
		</td>
		<td>
			<input type="date" name="fechasvigencia[]" class="form-control fecha" value="{{substr(date('Y-m-d'),0,10)}}"/>
		</td>
		<td>
			<button type="button" title="Elimina esta linea" style="padding:0;" class="btn-accion-tabla eliminarCosto tooltipsC">
              	<i class="fa fa-trash text-danger"></i>
			</button>
		</td>
	</tr>
</template>
