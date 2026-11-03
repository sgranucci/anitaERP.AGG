@php
    $transferencia = $transferencia ?? null;
    $esTransferencia = $transferencia !== null;
    $cheque = $esTransferencia ? null : ($cheque ?? null);
    $anulado = $esTransferencia ? $transferencia->chequeAnulado : $cheque?->chequeReemplazado;
    $chequeraFila = $cheque?->chequeras;
    $etiquetaChequera = '';
    $tipoChequeraFila = '';
    if ($chequeraFila) {
        $etiquetaChequera = \App\Support\Caja\ChequeConsultaChequeraSupport::etiquetaCompleta(
            (string) ($chequeraFila->codigo ?? ''),
            (string) ($chequeraFila->tipocheque ?? 'N'),
            $chequeraFila->desdenumerocheque ?? null,
            $chequeraFila->hastanumerocheque ?? null
        );
        $tipoChequeraFila = (string) ($chequeraFila->tipocheque ?? '');
    }
    $origenVal = (string) ($cheque?->origen ?? ($anulado?->origen ?? 'E'));
    $esEmitido = $origenVal === 'E';
    $tipoReemplazo = $esTransferencia ? 'T' : ($cheque ? ($esEmitido ? 'E' : 'R') : 'E');
    $cuentaReemplazo = $esTransferencia ? $transferencia->cuentacajas : $cheque?->cuentacajas;
    $montoReemplazo = $esTransferencia ? $transferencia->monto : $cheque?->monto;
    $monedaReemplazoId = $esTransferencia ? $transferencia->moneda_id : $cheque?->moneda_id;
    $cotizacionReemplazo = $esTransferencia ? $transferencia->cotizacion : ($cheque?->cotizacion ?? 1);
    $fechaReemplazo = $esTransferencia ? $transferencia->fecha : $cheque?->fechapago;
    $fechaAnulado = $anulado?->fechapago;
    if ($fechaAnulado instanceof \DateTimeInterface) {
        $fechaAnulado = $fechaAnulado->format('Y-m-d');
    }
    if ($fechaReemplazo instanceof \DateTimeInterface) {
        $fechaReemplazo = $fechaReemplazo->format('Y-m-d');
    }
    $detalleAnulado = '';
    if ($anulado) {
        $detalleAnulado = trim(($anulado->numerocheque ?? '').' ('.($anulado->bancos?->nombre ?? '').')');
    }
