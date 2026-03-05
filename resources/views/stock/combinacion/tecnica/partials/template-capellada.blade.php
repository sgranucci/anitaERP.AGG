<template id="template-renglon-capellada">
    <tr class="item-capellada">
   		<td>
			<select name="materiales[]" class="form-control">
       			<option value="">-- Elija medida/talle --</option>
                	@foreach ($capelladas as $material)
                		<option value="{{ $material->id }}"> {{ $material->nombre }} </option>
                	@endforeach
            </select>
       	</td>
		<td>
            <select name="colores[]" class="form-control">
                <option value="">-- Elija color --</option>
                	@foreach ($color as $capellada_color)
                		<option value="{{ $capellada_color->id }}" >{{ $capellada_color->nombre }}</option>
                	@endforeach
                </select>
		</td>
        <td>
         	<input type="text" name="piezas[]" class="form-control" value="{{ old('piezas.1') ?? '' }}" />
        </td>
        <td>
        	<input type="number" name="consumo1[]" class="form-control" value="{{ old('consumo1.1') ?? '0' }}" />
        </td>
        <td>
            <input type="number" name="consumo2[]" class="form-control" value="{{ old('consumo2.1') ?? '0' }}" />
        </td>
        <td>
            <input type="number" name="consumo3[]" class="form-control" value="{{ old('consumo3.1') ?? '0' }}" />
        </td>
        <td>
            <input type="number" name="consumo4[]" class="form-control" value="{{ old('consumo4.1') ?? '0' }}" />
        </td>
		<td>
            <select name="tipos[]" class="form-control requerido" required>
              	<option value="">-- Elija tipo de material --</option>
                	@foreach ($tipos as $tipo)
                		<option value="{{ $tipo['id'] }}" >{{ $tipo['nombre'] }}</option>
                	@endforeach
            </select>
        </td>
		<td>
			<input name="tiposcalculo[]" class="tipoCalculo" title="Calculo Definitivo o Provisorio" type="checkbox" autocomplete="off" value=""> 
        </td>
		<td>
			<button type="button" title="Elimina esta linea" style="padding:0;" class="btn-accion-tabla eliminarCapeart tooltipsC">
              	<i class="fa fa-trash text-danger"></i>
			</button>
		</td>
	</tr>
</template>
