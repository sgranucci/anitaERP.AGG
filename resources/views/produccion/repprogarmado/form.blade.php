<div class="form-group row">
	<label for="ot" class="col-lg-3 col-form-label">Ordenes de trabajo a programar</label>
	<div class="col-lg-8">
		<input type="text" name="ordenestrabajo" id="ordenestrabajo" class="form-control" value="{{old('ordenestrabajo')}}">
	</div>
</div>
<div class="form-group row">
	<label for="tipoprogramacion" class="col-lg-3 col-form-label requerido">Tipo de programaci&oacute;n</label>
	<select name="tipoprogramacion" class="col-lg-3 form-control" required>
		<option value="">-- Elija tipo de programaci&oacute;n --</option>
		@foreach($tipoProgramacion_enum as $value => $tipoProgramacion)
			@if($value == 'PROVISORIA')
				<option value="{{ $value }}" selected="select">{{ $tipoProgramacion }}</option>    
			@else
				<option value="{{ $value }}">{{ $tipoProgramacion }}</option>    
			@endif
		@endforeach
	</select>
</div>



