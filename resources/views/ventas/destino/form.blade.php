<div class="alert alert-info py-2">
    El c&oacute;digo es el de la zona de venta (Anita <code>dest_destino</code> / <code>zonv_codigo</code>).
    El c&oacute;digo de localidad SENASA del destino se usa en <code>se:localidad</code> solo si la localidad del cliente no tiene c&oacute;digo.
</div>
<div class="form-group row tm-zonavta-campo">
    <label for="codigozonavta" class="col-lg-3 control-label text-right pr-2 requerido">Zona de venta</label>
    <div class="col-lg-6">
        <div class="input-group">
            <input type="hidden" class="zonavta_id" name="zonavta_id" id="zonavta_id"
                value="{{ old('zonavta_id', $data->zonavta_id ?? '') }}">
            <input type="text" class="form-control codigozonavta" id="codigozonavta" name="codigozonavta"
                value="{{ old('codigozonavta', optional($data->zonavta)->codigo ?? $data->codigo ?? '') }}"
                placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off" style="max-width:7rem;" required>
            <input type="text" class="form-control nombrezonavta" id="nombrezonavta"
                value="{{ old('nombrezonavta', optional($data->zonavta)->nombre ?? '') }}"
                placeholder="Descripci&oacute;n" readonly>
            <div class="input-group-append">
                <button type="button" class="btn btn-outline-secondary consultazonavta" title="Consultar zonas (F1)">
                    <i class="fa fa-search"></i>
                </button>
            </div>
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 control-label text-right pr-2">C&oacute;digo zona</label>
    <div class="col-lg-2">
        <input type="text" name="codigo" id="codigo" class="form-control" readonly
            value="{{ old('codigo', $data->codigo ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="localidad" class="col-lg-3 control-label text-right pr-2 requerido">Localidad</label>
    <div class="col-lg-6">
        <input type="text" name="localidad" id="localidad" class="form-control" maxlength="80" required
            value="{{ old('localidad', $data->localidad ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="provincia" class="col-lg-3 control-label text-right pr-2">Provincia</label>
    <div class="col-lg-6">
        <input type="text" name="provincia" id="provincia" class="form-control" maxlength="80"
            value="{{ old('provincia', $data->provincia ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="pais_codigo" class="col-lg-3 control-label text-right pr-2">Pa&iacute;s (c&oacute;d. Anita)</label>
    <div class="col-lg-2">
        <input type="number" name="pais_codigo" id="pais_codigo" class="form-control" min="1"
            value="{{ old('pais_codigo', $data->pais_codigo ?? '') }}">
    </div>
    <label for="codigo_localidad_senasa" class="col-lg-3 control-label text-right pr-2">C&oacute;digo de localidad SENASA</label>
    <div class="col-lg-2">
        <input type="number" name="codigo_localidad_senasa" id="codigo_localidad_senasa" class="form-control" min="1"
            value="{{ old('codigo_localidad_senasa', $data->codigo_localidad_senasa ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2">Patag&oacute;nico</label>
    <div class="col-lg-6">
        <div class="form-check mt-2">
            <input type="checkbox" class="form-check-input" name="patagonico" id="patagonico" value="1"
                {{ old('patagonico', $data->patagonico ?? false) ? 'checked' : '' }}>
            <label class="form-check-label" for="patagonico">S&iacute;</label>
        </div>
    </div>
</div>
