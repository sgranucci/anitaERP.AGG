<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
	<label for="tipooperacion" class="col-lg-4 col-form-label requerido">Tipo de operaci&oacute;n</label>
	<select name="tipooperacion" class="col-lg-3 form-control" required>
   		<option value="">-- Elija tipo de operacion --</option>
		@foreach($tipooperacion_enum as $value => $tipooperacion)
   			@if($value == old('tipooperacion', $data->tipooperacion ?? ''))
   				<option value="{{ $value }}" selected="select">{{ $tipooperacion }}</option>    
   			@else
   				<option value="{{ $value }}">{{ $tipooperacion }}</option>    
   			@endif
   		@endforeach
	</select>
</div>
