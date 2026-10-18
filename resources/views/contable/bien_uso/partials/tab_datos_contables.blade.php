@php
    $tieneContable = \Illuminate\Support\Facades\Schema::hasColumn('bien_uso', 'codigo_bien');
    $rubro = optional($data->rubro ?? null);
    $subrubro = optional($data->subrubro ?? null);
    $ctaOrigen = optional($data->cuentaOrigen ?? null);
    $ctaAmort = optional($data->cuentaAmort ?? null);
    $ctaContrap = optional($data->cuentaContrap ?? null);
    $cc = optional($data->centrocostos ?? null);
    $bienReemp = optional($data->bienReemplazo ?? null);
    $amortOpciones = \App\Support\Contable\BienUsoAmortizacionUnidad::opciones();
@endphp

@if ($tieneContable)
<div class="form-group row">
    <label for="codigo_bien" class="col-lg-4 control-label text-right pr-2">C&oacute;digo bien</label>
    <div class="col-lg-3">
        <input type="number" name="codigo_bien" id="codigo_bien" class="form-control" min="1"
               value="{{ old('codigo_bien', $data->codigo_bien ?? '') }}">
    </div>
</div>

<div class="form-group row">
    <label for="descripcion" class="col-lg-4 control-label text-right pr-2">Descripci&oacute;n</label>
    <div class="col-lg-7">
        <input type="text" name="descripcion" id="descripcion" class="form-control" maxlength="80"
               value="{{ old('descripcion', $data->descripcion ?? '') }}">
    </div>
</div>

@include('contable.partials.campo_consulta_bien_uso_rubro', [
    'prefix' => 'bien',
    'rubroId' => old('bien_uso_rubro_id', $data->bien_uso_rubro_id ?? ''),
    'codigo' => old('rubro_codigo', $rubro->codigo ?? ''),
    'nombre' => old('rubro_nombre', $rubro->nombre ?? ''),
    'col_label' => 'col-lg-4 control-label text-right pr-2',
    'col_input' => 'col-lg-7',
])

@include('contable.partials.campo_consulta_bien_uso_subrubro', [
    'prefix' => 'bien',
    'subrubroId' => old('bien_uso_subrubro_id', $data->bien_uso_subrubro_id ?? ''),
    'codigo' => old('subrubro_codigo', $subrubro->codigo ?? ''),
    'nombre' => old('subrubro_nombre', $subrubro->nombre ?? ''),
    'col_label' => 'col-lg-4 control-label text-right pr-2',
    'col_input' => 'col-lg-7',
])

<div class="form-group row">
    <label for="fecha_alta" class="col-lg-4 control-label text-right pr-2">Fecha alta</label>
    <div class="col-lg-3">
        <input type="date" name="fecha_alta" id="fecha_alta" class="form-control"
               value="{{ old('fecha_alta', optional($data->fecha_alta ?? null)->format('Y-m-d') ?? '') }}">
    </div>
    <label for="fecha_compra" class="col-lg-2 control-label text-right pr-2">Fecha compra</label>
    <div class="col-lg-3">
        <input type="date" name="fecha_compra" id="fecha_compra" class="form-control"
               value="{{ old('fecha_compra', optional($data->fecha_compra ?? null)->format('Y-m-d') ?? '') }}">
    </div>
</div>

<div class="form-group row">
    <label for="fecha_baja" class="col-lg-4 control-label text-right pr-2">Fecha baja</label>
    <div class="col-lg-3">
        <input type="date" name="fecha_baja" id="fecha_baja" class="form-control"
               value="{{ old('fecha_baja', optional($data->fecha_baja ?? null)->format('Y-m-d') ?? '') }}">
    </div>
    <label for="vida_util" class="col-lg-2 control-label text-right pr-2">Vida &uacute;til</label>
    <div class="col-lg-3">
        <input type="number" name="vida_util" id="vida_util" class="form-control" min="0"
               value="{{ old('vida_util', $data->vida_util ?? '') }}">
    </div>
</div>

<div class="form-group row">
    <label for="valor_origen" class="col-lg-4 control-label text-right pr-2">Valor origen</label>
    <div class="col-lg-3">
        <input type="number" step="0.01" name="valor_origen" id="valor_origen" class="form-control"
               value="{{ old('valor_origen', $data->valor_origen ?? '0') }}">
    </div>
    <label for="valor_baja" class="col-lg-2 control-label text-right pr-2">Valor baja</label>
    <div class="col-lg-3">
        <input type="number" step="0.01" name="valor_baja" id="valor_baja" class="form-control"
               value="{{ old('valor_baja', $data->valor_baja ?? '0') }}">
    </div>
</div>