@endphp
<tr class="item-cheque-reemplazo">
    <td>
        <input type="hidden" name="cheque_anulado_ids[]" class="cheque_anulado_id" value="{{ $esTransferencia ? ($transferencia->cheque_id ?? '') : ($cheque?->cheque_reemplaza_id ?? '') }}">
        <input type="hidden" name="origen_anulado[]" class="origen_anulado" value="{{ $anulado?->origen ?? '' }}">
        <input type="hidden" name="cuentacaja_anulado_ids[]" class="cuentacaja_anulado_id" value="{{ $anulado?->cuentacaja_id ?? '' }}">
        <input type="hidden" name="fechapago_anulado[]" class="fechapago_anulado" value="{{ $fechaAnulado ?? '' }}">
        @if ($cheque || $esTransferencia)
            <input type="text" class="form-control form-control-sm numerocheque_anulado" readonly value="{{ $detalleAnulado }}">
        @else
            <div class="d-flex align-items-center flex-nowrap mb-1" style="gap:4px;">
                <input type="text" class="form-control form-control-sm numerocheque_anulado_buscar" placeholder="Nro. a anular"
                    title="N&uacute;mero del cheque a anular; Buscar o Enter" autocomplete="off" style="width:6.5rem;">
                <button type="button" class="btn btn-sm btn-info buscar_cheque_anulado flex-shrink-0">Buscar</button>
            </div>
            <input type="text" class="form-control form-control-sm numerocheque_anulado" readonly value=""
                placeholder="Detalle del cheque anulado">
        @endif
        <small class="text-muted d-block ie-reemplazo-aviso-empresa" style="font-size:10px;line-height:1.2;display:none;">
            Elija la empresa en Datos principales antes de buscar.
        </small>
    </td>
    <td>
        <select name="origen_reemplazo[]" class="form-control form-control-sm origen_reemplazo" title="Cheque nuevo o transferencia">
            <option value="E" @selected($tipoReemplazo === 'E')>Emitido</option>
            <option value="R" @selected($tipoReemplazo === 'R')>Recibido</option>
            <option value="T" @selected($tipoReemplazo === 'T')>Transferencia</option>
        </select>
    </td>
    <td>
        <input type="hidden" name="cuentacaja_reemplazo_ids[]" class="cuentacaja_reemplazo_id" value="{{ $esTransferencia ? ($transferencia->cuentacaja_id ?? '') : ($cheque?->cuentacaja_id ?? '') }}">
        <input type="hidden" name="banco_reemplazo_ids[]" class="banco_reemplazo_id" value="{{ $cheque?->banco_id ?? '' }}">
        <input type="hidden" name="chequera_reemplazo_ids[]" class="chequera_reemplazo_id" value="{{ $cheque?->chequera_id ?? '' }}">
        <input type="hidden" class="chequera_reemplazo_tipo" value="{{ $tipoChequeraFila }}">
        <input type="hidden" class="chequera_reemplazo_tipochequera" value="{{ $chequeraFila?->tipochequera ?? '' }}">

        <div class="bloque-reemplazo-emitido" @if ($tipoReemplazo === 'R') style="display:none" @endif>
            <div class="d-flex align-items-center flex-nowrap mb-1" style="gap:4px;">
                <button type="button" class="btn-accion-tabla consultacuentacaja_reemplazo tooltipsC flex-shrink-0" title="Cuenta banco (F1)">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="codigo_reemplazo form-control form-control-sm" value="{{ $cuentaReemplazo?->codigo ?? '' }}"
                    placeholder="C&oacute;d." title="C&oacute;digo cuenta; Enter valida; F1 consulta" autocomplete="off" style="width:5rem;">
            </div>
            <input type="text" class="nombre_reemplazo form-control form-control-sm mb-1" readonly
                value="{{ $cuentaReemplazo?->nombre ?? '' }}" placeholder="Cuenta tesorer&iacute;a">
            <div class="bloque-reemplazo-chequera align-items-center flex-nowrap" style="gap:4px; display:{{ $tipoReemplazo === 'E' ? 'flex' : 'none' }};">
                <button type="button" class="btn-accion-tabla consultachequera_reemplazo tooltipsC flex-shrink-0" title="Chequera (puede ser otra)">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="chequera_reemplazo_lbl form-control form-control-sm" readonly tabindex="0"
                    value="{{ $etiquetaChequera }}"
                    placeholder="Chequera" title="{{ $etiquetaChequera !== '' ? $etiquetaChequera : 'F1 / lupa: elegir chequera (puede ser distinta a la anulada)' }}"
                    style="min-width:7rem; cursor:pointer;">
            </div>
        </div>

        <div class="bloque-reemplazo-recibido" @if ($tipoReemplazo !== 'R') style="display:none" @endif>
            <div class="d-flex align-items-center flex-nowrap mb-1" style="gap:4px;">
                <button type="button" class="btn-accion-tabla consultabanco_reemplazo tooltipsC flex-shrink-0" title="Banco">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="codigobanco_reemplazo form-control form-control-sm" style="width:4.5rem;"
                    value="{{ $cheque?->bancos?->codigo ?? '' }}" placeholder="C&oacute;d." autocomplete="off">
                <input type="text" class="nombrebanco_reemplazo form-control form-control-sm" style="min-width:6rem;" readonly
                    value="{{ $cheque?->bancos?->nombre ?? '' }}" placeholder="Banco">
            </div>
            <input type="text" name="sucursalpago_reemplazo[]" class="form-control form-control-sm sucursalpago_reemplazo mb-1"
                value="{{ $cheque?->sucursalpago ?? '' }}" placeholder="Sucursal" title="Sucursal del cheque recibido">
            <input type="text" name="cuentalibradora_reemplazo[]" class="form-control form-control-sm cuentalibradora_reemplazo"
                value="{{ $cheque?->cuentalibradora ?? '' }}" placeholder="Cta. libradora" title="Cuenta libradora">
        </div>
    </td>
    <td>
        <input type="text" name="numerocheque_reemplazo[]" class="form-control form-control-sm numerocheque_reemplazo"
            value="{{ $cheque?->numerocheque ?? '' }}" placeholder="Nro. nuevo"
            title="Se completa al buscar el cheque a anular (talonario ERP o Anita). En transferencia queda vac&iacute;o." inputmode="numeric"
            @if ($esTransferencia) readonly @endif>
        <small class="aviso-reemplazo-transferencia text-muted" style="font-size:10px;line-height:1.2;@if ($esTransferencia) display:block @else display:none @endif">
            Transferencia: solo la cuenta, sin n&uacute;mero de cheque.
        </small>
        <small class="tctes_reemplazo_lbl text-muted d-block" style="font-size:10px;line-height:1.2;"></small>
    </td>
    <td>
        <input type="date" name="fechapago_reemplazo[]" class="form-control form-control-sm fechapago_reemplazo"
            value="{{ $fechaReemplazo ?? '' }}">
    </td>
    <td>
        <input type="text" name="anombrede_reemplazo[]" class="form-control form-control-sm anombrede_reemplazo bloque-reemplazo-emitido-extra"
            value="{{ $cheque?->anombrede ?? ($anulado?->anombrede ?? '') }}" placeholder="A nombre de" maxlength="40"
            @if ($tipoReemplazo === 'R') style="display:none" @endif title="Beneficiario (emitido o transferencia)">
        <span class="text-muted small bloque-reemplazo-recibido-extra" @if ($tipoReemplazo !== 'R') style="display:none" @endif>—</span>
    </td>
    <td>
        <input type="number" name="montocheque_reemplazo[]" class="form-control form-control-sm montocheque_reemplazo text-right"
            min="0" step="0.01" value="{{ $montoReemplazo ?? '' }}">
    </td>
    <td>
        <select name="moneda_reemplazo_ids[]" class="form-control form-control-sm moneda_reemplazo_id">
            @foreach ($moneda_query as $m)
                <option value="{{ $m->id }}" @selected(($monedaReemplazoId && (int) $m->id === (int) $monedaReemplazoId) || (! $monedaReemplazoId && (int) $m->id === (int) config('cotizacion.ID_MONEDA_DEFAULT', 1)))>{{ $m->abreviatura }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <input type="number" name="cotizacioncheque_reemplazo[]" class="form-control form-control-sm cotizacioncheque_reemplazo"
            step="0.0001" value="{{ $cotizacionReemplazo ?? 1 }}">
    </td>
    <td class="text-center">
        <button type="button" class="btn-accion-tabla eliminar_cheque_reemplazo tooltipsC" title="Eliminar">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
