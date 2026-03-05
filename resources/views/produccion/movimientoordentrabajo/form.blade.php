<div class="form-group row">
	<label for="fecha" class="col-lg-3 col-form-label requerido">Fecha</label>
   	<div class="col-lg-2">
   		<input type="date" name="fecha" id="fecha" class="form-control" value="{{substr(old('fecha', $data->fecha ?? date('Y-m-d')),0,10)}}" required>
   	</div>
</div>
<div class="form-group row">
	<label for="tarea" class="col-lg-3 col-form-label requerido">Tarea</label>
   	<select name="tarea_id" id="tarea_id" data-placeholder="Tarea" class="col-lg-3 form-control required" data-fouc>
   	<option value="">-- Seleccionar tarea --</option>
   		@foreach($tarea_query as $key => $value)
   			@if( (int) $value->id == (int) old('tarea_id', $data->tarea_id ?? session()->get('tarea_id')))
   				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
   			@else
   				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
   			@endif
   		@endforeach
   	</select>
</div>
<div class="form-group row">
	<label for="operacion" class="col-lg-3 col-form-label requerido">Operaci&oacute;n</label>
   	<select name="operacion_id" id="operacion_id" data-placeholder="Operaci&oacute;n" class="col-lg-3 form-control required" data-fouc>
   	<option value="">-- Seleccionar operacion --</option>
   		@foreach($operacion_query as $key => $value)
   			@if( (int) $value->id == (int) old('operacion_id', $data->operacion_id ?? session()->get('operacion_id')))
   				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
   			@else
   				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
   			@endif
   		@endforeach
   	</select>
</div>
<div class="form-group row">
	<label for="empleado" class="col-lg-3 col-form-label requerido">Empleado</label>
   	<select name="empleado_id" id="empleado_id" data-placeholder="Empleado" class="col-lg-3 form-control required" data-fouc>
   	<option value="">-- Seleccionar tarea --</option>
   		@foreach($empleado_query as $key => $value)
   			@if( (int) $value->id == (int) old('empleado_id', $data->empleado_id ?? session()->get('empleado_id')))
   				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
   			@else
   				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
   			@endif
   		@endforeach
   	</select>
</div>
<div class="form-group row">
	<label for="ot" class="col-lg-3 col-form-label">Ordenes de trabajo</label>
   	<div class="col-lg-8">
   		<input type="text" name="ordenestrabajo" id="ordenestrabajo" class="form-control" value="{{old('ordenestrabajo', $data->ordenestrabajo ?? '')}}">
	</div>
</div>

