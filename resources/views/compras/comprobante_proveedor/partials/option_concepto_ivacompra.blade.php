@php
    $mapaCuentas = method_exists($concepto, 'mapaCuentaDebePorEmpresa')
        ? $concepto->mapaCuentaDebePorEmpresa()
        : [];
    $cuentaDebeDefault = method_exists($concepto, 'cuentacontableDebeIdParaEmpresa')
        ? $concepto->cuentacontableDebeIdParaEmpresa(isset($empresaId) ? (int) $empresaId : null)
        : (int) ($concepto->cuentacontabledebe_id ?? 0);
@endphp
<option value="{{ $concepto->id }}"
    data-cuenta-debe-id="{{ $cuentaDebeDefault }}"
    data-cuentas-por-empresa="{{ e(json_encode($mapaCuentas)) }}"
    data-tipo-concepto="{{ $concepto->tipoconcepto }}"
    @if (isset($selectedId) && (int) $selectedId === (int) $concepto->id) selected @endif>
    {{ $concepto->nombre }}
</option>
