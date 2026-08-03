@php
    use App\Support\Sueldos\NovedadSueldosCatalogo;
    $d = $data ?? null;
    $empresaId = old('empresa_id', $d->empresa_id ?? optional($liquidacionPrefill ?? null)->empresa_id);
    $liqId = old('liquidacion_id', $d->liquidacion_id ?? optional($liquidacionPrefill ?? null)->id);
@endphp

@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresas ?? $empresa_query ?? collect(),
    'empresa_id' => $empresaId,
    'col_label' => 'col-lg-3 control-label text-right pr-2',
    'col_input' => 'col-lg-6',
    'required' => true,
])

<div class="form-group row">
    <label for="liquidacion_id" class="col-lg-3 control-label text-right pr-2">Corrida de liquidaci&oacute;n</label>
    <div class="col-lg-6">
        <select name="liquidacion_id" id="liquidacion_id" class="form-control">
            <option value="">— Sin corrida (solo per&iacute;odo) —</option>
            @foreach(($liquidaciones ?? []) as $liq)
                <option value="{{ $liq->id }}" {{ (int) $liqId === (int) $liq->id ? 'selected' : '' }}>
                    N&deg; {{ $liq->numero }} · {{ $liq->periodo }} · {{ $liq->descripcion }} ({{ $liq->estado }})
                </option>
            @endforeach
        </select>
    </div>
</div>

@if (!empty($liquidacionPrefill))
    <input type="hidden" name="retorno_liquidacion_id" value="{{ $liquidacionPrefill->id }}">
@endif

