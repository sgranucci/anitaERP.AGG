<div class="form-group row">
    <label for="codigo_interno_sifab" class="col-lg-3 col-form-label">C&oacute;digo interno SIFAB</label>
    <div class="col-lg-2">
        <input type="number" name="codigo_interno_sifab" id="codigo_interno_sifab" class="form-control"
            value="{{old('codigo_interno_sifab', $data->codigo_interno_sifab ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label">C&oacute;digo</label>
    <div class="col-lg-2">
        <input type="text" name="codigo" id="codigo" class="form-control" maxlength="30"
            value="{{old('codigo', $data->codigo ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="150"
            value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo_interno_cuenta_compra" class="col-lg-3 col-form-label">Cta. compra (SIFAB)</label>
    <div class="col-lg-2">
        <input type="number" name="codigo_interno_cuenta_compra" id="codigo_interno_cuenta_compra" class="form-control"
            value="{{old('codigo_interno_cuenta_compra', $data->codigo_interno_cuenta_compra ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo_interno_cuenta_gasto" class="col-lg-3 col-form-label">Cta. gasto (SIFAB)</label>
    <div class="col-lg-2">
        <input type="number" name="codigo_interno_cuenta_gasto" id="codigo_interno_cuenta_gasto" class="form-control"
            value="{{old('codigo_interno_cuenta_gasto', $data->codigo_interno_cuenta_gasto ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo_interno_cuenta_variacion" class="col-lg-3 col-form-label">Cta. variaci&oacute;n (SIFAB)</label>
    <div class="col-lg-2">
        <input type="number" name="codigo_interno_cuenta_variacion" id="codigo_interno_cuenta_variacion" class="form-control"
            value="{{old('codigo_interno_cuenta_variacion', $data->codigo_interno_cuenta_variacion ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 col-form-label">Subrubro obligatorio</label>
    <div class="col-lg-8">
        <div class="form-check">
            <input type="hidden" name="subrubro_obligatorio" value="0">
            <input type="checkbox" name="subrubro_obligatorio" id="subrubro_obligatorio" class="form-check-input" value="1"
                {{ old('subrubro_obligatorio', $data->subrubro_obligatorio ?? false) ? 'checked' : '' }}>
            <label class="form-check-label" for="subrubro_obligatorio">S&iacute;</label>
        </div>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 col-form-label">Habilitado</label>
    <div class="col-lg-8">
        <div class="form-check">
            <input type="hidden" name="habilitado" value="0">
            <input type="checkbox" name="habilitado" id="habilitado" class="form-check-input" value="1"
                {{ old('habilitado', $data->habilitado ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="habilitado">S&iacute;</label>
        </div>
    </div>
</div>
