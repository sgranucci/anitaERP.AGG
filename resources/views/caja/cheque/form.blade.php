<div class="form-group row">
	<label for="Empresa" class="col-lg-3 col-form-label">Empresa</label>
	<select name="empresa_id" id="empresa_id" data-placeholder="Empresa" class="col-lg-3 form-control" data-fouc>
		<option value="">-- Seleccionar empresa --</option>
		@foreach($empresa_query as $key => $value)
			@if( (int) $value->id == (int) old('empresa_id', $data->empresa_id ?? session('empresa_id')))
				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
			@else
				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
			@endif
		@endforeach
	</select>
</div>
<div class="form-group row" id="cuenta">
	<input type="hidden" class="cuentacaja_id" name="cuentacaja_id" value="{{$data->cuentacaja_id ?? ''}}" >
	<input type="hidden" class="cuentacaja_id_previa" name="cuentacaja_id_previa[]" value="{{$data->cuentacaja_id ?? ''}}" >
	<button type="button" title="Consulta cuentas" style="padding:1;" class="btn-accion-tabla consultacuentacaja tooltipsC">
			<i class="fa fa-search text-primary"></i>
	</button>
	<input type="text" style="WIDTH: 100px;HEIGHT: 38px" class="codigo form-control" name="codigos[]" value="{{$data->cuentacajas->codigo ?? ''}}" >
	<input type="hidden" class="codigo_previo" name="codigo_previo" value="{{$data->cuentacajas->codigo ?? ''}}" >
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="nombre form-control" value="{{old('nombre', $data->cuentacajas->nombre ?? '')}}" readonly/>
    </div>
</div>
<div class="form-group row">
	<label for="chequera" class="col-lg-3 col-form-label">Chequera</label>
	<select name="chequera_id" id="bachequera_idnco_id" data-placeholder="Chequera" class="col-lg-3 form-control" data-fouc>
		<option value="">-- Seleccionar chequera --</option>
		@foreach($chequera_query as $key => $value)
			@if( (int) $value->id == (int) old('chequera_id', $data->chequera_id ?? session('chequera_id')))
				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
			@else
				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
			@endif
		@endforeach
	</select>
</div>
<div class="form-group row">
    <label for="disponible" class="col-lg-3 col-form-label requerido">Cheques disponibles</label>
    <div class="col-lg-8">
    	<input type="text" name="disponible" id="disponible" class="form-control" value="{{old('disponible', $disponible)}}" readonly/>
    </div>
</div>
<div class="col-sm-6">
	<div class="form-group row">
		<label for="fecha" class="col-lg-3 col-form-label">Fecha pago</label>
		<div class="col-lg-3">
			<input type="date" name="fechapago" id="fechapago" class="form-control" value="{{old('fecha', $data->fechapago ?? date('Y-m-d'))}}">
		</div>
	</div>
</div>
<div class="form-group row">
	<label for="caracter" class="col-lg-3 col-form-label requerido">Caracter</label>
	<select id="caracter" name="caracter" class="col-lg-4 form-control" required>
    	<option value="">-- Elija carácter del cheque --</option>
       	@foreach($caracter_enum as $caracter)
			@if ($caracter['valor'] == old('caracter',$data->caracter??''))
       			<option value="{{ $caracter['valor'] }}" selected>{{ $caracter['nombre'] }}</option>    
			@else
			    <option value="{{ $caracter['valor'] }}">{{ $caracter['nombre'] }}</option>
			@endif
    	@endforeach
	</select>
</div>
<div class="form-group row">
	<label for="monto" class="col-lg-6 col-form-label">Monto</label>
	<div class="col-lg-6">
		<input type="number" name="monto" id="monto" class="form-control" value="{{old('monto', $data->monto ?? '0')}}">
	</div>
