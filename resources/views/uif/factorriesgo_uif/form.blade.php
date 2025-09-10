<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-4">
       <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="ponderacion" class="col-lg-3 col-form-label requerido">Ponderación</label>
    <div class="col-lg-2">
       <input type="number" name="ponderacion" id="ponderacion" class="form-control" value="{{old('ponderacion', $data->ponderacion ?? '')}}" required/>
    </div>
</div>
