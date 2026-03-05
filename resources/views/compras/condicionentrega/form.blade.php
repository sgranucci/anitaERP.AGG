<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label">C&oacute;digo</label>
    <div class="col-lg-2">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" readonly>
    </div>
</div>
<div class="form-group row">
    <label for="dias" class="col-lg-3 col-form-label">Días de entrega</label>
    <div class="col-lg-1">
        <input type="number" name="dias" id="dias" class="form-control" value="{{old('dias', $data->dias ?? '')}}">
    </div>
</div>

