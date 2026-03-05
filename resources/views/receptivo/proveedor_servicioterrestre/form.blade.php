<div class="form-group row">
		<label for="proveedor" class="col-lg-3 col-form-label">Proveedor</label>
		<input type="text" class="col-lg-2 proveedor_id" id="proveedor_id" name="proveedor_id" value="{{$proveedor_servicioterrestre->proveedor_id ?? ''}}" >
		<input type="text" class="col-lg-5 proveedor" id="proveedor" name="proveedor" value="{{$proveedor_servicioterrestre->proveedores->nombre ?? ''}}" readonly>
		<button type="button" title="Consulta proveedores" style="padding:1;" class="btn-accion-tabla consultaproveedor tooltipsC">
			<i class="fa fa-search text-primary"></i>
		</button>
		<input type="hidden" name="nombreproveedor" id="nombreproveedor" class="form-control" value="{{old('nombreproveedor', $proveedor_servicioterrestre->proveedores->nombre ?? '')}}">
</div>
<div class="form-group row">
		<label for="servicioterrestre" class="col-lg-3 col-form-label">Servicio</label>
		<input type="text" class="col-lg-2 codigoservicioterrestre" id="codigoservicioterrestre" name="codigoservicioterrestre" value="{{$proveedor_servicioterrestre->servicioterrestres->codigo ?? ''}}" >
		<input type="text" class="col-lg-5 servicioterrestre" id="servicioterrestre" name="servicioterrestre" value="{{$proveedor_servicioterrestre->servicioterrestres->nombre ?? ''}}" readonly>
		<button type="button" title="Consulta servicios" style="padding:1;" class="btn-accion-tabla consultaservicioterrestre tooltipsC">
			<i class="fa fa-search text-primary"></i>
		</button>
		<input type="hidden" class="servicioterrestre_id" id="servicioterrestre_id" name="servicioterrestre_id" value="{{$proveedor_servicioterrestre->servicioterrestre_id ?? ''}}" >
		<input type="hidden" name="nombreservicioterrestre" id="nombreservicioterrestre" class="form-control" value="{{old('nombreservicioterrestre', $proveedor_servicioterrestre->servicioterrestres->nombre ?? '')}}">
</div>
<div class="form-group row">
	<label for="costo" class="col-lg-3 col-form-label">Costo del servicio</label>
	<div class="col-lg-3">
	<input type="number" name="costo" id="costo" class="form-control" value="{{old('costo', $proveedor_servicioterrestre->costo ?? '0')}}" required>
	</div>
</div>
<div class="form-group row">
	<label for="moneda" class="col-lg-3 col-form-label">Moneda</label>
	<select name="moneda_id" id="moneda_id" data-placeholder="Moneda del costo" class="col-lg-4 form-control required" data-fouc required>
		<option value="">-- Seleccionar --</option>
		@foreach($moneda_query as $key => $value)
			@if( (int) $value->id == (int) old('moneda_id', $proveedor_servicioterrestre->moneda_id ?? session('moneda_id')))
				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
			@else
				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
			@endif
		@endforeach
	</select>
</div>
@include('includes.compras.modalconsultaproveedor')
@include('includes.receptivo.modalconsultaservicioterrestre')
