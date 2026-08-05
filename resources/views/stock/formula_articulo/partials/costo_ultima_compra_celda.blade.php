@php
    $costoUlt = $costoUltimaCompra ?? null;
    $costoUltTxt = $costoUlt !== null && $costoUlt !== ''
        ? number_format((float) $costoUlt, 2, ',', '.')
        : '';
@endphp
<input type="text"
       readonly
       class="form-control form-control-sm js-costo-ultima-compra text-right text-monospace"
       value="{{ $costoUltTxt }}"
       placeholder="—"
       title="Última compra (ERP → Anita stkm_pre_compra3)" />
