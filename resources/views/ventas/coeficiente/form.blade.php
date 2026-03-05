<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="porcentajedivision" class="col-lg-3 col-form-label requerido">Porc.de división</label>
    <div class="col-lg-3">
        <input type="number" name="porcentajedivision" id="porcentajedivision" min="0" max="100" class="form-control" value="{{old('porcentajedivision', $data->porcentajedivision ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="tasa" class="col-lg-3 col-form-label requerido">Tasa</label>
    <div class="col-lg-3">
        <input type="number" name="tasa" id="tasa" min="0" max="100" class="form-control" value="{{old('tasa', $data->tasa ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">Código Anita</label>
    <div class="col-lg-3">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" readonly/>
    </div>
</div>