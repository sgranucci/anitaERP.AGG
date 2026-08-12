@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => old('empresa_id', $data->empresa_id ?? null),
    'solo_lectura' => isset($data->id) && $data->id,
    'col_label' => 'col-lg-3 control-label text-right pr-2',
    'col_input' => 'col-lg-6',
])
<div class="form-group row">
    <label for="fecha" class="col-lg-3 control-label text-right pr-2 requerido">Fecha propuesta</label>
    <div class="col-lg-3">
        <input type="date" name="fecha" id="fecha" class="form-control" value="{{ old('fecha', optional($data->fecha)->format('Y-m-d') ?? date('Y-m-d')) }}" required>
    </div>
</div>
<div class="form-group row">
    <label for="fecha_vencimiento_desde" class="col-lg-3 control-label text-right pr-2">Vencimiento desde</label>
    <div class="col-lg-3">
        <input type="date" name="fecha_vencimiento_desde" id="fecha_vencimiento_desde" class="form-control"
               value="{{ old('fecha_vencimiento_desde', optional($data->fecha_vencimiento_desde)->format('Y-m-d')) }}">
    </div>
    <label for="fecha_vencimiento_hasta" class="col-lg-2 control-label text-right pr-2">Hasta</label>
    <div class="col-lg-3">
        <input type="date" name="fecha_vencimiento_hasta" id="fecha_vencimiento_hasta" class="form-control"
               value="{{ old('fecha_vencimiento_hasta', optional($data->fecha_vencimiento_hasta)->format('Y-m-d')) }}">
    </div>
</div>
<div class="form-group row">
    <label for="detalle" class="col-lg-3 control-label text-right pr-2">Detalle</label>
    <div class="col-lg-8">
        <input type="text" name="detalle" id="detalle" class="form-control" maxlength="500"
               value="{{ old('detalle', $data->detalle ?? '') }}">
    </div>
</div>
@php
    $estadoPp = (string) ($data->estado ?? 'BORRADOR');
    $instrumentosEditables = in_array($estadoPp, array_merge(\App\Models\Compras\PropuestaPago::estadosEditables(), ['AUTORIZADA']), true);
@endphp
<div class="form-group row">
    <label for="caja_id" class="col-lg-3 control-label text-right pr-2">Caja (ejecución)</label>
    <div class="col-lg-4">
        <select name="caja_id" id="caja_id" class="form-control" {{ $instrumentosEditables ? '' : 'disabled' }}>
            <option value="">— Sin asignar —</option>
            @foreach(($caja_query ?? []) as $c)
                <option value="{{ $c->id }}" @selected((int) old('caja_id', $data->caja_id ?? 0) === (int) $c->id)>{{ $c->nombre ?? ('#'.$c->id) }}</option>
            @endforeach
        </select>
        <small class="form-text text-muted">Se copia a cada OP al ejecutar el lote.</small>
    </div>
</div>
<div class="form-group row">
    <label for="cuentacaja_id" class="col-lg-3 control-label text-right pr-2">Cuenta egreso (transferencias)</label>
    <div class="col-lg-5">
        <select name="cuentacaja_id" id="cuentacaja_id" class="form-control" {{ $instrumentosEditables ? '' : 'disabled' }}>
            <option value="">— Completar en cada OP —</option>
            @foreach(($cuentacaja_query ?? []) as $cc)
                <option value="{{ $cc->id }}" @selected((int) old('cuentacaja_id', $data->cuentacaja_id ?? 0) === (int) $cc->id)>
                    {{ $cc->codigo ?? '' }} — {{ $cc->nombre ?? ('#'.$cc->id) }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="chequera_id" class="col-lg-3 control-label text-right pr-2">Chequera (medios Cheque)</label>
    <div class="col-lg-5">
        <select name="chequera_id" id="chequera_id" class="form-control" {{ $instrumentosEditables ? '' : 'disabled' }}>
            <option value="">— Sin chequera —</option>
            @foreach(($chequera_query ?? []) as $ch)
                <option value="{{ $ch->id }}" @selected((int) old('chequera_id', $data->chequera_id ?? 0) === (int) $ch->id)>
                    {{ $ch->codigo ?? $ch->id }} — {{ $ch->tipochequera ?? '' }} (cta {{ $ch->cuentacaja_id ?? '-' }})
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">
            Transferencias: cuenta egreso + CBU proveedor. Cheques: chequera (próximo número automático).
        </small>
    </div>
</div>

@include('compras.propuesta_pago.partials.grilla_lineas')

@if (isset($data->id) && $data->id && (! $data->lineas || $data->lineas->count() === 0))
    <div class="alert alert-warning mt-3">No hay líneas de deuda en el rango de vencimientos. Guarde con «Rearmar líneas» o amplíe el rango.</div>
    <input type="hidden" name="rearmar_lineas" value="1">
@elseif (! isset($data->id) || ! $data->id)
    <div class="alert alert-info mt-3">Al guardar se cargarán las deudas abiertas del rango de vencimiento indicado.</div>
@endif
