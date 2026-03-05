<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-4">
       <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
	<label for="Area de destino" class="col-lg-3 col-form-label">Area de destino</label>
	<select name="areadestino_id" id="areadestino_id" data-placeholder="Area de destino" class="col-lg-3 form-control" required data-fouc>
		<option value="">-- Seleccionar area de destino --</option>
		@foreach($areadestino_query as $key => $value)
			@if( (int) $value->id == (int) old('areadestino_id', $data->areadestino_id ?? session('areadestino_id')))
				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
			@else
				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
			@endif
		@endforeach
	</select>
</div>
<div class="form-group row">
	<label for="tipotarea" class="col-lg-3 col-form-label requerido">Tipo de cuenta</label>
	<select id="tipotarea" name="tipotarea" class="col-lg-4 form-control" required>
    	<option value="">-- Elija tipo de tarea --</option>
       	@foreach($tipotarea_enum as $tipotarea)
			@if ($tipotarea['valor'] == old('tipotarea',$data->tipotarea??''))
       			<option value="{{ $tipotarea['valor'] }}" selected>{{ $tipotarea['nombre'] }}</option>    
			@else
			    <option value="{{ $tipotarea['valor'] }}">{{ $tipotarea['nombre'] }}</option>
			@endif
    	@endforeach
	</select>
</div>
<div class="form-group row">
    <label for="tiempoestimado" class="col-lg-3 col-form-label requerido">Tiempo estimado en minutos</label>
    <div class="col-lg-2">
       <input type="text" name="tiempoestimado" id="tiempoestimado" class="form-control" value="{{old('tiempoestimado', $data->tiempoestimado ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
	<label for="enviacorreo" class="col-lg-3 col-form-label requerido">Tipo de cuenta</label>
	<select id="enviacorreo" name="enviacorreo" class="col-lg-4 form-control" required>
    	<option value="">-- Elija tipo de tarea --</option>
       	@foreach($enviacorreo_enum as $enviacorreo)
			@if ($enviacorreo['valor'] == old('enviacorreo',$data->enviacorreo??''))
       			<option value="{{ $enviacorreo['valor'] }}" selected>{{ $enviacorreo['nombre'] }}</option>    
			@else
			    <option value="{{ $enviacorreo['valor'] }}">{{ $enviacorreo['nombre'] }}</option>
			@endif
    	@endforeach
	</select>
</div>
