<div class="form-group row">
	<label for="hastafecha" class="col-lg-3 col-form-label requerido">Hasta fecha</label>
	<div class="col-lg-4">
		<input type="date" name="hastafecha" id="hastafecha" class="form-control" value="" required/>
	</div>
</div>
<div class="form-group row">
	<label for="motivocierrepedido" class="col-lg-3 col-form-label requerido">Motivo de cierre</label>
	<select name="motivocierrepedido_id" id="motivocierrepedido_id" data-placeholder="Motivo de cierre" class="col-lg-8 form-control required" data-fouc>
		<option value="">-- Seleccionar motivo de cierre  --</option>
		@foreach($motivocierrepedido_query as $key => $value)
			@if( (int) $value->id == (int) old('motivocierrepedido_id', $pedido->motivocierrepedido_id ?? ''))
				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
			@else
				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
			@endif
		@endforeach
	</select>
</div>
