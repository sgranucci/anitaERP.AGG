<div class="form1">
    <div class="form-group row">
        <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
        <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
        </div>
    </div>
    <div class="form-group row">
        <label for="valor" class="col-lg-3 col-form-label requerido">Valor</label>
        <div class="col-lg-2">
        <input type="number" name="valor" id="valor" class="form-control" value="{{old('valor', $data->valor ?? '0')}}" required/>
        </div>
    </div>
    <div class="form-group row">
        <label for="fechavigencia" class="col-lg-3 col-form-label requerido">Fecha de vigencia</label>
        <div class="col-lg-8">
            <input type="date" name="fechavigencia" id="fechavigencia" class="form-control" value="{{old('fechavigencia', $data->fechavigencia ?? 0)}}" required/>
        </div>
    </div>
    <div class="form-group row">
        <label for="codigo" class="col-lg-3 col-form-label requerido">C&oacute;digo Anita</label>
        <div class="col-lg-2">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" required/>
        </div>
    </div>
    <div class="form-group row">
        <label for="codigoarca" class="col-lg-3 col-form-label requerido">C&oacute;digo ARCA</label>
        <div class="col-lg-2">
        <input type="text" name="codigoarca" id="codigoarca" class="form-control" value="{{old('codigoarca', $data->codigoarca ?? '')}}" required/>
        </div>
    </div>
    @if (isset($data) && ($data->codigo ?? '') === 'PNC')
        <div class="form-group row">
            <div class="col-lg-3"></div>
            <div class="col-lg-8">
                <div class="alert alert-info mb-0">
                    Impuesto de percepción RG 2126 (sujeto no categorizado). La alícuota y el mínimo se editan
                    en el listado de Impuestos. Acá se cargan las cuentas del asiento por empresa.
                </div>
            </div>
        </div>
    @endif
</div>
