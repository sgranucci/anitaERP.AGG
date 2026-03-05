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
	<label for="Usuario" class="col-lg-3 col-form-label">Usuario</label>
	<select name="usuario_id" id="usuario_id" data-placeholder="Usuario" class="col-lg-3 form-control" data-fouc>
		<option value="">-- Seleccionar area de destino --</option>
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
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />
