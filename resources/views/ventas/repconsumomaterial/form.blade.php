<div class="form-group row">
	<label for="tipolistado" class="col-lg-3 col-form-label requerido">Tipo de listado</label>
   	<select name="tipolistado" id="tipolistado" data-placeholder="Tipo de listado" class="col-lg-4 form-control required" data-fouc required>
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
	<label for="estado" class="col-lg-3 col-form-label requerido">Estado del pedido</label>
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
<div class="form-group row" id="tipocapellada">
	<label for="tipocapellada" class="col-lg-3 col-form-label requerido">Tipo de material</label>
   	<select name="tipocapellada" id="tipocapellada" data-placeholder="Tipo de material" class="col-lg-4 form-control required" data-fouc>
   		<option value="">-- Seleccionar tipo de material --</option>
       	@foreach($tipocapellada_enum as $key => $value)
       		@if( $key == 'TODOS')
       			<option value="{{ $key }}" selected="select">{{ $value }}</option>    
       		@else
       			<option value="{{ $key }}">{{ $value }}</option>    
       		@endif
       	@endforeach
    </select>
</div>
<div class="form-group row" id="tipoavio">
	<label for="tipoavio" class="col-lg-3 col-form-label requerido">Tipo de av&iacute;o</label>
   	<select name="tipoavio" id="tipoavio" data-placeholder="Tipo de av&iacute;o" class="col-lg-4 form-control required" data-fouc>
   		<option value="">-- Seleccionar tipo de avio --</option>
       	@foreach($tipoavio_enum as $key => $value)
       		@if( $key == 'TODOS')
       			<option value="{{ $key }}" selected="select">{{ $value }}</option>    
       		@else
       			<option value="{{ $key }}">{{ $value }}</option>    
       		@endif
       	@endforeach
    </select>
</div>
<div class="row">
	<div class="col-sm-6">
		<div class="form-group row" id="desde-capellada">
			<label for="desdematerialcapellada" class="col-lg-4 col-form-label ">Desde material capellada</label>
			<select name="desdematerialcapellada_id" id="desdematerialcapellada_id" data-placeholder="Material de capellada" class="col-lg-4 form-control " data-fouc>
				<option value="">-- Seleccionar material --</option>
				@foreach($materialcapellada_query as $key => $value)
					@if( (int) $value->id == '0')
						<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
					@else
					<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
					@endif
				@endforeach
			</select>
		</div>
		<div class="form-group row" id="hasta-capellada">
			<label for="hastamaterialcapellada" class="col-lg-4 col-form-label">Hasta material capellada</label>
			<select name="hastamaterialcapellada_id" id="hastamaterialcapellada_id" data-placeholder="Material de capellada" class="col-lg-4 form-control" data-fouc>
				<option value="">-- Seleccionar material --</option>
				@foreach($materialcapellada_query as $key => $value)
				@if( (int) $value->id == '99999999')
						<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
					@else
						<option value="{{ $value->id }}">{{ $value->nombre }}</option>   
					@endif 
				@endforeach
			</select>
		</div>
		<div class="form-group row" id="desde-avio">
			<label for="desdematerialavio" class="col-lg-4 col-form-label ">Desde material avio</label>
			<select name="desdematerialavio_id" id="desdematerialavio_id" data-placeholder="Material de avio" class="col-lg-4 form-control " data-fouc>
				<option value="">-- Seleccionar material de avio --</option>
				@foreach($materialavio_query as $key => $value)
					@if( (int) $value->id == '0')
						<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
					@else
					<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
					@endif
				@endforeach
			</select>
		</div>
		<div class="form-group row" id="hasta-avio">
			<label for="hastamaterialavio" class="col-lg-4 col-form-label">Hasta material avio</label>
			<select name="hastamaterialavio_id" id="hastamaterialavio_id" data-placeholder="Material de avio" class="col-lg-4 form-control" data-fouc>
				<option value="">-- Seleccionar material de avio --</option>
				@foreach($materialavio_query as $key => $value)
				@if( (int) $value->id == '99999999')
						<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
					@else
						<option value="{{ $value->id }}">{{ $value->nombre }}</option>   
					@endif 
				@endforeach
			</select>
		</div>
		<div class="form-group row">
			<label for="desdecolor" class="col-lg-4 col-form-label ">Desde color</label>
			<select name="desdecolor_id" id="desdecolor_id" data-placeholder="color" class="col-lg-4 form-control " data-fouc>
				<option value="">-- Seleccionar color --</option>
				@foreach($color_query as $key => $value)
					@if( (int) $value->id == '0')
						<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
					@else
					<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
					@endif
				@endforeach
			</select>
		</div>
		<div class="form-group row">
			<label for="hastacolor" class="col-lg-4 col-form-label ">Hasta color</label>
			<select name="hastacolor_id" id="hastacolor_id" data-placeholder="color" class="col-lg-4 form-control " data-fouc>
				<option value="">-- Seleccionar color --</option>
				@foreach($color_query as $key => $value)
				@if( (int) $value->id == '99999999')
						<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
					@else
						<option value="{{ $value->id }}">{{ $value->nombre }}</option>   
					@endif 
				@endforeach
			</select>
		</div>
	</div>
	<div class="col-sm-6">
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
	</div>
</div>