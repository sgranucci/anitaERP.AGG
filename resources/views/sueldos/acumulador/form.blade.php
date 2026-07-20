@php
    use App\Support\Sueldos\ConceptoTipo;
    $tiposSeleccionados = old('tipos_incluye', $data->tipos_incluye ?? []);
    if (! is_array($tiposSeleccionados)) {
        $tiposSeleccionados = [];
    }
    $esReservado = isset($data) && $data->reservado;
@endphp
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">C&oacute;digo</label>
    <div class="col-lg-4">
        @if ($esReservado)
            <input type="hidden" name="codigo" value="{{ $data->codigo }}"/>
            <input type="text" id="codigo" class="form-control" value="{{ $data->codigo }}" readonly/>
            <span class="badge badge-secondary mt-1">Reservado</span>
        @elseif (isset($data))
            <input type="text" name="codigo" id="codigo" class="form-control text-uppercase" maxlength="30" required
                   value="{{ old('codigo', $data->codigo ?? '') }}"/>
        @else
            <input type="text" name="codigo" id="codigo" class="form-control text-uppercase" maxlength="30" required
                   value="{{ old('codigo') }}"
                   placeholder="Ej. BASE_SAC"/>
        @endif
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
    <label class="col-lg-3 col-form-label">Tipos incluidos</label>
    <div class="col-lg-8">
        <div class="row">
            @foreach (ConceptoTipo::TIPOS as $val => $label)
                <div class="col-md-6 col-lg-4">
                    <div class="custom-control custom-checkbox mb-1">
                        <input type="checkbox" class="custom-control-input" name="tipos_incluye[]" id="tipo_{{ $val }}"
                               value="{{ $val }}" {{ in_array($val, $tiposSeleccionados, true) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="tipo_{{ $val }}">{{ $label }}</label>
                    </div>
                </div>
            @endforeach
        </div>
        <small class="form-text text-muted">Tipos de concepto que alimentan este acumulador en la liquidaci&oacute;n.</small>
    </div>
</div>
<div class="form-group row">
    <label for="signo" class="col-lg-3 col-form-label requerido">Signo</label>
    <div class="col-lg-3">
        <select name="signo" id="signo" class="form-control" required>
            <option value="1" {{ (int) old('signo', $data->signo ?? 1) === 1 ? 'selected' : '' }}>+1 (suma)</option>
            <option value="-1" {{ (int) old('signo', $data->signo ?? 1) === -1 ? 'selected' : '' }}>-1 (resta)</option>
        </select>
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
    <div class="col-lg-3 col-form-label">Estado</div>
    <div class="col-lg-6">
        <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" name="activo" id="activo" value="1"
                   {{ old('activo', $data->activo ?? true) ? 'checked' : '' }}>
            <label class="custom-control-label" for="activo">Activo</label>
        </div>
    </div>
</div>
