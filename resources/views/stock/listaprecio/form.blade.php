<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    	<input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="formula" class="col-lg-3 col-form-label requerido">F&oacute;rmula</label>
    <div class="col-lg-8">
    	<input type="text" name="formula" id="formula" class="form-control" value="{{old('formula', $data->formula ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="incluyeimpuesto" class="col-lg-3 col-form-label requerido">Incluye impuestos</label>
	<select name="incluyeimpuesto" class="col-lg-3 form-control">
		@if (empty($data['incluyeimpuesto']))
			<option value="">-- Elija si incluye impuesto --</option>
		@endif
		@for ($i = 1; $i <= 2; $i++)
			@if (!empty($data['incluyeimpuesto']) && $data['incluyeimpuesto'] == $i))
				<option selected value="{{$i}}">{{$i==1?'S':'N'}}</option>
			@else
				<option value="{{$i}}">{{$i==1?'S':'N'}}</option>
			@endif
		@endfor
	</select>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">C&oacute;digo ANITA</label>
    <div class="col-lg-8">
    	<input type="number" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" required/>
    </div>
</div>
@if (config('app.empresa') == 'Calzados Ferli')
	<div class="form-group row">
		<label for="desdetalle" class="col-lg-3 col-form-label requerido">Desde talle</label>
		<div class="col-lg-2">
			<input type="text" name="desdetalle" id="desdetalle" class="form-control" value="{{old('desdetalle', $data->desdetalle)}}" required/>
		</div>
	</div>
	<div class="form-group row">
		<label for="hastatalle" class="col-lg-3 col-form-label requerido">Hasta talle</label>
		<div class="col-lg-2">
			<input type="text" name="hastatalle" id="hastatalle" class="form-control" value="{{old('hastatalle', $data->hastatalle)}}" required/>
		</div>
	</div>
	<div class="form-group row">
		<label for="tiponumeracion_id" class="col-lg-3 col-form-label requerido">Tipo de numeraci&oacute;n</label>
		<select name="tiponumeracion_id" class="col-lg-3 form-control">
			<option value="">-- Elija tipo de numeraci&oacute;n --</option>
			@foreach ($tiponumeracion_query as $tiponumeracion)
				<option value="{{ $tiponumeracion->id }}"
					@if (old('tiponumeracion', $linea->tiponumeracion_id ?? '') == $tiponumeracion->id) selected @endif
					>{{ $tiponumeracion->nombre }}
				</option>
			@endforeach
		</select>
	</div>
@endif

