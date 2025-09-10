<div class="form-group row">
    <label for="desdeoperacion" class="col-lg-3 col-form-label requerido">Desde Operaciones</label>
    <div class="col-lg-2">
       <input type="number" name="desdeoperacion" id="desdeoperacion" class="form-control" value="{{old('desdeoperacion', $data->desdeoperacion ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="hastaoperacion" class="col-lg-3 col-form-label requerido">Hasta Operaciones</label>
    <div class="col-lg-2">
       <input type="number" name="hastaoperacion" id="hastaoperacion" class="form-control" value="{{old('hastaoperacion', $data->hastaoperacion ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
	<label for="riesgo" class="col-lg-3 col-form-label requerido">Riesgo</label>
	<select id="riesgo" name="riesgo" class="col-lg-4 form-control" required>
    	<option value="">-- Elija riesgo --</option>
       	@foreach($riesgo_enum as $riesgo)
			@if ($riesgo['nombre'] == old('riesgo',$data->riesgo??''))
       			<option value="{{ $riesgo['nombre'] }}" selected>{{ $riesgo['nombre'] }}</option>    
			@else
			    <option value="{{ $riesgo['nombre'] }}">{{ $riesgo['nombre'] }}</option>
			@endif
    	@endforeach
	</select>
</div>
<div class="form-group row">
    <label for="puntaje" class="col-lg-3 col-form-label requerido">Puntaje</label>
    <div class="col-lg-2">
       <input type="text" name="puntaje" id="puntaje" class="form-control" value="{{old('puntaje', $data->puntaje ?? '')}}" required/>
    </div>
</div>
