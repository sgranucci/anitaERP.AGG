<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">C&oacute;digo Anita</label>
    <div class="col-lg-2">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" required maxlength="10" inputmode="numeric"/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo_afip" class="col-lg-3 col-form-label">C&oacute;digo AFIP</label>
    <div class="col-lg-2">
        <input type="text" name="codigo_afip" id="codigo_afip" class="form-control" value="{{old('codigo_afip', $data->codigo_afip ?? '')}}" maxlength="3"/>
    </div>
</div>
