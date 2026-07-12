@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'mostrar_id' => true,
    'col_input' => 'col-lg-4',
])
<div class="form-group row">
	<label for="impuesto" class="col-lg-3 col-form-label">Impuesto</label>
	<select name="impuesto" id="impuesto" data-placeholder="Impuesto" class="col-lg-4 form-control required" data-fouc required>
		@foreach($impuesto_query as $key => $value)
			<option value="{{ $value->impuesto }}">{{ $value->impuesto }} {{ $value->descripcionimpuesto }}</option>    
		@endforeach
	</select>
</div>  
<div class="form-group row">
	<label for="regimen" class="col-lg-3 col-form-label">Régimen</label>
	<select name="regimen" id="regimen" data-placeholder="Régimen" class="col-lg-4 form-control required" data-fouc required>
		@foreach($regimen_query as $key => $value)
			<option value="{{ $value->regimen }}">{{ $value->regimen }} {{ $value->descripcionregimen }}</option>    
		@endforeach
	</select>
</div>  
<div class="form-group row">
	<label for="desdefecha" class="col-lg-3 col-form-label requerido">Desde Fecha de Retención</label>
	<div class="col-lg-3">
	<input type="date" name="desdefecha" id="desdefecha" class="form-control" value="{{old('fecha', \Carbon\Carbon::now()->format('Y-m-d'))}}"/>
	</div>
</div>
<div class="form-group row">
	<label for="hastafecha" class="col-lg-3 col-form-label requerido">Hasta Fecha de Retención</label>
	<div class="col-lg-3">
	<input type="date" name="hastafecha" id="hastafecha" class="form-control" value="{{old('fecha', \Carbon\Carbon::now()->format('Y-m-d'))}}"/>
	</div>
</div>


