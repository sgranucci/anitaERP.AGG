<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="abreviatura" class="col-lg-3 col-form-label">Abreviatura</label>
    <div class="col-lg-4">
        <input type="text" name="abreviatura" id="abreviatura" class="form-control" value="{{old('abreviatura', $data->abreviatura ?? '')}}">
    </div>
</div>

