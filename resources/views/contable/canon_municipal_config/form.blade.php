@php
    $colLabel = 'col-lg-4 control-label text-right pr-2';
    $colInput = 'col-lg-6';
@endphp

@include('includes.form-error')

@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => old('empresa_id', $data->empresa_id ?? null),
    'solo_lectura' => isset($data),
    'col_label' => 'col-lg-4 text-right pr-2',
    'col_input' => $colInput,
    'required' => true,
])

<div class="form-group row">
    <label for="municipio" class="{{ $colLabel }} requerido">Municipio</label>
    <div class="{{ $colInput }}">
        <input type="text" name="municipio" id="municipio" class="form-control"
               value="{{ old('municipio', $data->municipio ?? '') }}" required maxlength="120">
    </div>
</div>

<div class="form-group row">
    <label for="legajo" class="{{ $colLabel }} requerido">Legajo municipal</label>
    <div class="{{ $colInput }}">
        <input type="text" name="legajo" id="legajo" class="form-control"
               value="{{ old('legajo', $data->legajo ?? '') }}" required maxlength="40">
    </div>
</div>

<div class="form-group row">
    <label for="periodicidad" class="{{ $colLabel }} requerido">Periodicidad</label>
    <div class="{{ $colInput }}">
        <select name="periodicidad" id="periodicidad" class="form-control" required>
            @foreach ($periodicidad_enum as $val => $label)
                <option value="{{ $val }}" @selected(old('periodicidad', $data->periodicidad ?? 'semanal') === $val)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row">
    <label for="plantilla" class="{{ $colLabel }} requerido">Plantilla de nota</label>
    <div class="{{ $colInput }}">
        <select name="plantilla" id="plantilla" class="form-control" required>
            @foreach ($plantilla_enum as $val => $label)
                <option value="{{ $val }}" @selected(old('plantilla', $data->plantilla ?? 'biyemas') === $val)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row">
    <label for="alicuota" class="{{ $colLabel }} requerido">Alícuota</label>
    <div class="{{ $colInput }}">
        <input type="number" step="0.0001" min="0.0001" max="1" name="alicuota" id="alicuota" class="form-control"
               value="{{ old('alicuota', $data->alicuota ?? 0.04) }}" required>
        <small class="form-text text-muted">Ej. 0,04 = 4%</small>
    </div>
</div>

<div class="form-group row">
    <label for="firmante_nombre" class="{{ $colLabel }} requerido">Firmante</label>
    <div class="{{ $colInput }}">
        <input type="text" name="firmante_nombre" id="firmante_nombre" class="form-control"
               value="{{ old('firmante_nombre', $data->firmante_nombre ?? 'Marisol Gonzalez') }}" required maxlength="120">
    </div>
</div>

<div class="form-group row">
    <label for="firmante_cargo" class="{{ $colLabel }} requerido">Cargo</label>
    <div class="{{ $colInput }}">
        <input type="text" name="firmante_cargo" id="firmante_cargo" class="form-control"
               value="{{ old('firmante_cargo', $data->firmante_cargo ?? 'Impuestos') }}" required maxlength="80">
    </div>
</div>

<div class="form-group row">
    <label for="pie_razon_social" class="{{ $colLabel }}">Razón social al pie</label>
    <div class="{{ $colInput }}">
        <input type="text" name="pie_razon_social" id="pie_razon_social" class="form-control"
               value="{{ old('pie_razon_social', $data->pie_razon_social ?? '') }}" maxlength="120">
    </div>
</div>

<div class="form-group row">
    <label for="direccion_extra" class="{{ $colLabel }}">Dirección extra (membrete)</label>
    <div class="{{ $colInput }}">
        <input type="text" name="direccion_extra" id="direccion_extra" class="form-control"
               value="{{ old('direccion_extra', $data->direccion_extra ?? '') }}" maxlength="255"
               placeholder="Ej. B1875ABF, Wilde, Buenos Aires">
    </div>
</div>

<div class="form-group row">
    <label for="telefono" class="{{ $colLabel }}">Teléfono</label>
    <div class="{{ $colInput }}">
        <input type="text" name="telefono" id="telefono" class="form-control"
               value="{{ old('telefono', $data->telefono ?? '') }}" maxlength="80">
    </div>
</div>

<div class="form-group row">
    <div class="{{ $colLabel }}"></div>
    <div class="{{ $colInput }}">
        <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" name="activo" id="activo" value="1"
                @checked(old('activo', $data->activo ?? true))>
            <label class="custom-control-label" for="activo">Activo</label>
        </div>
    </div>
</div>
