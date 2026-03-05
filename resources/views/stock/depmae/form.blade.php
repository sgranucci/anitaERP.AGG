<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
	<label for="tipodeposito" class="col-lg-3 col-form-label requerido">Tipo de depósito</label>
	<select id="tipodeposito" name="tipodeposito" class="col-lg-4 form-control" required>
    	<option value="">-- Elija tipo de depósito --</option>
       	@foreach($tipodeposito_enum as $tipodeposito)
			@if ($tipodeposito['nombre'] == old('tipodeposito',$data->tipodeposito??''))
       			<option value="{{ $tipodeposito['nombre'] }}" selected>{{ $tipodeposito['nombre'] }}</option>    
			@else
			    <option value="{{ $tipodeposito['nombre'] }}">{{ $tipodeposito['nombre'] }}</option>
			@endif
    	@endforeach
	</select>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">Código ANITA</label>
    <div class="col-lg-3">
    <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" required/>
    </div>
</div>
