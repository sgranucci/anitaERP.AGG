@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => old('empresa_id', $data->empresa_id ?? null),
    'required' => true,
    'permite_vacio' => false,
    'col_label' => 'col-lg-3 text-right pr-2',
    'col_input' => 'col-lg-8',
])
<div class="form-group row">
    <label for="codigo" class="col-lg-3 control-label text-right pr-2 requerido">C&oacute;digo</label>
    <div class="col-lg-8">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{ old('codigo', $data->codigo ?? '') }}" required maxlength="80" placeholder="Ej. caja.OPP"/>
        <small class="form-text text-muted">Clave estable por empresa: m&oacute;dulo.tipo (ej. <code>caja.OPP</code>, <code>compras.OC</code>).</small>
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 control-label text-right pr-2 requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required maxlength="120"/>
    </div>
</div>
<div class="form-group row">
    <label for="modulo" class="col-lg-3 control-label text-right pr-2 requerido">M&oacute;dulo</label>
    <div class="col-lg-4">
        <input type="text" name="modulo" id="modulo" class="form-control" value="{{ old('modulo', $data->modulo ?? 'caja') }}" required maxlength="40" placeholder="caja, compras, stock…"/>
    </div>
</div>
<div class="form-group row">
    <label for="ultimo_numero" class="col-lg-3 control-label text-right pr-2 requerido">&Uacute;ltimo n&uacute;mero</label>
    <div class="col-lg-4">
        <input type="number" name="ultimo_numero" id="ultimo_numero" class="form-control" value="{{ old('ultimo_numero', $data->ultimo_numero ?? 0) }}" required min="0" step="1"/>
        <small class="form-text text-muted">El pr&oacute;ximo documento usar&aacute; este valor + 1 (salvo que Anita o el MAX ERP sea mayor).</small>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2">Activo</label>
    <div class="col-lg-8">
        <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="activo" name="activo" value="1" {{ old('activo', $data->activo ?? true) ? 'checked' : '' }}>
            <label class="custom-control-label" for="activo">Disponible para numerar</label>
        </div>
    </div>
</div>
<hr>
<h5 class="mb-3">Puente Anita (opcional)</h5>
<div class="form-group row">
    <label for="anita_sistema" class="col-lg-3 control-label text-right pr-2">Sistema Anita</label>
    <div class="col-lg-4">
        <input type="text" name="anita_sistema" id="anita_sistema" class="form-control" value="{{ old('anita_sistema', $data->anita_sistema ?? '') }}" maxlength="30" placeholder="ventas"/>
    </div>
</div>
<div class="form-group row">
    <label for="anita_fuente" class="col-lg-3 control-label text-right pr-2">Fuente</label>
    <div class="col-lg-4">
        <input type="text" name="anita_fuente" id="anita_fuente" class="form-control" value="{{ old('anita_fuente', $data->anita_fuente ?? '') }}" maxlength="20" placeholder="numerador"/>
    </div>
</div>
<div class="form-group row">
    <label for="anita_clave" class="col-lg-3 control-label text-right pr-2">Clave Anita</label>
    <div class="col-lg-4">
        <input type="text" name="anita_clave" id="anita_clave" class="form-control" value="{{ old('anita_clave', $data->anita_clave ?? '') }}" maxlength="40" placeholder="num_clave (ej. 223)"/>
    </div>
</div>
<div class="form-group row">
    <label for="observacion" class="col-lg-3 control-label text-right pr-2">Observaci&oacute;n</label>
    <div class="col-lg-8">
        <input type="text" name="observacion" id="observacion" class="form-control" value="{{ old('observacion', $data->observacion ?? '') }}" maxlength="255"/>
    </div>
</div>
