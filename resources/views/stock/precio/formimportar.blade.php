<div class="form-group row">
	<label for="fechavigencia" class="col-lg-3 col-form-label requerido">Fecha de vigencia</label>
	<div class="col-lg-3">
		<input type="text" name="fechavigencia" id="fechavigencia" class="form-control" value="{{old('fechavigencia', \Carbon\Carbon::parse(strtotime($precio->fechavigencia ?? date('d-m-Y')))->formatLocalized('%d-%m-%Y') ?? date('d-m-Y'))}}" required/>
	</div>
</div>
<div class="form-group row">
    <label for="moneda_id" class="col-lg-3 col-form-label requerido">Moneda</label>
	<select name="moneda_id" class="col-lg-3 form-control">
		<option value="">-- Elija moneda --</option>
		@foreach ($moneda_query as $moneda)
			<option value="{{ $moneda->id }}"
				@if ($moneda->id == 1) selected @endif
				>{{ $moneda->nombre }}
			</option>
		@endforeach
	</select>
</div>
<div class="form-group row">
	<label for="file" class="col-lg-3 col-form-label requerido">Archivo</label>
	<div class="col-lg-8">
		<input type="file" name="file" class="form-control" value="" required/>
	</div>
</div>


