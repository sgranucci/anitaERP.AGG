<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="codigopostal" class="col-lg-3 col-form-label">C&oacute;digo postal</label>
    <div class="col-lg-1">
    <input type="text" name="codigopostal" id="codigopostal" class="form-control" value="{{old('codigopostal', $data->codigopostal ?? '')}}">
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label">C&oacute;digo externo</label>
    <div class="col-lg-2">
    <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}">
    </div>
</div>
<div class="form-group row">
    <label for="provincia_id" class="col-lg-3 col-form-label requerido">Provincia</label>
	<select name="provincia_id" class="col-lg-3 form-control" required>
		<option value="">-- Elija pa&iacute;s --</option>
		@foreach ($provincia_query as $provincia)
			<option value="{{ $provincia->id }}"
				@if (old('provincia_id', $data->provincia_id ?? '') == $provincia->id) selected @endif
				>{{ $provincia->nombre }}
			</option>
		@endforeach
	</select>
</div>
<div class="form-group row">
    <label for="codigosenasa" class="col-lg-3 col-form-label">C&oacute;digo SENASA</label>
    <div class="col-lg-2">
    <input type="text" name="codigosenasa" id="codigosenasa" class="form-control" value="{{old('codigosenasa', $data->codigosenasa ?? '')}}">
    </div>
</div>