<div class="form-group row">
    <label for="empleado_id" class="col-lg-3 col-form-label requerido">Empleado</label>
    <div class="col-lg-6">
        <select name="empleado_id" id="empleado_id" class="form-control" required>
            <option value="">— Seleccione empresa primero —</option>
            @foreach(($empleados ?? []) as $emp)
                <option value="{{ $emp->id }}" {{ (int) old('empleado_id', $d->empleado_id ?? 0) === (int) $emp->id ? 'selected' : '' }}>
                    {{ $emp->legajo }} — {{ $emp->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>

@include('sueldos.partials.campo_consulta_concepto_sueldos', [
    'layout' => 'form_row',
    'label' => 'Concepto',
    'inputName' => 'concepto_id',
    'inputId' => 'concepto_sueldos_id',
    'conceptoId' => old('concepto_id', $d->concepto_id ?? ''),
    'codigo' => old('concepto_codigo', optional($d->concepto ?? null)->codigo ?? ''),
    'descripcion' => old('concepto_descripcion', optional($d->concepto ?? null)->descripcion ?? ''),
    'required' => true,
    'col_label' => 'col-lg-3',
    'col_input' => 'col-lg-6',
])

<div class="form-group row">
    <label for="valor1" class="col-lg-3 col-form-label">Valor 1 <small class="text-muted">(V)</small></label>
    <div class="col-lg-3">
        <input type="number" step="0.0001" name="valor1" id="valor1" class="form-control"
               value="{{ old('valor1', $d->valor1 ?? 0) }}">
    </div>
    <label for="valor2" class="col-lg-2 col-form-label">Valor 2 <small class="text-muted">(P)</small></label>
    <div class="col-lg-3">
        <input type="number" step="0.0001" name="valor2" id="valor2" class="form-control"
               value="{{ old('valor2', $d->valor2 ?? 0) }}">
    </div>
</div>

<div class="form-group row">
    <label for="estado" class="col-lg-3 col-form-label requerido">Estado</label>
    <div class="col-lg-3">
        <select name="estado" id="estado" class="form-control" required>
            @foreach(($estados ?? NovedadSueldosCatalogo::ESTADOS) as $cod => $label)
                <option value="{{ $cod }}" {{ old('estado', $d->estado ?? 'pendiente') === $cod ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <label for="fecha_vto" class="col-lg-2 col-form-label">Fecha vto.</label>
    <div class="col-lg-3">
        <input type="date" name="fecha_vto" id="fecha_vto" class="form-control"
               value="{{ old('fecha_vto', isset($d->fecha_vto) && $d->fecha_vto ? \Illuminate\Support\Carbon::parse($d->fecha_vto)->format('Y-m-d') : '') }}">
    </div>
</div>

<div class="form-group row">
    <label for="fecha_desde" class="col-lg-3 col-form-label">Vigente desde</label>
    <div class="col-lg-3">
        <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
               value="{{ old('fecha_desde', isset($d->fecha_desde) && $d->fecha_desde ? \Illuminate\Support\Carbon::parse($d->fecha_desde)->format('Y-m-d') : '') }}">
        <small class="form-text text-muted">Si completa &laquo;desde&raquo;, se repite en cada corrida (tipo SAP 0014). Sin fechas = solo esta corrida/per&iacute;odo.</small>
    </div>
    <label for="fecha_hasta" class="col-lg-2 col-form-label">Vigente hasta</label>
    <div class="col-lg-3">
        <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
               value="{{ old('fecha_hasta', isset($d->fecha_hasta) && $d->fecha_hasta ? \Illuminate\Support\Carbon::parse($d->fecha_hasta)->format('Y-m-d') : '') }}">
        <small class="form-text text-muted">Vac&iacute;o = sin corte (ongoing).</small>
    </div>
</div>

<div class="form-group row">
    <label for="nro_interno" class="col-lg-3 col-form-label">Nro. interno</label>
    <div class="col-lg-3">
        <input type="number" min="0" name="nro_interno" id="nro_interno" class="form-control"
               value="{{ old('nro_interno', $d->nro_interno ?? 0) }}">
    </div>
    <label for="origen" class="col-lg-2 col-form-label">Origen</label>
    <div class="col-lg-3">
        <select name="origen" id="origen" class="form-control">
            @foreach(($origenes ?? NovedadSueldosCatalogo::ORIGENES) as $cod => $label)
                <option value="{{ $cod }}" {{ old('origen', $d->origen ?? 'manual') === $cod ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row">
    <label for="observacion" class="col-lg-3 col-form-label">Observaci&oacute;n</label>
    <div class="col-lg-8">
        <input type="text" name="observacion" id="observacion" class="form-control" maxlength="500"
               value="{{ old('observacion', $d->observacion ?? '') }}">
    </div>
</div>

<script>
(function () {
    var urlEmp = @json(route('empleados_empresa_novedad_sueldos'));
    var urlLiq = @json(route('liquidaciones_empresa_novedad_sueldos'));
    var $empresa = $('#empresa_id');
    var $emp = $('#empleado_id');
    var $liq = $('#liquidacion_id');
    var empSel = @json((int) old('empleado_id', $d->empleado_id ?? 0));
    var liqSel = @json((int) $liqId);

    function cargarDependientes() {
        var eid = $empresa.val();
        if (!eid) {
            $emp.html('<option value="">— Seleccione empresa primero —</option>');
            $liq.html('<option value="">— Sin corrida —</option>');
            return;
        }
        $.get(urlEmp, { empresa_id: eid }).done(function (items) {
            var html = '<option value="">— Seleccione —</option>';
            (items || []).forEach(function (it) {
                html += '<option value="' + it.id + '"' + (parseInt(it.id, 10) === empSel ? ' selected' : '') + '>' + it.texto + '</option>';
            });
            $emp.html(html);
        });
        $.get(urlLiq, { empresa_id: eid }).done(function (items) {
            var html = '<option value="">— Sin corrida (solo período) —</option>';
            (items || []).forEach(function (it) {
                html += '<option value="' + it.id + '"' + (parseInt(it.id, 10) === liqSel ? ' selected' : '') + '>' + it.texto + '</option>';
            });
            $liq.html(html);
        });
    }

    $empresa.on('change', function () {
        empSel = 0;
        liqSel = 0;
        cargarDependientes();
    });
})();
</script>
