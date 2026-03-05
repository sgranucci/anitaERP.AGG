<div class="form-group row">
	<label for="tipochequera" class="col-lg-3 col-form-label requerido">Tipo de chequera</label>
	<select id="tipochequera" name="tipochequera" class="col-lg-4 form-control" required>
    	<option value="">-- Elija tipo de chequera --</option>
       	@foreach($tipochequera_enum as $tipochequera)
			@if ($tipochequera['valor'] == old('tipochequera',$data->tipochequera??''))
       			<option value="{{ $tipochequera['valor'] }}" selected>{{ $tipochequera['nombre'] }}</option>    
			@else
			    <option value="{{ $tipochequera['valor'] }}">{{ $tipochequera['nombre'] }}</option>
			@endif
    	@endforeach
	</select>
</div>
<div class="form-group row">
	<label for="tipocheque" class="col-lg-3 col-form-label requerido">Tipo de cheque</label>
	<select id="tipocheque" name="tipocheque" class="col-lg-4 form-control" required>
    	<option value="">-- Elija tipo de cheque --</option>
       	@foreach($tipocheque_enum as $tipocheque)
			@if ($tipocheque['valor'] == old('tipocheque',$data->tipocheque??''))
       			<option value="{{ $tipocheque['valor'] }}" selected>{{ $tipocheque['nombre'] }}</option>    
			@else
			    <option value="{{ $tipocheque['valor'] }}">{{ $tipocheque['nombre'] }}</option>
			@endif
    	@endforeach
	</select>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Código chequera</label>
    <div class="col-lg-2">
    <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
	<label for="Cuenta de caja" class="col-lg-3 col-form-label">Cuenta de caja</label>
	<select name="cuentacaja_id" id="cuentacaja_id" data-placeholder="Cuenta de caja" class="col-lg-3 form-control" data-fouc>
		<option value="">-- Seleccionar cuenta de caja --</option>
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
    <label for="desdenumerocheque" class="col-lg-3 col-form-label requerido">Desde nro. de cheque</label>
    <div class="col-lg-2">
    <input type="text" name="desdenumerocheque" id="desdenumerocheque" class="form-control" value="{{old('desdenumerocheque', $data->desdenumerocheque ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="hastanumerocheque" class="col-lg-3 col-form-label requerido">Desde nro. de cheque</label>
    <div class="col-lg-2">
    <input type="text" name="hastanumerocheque" id="hastanumerocheque" class="form-control" value="{{old('hastanumerocheque', $data->hastanumerocheque ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="fechauso" class="col-lg-3 col-form-label requerido">Fecha de uso</label>
    <div class="col-lg-2">
    <input type="text" name="fechauso" id="fechauso" class="form-control" value="{{old('fechauso', $data->fechauso ?? '')}}" readonly/>
    </div>
</div>
<div class="form-group row">
	<label for="estado" class="col-lg-3 col-form-label requerido">Estado</label>
	<select id="estado" name="estado" class="col-lg-4 form-control" required>
    	<option value="">-- Elija estado --</option>
       	@foreach($estado_enum as $estado)
			@if ($estado['valor'] == old('estado',$data->estado??'A'))
       			<option value="{{ $estado['valor'] }}" selected>{{ $estado['nombre'] }}</option>    
			@else
			    <option value="{{ $estado['valor'] }}">{{ $estado['nombre'] }}</option>
			@endif
    	@endforeach
	</select>
</div>
