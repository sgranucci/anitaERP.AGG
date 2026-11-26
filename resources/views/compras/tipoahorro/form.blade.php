<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido text-right pr-2">Nombre</label>
    <div class="col-lg-6">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="120" value="{{ old('nombre', $data->nombre ?? '') }}" required>
    </div>
</div>
<div class="form-group row">
    <label for="orden" class="col-lg-3 col-form-label text-right pr-2">Orden</label>
    <div class="col-lg-2">
        <input type="number" name="orden" id="orden" class="form-control" min="0" step="1" value="{{ old('orden', $data->orden ?? 0) }}">
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right pr-2">Activo</label>
    <div class="col-lg-6 d-flex align-items-center">
        <input type="hidden" name="activo" value="0">
        <input type="checkbox" name="activo" id="activo" value="1" {{ old('activo', ($data->activo ?? true) ? '1' : '0') ? 'checked' : '' }}>
        <small class="text-muted ml-2">Un tipo inactivo no se ofrece en requisici&oacute;n ni OC; las l&iacute;neas ya cargadas lo conservan.</small>
    </div>
</div>
