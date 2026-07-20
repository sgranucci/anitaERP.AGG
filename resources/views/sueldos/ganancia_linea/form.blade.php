@php
    use App\Models\Sueldos\Ganancia_Linea_Sueldos;
    $origenActual = old('origen', $data->origen ?? 'formula');
@endphp
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">C&oacute;digo</label>
    <div class="col-lg-4">
        <input type="text" name="codigo" id="codigo" class="form-control text-uppercase" maxlength="40" required
               value="{{ old('codigo', $data->codigo ?? '') }}"
               placeholder="Ej. REMUNERACION"/>
    </div>
</div>
<div class="form-group row">
    <label for="descripcion" class="col-lg-3 col-form-label requerido">Descripci&oacute;n</label>
    <div class="col-lg-6">
        <input type="text" name="descripcion" id="descripcion" class="form-control" maxlength="80" required
               value="{{ old('descripcion', $data->descripcion ?? '') }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="orden" class="col-lg-3 col-form-label">Orden</label>
    <div class="col-lg-3">
        <input type="number" name="orden" id="orden" class="form-control" min="0"
               value="{{ old('orden', $data->orden ?? 0) }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="origen" class="col-lg-3 col-form-label requerido">Origen</label>
    <div class="col-lg-6">
        <select name="origen" id="origen" class="form-control" required>
            @foreach (Ganancia_Linea_Sueldos::ORIGENES as $val => $label)
                <option value="{{ $val }}" {{ $origenActual === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="formula" class="col-lg-3 col-form-label">F&oacute;rmula</label>
    <div class="col-lg-8">
        <textarea name="formula" id="formula" class="form-control" rows="4"
                  placeholder="Expresi&oacute;n cuando origen = f&oacute;rmula">{{ old('formula', $data->formula ?? '') }}</textarea>
    </div>
</div>
<div class="form-group row">
    <label for="deduccion_codigo" class="col-lg-3 col-form-label">C&oacute;d. deducci&oacute;n Art. 30</label>
    <div class="col-lg-4">
        <select name="deduccion_codigo" id="deduccion_codigo" class="form-control">
            <option value="">— Ninguno —</option>
            @foreach ($deduccionesCatalogo ?? [] as $ded)
                <option value="{{ $ded->codigo }}"
                    {{ strtoupper((string) old('deduccion_codigo', $data->deduccion_codigo ?? '')) === $ded->codigo ? 'selected' : '' }}>
                    {{ $ded->codigo }} — {{ $ded->descripcion }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="concepto_afip" class="col-lg-3 col-form-label">Concepto AFIP</label>
    <div class="col-lg-3">
        <input type="text" name="concepto_afip" id="concepto_afip" class="form-control" maxlength="10"
               value="{{ old('concepto_afip', $data->concepto_afip ?? '') }}"/>
    </div>
</div>
<div class="form-group row">
    <div class="col-lg-3 col-form-label">Opciones</div>
    <div class="col-lg-6">
        <div class="custom-control custom-checkbox mb-2">
            <input type="checkbox" class="custom-control-input" name="activo" id="activo" value="1"
                   {{ old('activo', $data->activo ?? true) ? 'checked' : '' }}>
            <label class="custom-control-label" for="activo">Activo</label>
        </div>
        <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" name="va_planilla" id="va_planilla" value="1"
                   {{ old('va_planilla', $data->va_planilla ?? true) ? 'checked' : '' }}>
            <label class="custom-control-label" for="va_planilla">Visible en planilla</label>
        </div>
    </div>
</div>
