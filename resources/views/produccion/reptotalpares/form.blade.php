<div class="form-group row">
	<label for="desdefecha" class="col-lg-3 col-form-label requerido">Desde fecha</label>
	<div class="col-lg-4">
		<input type="date" name="desdefecha" id="desdefecha" class="form-control" value="{{date('Y-m-01')}}" required/>
	</div>
</div>
<div class="form-group row">
	<label for="hastafecha" class="col-lg-3 col-form-label requerido">Hasta fecha</label>
	<div class="col-lg-4">
		<input type="date" name="hastafecha" id="hastafecha" class="form-control" value="{{date('Y-m-d')}}" required/>
	</div>
</div>
<div class="form-group row">
	<label for="ot" class="col-lg-3 col-form-label">Ordenes de trabajo a imprimir</label>
	<div class="col-lg-8">
		<input type="text" name="ordenestrabajo" id="ordenestrabajo" class="form-control" value="{{old('ordenestrabajo')}}">
	</div>
</div>
<div class="form-group row">
	<label for="apertura" class="col-lg-3 col-form-label requerido">Apertura</label>
	<select name="apertura" class="col-lg-3 form-control" required>
		<option value="">-- Elija apertura de per&iacute;odos --</option>
		@foreach($apertura_enum as $value => $apertura)
			@if($value == 'DIARIA')
				<option value="{{ $value }}" selected="select">{{ $apertura }}</option>    
			@else
				<option value="{{ $value }}">{{ $apertura }}</option>    
			@endif
		@endforeach
	</select>
</div>