@include('sueldos.partials.campo_consulta_cuentacontable', [
    'label' => 'Cuenta origen',
    'cuentaId' => old('cuentacontable_origen_id', $data->cuentacontable_origen_id ?? ''),
    'codigo' => old('cta_origen_codigo', $ctaOrigen->codigo ?? ''),
    'descripcion' => old('cta_origen_nombre', $ctaOrigen->nombre ?? ''),
    'inputName' => 'cuentacontable_origen_id',
    'inputId' => 'cuentacontable_origen_id',
    'col_label' => 'col-lg-4 control-label text-right pr-2',
    'col_input' => 'col-lg-7',
])

@include('sueldos.partials.campo_consulta_cuentacontable', [
    'label' => 'Cuenta amortización',
    'cuentaId' => old('cuentacontable_amort_id', $data->cuentacontable_amort_id ?? ''),
    'codigo' => old('cta_amort_codigo', $ctaAmort->codigo ?? ''),
    'descripcion' => old('cta_amort_nombre', $ctaAmort->nombre ?? ''),
    'inputName' => 'cuentacontable_amort_id',
    'inputId' => 'cuentacontable_amort_id',
    'col_label' => 'col-lg-4 control-label text-right pr-2',
    'col_input' => 'col-lg-7',
])

@include('sueldos.partials.campo_consulta_cuentacontable', [
    'label' => 'Cuenta contrap.',
    'cuentaId' => old('cuentacontable_contrap_id', $data->cuentacontable_contrap_id ?? ''),
    'codigo' => old('cta_contrap_codigo', $ctaContrap->codigo ?? ''),
    'descripcion' => old('cta_contrap_nombre', $ctaContrap->nombre ?? ''),
    'inputName' => 'cuentacontable_contrap_id',
    'inputId' => 'cuentacontable_contrap_id',
    'col_label' => 'col-lg-4 control-label text-right pr-2',
    'col_input' => 'col-lg-7',
])
@endif

@include('contable.partials.campo_consulta_centrocosto', [
    'prefix' => 'bien',
    'layout' => 'form_row',
    'inputName' => 'centrocosto_id',
    'inputId' => 'centrocosto_id',
    'centrocostoId' => old('centrocosto_id', $data->centrocosto_id ?? ''),
    'codigo' => old('centrocosto_codigo', $cc->codigo ?? ''),
    'descripcion' => old('centrocosto_descripcion', $cc->nombre ?? ''),
    'required' => true,
    'col_label' => 'col-lg-4',
    'col_input' => 'col-lg-7',
])

@if ($tieneContable)
<div class="form-group row">
    <label for="fl_amort_unid" class="col-lg-4 control-label text-right pr-2">Amortizaci&oacute;n</label>
    <div class="col-lg-4">
        <select name="fl_amort_unid" id="fl_amort_unid" class="form-control">
            @foreach ($amortOpciones as $opt)
                <option value="{{ $opt['valor'] }}"
                    {{ (string) old('fl_amort_unid', $data->fl_amort_unid ?? '2') === $opt['valor'] ? 'selected' : '' }}>
                    {{ $opt['nombre'] }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row">
    <label for="moneda_id" class="col-lg-4 control-label text-right pr-2">Moneda</label>
    <div class="col-lg-4">
        <select name="moneda_id" id="moneda_id" class="form-control">
            <option value="">—</option>
            @foreach (($moneda_query ?? collect()) as $mon)
                <option value="{{ $mon->id }}"
                    {{ (int) old('moneda_id', $data->moneda_id ?? 1) === (int) $mon->id ? 'selected' : '' }}>
                    {{ $mon->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>

@include('contable.partials.campo_consulta_bien_uso', [
    'prefix' => 'reemplazo',
    'label' => 'Bien reemplazo',
    'inputName' => 'bien_reemplazo_id',
    'inputId' => 'bien_reemplazo_id',
    'bienId' => old('bien_reemplazo_id', $data->bien_reemplazo_id ?? ''),
    'codigo' => old('reemplazo_codigo', $bienReemp->codigo_bien ?? $bienReemp->codigo_inventario ?? ''),
    'descripcion' => old('reemplazo_descripcion', $bienReemp->descripcion ?? $bienReemp->hostname ?? ''),
    'col_label' => 'col-lg-4 control-label text-right pr-2',
    'col_input' => 'col-lg-7',
])
@endif

<div class="form-group row">
    <label for="estado" class="col-lg-4 control-label text-right pr-2 requerido">Estado</label>
    <div class="col-lg-3">
        <select id="estado" name="estado" class="form-control" required>
            @foreach ($estado_enum as $item)
                <option value="{{ $item['valor'] }}"
                    {{ (string) old('estado', $data->estado ?? 'A') === $item['valor'] ? 'selected' : '' }}>
                    {{ $item['nombre'] }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row">
    <label for="observaciones" class="col-lg-4 control-label text-right pr-2">Observaciones</label>
    <div class="col-lg-7">
        <textarea name="observaciones" id="observaciones" class="form-control" rows="3">{{ old('observaciones', $data->observaciones ?? '') }}</textarea>
    </div>
</div>
