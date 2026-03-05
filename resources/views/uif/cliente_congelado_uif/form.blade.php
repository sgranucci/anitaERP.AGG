<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-4">
       <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="numerodocumento" class="col-lg-3 col-form-label requerido">Nro. Documento</label>
    <div class="col-lg-3">
       <input type="text" name="numerodocumento" id="numerodocumento" class="form-control" value="{{old('numerodocumento', $data->numerodocumento ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="resolucion" class="col-lg-3 col-form-label requerido">Resolución</label>
    <div class="col-lg-3">
       <input type="text" name="resolucion" id="resolucion" class="form-control" value="{{old('resolucion', $data->resolucion ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
	<label for="fechacaducidad" class="col-lg-3 col-form-label requerido">Fecha de Caducidad</label>
	<div class="col-lg-3">
		<input type="date" name="fechacaducidad" id="fechacaducidad" value="{{old('fechacaducidad', $data['fechacaducidad'] ?? '')}}" placeholder="Fecha de caducidad">
	</div>
</div>
