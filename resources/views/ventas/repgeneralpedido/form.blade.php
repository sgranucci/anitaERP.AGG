<div class="form-group row">
	<label for="tipolistado" class="col-lg-3 col-form-label requerido">Tipo de listado</label>
   	<select name="tipolistado" id="tipolistado" data-placeholder="Tipo de listado" class="col-lg-4 form-control required" data-fouc>
   		<option value="">-- Seleccionar tipo de listado --</option>
       	@foreach($tipolistado_enum as $key => $value)
       		@if( $key == 'CLIENTE')
       			<option value="{{ $key }}" selected="select">{{ $value }}</option>    
       		@else
       			<option value="{{ $key }}">{{ $value }}</option>    
       		@endif
       	@endforeach
    </select>
</div>
<div class="form-group row">
	<label for="tipolistado" class="col-lg-3 col-form-label requerido">Estado del pedido</label>
   	<select name="estado" id="estado" data-placeholder="Estado del pedido" class="col-lg-4 form-control required" data-fouc>
   		<option value="">-- Seleccionar estado del pedido --</option>
       	@foreach($estado_enum as $key => $value)
       		@if( $key == 'TODOS')
       			<option value="{{ $key }}" selected="select">{{ $value }}</option>    
       		@else
       			<option value="{{ $key }}">{{ $value }}</option>    
       		@endif
       	@endforeach
    </select>
</div>
<div class="form-group row">
	<label for="desdefecha" class="col-lg-3 col-form-label requerido">Desde fecha</label>
	<div class="col-lg-4">
		<input type="date" name="desdefecha" id="desdefecha" class="form-control" value="{{substr(old('desdefecha', date('Y-m-d')),0,10)}}" required/>
	</div>
</div>
<div class="form-group row">
	<label for="hastafecha" class="col-lg-3 col-form-label requerido">Hasta fecha</label>
	<div class="col-lg-4">
		<input type="date" name="hastafecha" id="hastafecha" class="form-control" value="{{substr(old('hastafecha', date('Y-m-d')),0,10)}}" required/>
	</div>
</div>
<div class="form-group row">
	<label for="mventa" class="col-lg-3 col-form-label requerido">Marca</label>
	<select name="mventa_id" id="mventa_id" data-placeholder="Marca de Venta" class="col-lg-3 form-control required" data-fouc required>
		<option value="">-- Seleccionar marca --</option>
		@foreach($mventa_query as $key => $value)
			@if( (int) $value->id == 0)
				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
			@else
				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
			@endif
		@endforeach
	</select>
</div>
<div class="row">
	<div class="col-sm-6">
		<div class="form-group row">
			<label for="desdevendedor" class="col-lg-3 col-form-label ">Desde vendedor</label>
			<select name="desdevendedor_id" id="desdevendedor_id" data-placeholder="Vendedor" class="col-lg-4 form-control " data-fouc>
				<option value="">-- Seleccionar vendedor --</option>
				@foreach($vendedor_query as $key => $value)
					@if( (int) $value->id == '0')
						<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
					@else
						<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
					@endif
				@endforeach
			</select>
		</div>
		<div class="form-group row">
			<label for="hastavendedor" class="col-lg-3 col-form-label ">Hasta vendedor</label>
			<select name="hastavendedor_id" id="hastavendedor_id" data-placeholder="Vendedor" class="col-lg-4 form-control " data-fouc>
				<option value="">-- Seleccionar vendedor --</option>
				@foreach($vendedor_query as $key => $value)
					@if( (int) $value->id == '99999999')
						<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
					@else
						<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
					@endif
				@endforeach
			</select>
		</div>

		<div class="form-group row">
			<label for="desdecliente" class="col-lg-3 col-form-label ">Desde cliente</label>
			<select name="desdecliente_id" id="desdecliente_id" data-placeholder="cliente" class="col-lg-4 form-control " data-fouc>
				<option value="">-- Seleccionar cliente --</option>
				@foreach($cliente_query as $key => $value)
					@if( (int) $value->id == '0')
						<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
					@else
					<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
					@endif
				@endforeach
			</select>
		</div>
		<div class="form-group row">
			<label for="hastacliente" class="col-lg-3 col-form-label ">Hasta cliente</label>
			<select name="hastacliente_id" id="hastacliente_id" data-placeholder="cliente" class="col-lg-4 form-control " data-fouc>
				<option value="">-- Seleccionar cliente --</option>
				@foreach($cliente_query as $key => $value)
				@if( (int) $value->id == '99999999')
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
	</div>

	<div class="col-sm-6">
		<div class="form-group row">
			<label for="desdelinea" class="col-lg-3 col-form-label ">Desde l&iacute;nea</label>
			<select name="desdelinea_id" id="desdelinea_id" data-placeholder="linea" class="col-lg-4 form-control " data-fouc>
				<option value="">-- Seleccionar l&iacute;nea --</option>
				@foreach($linea_query as $key => $value)
					@if( (int) $value->id == '0')
						<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
					@else
						<option value="{{ $value->id }}">{{ $value->nombre }}-{{$value->id}}</option>    
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
						<option value="{{ $value->id }}">{{ $value->nombre }}-{{$value->id}}</option>   
					@endif 
				@endforeach
			</select>
		</div>

		<div class="form-group row">
			<label for="desdefondo" class="col-lg-3 col-form-label ">Desde fondo</label>
			<select name="desdefondo_id" id="desdefondo_id" data-placeholder="fondo" class="col-lg-4 form-control " data-fouc>
				<option value="">-- Seleccionar fondo --</option>
				@foreach($fondo_query as $key => $value)
					@if( (int) $value->id == '0')
						<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
					@else
					<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
					@endif
				@endforeach
			</select>
		</div>
		<div class="form-group row">
			<label for="hastafondo" class="col-lg-3 col-form-label">Hasta fondo</label>
			<select name="hastafondo_id" id="hastafondo_id" data-placeholder="fondo" class="col-lg-4 form-control" data-fouc>
				<option value="">-- Seleccionar fondo --</option>
				@foreach($fondo_query as $key => $value)
				@if( (int) $value->id == '99999999')
						<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
					@else
						<option value="{{ $value->id }}">{{ $value->nombre }}</option>   
					@endif 
				@endforeach
			</select>
		</div>
	</div>
</div>