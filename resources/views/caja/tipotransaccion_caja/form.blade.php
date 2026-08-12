<div class="form-group row">
    <label for="nombre" class="col-lg-3 control-label text-right pr-2 requerido">Nombre</label>
    <div class="col-lg-6">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required maxlength="255"/>
    </div>
</div>
<div class="form-group row">
    <label for="abreviatura" class="col-lg-3 control-label text-right pr-2 requerido">Abreviatura</label>
    <div class="col-lg-2">
        <input type="text" name="abreviatura" id="abreviatura" class="form-control" value="{{ old('abreviatura', $data->abreviatura ?? '') }}" required maxlength="5"/>
    </div>
</div>
<div class="form-group row">
    <label for="operacion" class="col-lg-3 control-label text-right pr-2 requerido">Operaci&oacute;n</label>
    <div class="col-lg-4">
        <select name="operacion" id="operacion" class="form-control" required>
            <option value="">-- Elija operaci&oacute;n --</option>
            @foreach($operacionEnum as $value => $operacion)
                <option value="{{ $value }}" {{ (string) $value === (string) old('operacion', $data->operacion ?? '') ? 'selected' : '' }}>
                    {{ $operacion }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="signo" class="col-lg-3 control-label text-right pr-2 requerido">Signo</label>
    <div class="col-lg-4">
        <select name="signo" id="signo" class="form-control" required>
            <option value="">-- Elija signo --</option>
            @foreach($signoEnum as $value => $signo)
                <option value="{{ $value }}" {{ (string) $value === (string) old('signo', $data->signo ?? '') ? 'selected' : '' }}>
                    {{ $signo }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="estado" class="col-lg-3 control-label text-right pr-2 requerido">Estado</label>
    <div class="col-lg-4">
        <select name="estado" id="estado" class="form-control" required>
            <option value="">-- Elija estado --</option>
            @foreach($estadoEnum as $value => $estado)
                <option value="{{ $value }}" {{ (string) $value === (string) old('estado', $data->estado ?? '') ? 'selected' : '' }}>
                    {{ $estado }}
                </option>
            @endforeach
        </select>
    </div>
</div>
