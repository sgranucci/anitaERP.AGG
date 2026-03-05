<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="ubicacion" class="col-lg-3 col-form-label requerido">Ubicación</label>
    <div class="col-lg-8">
    <input type="text" name="ubicacion" id="ubicacion" class="form-control" value="{{old('ubicacion', $data->ubicacion ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="comando" class="col-lg-3 col-form-label requerido">Comando de salida</label>
    <div class="col-lg-8">
    <input type="text" name="comando" id="comando" class="form-control" value="{{old('comando', $data->comando ?? '')}}" required/>
    </div>
</div>
