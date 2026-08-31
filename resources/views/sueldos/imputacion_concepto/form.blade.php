@php
    use App\Support\Sueldos\ConceptoTipo;
    use App\Support\Sueldos\RubroCostoLaboral;
    use App\Support\Sueldos\SueldosAsientoMapeoSupport;
    $d = $data ?? null;
    $alcance = old('alcance', $d->alcance ?? SueldosAsientoMapeoSupport::ALCANCE_TIPO);
    $colLabel = 'col-lg-3 control-label text-right pr-2';
    $colInput = 'col-lg-8';
@endphp

@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query ?? collect(),
    'empresa_id' => old('empresa_id', $d->empresa_id ?? null),
    'label' => 'Empresa',
    'col_label' => $colLabel,
    'col_input' => $colInput,
    'required' => true,
    'permite_vacio' => false,
    'solo_lectura' => isset($data),
])

<div class="form-group row">
    <label for="alcance" class="{{ $colLabel }} requerido">Alcance</label>
    <div class="col-lg-5">
        <select name="alcance" id="alcance" class="form-control" required>
            @foreach (SueldosAsientoMapeoSupport::ALCANCES as $val => $label)
                <option value="{{ $val }}" {{ $alcance === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <small class="form-text text-muted">Override de un concepto, de un rubro de cargas o fallback por tipo.</small>
    </div>
</div>

<div id="bloque-alcance-concepto" class="{{ $alcance === SueldosAsientoMapeoSupport::ALCANCE_CONCEPTO ? '' : 'd-none' }}">
    @include('sueldos.partials.campo_consulta_concepto_sueldos', [
        'prefix' => 'imputacion',
        'layout' => 'form_row',
        'inputName' => 'concepto_id',
        'inputId' => 'concepto_sueldos_id',
        'conceptoId' => old('concepto_id', $d->concepto_id ?? ''),
        'codigo' => old('concepto_codigo', $d->concepto->codigo ?? ''),
        'descripcion' => old('concepto_descripcion', $d->concepto->descripcion ?? ''),
        'label' => 'Concepto',
        'col_label' => $colLabel,
        'col_input' => $colInput,
        'required' => $alcance === SueldosAsientoMapeoSupport::ALCANCE_CONCEPTO,
    ])
</div>

<div id="bloque-alcance-rubro" class="form-group row {{ $alcance === SueldosAsientoMapeoSupport::ALCANCE_RUBRO ? '' : 'd-none' }}">
    <label for="rubro" class="{{ $colLabel }}">Rubro costo laboral</label>
    <div class="col-lg-5">
        <select name="rubro" id="rubro" class="form-control">
            <option value="">— Elija rubro —</option>
            @foreach (RubroCostoLaboral::ETIQUETAS as $val => $label)
                <option value="{{ $val }}" {{ old('rubro', $d->rubro ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

<div id="bloque-alcance-tipo" class="form-group row {{ $alcance === SueldosAsientoMapeoSupport::ALCANCE_TIPO ? '' : 'd-none' }}">
    <label for="tipo" class="{{ $colLabel }}">Tipo de concepto</label>
    <div class="col-lg-5">
        <select name="tipo" id="tipo" class="form-control">
            <option value="">— Elija tipo —</option>
            @foreach (SueldosAsientoMapeoSupport::tiposImputables() as $val)
                <option value="{{ $val }}" {{ old('tipo', $d->tipo ?? '') === $val ? 'selected' : '' }}>{{ ConceptoTipo::etiquetaTipo($val) }}</option>
            @endforeach
        </select>
    </div>
</div>

@include('sueldos.partials.campo_consulta_cuentacontable', [
    'label' => 'Cuenta debe',
    'inputName' => 'cuenta_debe_id',
    'inputId' => 'cuenta_debe_id',
    'cuentaId' => old('cuenta_debe_id', $d->cuenta_debe_id ?? ''),
    'codigo' => old('cuenta_debe_codigo', $d->cuentaDebe->codigo ?? ''),
    'descripcion' => old('cuenta_debe_nombre', $d->cuentaDebe->nombre ?? ''),
    'col_label' => $colLabel,
    'col_input' => $colInput,
])

@include('sueldos.partials.campo_consulta_cuentacontable', [
    'label' => 'Cuenta haber',
    'inputName' => 'cuenta_haber_id',
    'inputId' => 'cuenta_haber_id',
    'cuentaId' => old('cuenta_haber_id', $d->cuenta_haber_id ?? ''),
    'codigo' => old('cuenta_haber_codigo', $d->cuentaHaber->codigo ?? ''),
    'descripcion' => old('cuenta_haber_nombre', $d->cuentaHaber->nombre ?? ''),
    'col_label' => $colLabel,
    'col_input' => $colInput,
])

<div class="form-group row">
    <label for="observacion" class="{{ $colLabel }}">Observaci&oacute;n</label>
    <div class="{{ $colInput }}">
        <input type="text" name="observacion" id="observacion" class="form-control" maxlength="160"
               value="{{ old('observacion', $d->observacion ?? '') }}">
    </div>
</div>

<p class="text-muted small mb-0 pl-lg-3">
    Haberes (remunerativo / no remunerativo / asignaci&oacute;n): suele ir solo el <strong>debe</strong> (gasto).
    Descuentos, aportes y retenciones: solo el <strong>haber</strong> (pasivo).
    Contribuciones: ambas patas. El neto no se mapea: va a la pata fija <code>sueldos.a_pagar</code>.
</p>
