<div class="form-group row">
    <label for="codigo_interno_sifab" class="col-lg-3 col-form-label">C&oacute;digo interno SIFAB</label>
    <div class="col-lg-2">
        <input type="number" name="codigo_interno_sifab" id="codigo_interno_sifab" class="form-control"
            value="{{old('codigo_interno_sifab', $data->codigo_interno_sifab ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="linea_id" class="col-lg-3 col-form-label">L&iacute;nea de negocio</label>
    <div class="col-lg-6">
        <select name="linea_id" id="linea_id" class="form-control">
            <option value="">Seleccione</option>
            @foreach ($lineas as $linea)
                <option value="{{$linea->id}}" {{ (string) old('linea_id', $data->linea_id ?? '') === (string) $linea->id ? 'selected' : '' }}>
                    {{$linea->codigo}} — {{$linea->nombre}}
                </option>
            @endforeach
        </select>
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
