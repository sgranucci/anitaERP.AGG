<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">Código</label>
    <div class="col-lg-2">
    <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
	<label for="tipocuenta" class="col-lg-3 col-form-label requerido">Tipo de cuenta</label>
	<div class="col-lg-4">
		<select id="tipocuenta" name="tipocuenta" class="form-control" required>
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
</div>
<div class="form-group row">
	<label for="banco_id" class="col-lg-3 col-form-label">Banco</label>
	<div class="col-lg-8">
		<select name="banco_id" id="banco_id" data-placeholder="Banco" class="form-control" data-fouc>
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
</div>
<div class="form-group row">
	<label for="empresa_id" class="col-lg-3 col-form-label">Empresa</label>
	<div class="col-lg-8">
		<select name="empresa_id" id="empresa_id" data-placeholder="Empresa" class="form-control" data-fouc>
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
</div>
<div class="form-group row" id="cuenta">
	<label for="cuentacontable_id" class="col-lg-3 col-form-label requerido">Cuenta contable</label>
	<input type="hidden" class="cuentacontable_id" id="cuentacontable_id" name="cuentacontable_id" value="{{ old('cuentacontable_id', $data->cuentacontable_id ?? '') }}">
	<div class="col-lg-2">
		<input type="text" class="codigocuentacontable form-control" id="codigocuentacontable" name="codigocuentacontable" value="{{ old('codigocuentacontable', $data->cuentacontables->codigo ?? '') }}">
	</div>
	<div class="col-lg-1">
		<button type="button" title="Consulta cuentas" class="btn-accion-tabla consultacuentacontable tooltipsC">
			<i class="fa fa-search text-primary"></i>
		</button>
	</div>
	<div class="col-lg-5">
		<input type="text" class="nombrecuentacontable form-control" id="nombrecuentacontable" name="nombrecuentacontable" value="{{ old('nombrecuentacontable', $data->cuentacontables->nombre ?? '') }}">
	</div>
</div>
<div class="form-group row">
	<label for="moneda_id" class="col-lg-3 col-form-label">Moneda</label>
	<div class="col-lg-4">
		<select name="moneda_id" id="moneda_id" data-placeholder="Moneda" class="form-control" data-fouc>
			<option value=""></option>
			@foreach($moneda_query as $key => $value)
				@if( (int) $value->id == (int) old('moneda_id', $data->moneda_id ?? ''))
					<option value="{{ $value->id }}" selected="select">{{ $value->abreviatura }}</option>
				@else
					<option value="{{ $value->id }}">{{ $value->abreviatura }}</option>
				@endif
			@endforeach
		</select>
	</div>
</div>
<div class="form-group row">
    <label for="cbu" id="cbu_label" class="col-lg-3 col-form-label">Nro. de CBU</label>
    <div class="col-lg-8">
    <input type="text" name="cbu" id="cbu" class="form-control" value="{{old('cbu', $data->cbu ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="cuenta_interbanking" class="col-lg-3 col-form-label">Cuenta Interbanking</label>
    <div class="col-lg-8">
    <input type="text" name="cuenta_interbanking" id="cuenta_interbanking" class="form-control" value="{{old('cuenta_interbanking', $data->cuenta_interbanking ?? '')}}" maxlength="255"/>
    </div>
</div>
<div class="form-group row">
	<label for="usocuentacaja_ids" class="col-lg-3 col-form-label">Usos</label>
	<div class="col-lg-8">
		<select name="usocuentacaja_ids[]" id="usocuentacaja_ids" data-placeholder="Usos de la cuenta de caja" class="form-control" data-fouc multiple>
			@php
				$selectedUsos = old('usocuentacaja_ids', ($data->exists ?? false) ? $data->usocuentacajas->pluck('id')->all() : []);
			@endphp
			@foreach($usocuentacaja_query as $value)
				@if(in_array((int) $value->id, array_map('intval', (array) $selectedUsos), true))
					<option value="{{ $value->id }}" selected="selected">{{ $value->nombre }}</option>
				@else
					<option value="{{ $value->id }}">{{ $value->nombre }}</option>
				@endif
			@endforeach
		</select>
	</div>
</div>
