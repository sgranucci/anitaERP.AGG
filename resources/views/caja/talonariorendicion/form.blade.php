<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Detalle</label>
    <div class="col-lg-4">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">Serie</label>
    <div class="col-lg-1">
    <input type="text" name="serie" id="serie" class="form-control" value="{{old('serie', $data->serie ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="desdenumero" class="col-lg-3 col-form-label">Desde número</label>
    <div class="col-lg-2">
        <input type="number" name="desdenumero" id="desdenumero" class="form-control" value="{{old('desdenumero', $data->desdenumero ?? '0')}}">
    </div>
</div>
<div class="form-group row">
    <label for="hastanumero" class="col-lg-3 col-form-label">Hasta número</label>
    <div class="col-lg-2">
        <input type="number" name="hastanumero" id="hastanumero" class="form-control" value="{{old('hastanumero', $data->hastanumero ?? '0')}}">
    </div>
</div>
<div class="form-group row">
	<label for="fechainicio" class="col-lg-3 col-form-label">Fecha de inicio</label>
	<div class="col-lg-4">
		<input type="date" id="fechainicio" name="fechainicio" class="form-control" value="{{old('fechainicio', date('d-m-Y'))}}"/>
	</div>
</div>
<div class="form-group row">
	<label for="fechacierre" class="col-lg-3 col-form-label">Fecha de cierre</label>
	<div class="col-lg-4">
		<input type="date" name="fechacierre" id="fechacierre" class="form-control" value="{{old('fechacierre', date('d-m-Y'))}}"/>
	</div>
</div>
<div class="form-group row">
	<label for="estado" class="col-lg-3 col-form-label requerido">Estado</label>
	<select name="estado" class="col-lg-3 form-control" required>
    	<option value="">-- Elija estado --</option>
       	@foreach($estado_enum as $value => $estado)
			@if ($value == old('estado','P'))
       			<option value="{{ $value }}" selected>{{ $estado }}</option>    
			@else
			    <option value="{{ $value }}">{{ $estado }}</option>
			@endif
    	@endforeach
	</select>
</div>
