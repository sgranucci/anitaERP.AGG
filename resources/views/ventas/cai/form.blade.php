<div class="form-group row">
    <label for="tipo" class="col-lg-3 col-form-label">Tipo</label>
    <div class="col-lg-3">
        <input type="text" name="tipo" id="tipo" class="form-control" maxlength="3"
               value="{{ old('tipo', $data->tipo ?? 'REM') }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="descripcion" class="col-lg-3 col-form-label">Descripci&oacute;n</label>
    <div class="col-lg-6">
        <input type="text" name="descripcion" id="descripcion" class="form-control" maxlength="30"
               value="{{ old('descripcion', $data->descripcion ?? 'Remito') }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="letra" class="col-lg-3 col-form-label">Letra</label>
    <div class="col-lg-2">
        <input type="text" id="letra" class="form-control" value="R" readonly
               title="Solo remitos letra R"/>
    </div>
</div>
<div class="form-group row">
    <label for="sucursal" class="col-lg-3 col-form-label requerido">Sucursal</label>
    <div class="col-lg-3">
        <input type="number" name="sucursal" id="sucursal" class="form-control" min="1" max="9999" required
               value="{{ old('sucursal', $data->sucursal ?? 1) }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="numero_cai" class="col-lg-3 col-form-label requerido">N&uacute;mero CAI</label>
    <div class="col-lg-5">
        <input type="text" name="numero_cai" id="numero_cai" class="form-control" maxlength="18" required
               value="{{ old('numero_cai', $data->numero_cai ?? '') }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="fecha_vencimiento" class="col-lg-3 col-form-label requerido">Fecha vencimiento</label>
    <div class="col-lg-4">
        <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" class="form-control" required
               value="{{ old('fecha_vencimiento', isset($data) ? optional($data->fecha_vencimiento)->format('Y-m-d') : '') }}"/>
    </div>
</div>
@if (isset($data))
<div class="form-group row">
    <label for="orden" class="col-lg-3 col-form-label">Orden Anita</label>
    <div class="col-lg-3">
        <input type="text" id="orden" class="form-control" value="{{ $data->orden }}" readonly/>
    </div>
</div>
@endif
