<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label">Código Anita</label>
    <div class="col-lg-3">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" readonly/>
    </div>
</div>
<div class="form-group row">
    <label for="dominio" class="col-lg-3 col-form-label requerido">Dominio / patente</label>
    <div class="col-lg-3">
        <input type="text" name="dominio" id="dominio" class="form-control" maxlength="15" value="{{old('dominio', $data->dominio ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="habilitacion" class="col-lg-3 col-form-label">Habilitación SENASA</label>
    <div class="col-lg-4">
        <input type="text" name="habilitacion" id="habilitacion" class="form-control" maxlength="30" value="{{old('habilitacion', $data->habilitacion ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="tipo" class="col-lg-3 col-form-label">Tipo</label>
    <div class="col-lg-3">
        <input type="text" name="tipo" id="tipo" class="form-control" maxlength="15" value="{{old('tipo', $data->tipo ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="dominio_acoplado" class="col-lg-3 col-form-label">Dominio acoplado</label>
    <div class="col-lg-3">
        <input type="text" name="dominio_acoplado" id="dominio_acoplado" class="form-control" maxlength="10" value="{{old('dominio_acoplado', $data->dominio_acoplado ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="cuit_chofer" class="col-lg-3 col-form-label">CUIT chofer</label>
    <div class="col-lg-3">
        <input type="text" name="cuit_chofer" id="cuit_chofer" class="form-control" maxlength="13" value="{{old('cuit_chofer', $data->cuit_chofer ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="cantidad_precinto" class="col-lg-3 col-form-label">Cant. precintos</label>
    <div class="col-lg-2">
        <input type="number" name="cantidad_precinto" id="cantidad_precinto" class="form-control" min="0" max="99" value="{{old('cantidad_precinto', $data->cantidad_precinto ?? 0)}}"/>
    </div>
</div>
