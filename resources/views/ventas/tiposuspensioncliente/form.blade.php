<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label">Pol&iacute;tica</label>
    <div class="col-lg-8">
        <select name="codigo" id="codigo" class="form-control">
            <option value="">Solo motivo (el estado suspendido bloquea todo)</option>
            <option value="MOROSO" {{ old('codigo', $data->codigo ?? '') === 'MOROSO' ? 'selected' : '' }}>Moroso — pide, no boleta ni factura</option>
            <option value="PROFORMA" {{ old('codigo', $data->codigo ?? '') === 'PROFORMA' ? 'selected' : '' }}>Proforma — pide y boleta, no factura</option>
            <option value="BLOQUEADO" {{ old('codigo', $data->codigo ?? '') === 'BLOQUEADO' ? 'selected' : '' }}>Suspendido — no aparece en carga de pedidos</option>
        </select>
        <small class="form-text text-muted">El nombre es el motivo (leyenda). La pol&iacute;tica define hasta d&oacute;nde llega el circuito.</small>
    </div>
</div>
