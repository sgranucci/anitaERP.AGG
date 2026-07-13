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
    <label for="calle" class="col-lg-3 col-form-label">Calle</label>
    <div class="col-lg-5">
        <input type="text" name="calle" id="calle" class="form-control" maxlength="100"
            value="{{old('calle', $data->calle ?? '')}}"/>
    </div>
    <label for="numero" class="col-lg-1 col-form-label">Nro</label>
    <div class="col-lg-2">
        <input type="text" name="numero" id="numero" class="form-control" maxlength="20"
            value="{{old('numero', $data->numero ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="piso" class="col-lg-3 col-form-label">Piso</label>
    <div class="col-lg-2">
        <input type="text" name="piso" id="piso" class="form-control" maxlength="20"
            value="{{old('piso', $data->piso ?? '')}}"/>
    </div>
    <label for="departamento" class="col-lg-2 col-form-label">Depto</label>
    <div class="col-lg-2">
        <input type="text" name="departamento" id="departamento" class="form-control" maxlength="20"
            value="{{old('departamento', $data->departamento ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo_postal" class="col-lg-3 col-form-label">C&oacute;digo postal</label>
    <div class="col-lg-2">
        <input type="text" name="codigo_postal" id="codigo_postal" class="form-control" maxlength="20"
            value="{{old('codigo_postal', $data->codigo_postal ?? '')}}"/>
    </div>
    <label for="barrio" class="col-lg-2 col-form-label">Barrio</label>
    <div class="col-lg-3">
        <input type="text" name="barrio" id="barrio" class="form-control" maxlength="100"
            value="{{old('barrio', $data->barrio ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="oficinacompra_id" class="col-lg-3 col-form-label">Oficina de compra ERP</label>
    <div class="col-lg-6">
        <select name="oficinacompra_id" id="oficinacompra_id" class="form-control">
            <option value="">Seleccione</option>
            @foreach ($oficinacompras as $oficina)
                <option value="{{$oficina->id}}" {{ (string) old('oficinacompra_id', $data->oficinacompra_id ?? '') === (string) $oficina->id ? 'selected' : '' }}>
                    {{$oficina->nombre}}
                </option>
            @endforeach
        </select>
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
