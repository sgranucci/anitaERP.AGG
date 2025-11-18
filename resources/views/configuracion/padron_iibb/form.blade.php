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