</div>
<div class="form-group row">
	<label for="moneda" class="col-lg-3 col-form-label">Moneda</label>
	<input type="text" name="moneda_id" id="moneda_id" data-placeholder="Moneda" class="col-lg-3 form-control" readonly>
	<input type="text" name="nombremoneda" id="nombremoneda" class="form-control" value="{{old('nombremoneda', $data->monedas->nombre ?? '')}}"  readonly>
</div>
<h4>Datos Beneficiario</h4>
<div class="form-group row">
	<label for="proveedor" class="col-lg-2 col-form-label">Proveedor</label>
	<input type="text" class="col-lg-9 proveedor" id="proveedor" name="proveedor" value="{{$data->proveedores->nombre ?? ''}}" readonly>
	<button type="button" title="Consulta proveedores" style="padding:1;" class="btn-accion-tabla consultaproveedor tooltipsC">
		<i class="fa fa-search text-primary"></i>
	</button>
	<input type="hidden" class="proveedor_id" id="proveedor_id" name="proveedor_id" value="{{$data->proveedor_id ?? ''}}" >
	<input type="hidden" name="nombreproveedor" id="nombreproveedor" class="form-control" value="{{old('nombreproveedor', $data->proveedores->nombre ?? '')}}">
</div>
<div class="form-group row">
	<label for="tipodocumento" class="col-lg-3 col-form-label requerido">Tipo de documento</label>
	<select id="tipodocumento_id" name="tipodocumento_id" class="col-lg-4 form-control">
    	<option value="">-- Elija tipo de documento del beneficiario --</option>
       	@foreach($tipodocumento_enum as $tipodocumento)
			@if ($tipodocumento['valor'] == old('tipodocumento_id',$data->tipodocumento_id??''))
       			<option value="{{ $tipodocumento['valor'] }}" selected>{{ $tipodocumento['nombre'] }}</option>    
			@else
			    <option value="{{ $tipodocumento['valor'] }}">{{ $tipodocumento['nombre'] }}</option>
			@endif
    	@endforeach
	</select>
</div>
<div class="form-group row">
	<label for="numerodocumento" class="col-lg-6 col-form-label">Nro. de documento</label>
	<div class="col-lg-6">
		<input type="number" name="numerodocumento" id="numerodocumento" class="form-control" value="{{old('numerodocumento', $data->numerodocumento ?? '')}}">
	</div>
</div>
<div class="form-group row">
	<label for="entregado" class="col-lg-6 col-form-label">Entregado</label>
	<div class="col-lg-6">
		<input type="text" name="entregado" id="entregado" class="form-control" value="{{old('entregado', $data->entregado ?? '')}}">
	</div>
</div>
<div class="form-group row">
	<label for="anombrede" class="col-lg-6 col-form-label">Cheque a nombre de</label>
	<div class="col-lg-6">
		<input type="text" name="anombrede" id="anombrede" class="form-control" value="{{old('anombrede', $data->anombrede ?? '')}}">
	</div>
</div>
<h4>Transacción de caja</h4>
<div class="form-group row">
	<label for="tipotransaccioncaja" class="col-lg-3 col-form-label">Tipo de transacción</label>
	<input type="text" name="tipotransaccioncaja" id="tipotransaccioncaja" class="form-control" value="{{old('tipotransaccioncaja', $data->caja_movimientos->tipotransaccion_cajas->nombre ?? '')}}"  readonly>
</div>
<div class="form-group row">
	<label for="numerotransaccion" class="col-lg-3 col-form-label">Número</label>
	<input type="text" name="caja_movimiento_id" id="caja_movimiento_id" class="form-control" value="{{old('caja_movimiento_id', $data->caja_movimiento_id ?? '')}}"  readonly>
</div>
<div class="form-group row">
	<label for="fechatransaccioncaja" class="col-lg-3 col-form-label">Fecha</label>
	<input type="date" name="fechatransaccioncaja" id="fechatransaccioncaja" class="form-control" value="{{old('fechatransaccioncaja', $data->caja_movimientos->fecha ?? '')}}"  readonly>
</div>