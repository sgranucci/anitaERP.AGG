<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-6">
       <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="cuit" class="col-lg-3 col-form-label requerido">CUIT</label>
    <div class="col-lg-2">
       <input type="text" name="cuit" id="cuit" class="form-control" value="{{old('cuit', $data->cuit ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="desdefecha" class="col-lg-3 col-form-label requerido">Desde Fecha</label>
    <div class="col-lg-3">
       <input type="date" name="desdefecha" id="desdefecha" class="form-control" value="{{old('desdefecha', $data->desdefecha ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="hastafecha" class="col-lg-3 col-form-label">Hasta Fecha</label>
    <div class="col-lg-3">
       <input type="date" name="hastafecha" id="hastafecha" class="form-control" value="{{old('hastafecha', $data->hastafecha ?? '')}}"/>
    </div>
</div>
