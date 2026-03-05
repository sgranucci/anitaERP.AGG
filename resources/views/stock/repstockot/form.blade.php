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
<div class="form-group row">
	<label for="deposito" class="col-lg-3 col-form-label requerido">Depósito</label>
	<select name="deposito_id" id="deposito_id" data-placeholder="Depósito de stock" class="col-lg-3 form-control required" data-fouc required>
		<option value="">-- Seleccionar depósito --</option>
		@foreach($deposito_query as $key => $value)
			@if( (int) $value->id == (int) 0)
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
	<label for="desdecategoria" class="col-lg-3 col-form-label ">Desde categor&iacute;a</label>
	<select name="desdecategoria_id" id="desdecategoria_id" data-placeholder="categoria" class="col-lg-4 form-control " data-fouc>
		<option value="">-- Seleccionar categor&iacute;a --</option>
		@foreach($categoria_query as $key => $value)
			@if( (int) $value->id == '0')
				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
			@else
			<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
			@endif
		@endforeach
	</select>
</div>
<div class="form-group row">
	<label for="hastacategoria" class="col-lg-3 col-form-label">Hasta categor&iacute;a</label>
	<select name="hastacategoria_id" id="hastacategoria_id" data-placeholder="categoria" class="col-lg-4 form-control" data-fouc>
		<option value="">-- Seleccionar categor&iacute;a --</option>
		@foreach($categoria_query as $key => $value)
		@if( (int) $value->id == '99999999')
				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
			@else
				<option value="{{ $value->id }}">{{ $value->nombre }}</option>   
			@endif 
		@endforeach
	</select>
</div>
<div class="form-group row">
    <label for="desdelote" class="col-lg-3 col-form-label">Desde lote</label>
    <div class="col-lg-2">
    <input type="text" name="desdelote" id="desdelote" class="form-control" value=""/>
    </div>
</div>
<div class="form-group row">
    <label for="hastalote" class="col-lg-3 col-form-label">Hasta lote</label>
    <div class="col-lg-2">
    <input type="text" name="hastalote" id="hastalote" class="form-control" value=""/>
    </div>
</div>
<div class="row">
	<div class="col-sm-6">
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
		<div class="form-group row">
			<label for="estadoOt" class="col-lg-3 col-form-label requerido">Estado de las OT</label>
			<select name="estadoOt" id="estadoOt" data-placeholder="Estado de las órdenes de trabajo" class="col-lg-4 form-control required" data-fouc required>
				<option value="">-- Seleccionar estado de las OT --</option>
				@foreach($estadoOt_enum as $key => $value)
					@if( $key == 'TODAS')
						<option value="{{ $key }}" selected="select">{{ $value }}</option>    
					@else
						<option value="{{ $key }}">{{ $value }}</option>    
					@endif
				@endforeach
			</select>
		</div>
	</div>
	<div class="col-sm-6">
		<div class="form-group row">
			<label for="imprimefoto" class="col-lg-3 col-form-label requerido">Imprime fotos</label>
			<select name="imprimefoto" id="imprimefoto" data-placeholder="Imprime fotos" class="col-lg-2 form-control required" data-fouc required>
				<option value="">-- Seleccionar si imprime las fotos --</option>
				@foreach($foto_enum as $key => $value)
					@if( $key == 'SIN_FOTO')
						<option value="{{ $key }}" selected="select">{{ $value }}</option>    
					@else
						<option value="{{ $key }}">{{ $value }}</option>    
					@endif
				@endforeach
			</select>
		</div>
		<div class="form-group row">
			<label for="apertura" class="col-lg-3 col-form-label requerido">Apertura</label>
			<select name="apertura" id="apertura" data-placeholder="Imprime fotos" class="col-lg-3 form-control required" data-fouc required>
				<option value="">-- Seleccionar si totaliza o abre por movimientos --</option>
				@foreach($apertura_enum as $key => $value)
					@if( $key == 'TOTALIZADO')
						<option value="{{ $key }}" selected="select">{{ $value }}</option>    
					@else
						<option value="{{ $key }}">{{ $value }}</option>    
					@endif
				@endforeach
			</select>
		</div>
	</div>
</div>
