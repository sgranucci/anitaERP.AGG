<div class="form-group row">
	<label for="desdefecha" class="col-lg-3 col-form-label requerido">Desde fecha</label>
	<div class="col-lg-4">
		<input type="date" name="desdefecha" id="desdefecha" class="form-control" value="{{date('2001-01-01')}}" required/>
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

