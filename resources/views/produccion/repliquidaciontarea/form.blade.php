<div class="form-group row">
	<label for="desdefecha" class="col-lg-3 col-form-label requerido">Desde fecha</label>
	<div class="col-lg-4">
		<input type="date" name="desdefecha" id="desdefecha" class="form-control" value="{{date('Y-m-01')}}" required/>
	</div>
</div>
<div class="form-group row">
	<label for="hastafecha" class="col-lg-3 col-form-label requerido">Hasta fecha</label>
	<div class="col-lg-4">
		<input type="date" name="hastafecha" id="hastafecha" class="form-control" value="{{date('Y-m-d')}}" required/>
	</div>
</div>
<div class="form-group row">
	<label for="desdecliente" class="col-lg-3 col-form-label ">Desde Cliente</label>
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
	<label for="hastacliente" class="col-lg-3 col-form-label ">Hasta Cliente</label>
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
	<label for="desdetarea" class="col-lg-3 col-form-label ">Desde Tarea</label>
   	<select name="desdetarea_id" id="desdetarea_id" data-placeholder="Tarea" class="col-lg-4 form-control " data-fouc>
   		<option value="">-- Seleccionar tarea --</option>
       	@foreach($tarea_query as $key => $value)
       		@if( (int) $value->id == '0')
       			<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
       		@else
			   <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
			@endif
       	@endforeach
    </select>
</div>
<div class="form-group row">
	<label for="hastatarea" class="col-lg-3 col-form-label ">Hasta Tarea</label>
   	<select name="hastatarea_id" id="hastatarea_id" data-placeholder="Tarea" class="col-lg-4 form-control " data-fouc>
   		<option value="">-- Seleccionar tarea --</option>
       	@foreach($tarea_query as $key => $value)
		   @if( (int) $value->id == '99999999')
       			<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
       		@else
       			<option value="{{ $value->id }}">{{ $value->nombre }}</option>   
			@endif 
       	@endforeach
    </select>
</div>
<div class="form-group row">
	<label for="desdeempleado" class="col-lg-3 col-form-label ">Desde Empleado</label>
   	<select name="desdeempleado_id" id="desdeempleado_id" data-placeholder="Empleado" class="col-lg-4 form-control " data-fouc>
   		<option value="">-- Seleccionar empleado --</option>
       	@foreach($empleado_query as $key => $value)
       		@if( (int) $value->id == '0')
       			<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
       		@else
			   <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
			@endif
       	@endforeach
    </select>
</div>
<div class="form-group row">
	<label for="hastaempleado" class="col-lg-3 col-form-label ">Hasta Empleado</label>
   	<select name="hastaempleado_id" id="hastaempleado_id" data-placeholder="Empleado" class="col-lg-4 form-control " data-fouc>
   		<option value="">-- Seleccionar empleado --</option>
       	@foreach($empleado_query as $key => $value)
		   @if( (int) $value->id == '99999999')
       			<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
       		@else
       			<option value="{{ $value->id }}">{{ $value->nombre }}</option>   
			@endif 
       	@endforeach
    </select>
</div>
<div class="form-group row">
	<label for="desdearticulo" class="col-lg-3 col-form-label ">Desde Art&iacute;culo</label>
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
	<label for="hastaarticulo" class="col-lg-3 col-form-label ">Hasta Art&iacute;culo</label>
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
	<label for="estadoot" class="col-lg-3 col-form-label requerido">Estado OT</label>
	<select name="estadoot" class="col-lg-3 form-control" required>
		<option value="">-- Elija estado de ot --</option>
		@foreach($estadoOt_enum as $value => $estadoot)
			@if($value == 'CUMPLIDA')
				<option value="{{ $value }}" selected="select">{{ $estadoot }}</option>    
			@else
				<option value="{{ $value }}">{{ $estadoot }}</option>    
			@endif
		@endforeach
	</select>
</div>
