<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="desde_nro" class="col-lg-3 col-form-label requerido">Desde nro.</label>
    <div class="col-lg-8">
    <input type="number" name="desde_nro" id="desde_nro" class="form-control" value="{{old('desde_nro', $data->desde_nro ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="hasta_nro" class="col-lg-3 col-form-label requerido">Hasta nro.</label>
    <div class="col-lg-8">
    <input type="number" name="hasta_nro" id="hasta_nro" class="form-control" value="{{old('hasta_nro', $data->hasta_nro ?? '')}}" required/>
    </div>
</div>
