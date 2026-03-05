<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Código</label>
    <div class="col-lg-2">
    <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
	<label for="cuentacaja" class="col-lg-3 col-form-label">Cuenta de Caja</label>
	<select name="cuentacaja_id" id="cuentacaja_id" data-placeholder="Cuenta de Caja" class="col-lg-3 form-control" data-fouc required>
		<option value="">-- Seleccionar Cuenta de Caja --</option>
		@foreach($cuentacaja_query as $key => $value)
			@if( (int) $value->id == (int) old('cuentacaja_id', $data->cuentacaja_id ?? ''))
				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
			@else
				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
			@endif
		@endforeach
	</select>
</div>
<div class="form-group row">
	<label for="Empresa" class="col-lg-3 col-form-label">Empresa</label>
	<select name="empresa_id" id="empresa_id" data-placeholder="Empresa" class="col-lg-3 form-control" data-fouc>
		<option value="">-- Para todas las empresas --</option>
		@foreach($empresa_query as $key => $value)
			@if( (int) $value->id == (int) old('empresa_id', $data->empresa_id ?? ''))
				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
			@else
				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
			@endif
		@endforeach
	</select>
</div>
