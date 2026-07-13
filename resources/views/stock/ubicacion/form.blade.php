<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">C&oacute;digo</label>
    <div class="col-lg-2">
        <input type="text" name="codigo" id="codigo" class="form-control" maxlength="6"
            value="{{old('codigo', $data->codigo ?? '')}}" required
            @if (isset($data)) readonly @endif />
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Descripci&oacute;n</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="30"
            value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="zona" class="col-lg-3 col-form-label">Zona / pasillo</label>
    <div class="col-lg-2">
        <input type="text" name="zona" id="zona" class="form-control" maxlength="6"
            value="{{old('zona', $data->zona ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="area" class="col-lg-3 col-form-label">&Aacute;rea</label>
    <div class="col-lg-2">
        <input type="text" name="area" id="area" class="form-control" maxlength="6"
            value="{{old('area', $data->area ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="nivel" class="col-lg-3 col-form-label">Nivel</label>
    <div class="col-lg-2">
        <input type="text" name="nivel" id="nivel" class="form-control" maxlength="6"
            value="{{old('nivel', $data->nivel ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="estado" class="col-lg-3 col-form-label">Estado</label>
    <div class="col-lg-3">
        @php
            $estadoActual = old('estado', $data->estado ?? \App\Models\Stock\Ubicacion::ESTADO_ACTIVA);
            if ($estadoActual === '' || $estadoActual === 'A') {
                $estadoActual = \App\Models\Stock\Ubicacion::ESTADO_ACTIVA;
            }
        @endphp
        <select name="estado" id="estado" class="form-control">
            <option value=" " @if ($estadoActual === ' ' || $estadoActual === \App\Models\Stock\Ubicacion::ESTADO_ACTIVA) selected @endif>Activa</option>
            <option value="I" @if ($estadoActual === 'I') selected @endif>Inactiva</option>
        </select>
    </div>
</div>
