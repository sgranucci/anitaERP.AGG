<div class="form-group row">
    <label for="codigo" class="col-lg-3 control-label text-right pr-2">C&oacute;digo</label>
    <div class="col-lg-3">
        <input type="text" name="codigo" id="codigo" class="form-control" maxlength="20"
               value="{{ old('codigo', $data->codigo ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 control-label text-right pr-2 requerido">Nombre</label>
    <div class="col-lg-6">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="120" required
               value="{{ old('nombre', $data->nombre ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2">Activo</label>
    <div class="col-lg-6">
        <div class="form-check">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" name="activo" id="activo" value="1" class="form-check-input"
                   {{ old('activo', $data->activo ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="activo">Disponible en la carga de tickets</label>
        </div>
    </div>
</div>
