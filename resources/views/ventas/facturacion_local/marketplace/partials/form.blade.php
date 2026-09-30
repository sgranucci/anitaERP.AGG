@php
    $activo = (bool) old('activo', $data->activo ?? true);
@endphp
<div class="form-group row">
    <label for="codigo" class="col-lg-3 control-label text-right pr-2 requerido">Código</label>
    <div class="col-lg-3">
        <input type="text" name="codigo" id="codigo" class="form-control" maxlength="10" required inputmode="numeric"
               value="{{ old('codigo', $data->codigo ?? '') }}" autocomplete="off">
        <small class="form-text text-muted">Código del maestro. No se vuelve a grabar en Anita.</small>
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 control-label text-right pr-2 requerido">Nombre</label>
    <div class="col-lg-6">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="60" required
               value="{{ old('nombre', $data->nombre ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2" for="activo">Activo</label>
    <div class="col-lg-8">
        <input type="hidden" name="activo" value="0">
        <div class="form-check">
            <input type="checkbox" name="activo" id="activo" class="form-check-input" value="1" {{ $activo ? 'checked' : '' }}>
            <label class="form-check-label" for="activo">Se puede asignar a artículos del canal Local</label>
        </div>
    </div>
</div>
