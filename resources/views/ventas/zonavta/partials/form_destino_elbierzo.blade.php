@php
    $dest = $data->destino ?? null;
@endphp
<div class="alert alert-info py-2 mb-3">
    En el certificado se usa:
    <strong>localidad + provincia</strong> &rarr; <code>se:lugarDestino</code>
    y el <strong>c&oacute;digo de localidad SENASA</strong> &rarr; fallback de <code>se:localidad</code>
    (si la localidad del cliente no tiene c&oacute;digo).
    Con cualquiera de esos dos se crea o actualiza el destino de esta zona.
</div>
<div class="form-group row">
    <label for="dest_codigo_localidad_senasa" class="col-lg-4 control-label text-right pr-2">C&oacute;digo de localidad SENASA</label>
    <div class="col-lg-8">
        <input type="number" name="dest_codigo_localidad_senasa" id="dest_codigo_localidad_senasa" class="form-control" min="1"
            value="{{ old('dest_codigo_localidad_senasa', $dest->codigo_localidad_senasa ?? '') }}">
        <small class="form-text text-muted">Fallback de <code>se:localidad</code>.</small>
    </div>
</div>
<div class="form-group row">
    <label for="dest_localidad" class="col-lg-4 control-label text-right pr-2">Localidad (texto destino)</label>
    <div class="col-lg-8">
        <input type="text" name="dest_localidad" id="dest_localidad" class="form-control" maxlength="80"
            value="{{ old('dest_localidad', $dest->localidad ?? '') }}">
        <small class="form-text text-muted">Nombre para <code>se:lugarDestino</code>. En el alta se precarga con el nombre de la zona.</small>
    </div>
</div>
<div class="form-group row">
    <label for="dest_provincia" class="col-lg-4 control-label text-right pr-2">Provincia</label>
    <div class="col-lg-8">
        <input type="text" name="dest_provincia" id="dest_provincia" class="form-control" maxlength="80"
            value="{{ old('dest_provincia', $dest->provincia ?? '') }}">
        <small class="form-text text-muted">Se concatena a <code>se:lugarDestino</code>.</small>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-4 control-label text-right pr-2">Patag&oacute;nico</label>
    <div class="col-lg-8">
        <div class="form-check mt-2">
            <input type="checkbox" class="form-check-input" name="dest_patagonico" id="dest_patagonico" value="1"
                {{ old('dest_patagonico', $dest->patagonico ?? false) ? 'checked' : '' }}>
            <label class="form-check-label" for="dest_patagonico">S&iacute;</label>
        </div>
        <small class="form-text text-muted">Marca de zona patag&oacute;nica del certificado.</small>
    </div>
</div>
<div class="form-group row">
    <label for="dest_pais_codigo" class="col-lg-4 control-label text-right pr-2">Pa&iacute;s (c&oacute;d. Anita)</label>
    <div class="col-lg-8">
        <input type="number" name="dest_pais_codigo" id="dest_pais_codigo" class="form-control" min="1"
            value="{{ old('dest_pais_codigo', $dest->pais_codigo ?? '') }}">
        <small class="form-text text-muted">No entra en el XML del certificado.</small>
    </div>
</div>
