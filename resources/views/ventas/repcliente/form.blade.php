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

<div class="form-group row" id="tiposuspensioncliente_id">
	<label for="tiposuspensioncliente_id" class="col-lg-3 col-form-label requerido">Tipo de suspensi&oacute;n</label>
   	<select name="tiposuspensioncliente_id" id="tiposuspensioncliente_id" data-placeholder="Tipo de material" class="col-lg-4 form-control required" data-fouc>
   		<option value="">-- Seleccionar tipo de material --</option>
       	@foreach($tiposuspensioncliente_query as $key => $value)
       		@if( $key == 'TODOS')
       			<option value="{{ $key }}" selected="select">{{ $value->nombre }}</option>    
       		@else
       			<option value="{{ $key }}">{{ $value->nombre }}</option>    
       		@endif
       	@endforeach
    </select>
</div>
