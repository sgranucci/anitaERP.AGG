<div class="form-group row">
	<label for="desdefecha" class="col-lg-3 col-form-label requerido">Desde fecha</label>
	<div class="col-lg-4">
		<input type="date" name="desdefecha" id="desdefecha" class="form-control" value="{{old('desdefecha', date('d-m-Y'))}}" required/>
	</div>
</div>
<div class="form-group row">
	<label for="desdehora" class="col-lg-3 col-form-label requerido">Desde hora:</label>
	<div class="col-lg-4">
		<input type="time" id="desdehora" name="desdehora" class="form-control">
	</div>
</div>
<div class="form-group row">
	<label for="hastafecha" class="col-lg-3 col-form-label requerido">Hasta fecha</label>
	<div class="col-lg-4">
		<input type="date" name="hastafecha" id="hastafecha" class="form-control" value="{{old('desdefecha', date('d-m-Y'))}}" required/>
	</div>
</div>
<div class="form-group row">
	<label for="hastahora" class="col-lg-3 col-form-label requerido">Hasta hora:</label>
	<div class="col-lg-4">
		<input type="time" id="hastahora" name="hastahora" class="form-control">
	</div>
</div>
<div class="form-group row">
	<label for="compresion" class="col-lg-3 col-form-label requerido">Compresi&oacute;n:</label>
	
	<select name="compresion" class="col-lg-3 form-control" required>
    	<option value="">-- Elija la compresi&oacute;n --</option>
       	@foreach($compresion_enum as $value => $compresion)
       		<option value="{{ $value }}">{{ $compresion }}</option>    
    	@endforeach
	</select>
	
</div>

