@php
    $vuelve = (bool) old('vuelve_stock', $data->vuelve_stock ?? true);
    $activo = (bool) old('activo', $data->activo ?? true);
@endphp
<div class="form-group row">
    <label for="codigo" class="col-lg-3 control-label text-right pr-2 requerido">Código</label>
    <div class="col-lg-6">
        <input type="text" name="codigo" id="codigo" class="form-control" maxlength="40" required
               value="{{ old('codigo', $data->codigo ?? '') }}" autocomplete="off">
        <small class="form-text text-muted">Clave interna. No se reutiliza si el motivo ya tiene historial.</small>
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
    <label for="orden" class="col-lg-3 control-label text-right pr-2">Orden</label>
    <div class="col-lg-3">
        <input type="number" name="orden" id="orden" class="form-control" min="0" max="9999"
               value="{{ old('orden', $data->orden ?? 0) }}">
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2" for="vuelve_stock">Stock</label>
    <div class="col-lg-8">
        <input type="hidden" name="vuelve_stock" value="0">
        <div class="form-check">
            <input type="checkbox" name="vuelve_stock" id="vuelve_stock" class="form-check-input" value="1" {{ $vuelve ? 'checked' : '' }}>
            <label class="form-check-label" for="vuelve_stock">El par vuelve al stock vendible</label>
        </div>
        <small class="form-text text-muted">Como en Oracle: tildado vuelve al stock vendible. Destildado (Fallado, Dañado, Usado) el par no entra al stock.</small>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2" for="activo">Activo</label>
    <div class="col-lg-8">
        <input type="hidden" name="activo" value="0">
        <div class="form-check">
            <input type="checkbox" name="activo" id="activo" class="form-check-input" value="1" {{ $activo ? 'checked' : '' }}>
            <label class="form-check-label" for="activo">Se ofrece en POS, notas de crédito y Tienda Nube</label>
        </div>
    </div>
</div>
