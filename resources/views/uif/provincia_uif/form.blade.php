<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-4">
       <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
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
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">Código Anita</label>
    <div class="col-lg-2">
       <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" required/>
    </div>
</div>
