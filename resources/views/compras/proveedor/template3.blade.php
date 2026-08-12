<template id="template-renglon-formapago">
	<tr class="item-formapago">
		<td>
			<input type="text" name="formapagos[]" class="form-control iiformapago" readonly value="1" />
		</td>
		<td>
			<input type="text" name="nombres[]" class="form-control fp-nombre fp-requerido" value="" data-fp-label="Nombre" />
		</td>
		<td>
			<select name="formapago_ids[]" data-placeholder="Forma de pago" class="form-control formapago fp-formapago fp-requerido" data-fouc data-fp-label="Forma de pago">
				<option value=""></option>
				@foreach($formapago_query as $key => $value)
					<option value="{{ $value->id }}" data-abreviatura="{{ $value->abreviatura }}">{{ $value->nombre }}</option>
				@endforeach
			</select>
		</td>
		<td>
			<div class="form-group">
				<input type="text" name="cbus[]" value="" class="form-control cbus fp-cbu" placeholder="CBU">
			</div>
		</td>
		<td>
			<div class="form-group">
				<input type="text" name="alias_cbus[]" value="" class="form-control alias_cbus fp-alias-cbu" placeholder="Alias" maxlength="80">
			</div>
		</td>
		<td>
			<select name="tipocuentacaja_ids[]" data-placeholder="Tipo de cuenta de caja" class="form-control tipocuentacaja fp-tipocuentacaja" data-fouc data-fp-label="TC (tipo de cuenta)">
				<option value=""></option>
				@foreach($tipocuentacaja_query as $key => $value)
					<option value="{{ $value->id }}">{{ $value->abreviatura }}</option>
				@endforeach
			</select>
		</td>
		<td>
			<select name="moneda_ids[]" data-placeholder="Moneda" class="form-control moneda fp-moneda fp-requerido" data-fouc data-fp-label="Moneda">
				<option value=""></option>
				@foreach($moneda_query as $key => $value)
					<option value="{{ $value->id }}">{{ $value->abreviatura }}</option>
				@endforeach
			</select>
		</td>
		<td>
			<div class="form-group">
				<input type="text" name="numerocuentas[]" value="" class="form-control numerocuentas fp-numerocuenta" placeholder="Nro.Cuenta">
			</div>
		</td>
		<td>
			<div class="form-group">
				<input type="text" name="nroinscripciones[]" value="" class="form-control nroinscripciones fp-nroinscripcion" placeholder="XX-XXXXXXXX-X" maxlength="13" oninput="formatarCUIT(this)">
			</div>
		</td>
		<td>
			<select name="banco_ids[]" data-placeholder="Banco" class="form-control banco fp-banco" data-fouc>
				<option value=""></option>
				@foreach($banco_query as $key => $value)
					<option value="{{ $value->id }}">{{ $value->nombre }}</option>
				@endforeach
			</select>
		</td>
		<td>
			<select name="mediopago_ids[]" data-placeholder="Medio de pago" class="form-control mediopago fp-mediopago" data-fouc>
				<option value=""></option>
				@foreach($mediopago_query as $key => $value)
					<option value="{{ $value->id }}">{{ $value->nombre }}</option>
				@endforeach
			</select>
		</td>
		<td>
			<div class="form-group">
				<input type="text" name="emails[]" value="" class="form-control emails fp-email" placeholder="Email">
			</div>
		</td>
    	<td>
			<button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_formapago tooltipsC">
    			<i class="fa fa-times-circle text-danger"></i>
			</button>
    	</td>
	</tr>
</template>
