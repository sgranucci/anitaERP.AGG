<template id="template-renglon-formapago">
	<tr class="item-formapago">
		<td>
			<input type="text" name="formapagos[]" class="form-control iiformapago" readonly value="1" />
		</td>
		<td>
			<input type="text" name="nombres[]" class="form-control" value="" />
		</td>
		<td>
			<select name="formapago_ids[]" id="formapago_ids" data-placeholder="Forma de pago" class="form-control formapago" data-fouc>
				<option value=""></option>
				@foreach($formapago_query as $key => $value)
					<option value="{{ $value->id }}">{{ $value->nombre }}</option>
				@endforeach
			</select>
		</td>
		<td>
			<div class="form-group">
				<input type="text" name="cbus[]" value="" class="form-control cbus" placeholder="CBU">
			</div>
		</td>							
		<td>
			<select name="tipocuentacaja_ids[]" id="tipocuentacaja_ids" data-placeholder="Tipo de cuenta de caja" class="form-control tipocuentacaja" data-fouc>
				<option value=""></option>
				@foreach($tipocuentacaja_query as $key => $value)
					<option value="{{ $value->id }}">{{ $value->abreviatura }}</option>    
				@endforeach
			</select>
		</td>
		<td>
			<select name="moneda_ids[]" id="moneda_ids" data-placeholder="Moneda" class="form-control moneda" data-fouc>
				<option value=""></option>
				@foreach($moneda_query as $key => $value)
					<option value="{{ $value->id }}">{{ $value->abreviatura }}</option>    
				@endforeach
			</select>
		</td>
		<td>
			<div class="form-group">
				<input type="text" name="numerocuentas[]" value="" class="form-control numerocuentas" placeholder="Nro.Cuenta">
			</div>
		</td>
		<td>
			<div class="form-group">
				<input type="text" name="nroinscripciones[]" value="" class="form-control nroinscripciones" placeholder="C.U.I.T.">
			</div>
		</td>
		<td>
			<select name="banco_ids[]" id="banco_ids" data-placeholder="Banco" class="form-control banco" data-fouc>
				<option value=""></option>
				@foreach($banco_query as $key => $value)
					<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
				@endforeach
			</select>
		</td>
		<td>
			<select name="mediopago_ids[]" id="mediopago_ids" data-placeholder="Medio de pago" class="form-control mediopago" data-fouc>
				<option value=""></option>
				@foreach($mediopago_query as $key => $value)
					<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
				@endforeach
			</select>
		</td>
		<td>
			<div class="form-group">
				<input type="text" name="emails[]" value="" class="form-control emails" placeholder="Email">
			</div>
		</td>
    	<td>
			<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_formapago tooltipsC">
    			<i class="fa fa-times-circle text-danger"></i>
			</button>
    	</td>
	</tr>
</template>
