@php
    $cheque = $cheque ?? null;
    $chequeraFila = $cheque?->chequeras ?? null;
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
@endphp
<tr class="item-cheque-emitido">
    <td>
        <div class="d-flex align-items-center flex-nowrap" style="gap:4px;">
            <input type="hidden" name="cheque_emitido_ids[]" class="cheque_emitido_id" value="{{ $cheque?->id ?? '' }}">
            <input type="hidden" name="cuentacaja_emitido_ids[]" class="cuentacaja_emitido_id" value="{{ $cheque?->cuentacaja_id ?? '' }}">
            <input type="hidden" name="tctes_numero_emitidos[]" class="tctes_numero_emitido" value="">
            <input type="hidden" name="tctes_clave_emitidos[]" class="tctes_clave_emitido" value="">
            <button type="button" title="Consulta cuentas (F1)" class="btn-accion-tabla consultacuentacaja_emitido tooltipsC flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" class="codigo_emitido form-control form-control-sm" name="codigo_emitido[]"
                value="{{ $cheque?->cuentacajas?->codigo ?? '' }}"
                placeholder="Cód." title="Código; Enter valida; F1 consulta" autocomplete="off"
                style="width:5.5rem; flex-shrink:0;">
        </div>
    </td>
    <td>
        <input type="text" class="nombre_emitido form-control form-control-sm" readonly
            value="{{ $cheque?->cuentacajas?->nombre ?? '' }}" placeholder="Nombre" title="Cuenta de tesorería">
    </td>
    <td>
        <div class="d-flex align-items-center flex-nowrap" style="gap:4px;">
            <input type="hidden" name="chequera_emitido_ids[]" class="chequera_emitido_id" value="{{ $cheque?->chequera_id ?? '' }}">
            <input type="hidden" class="chequera_emitido_tipo" value="{{ $tipoChequeraFila }}">
            <input type="hidden" class="chequera_emitido_tipochequera" value="{{ $chequeraFila?->tipochequera ?? '' }}">
            <button type="button" title="Consulta chequeras (F1)" class="btn-accion-tabla consultachequera_emitido tooltipsC flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" class="chequera_emitido_lbl form-control form-control-sm" readonly tabindex="0"
                value="{{ $etiquetaChequera }}"
                placeholder="Chequera" title="{{ $etiquetaChequera !== '' ? $etiquetaChequera : 'F1 consulta chequera de la cuenta' }}"
                autocomplete="off" style="min-width:8.5rem; cursor:pointer;">
        </div>
    </td>
    <td>
        <input type="text" name="numerocheque_emitidos[]" class="form-control form-control-sm numerocheque_emitido"
            value="{{ $cheque?->numerocheque ?? '' }}" placeholder="Nro."
            title="Numerador Anita de la cuenta (se completa al elegirla)" inputmode="numeric">
        <small class="tctes_emitido_lbl text-muted d-block" style="font-size:10px;line-height:1.2;"></small>
    </td>
    <td>
        <input type="date" name="fechapago_emitidos[]" class="form-control form-control-sm fechapago_emitido"
            value="{{ $cheque?->fechapago ?? '' }}">
    </td>
    <td>
        @php
            $caracterEnum = $caracter_enum ?? \App\Models\Caja\Cheque::$enumCaracter;
            $paraDepEnum = $para_dep_enum ?? \App\Models\Caja\Cheque::$enumParaDep;
            $negociableEnum = $negociable_enum ?? \App\Models\Caja\Cheque::$enumNegociable;
            $paraDepVal = \App\Support\Caja\ChequePropioInstrumentoSupport::paraDep(
                (string) ($cheque?->para_dep ?? ''),
                \App\Support\Caja\ChequePropioInstrumentoSupport::paraDepDefault()
            );
            $negociableVal = \App\Support\Caja\ChequePropioInstrumentoSupport::negociable(
                (string) ($cheque?->negociable ?? ''),
                (string) ($chequeraFila?->tipochequera ?? 'F')
            );
        @endphp
        <select name="caracter_emitidos[]" class="form-control form-control-sm caracter_emitido" title="Carácter legal (impreso)">
            @foreach ($caracterEnum as $car)
                @if ($car['valor'] !== 'R')
                    <option value="{{ $car['valor'] }}" @selected($cheque && $car['valor'] === ($cheque->caracter ?? 'O'))>{{ $car['nombre'] }}</option>
                @endif
            @endforeach
        </select>
        <select name="para_dep_emitidos[]" class="form-control form-control-sm para_dep_emitido mt-1" title="Anita para depositar (cpro_para_dep)">
            @foreach ($paraDepEnum as $pd)
                <option value="{{ $pd['valor'] }}" @selected($pd['valor'] === $paraDepVal)>{{ $pd['nombre'] }}</option>
            @endforeach
        </select>
        <select name="negociable_emitidos[]" class="form-control form-control-sm negociable_emitido mt-1" title="Anita negociable: físico / electrónico">
            @foreach ($negociableEnum as $neg)
                <option value="{{ $neg['valor'] }}" @selected($neg['valor'] === $negociableVal)>{{ $neg['nombre'] }}</option>
            @endforeach
        </select>
        <input type="hidden" name="nro_echeq_emitidos[]" class="nro_echeq_emitido" value="{{ $cheque?->nro_echeq ?? '' }}">
        <input type="hidden" name="fecha_entrega_emitidos[]" class="fecha_entrega_emitido" value="{{ $cheque?->fecha_entrega ?? '' }}">
    </td>
    <td>
        <input type="text" name="anombrede_emitidos[]" class="form-control form-control-sm anombrede_emitido"
            value="{{ $cheque?->anombrede ?? '' }}">
    </td>
    <td>
        <select name="moneda_emitido_ids[]" class="form-control form-control-sm moneda_emitido_id">
            @foreach ($moneda_query as $m)
                <option value="{{ $m->id }}" @selected($cheque && (int) $m->id === (int) $cheque->moneda_id)>{{ $m->abreviatura }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <input type="number" name="montocheque_emitidos[]" class="form-control form-control-sm montocheque_emitido text-right"
            min="0" step="0.01" value="{{ $cheque?->monto ?? '' }}">
    </td>
    <td>
        <input type="number" name="cotizacioncheque_emitidos[]" class="form-control form-control-sm cotizacioncheque_emitido"
            step="0.0001" value="{{ $cheque?->cotizacion ?? 1 }}">
    </td>
    <td class="text-center">
        <button type="button" class="btn-accion-tabla eliminar_cheque_emitido tooltipsC" title="Eliminar">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
