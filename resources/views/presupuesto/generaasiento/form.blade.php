@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $data->empresa_id ?? null,
    'mostrar_id' => true,
    'col_input' => 'col-lg-7',
])
<div class="form-group row">
	<label for="presupuesto" class="col-lg-3 col-form-label">Presupuesto</label>
	<select name="presupuesto_id" id="presupuesto_id" data-placeholder="Presupuesto" class="col-lg-7 form-control required" data-fouc required>
		@foreach($presupuesto_query as $key => $value)
			@if( (int) $value->id == (int) old('presupuesto_id', $data->presupuesto_id ?? ''))
				<option value="{{ $value->id }}" selected="select">{{ $value->id }} {{ $value->nombre }}</option>    
			@else
				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
			@endif
		@endforeach
	</select>
</div>        
<div class="form-group row">
	<label for="presupuesto_escenario" class="col-lg-3 col-form-label">Escenario</label>
	<select name="presupuesto_escenario_id" id="presupuesto_escenario_id" data-placeholder="Escenario" class="col-lg-7 form-control required" data-fouc required>
	</select>
</div>         