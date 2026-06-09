<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required maxlength="255"/>
    </div>
</div>
<div class="form-group row">
    <label for="descripcion" class="col-lg-3 col-form-label">Descripci&oacute;n</label>
    <div class="col-lg-8">
        <textarea name="descripcion" id="descripcion" class="form-control" rows="3" maxlength="2000">{{ old('descripcion', $data->descripcion ?? '') }}</textarea>
    </div>
</div>
