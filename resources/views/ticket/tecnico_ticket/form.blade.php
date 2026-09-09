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
	<label for="Usuario" class="col-lg-3 col-form-label requerido">Usuario ERP</label>
	<select name="usuario_id" id="usuario_id" data-placeholder="Usuario" class="col-lg-3 form-control" required data-fouc>
		<option value="">-- Seleccionar usuario --</option>
		@foreach($usuario_query as $key => $value)
			@if( (int) $value->id == (int) old('usuario_id', $data->usuario_id ?? ''))
				<option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
			@else
				<option value="{{ $value->id }}">{{ $value->nombre }}</option>    
			@endif
		@endforeach
	</select>
	<div>
		<button type="button" id="crea_usuario" title="Crea Usuario" class="btn btn-primary">
			<i class="text-white">Crea Usuario</i>
		</button>     
	</div>
</div>
<div class="form-group row">
	<div class="col-lg-3"></div>
	<div class="col-lg-7">
		<small class="form-text text-muted">
			Debe ser el usuario con el que la persona inicia sesión. En cada área, un usuario solo puede
			estar en una ficha (necesario para <strong>Tomar</strong> en la bandeja de mantenimiento).
			Si aún no tiene usuario, usá «Crea Usuario» y después vincularlo acá.
		</small>
	</div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
