<div class="form-group row">
    <label for="desdemonto" class="col-lg-3 col-form-label requerido">Desde Monto</label>
    <div class="col-lg-4">
       <input type="number" name="desdemonto" id="desdemonto" class="form-control" value="{{old('desdemonto', $data->desdemonto ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="hastamonto" class="col-lg-3 col-form-label requerido">Hasta Monto</label>
    <div class="col-lg-4">
       <input type="number" name="hastamonto" id="hastamonto" class="form-control" value="{{old('hastamonto', $data->hastamonto ?? '')}}" required/>
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
