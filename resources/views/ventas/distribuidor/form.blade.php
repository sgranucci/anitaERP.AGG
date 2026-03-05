<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="porcentajecomision" class="col-lg-3 col-form-label requerido">Porcentaje de Comisión</label>
    <div class="col-lg-3">
        <input type="number" name="porcentajecomision" id="porcentajecomision" class="form-control" value="{{old('porcentajecomision', $data->porcentajecomision ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">Código Anita</label>
    <div class="col-lg-3">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" readonly/>
    </div>
</div>