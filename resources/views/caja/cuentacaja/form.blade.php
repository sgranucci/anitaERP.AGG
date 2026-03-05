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
	<label for="tipocuenta" class="col-lg-3 col-form-label requerido">Tipo de cuenta</label>
	<select id="tipocuenta" name="tipocuenta" class="col-lg-4 form-control" required>
    	<option value="">-- Elija tipo de cuenta --</option>
       	@foreach($tipocuenta_enum as $tipocuenta)
			@if ($tipocuenta['valor'] == old('tipocuenta',$data->tipocuenta??''))
       			<option value="{{ $tipocuenta['valor'] }}" selected>{{ $tipocuenta['nombre'] }}</option>    
			@else
			    <option value="{{ $tipocuenta['valor'] }}">{{ $tipocuenta['nombre'] }}</option>
			@endif
    	@endforeach
	</select>
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
<div class="form-group row">
	<label for="cuentacontable" class="col-lg-3 col-form-label">Cuenta contable</label>
	<select name="cuentacontable_id" id="cuentacontable_id" data-placeholder="Cuenta contable para imputaciones" class="col-lg-3 form-control" data-fouc>
		<option value="">-- Seleccionar Cta. Contable --</option>
		@foreach($cuentacontable_query as $key => $value)
			@if( (int) $value->id == (int) old('cuentacontable_id', $data->cuentacontable_id ?? ''))
				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
			@else
				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
			@endif
		@endforeach
	</select>
</div>
<div class="form-group row">
	<label for="moneda" class="col-lg-3 col-form-label">Moneda</label>
	<select name="moneda_id" id="moneda_id" data-placeholder="Moneda" class="col-lg-3 form-control" data-fouc>
		<option value=""></option>
		@foreach($moneda_query as $key => $value)
			@if( (int) $value->id == (int) old('moneda_ids', $data->moneda_id ?? ''))
				<option value="{{ $value->id }}" selected="select">{{ $value->abreviatura }}</option>    
			@else
				<option value="{{ $value->id }}">{{ $value->abreviatura }}</option>    
			@endif
		@endforeach
	</select>
</div>
<div class="form-group row">
    <label for="cbu" class="col-lg-3 col-form-label">Nro. de CBU</label>
    <div class="col-lg-3">
    <input type="text" name="cbu" id="cbu" class="form-control" value="{{old('cbu', $data->cbu ?? '')}}"/>
    </div>
</div>
