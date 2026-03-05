<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-4">
       <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="abreviatura" class="col-lg-3 col-form-label requerido">Abreviatura</label>
    <div class="col-lg-2">
       <input type="text" name="abreviatura" id="abreviatura" class="form-control" value="{{old('abreviatura', $data->abreviatura ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="codigoexterno" class="col-lg-3 col-form-label requerido">Código externo</label>
    <div class="col-lg-2">
       <input type="text" name="codigoexterno" id="codigoexterno" class="form-control" value="{{old('codigoexterno', $data->codigoexterno ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
	<label for="banco" class="col-lg-3 col-form-label">Banco</label>
	<select name="banco_id" id="banco_id" data-placeholder="Banco" class="col-lg-3 form-control" data-fouc>
		<option value="">-- Seleccionar Banco --</option>
		@foreach($banco_query as $key => $value)
			@if( (int) $value->id == (int) old('banco_id', $data->banco_id ?? ''))
				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
			@else
				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
			@endif
		@endforeach
	</select>
</div>
