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

@if ($esEdicion)
<div class="form-group row">
    <label class="col-lg-3 control-label text-right pr-2 requerido">Empresa</label>
    <div class="col-lg-6">
        <input type="hidden" name="empresa_id" id="empresa_id" value="{{ $d->empresa_id }}">
        <input type="text" class="form-control" value="{{ optional($d->empresa)->nombre }}" readonly>
        <small class="text-muted">La empresa no se modifica una vez creada la corrida.</small>
    </div>
</div>
@else
@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresas ?? $empresa_query ?? collect(),
    'empresa_id' => old('empresa_id'),
    'col_label' => 'col-lg-3 control-label text-right pr-2',
    'col_input' => 'col-lg-6',
    'required' => true,
])
@endif

{{-- Código Anita (mael_liquidacion). El id de la corrida es el PK automático del ERP. --}}
<div class="form-group row">
    <label for="numero" class="col-lg-3 col-form-label text-right">C&oacute;digo Anita @if($esEdicion)<span class="text-danger">*</span>@endif</label>
    <div class="col-lg-3">
        <input type="number" name="numero" id="numero" class="form-control" min="1"
               value="{{ old('numero', $d->numero ?? ($numeroSugerido ?? '')) }}"
               placeholder="Autom&aacute;tico si se deja vac&iacute;o"
               @if($esEdicion) required @endif
               data-url-proximo="{{ route('proximo_numero_liquidacion_sueldos') }}">
        <small class="form-text text-muted">
            N&deg; de corrida operativo (Anita). El ID interno del ERP es autom&aacute;tico.
            @if(! $esEdicion) Si lo dej&aacute;s vac&iacute;o se asigna el pr&oacute;ximo disponible. @endif
        </small>
    </div>
</div>

{{-- Espejo help_4 l-recibolargo: Todos / Empresa actual / Multiempresa --}}
<div class="form-group row">
    <label for="alcance" class="col-lg-3 col-form-label text-right">Alcance empresas</label>
    <div class="col-lg-6">
        <select name="alcance" id="alcance" class="form-control" @if($esEdicion && !($d->esEditable() ?? true)) disabled @endif>
            @foreach (Liquidacion_Sueldos::ALCANCES as $val => $label)
                <option value="{{ $val }}" {{ old('alcance', $d->alcance ?? 'todos') === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <small class="form-text text-muted">
            Como Anita: <em>Todos</em> liquida/emite la empresa elegida;
            <em>Empresa actual</em> solo legajos mono-empresa;
            <em>Multiempresa</em> solo legajos en varias empresas y al emitir el recibo incluye las otras empresas del mismo per&iacute;odo/tipo.
        </small>
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

@php
    $tipoActual = old('tipo', $d->tipo ?? 'mensual');
    $tipoVacActual = (int) old('tipo_vacaciones', $d->tipo_vacaciones ?? Liquidacion_Sueldos::TIPO_VACACIONES_PLUS);
@endphp
<div class="form-group row" id="bloque-tipo-vacaciones" style="{{ $tipoActual === 'vacaciones' ? '' : 'display:none' }}">
    <label for="tipo_vacaciones" class="col-lg-3 col-form-label text-right">Vacaciones (LVAC) <span class="text-danger">*</span></label>
    <div class="col-lg-6">
        <select name="tipo_vacaciones" id="tipo_vacaciones" class="form-control">
            @foreach (Liquidacion_Sueldos::TIPOS_VACACIONES as $cod => $label)
                <option value="{{ $cod }}" {{ $tipoVacActual === (int) $cod ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <small class="form-text text-muted">
            Anita <code>LVAC</code>: <em>Plus</em> liquida el 422; <em>Adelanto</em> liquida el 420.
            El 430 (descuento) va en la corrida mensual.
        </small>
    </div>
</div>

<div class="form-group row" id="bloque-motivo-egreso" style="{{ $tipoActual === 'final' ? '' : 'display:none' }}">
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
    var bloqueFinal = document.getElementById('bloque-motivo-egreso');
    var bloqueVac = document.getElementById('bloque-tipo-vacaciones');
    var selVac = document.getElementById('tipo_vacaciones');
    if (!sel) return;
    function sync() {
        if (bloqueFinal) bloqueFinal.style.display = sel.value === 'final' ? '' : 'none';
        if (bloqueVac) bloqueVac.style.display = sel.value === 'vacaciones' ? '' : 'none';
        if (selVac) selVac.required = sel.value === 'vacaciones';
        sugerirVentanaDevengado();
    }
    sel.addEventListener('change', sync);
    sync();
})();

/**
 * 1ra quincena → 01–15; 2da → 16–último día; resto → mes completo.
 * Vacaciones: si la descripción dice 1RA/2DA QUINC, usa esa ventana.
 */
function sugerirVentanaDevengado(forzar) {
    var periodo = document.querySelector('input[name="periodo"]');
    var desde = document.querySelector('input[name="periodo_desde"]');
    var hasta = document.querySelector('input[name="periodo_hasta"]');
    var tipo = document.getElementById('tipo_liquidacion');
    var desc = document.querySelector('input[name="descripcion"]');
    if (!periodo || !desde || !hasta || !tipo || !periodo.value) return;
    if (!forzar && desde.value && hasta.value) return;

    var parts = periodo.value.split('-');
    if (parts.length < 2) return;
    var y = parseInt(parts[0], 10);
    var m = parseInt(parts[1], 10);
    if (!y || !m) return;
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    var ultimo = new Date(y, m, 0).getDate();
    var t = tipo.value;
    var dtxt = ((desc && desc.value) || '').toUpperCase();
    var ventana = 'mes';
    if (t === 'quincena_1' || (t === 'vacaciones' && /1\s*RA|1RA|PRIMERA/.test(dtxt))) {
        ventana = '1q';
    } else if (t === 'quincena_2' || (t === 'vacaciones' && /2\s*DA|2DA|SEGUNDA/.test(dtxt))) {
        ventana = '2q';
    }
    if (ventana === '1q') {
        desde.value = y + '-' + pad(m) + '-01';
        hasta.value = y + '-' + pad(m) + '-15';
    } else if (ventana === '2q') {
        desde.value = y + '-' + pad(m) + '-16';
        hasta.value = y + '-' + pad(m) + '-' + pad(ultimo);
    } else {
        desde.value = y + '-' + pad(m) + '-01';
        hasta.value = y + '-' + pad(m) + '-' + pad(ultimo);
    }
}
document.addEventListener('DOMContentLoaded', function () {
    var periodo = document.querySelector('input[name="periodo"]');
    var tipo = document.getElementById('tipo_liquidacion');
    var desc = document.querySelector('input[name="descripcion"]');
    if (periodo) periodo.addEventListener('change', function () { sugerirVentanaDevengado(true); });
    if (tipo) tipo.addEventListener('change', function () { sugerirVentanaDevengado(true); });
    if (desc) desc.addEventListener('change', function () { sugerirVentanaDevengado(true); });
});
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
    <div class="col-lg-9 offset-lg-3">
        <small class="form-text text-muted">
            Quincenas: 1ra = 01 al 15 del mes; 2da = 16 al &uacute;ltimo d&iacute;a. En vacaciones us&aacute; la misma ventana seg&uacute;n la quincena que liquid&aacute;s.
        </small>
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
