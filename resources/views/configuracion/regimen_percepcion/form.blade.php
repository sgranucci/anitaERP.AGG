@php
    $codigoActual = old('codigo', $data->codigo ?? '');
    $codigoSistema = in_array(strtoupper((string) $codigoActual), ['PIVA', 'PNC'], true);
@endphp
<div class="alert alert-info py-2">
    Se aplica en facturación de administración (mostrador, pedido, remito letra A).
    Gastronomía, estacionamiento y POS omiten estas percepciones.
    <strong>PIVA</strong>: percepción IVA 3&nbsp;% a RI (RG 5329), mínimo sobre gravado.
    <strong>PNC</strong>: percepción a no categorizado (RG 2126), mínimo sobre el importe calculado.
    IIBB (Buenos Aires 902 y resto) no es un régimen de esta lista:
    <a href="{{ route('configuracion_general') }}">Agentes IIBB por empresa</a>.
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 control-label text-right pr-2 requerido">Código</label>
    <div class="col-lg-2">
        <input type="text" name="codigo" id="codigo" class="form-control" maxlength="20"
            value="{{ $codigoActual }}" required {{ $codigoSistema ? 'readonly' : '' }}>
    </div>
    @if ($codigoSistema)
        <div class="col-lg-6 col-form-label text-muted">Código de sistema, no se cambia.</div>
    @endif
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 control-label text-right pr-2 requerido">Nombre</label>
    <div class="col-lg-6">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="80"
            value="{{ old('nombre', $data->nombre ?? '') }}" required>
    </div>
</div>
<div class="form-group row">
    <label for="habilitado" class="col-lg-3 control-label text-right pr-2">Agente de percepción</label>
    <div class="col-lg-8">
        <div class="custom-control custom-checkbox pt-2">
            <input type="hidden" name="habilitado" value="0">
            <input type="checkbox" class="custom-control-input" name="habilitado" id="habilitado" value="1"
                {{ old('habilitado', $data->habilitado ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="habilitado">Aplicar este régimen al facturar</label>
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="tasa" class="col-lg-3 control-label text-right pr-2 requerido">Alícuota (%)</label>
    <div class="col-lg-2">
        <input type="number" step="0.0001" min="0" max="100" name="tasa" id="tasa" class="form-control"
            value="{{ old('tasa', $data->tasa ?? 0) }}" required>
    </div>
</div>
<div class="form-group row">
    <label for="minimo_base" class="col-lg-3 control-label text-right pr-2">Mínimo de gravado</label>
    <div class="col-lg-2">
        <input type="number" step="0.01" min="0" name="minimo_base" id="minimo_base" class="form-control"
            value="{{ old('minimo_base', $data->minimo_base ?? 0) }}" required>
    </div>
    <div class="col-lg-6 col-form-label text-muted">
        Si el neto gravado no llega, no se percibe (Anita / RG 5329). En PIVA El Bierzo: 100.000.
    </div>
</div>
<div class="form-group row">
    <label for="minimo_importe" class="col-lg-3 control-label text-right pr-2">Mínimo de percepción</label>
    <div class="col-lg-2">
        <input type="number" step="0.01" min="0" name="minimo_importe" id="minimo_importe" class="form-control"
            value="{{ old('minimo_importe', $data->minimo_importe ?? 0) }}" required>
    </div>
    <div class="col-lg-6 col-form-label text-muted">
        Si el importe calculado no supera este piso, no se percibe (uso típico de PNC).
    </div>
</div>
<div class="form-group row">
    <label for="vigencia_desde" class="col-lg-3 control-label text-right pr-2">Vigencia desde</label>
    <div class="col-lg-2">
        <input type="date" name="vigencia_desde" id="vigencia_desde" class="form-control"
            value="{{ old('vigencia_desde', isset($data->vigencia_desde) && $data->vigencia_desde ? $data->vigencia_desde->format('Y-m-d') : '') }}">
    </div>
    <label for="vigencia_hasta" class="col-lg-2 control-label text-right pr-2">Hasta</label>
    <div class="col-lg-2">
        <input type="date" name="vigencia_hasta" id="vigencia_hasta" class="form-control"
            value="{{ old('vigencia_hasta', isset($data->vigencia_hasta) && $data->vigencia_hasta ? $data->vigencia_hasta->format('Y-m-d') : '') }}">
    </div>
</div>
@include('configuracion.regimen_percepcion.form2')
