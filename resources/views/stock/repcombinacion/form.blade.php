<div class="form-group row">
	<label for="mventa" class="col-lg-3 col-form-label requerido">Marca</label>
	<select name="mventa_id" id="mventa_id" data-placeholder="Marca de Venta" class="col-lg-3 form-control required" data-fouc required>
		<option value="">-- Seleccionar marca --</option>
		@foreach($mventa_query as $key => $value)
			@if( (int) $value->id == (int) old('mventa_id', $pedido->mventa_id ?? ''))
				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
			@else
				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
			@endif
		@endforeach
	</select>
</div>
<div class="form-group row">
	<label for="desdearticulo" class="col-lg-3 col-form-label ">Desde art&iacute;culo</label>
	<select name="desdearticulo_id" id="desdearticulo_id" data-placeholder="articulo" class="col-lg-4 form-control " data-fouc>
		<option value="">-- Seleccionar art&iacute;culo --</option>
		@foreach($articulo_query as $key => $value)
			@if( (int) $value->id == '0')
				<option value="{{ $value->id }}" selected="select">{{ $value->descripcion }}</option>    
			@else
			<option value="{{ $value->id }}">{{ $value->descripcion }}</option>    
			@endif
		@endforeach
	</select>
</div>
<div class="form-group row">
	<label for="hastaarticulo" class="col-lg-3 col-form-label ">Hasta art&iacute;culo</label>
	<select name="hastaarticulo_id" id="hastaarticulo_id" data-placeholder="articulo" class="col-lg-4 form-control " data-fouc>
		<option value="">-- Seleccionar art&iacute;culo --</option>
		@foreach($articulo_query as $key => $value)
		@if( (int) $value->id == '99999999')
				<option value="{{ $value->id }}" selected="select">{{ $value->descripcion }}</option>    
			@else
				<option value="{{ $value->id }}">{{ $value->descripcion }}</option>   
			@endif 
		@endforeach
	</select>
</div>
<div class="form-group row">
	<label for="desdelinea" class="col-lg-3 col-form-label ">Desde l&iacute;nea</label>
	<select name="desdelinea_id" id="desdelinea_id" data-placeholder="linea" class="col-lg-4 form-control " data-fouc>
		<option value="">-- Seleccionar l&iacute;nea --</option>
		@foreach($linea_query as $key => $value)
			@if( (int) $value->id == '0')
				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
			@else
			<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
			@endif
		@endforeach
	</select>
</div>
<div class="form-group row">
	<label for="hastalinea" class="col-lg-3 col-form-label">Hasta l&iacute;nea</label>
	<select name="hastalinea_id" id="hastalinea_id" data-placeholder="linea" class="col-lg-4 form-control" data-fouc>
		<option value="">-- Seleccionar l&iacute;nea --</option>
		@foreach($linea_query as $key => $value)
		@if( (int) $value->id == '99999999')
				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
			@else
				<option value="{{ $value->id }}">{{ $value->nombre }}</option>   
			@endif 
		@endforeach
	</select>
</div>
<div class="form-group row">
	<label for="estado" class="col-lg-3 col-form-label requerido">Estado de las combinaciones</label>
   	<select name="estado" id="estado" data-placeholder="Estado de las combinaciones" class="col-lg-4 form-control required" data-fouc required>
   		<option value="">-- Seleccionar estado de las combinaciones --</option>
       	@foreach($estado_enum as $key => $value)
       		@if( $key == 'ACTIVAS')
       			<option value="{{ $key }}" selected="select">{{ $value }}</option>    
       		@else
       			<option value="{{ $key }}">{{ $value }}</option>    
       		@endif
       	@endforeach
    </select>
</div>

