@php
    use App\Models\Sueldos\Liquidacion_Sueldos;
    $d = $data ?? null;
    $esEdicion = $d !== null;
    $periodoInput = old('periodo');
    if (! $periodoInput && $d && $d->periodo && strlen((string) $d->periodo) === 6) {
        $periodoInput = substr($d->periodo, 0, 4).'-'.substr($d->periodo, 4, 2);
    }
    $fecha = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d') : '';
@endphp

<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right">Empresa <span class="text-danger">*</span></label>
    <div class="col-lg-6">
        @if ($esEdicion)
            <input type="text" class="form-control" value="{{ optional($d->empresa)->nombre }}" readonly>
            <small class="text-muted">La empresa no se modifica una vez creada la corrida.</small>
        @else
            <select name="empresa_id" class="form-control" required>
                <option value="">— Seleccione —</option>
                @foreach(($empresas ?? []) as $emp)
                    <option value="{{ $emp->id }}" {{ (int) old('empresa_id') === (int) $emp->id ? 'selected' : '' }}>{{ $emp->nombre }}</option>
                @endforeach
            </select>
        @endif
    </div>
</div>

<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right">Descripci&oacute;n <span class="text-danger">*</span></label>
    <div class="col-lg-6">
        <input type="text" name="descripcion" class="form-control" maxlength="60" required
               value="{{ old('descripcion', $d->descripcion ?? '') }}" placeholder="Ej: Sueldos julio 2026">
    </div>
</div>

<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right">Tipo <span class="text-danger">*</span></label>
    <div class="col-lg-6">
        <select name="tipo" id="tipo_liquidacion" class="form-control" required>
            @foreach(Liquidacion_Sueldos::TIPOS as $k => $v)
                <option value="{{ $k }}" {{ old('tipo', $d->tipo ?? 'mensual') === $k ? 'selected' : '' }}>{{ $v }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row" id="bloque-motivo-egreso" style="{{ old('tipo', $d->tipo ?? 'mensual') === 'final' ? '' : 'display:none' }}">
    <label class="col-lg-3 col-form-label text-right">Motivo de egreso</label>
    <div class="col-lg-6">
        <select name="motivoegreso_id" class="form-control">
            <option value="">— Todos los motivos (bajas del per&iacute;odo) —</option>
            @foreach(($motivosegreso ?? []) as $mot)
                <option value="{{ $mot->id }}" {{ (int) old('motivoegreso_id', $d->motivoegreso_id ?? 0) === (int) $mot->id ? 'selected' : '' }}>
                    {{ $mot->codigo }} — {{ $mot->descripcion }}
                    @if(!empty($mot->clase)) ({{ \App\Support\Sueldos\MotivoEgresoClase::etiqueta($mot->clase) }}) @endif
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">
            Solo aplica a corridas tipo <em>Liquidaci&oacute;n final</em>. Filtra empleados de baja del per&iacute;odo.
            Las f&oacute;rmulas usan <code>empleado.motivo_egreso_clase</code> de cada legajo.
        </small>
    </div>
</div>
<script>
(function () {
    var sel = document.getElementById('tipo_liquidacion');
    var bloque = document.getElementById('bloque-motivo-egreso');
    if (!sel || !bloque) return;
    function sync() { bloque.style.display = sel.value === 'final' ? '' : 'none'; }
    sel.addEventListener('change', sync);
})();
</script>

<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right">Per&iacute;odo <span class="text-danger">*</span></label>
    <div class="col-lg-3">
        <input type="month" name="periodo" class="form-control" required value="{{ $periodoInput }}">
    </div>
    <label class="col-lg-2 col-form-label text-right">Devengado</label>
    <div class="col-lg-2">
        <input type="date" name="periodo_desde" class="form-control" value="{{ old('periodo_desde', $fecha($d->periodo_desde ?? null)) }}" title="Desde">
    </div>
    <div class="col-lg-2">
        <input type="date" name="periodo_hasta" class="form-control" value="{{ old('periodo_hasta', $fecha($d->periodo_hasta ?? null)) }}" title="Hasta">
    </div>
</div>

<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right">Fecha liquidaci&oacute;n</label>
    <div class="col-lg-3">
        <input type="date" name="fecha_liquidacion" class="form-control" value="{{ old('fecha_liquidacion', $fecha($d->fecha_liquidacion ?? null)) }}">
    </div>
    <label class="col-lg-2 col-form-label text-right">Fecha pago</label>
    <div class="col-lg-3">
        <input type="date" name="fecha_pago" class="form-control" value="{{ old('fecha_pago', $fecha($d->fecha_pago ?? null)) }}">
    </div>
</div>

<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right">Lugar de pago</label>
    <div class="col-lg-6">
        <input type="text" name="lugar_pago" class="form-control" maxlength="60" value="{{ old('lugar_pago', $d->lugar_pago ?? '') }}">
    </div>
</div>

<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right">Opciones</label>
    <div class="col-lg-6">
        <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="simulacion" name="simulacion" value="1"
                   {{ old('simulacion', $d->simulacion ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="simulacion">Simulaci&oacute;n (no impacta acumuladores)</label>
        </div>
        <div class="custom-control custom-checkbox mt-1">
            <input type="checkbox" class="custom-control-input" id="acumula_novedades" name="acumula_novedades" value="1"
                   {{ old('acumula_novedades', $d->acumula_novedades ?? true) ? 'checked' : '' }}>
            <label class="custom-control-label" for="acumula_novedades">Acumula novedades del per&iacute;odo</label>
        </div>
    </div>
</div>

<div class="card card-outline card-secondary mt-2">
    <div class="card-header py-1"><h3 class="card-title">Dep&oacute;sito bancario (opcional)</h3></div>
    <div class="card-body">
        <div class="form-group row mb-1">
            <label class="col-lg-3 col-form-label text-right">Banco</label>
            <div class="col-lg-6">
                <input type="text" name="banco_deposito" class="form-control" maxlength="60" value="{{ old('banco_deposito', $d->banco_deposito ?? '') }}">
            </div>
        </div>
        <div class="form-group row mb-1">
            <label class="col-lg-3 col-form-label text-right">Per&iacute;odo dep&oacute;sito</label>
            <div class="col-lg-3">
                <input type="text" name="periodo_deposito" class="form-control" maxlength="15" value="{{ old('periodo_deposito', $d->periodo_deposito ?? '') }}">
            </div>
            <label class="col-lg-2 col-form-label text-right">&Uacute;lt. dep&oacute;sito</label>
            <div class="col-lg-3">
                <input type="date" name="fecha_ultimo_deposito" class="form-control" value="{{ old('fecha_ultimo_deposito', $fecha($d->fecha_ultimo_deposito ?? null)) }}">
            </div>
        </div>
    </div>
</div>

<div class="form-group row mt-2">
    <label class="col-lg-3 col-form-label text-right">Observaci&oacute;n</label>
    <div class="col-lg-6">
        <textarea name="observacion" class="form-control" rows="2" maxlength="2000">{{ old('observacion', $d->observacion ?? '') }}</textarea>
    </div>
</div>
