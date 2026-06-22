<option value="{{ $concepto->id }}"
    data-cuenta-debe-id="{{ (int) ($concepto->cuentacontabledebe_id ?? 0) }}"
    data-tipo-concepto="{{ $concepto->tipoconcepto }}"
    @if (isset($selectedId) && (int) $selectedId === (int) $concepto->id) selected @endif>
    {{ $concepto->nombre }}
</option>
