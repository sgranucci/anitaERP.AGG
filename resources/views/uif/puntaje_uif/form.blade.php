<div class="form-group row">
    <label for="desdepuntaje" class="col-lg-3 col-form-label requerido">Desde puntaje</label>
    <div class="col-lg-2">
       <input type="number" name="desdepuntaje" id="desdepuntaje" class="form-control" value="{{old('desdepuntaje', $data->desdepuntaje ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="hastapuntaje" class="col-lg-3 col-form-label requerido">Desde puntaje</label>
    <div class="col-lg-2">
       <input type="number" name="hastapuntaje" id="hastapuntaje" class="form-control" value="{{old('hastapuntaje', $data->hastapuntaje ?? '')}}" required/>
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
